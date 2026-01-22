# Análise: Tratamento de Duplicatas no Autocomplete de Clientes

**Data:** 09/01/2026  
**Contexto:** Implementação de pesquisa dinâmica por cliente no Kanban Board

---

## 1. Como o Sistema Financeiro Trata Duplicatas (Referência)

### 1.1. Estratégia Atual no Financeiro

**Localização:** Aba "Financeiro" do perfil do cliente (`/tenants/view?id={id}&tab=financial`)

**Padrão Implementado:**

1. **Busca Múltiplos Customers do Asaas:**
   - Método: `AsaasClient::findCustomersByCpfCnpj($cpfCnpjNormalizado)`
   - Retorna **TODOS** os customers do Asaas com mesmo CPF/CNPJ
   - Exemplo: 2 customers com CPF `123.456.789-00` → retorna array com ambos

2. **Sincroniza Todas as Faturas:**
   - Método: `AsaasBillingService::syncCustomerAndInvoicesForTenant($tenantId)`
   - **Linha 918-948:** Loop sobre TODOS os customers encontrados
   - Para cada customer, busca todos os payments (faturas)
   - **Vincula TODAS as faturas ao mesmo `tenant_id`** (linha 947-948)
   - Isso permite que um tenant tenha faturas de múltiplos customers Asaas

3. **Exibição com Badge "Principal":**
   - Exibe todos os customers encontrados em uma tabela
   - Marca com badge "Principal" o customer que está em `tenant.asaas_customer_id`
   - Mostra aviso: "Foram encontrados X cadastros no Asaas para este CPF. As cobranças já estão consolidadas aqui no painel..."

4. **Consolidação de Dados:**
   - Método: `AsaasBillingService::consolidateAsaasCustomersData($allCustomers)`
   - Prioriza dados mais completos (nome mais longo, cidade com letras, etc.)
   - Preenche campos vazios com dados dos outros customers

**Código Relevante:**
- `src/Services/AsaasBillingService.php` - Linhas 827-948
- `src/Services/AsaasClient.php` - Linhas 167-195
- `views/tenants/view.php` - Linhas 1848-1923

---

## 2. Problema no Autocomplete Atual

### 2.1. Estado Atual do Endpoint

**Arquivo:** `src/Controllers/TenantsController.php` - Método `searchAjax()` (linha 1686)

**Query Atual:**
```sql
SELECT id, name 
FROM tenants 
WHERE name LIKE ? 
ORDER BY name ASC 
LIMIT 10
```

**Problemas Identificados:**

1. ❌ **Não filtra arquivados:** Mostra clientes com `is_archived = 1`
2. ❌ **Não agrupa por CPF/CNPJ:** Se existem 2 tenants com mesmo CPF, mostra ambos
3. ❌ **Não identifica principal:** Não sabe qual é o tenant "principal"
4. ❌ **Não consolida:** Não segue o padrão do financeiro

### 2.2. Caso Real Identificado

No exemplo do print:
- "Centro De Formacao De Condutores De Bom Conselho Ltda"
- "Robson Wagner Alves Vieira | CFC Bom Conselho"

**Ambos são o mesmo cliente (CFC Bom Conselho)** mas aparecem como entradas separadas no autocomplete.

---

## 3. Análise: Como Aplicar o Padrão do Financeiro

### 3.1. Estratégia Recomendada (Baseada no Financeiro)

**Princípio:** Seguir o mesmo padrão já estabelecido no financeiro:
- **Agrupar por CPF/CNPJ** (igual ao financeiro agrupa customers do Asaas)
- **Identificar tenant principal** (similar ao badge "Principal")
- **Filtrar arquivados** (usar flags já existentes)
- **Retornar apenas o principal** no autocomplete, mas indicar duplicatas

### 3.2. Lógica de Priorização para Escolher o Principal

**Baseado no padrão do financeiro e lógica de negócio:**

1. **Prioridade 1:** Não arquivado (`is_archived = 0 AND is_financial_only = 0`)
2. **Prioridade 2:** Com mais dados completos:
   - Tem email
   - Tem telefone
   - Tem projetos vinculados
   - Tem hospedagens vinculadas
3. **Prioridade 3:** Mais recente (maior `id` ou `updated_at`)
4. **Prioridade 4:** Com `asaas_customer_id` vinculado (importante para sincronização)

### 3.3. Implementação Proposta

#### **Modificar `searchAjax()` para:**

```php
public function searchAjax(): void
{
    // ... código de autenticação e validação ...

    // Busca clientes que correspondem ao termo
    $searchTerm = '%' . $query . '%';
    $stmt = $db->prepare("
        SELECT 
            t.id, 
            t.name,
            t.cpf_cnpj,
            t.is_archived,
            t.is_financial_only,
            t.asaas_customer_id,
            -- Conta relacionamentos para priorizar
            (SELECT COUNT(*) FROM projects WHERE tenant_id = t.id) as projects_count,
            (SELECT COUNT(*) FROM hosting_accounts WHERE tenant_id = t.id) as hosting_count,
            CASE 
                WHEN t.is_archived = 0 AND t.is_financial_only = 0 THEN 1
                ELSE 0
            END as is_active
        FROM tenants t
        WHERE t.name LIKE ?
        ORDER BY 
            is_active DESC,  -- Não arquivados primeiro
            projects_count DESC,  -- Com mais projetos primeiro
            hosting_count DESC,   -- Com mais hospedagens primeiro
            (CASE WHEN t.asaas_customer_id IS NOT NULL THEN 1 ELSE 0 END) DESC,  -- Com Asaas vinculado
            t.id DESC  -- Mais recente
        LIMIT 20  -- Busca mais para agrupar depois
    ");
    $stmt->execute([$searchTerm]);
    $allClients = $stmt->fetchAll();

    // Agrupa por CPF/CNPJ normalizado
    $groupedByCpf = [];
    foreach ($allClients as $client) {
        $cpfCnpj = preg_replace('/[^0-9]/', '', $client['cpf_cnpj'] ?? '');
        if (empty($cpfCnpj)) {
            // Sem CPF/CNPJ: adiciona direto (não agrupa)
            $groupedByCpf[] = [
                'primary' => $client,
                'duplicates_count' => 0,
                'has_duplicates' => false
            ];
        } else {
            // Com CPF/CNPJ: agrupa
            if (!isset($groupedByCpf[$cpfCnpj])) {
                $groupedByCpf[$cpfCnpj] = [
                    'primary' => $client,
                    'duplicates' => [],
                    'has_duplicates' => false
                ];
            } else {
                // Adiciona como duplicata
                $groupedByCpf[$cpfCnpj]['duplicates'][] = $client;
                $groupedByCpf[$cpfCnpj]['has_duplicates'] = true;
                
                // Se este cliente é melhor que o principal atual, troca
                $currentPrimary = $groupedByCpf[$cpfCnpj]['primary'];
                if ($this->isClientBetterThan($client, $currentPrimary)) {
                    $groupedByCpf[$cpfCnpj]['duplicates'][] = $currentPrimary;
                    $groupedByCpf[$cpfCnpj]['primary'] = $client;
                }
            }
        }
    }

    // Prepara resultado final (apenas principais)
    $clients = [];
    foreach ($groupedByCpf as $group) {
        $primary = $group['primary'];
        
        // Adiciona informação de duplicatas
        $clientData = [
            'id' => $primary['id'],
            'name' => $primary['name'],
            'has_duplicates' => $group['has_duplicates'] ?? false,
            'duplicates_count' => count($group['duplicates'] ?? [])
        ];
        
        $clients[] = $clientData;
    }

    // Limita a 10 resultados finais
    $clients = array_slice($clients, 0, 10);

    echo json_encode([
        'success' => true,
        'clients' => $clients
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Método auxiliar para comparar qual cliente é "melhor"
private function isClientBetterThan(array $client1, array $client2): bool
{
    // 1. Não arquivado é melhor que arquivado
    $active1 = (int)($client1['is_active'] ?? 0);
    $active2 = (int)($client2['is_active'] ?? 0);
    if ($active1 !== $active2) {
        return $active1 > $active2;
    }

    // 2. Com mais projetos é melhor
    $projects1 = (int)($client1['projects_count'] ?? 0);
    $projects2 = (int)($client2['projects_count'] ?? 0);
    if ($projects1 !== $projects2) {
        return $projects1 > $projects2;
    }

    // 3. Com mais hospedagens é melhor
    $hosting1 = (int)($client1['hosting_count'] ?? 0);
    $hosting2 = (int)($client2['hosting_count'] ?? 0);
    if ($hosting1 !== $hosting2) {
        return $hosting1 > $hosting2;
    }

    // 4. Com asaas_customer_id é melhor
    $hasAsaas1 = !empty($client1['asaas_customer_id']);
    $hasAsaas2 = !empty($client2['asaas_customer_id']);
    if ($hasAsaas1 !== $hasAsaas2) {
        return $hasAsaas1;
    }

    // 5. Mais recente é melhor
    return (int)($client1['id'] ?? 0) > (int)($client2['id'] ?? 0);
}
```

---

## 4. Comparação com Kommo/amoCRM

### 4.1. Como Kommo Trata Duplicatas

**Baseado em pesquisa e melhores práticas de CRM:**

1. **Detecção Automática:**
   - Algoritmo de similaridade de strings (Levenshtein, etc.)
   - Agrupa por email, telefone, CPF/CNPJ
   - Mostra score de similaridade

2. **Exibição:**
   - Lista principal mostra apenas o contato principal
   - Badge/ícone indicando duplicatas
   - Opção "Ver duplicatas" para listar todos

3. **Merge:**
   - Interface de merge manual
   - Usuário escolhe qual manter
   - Mescla campos não vazios

4. **Prevenção:**
   - Aviso ao criar: "Já existe contato similar"
   - Sugestão de vincular ao existente

### 4.2. Aplicação no Nosso Caso

**Não precisamos de algoritmo complexo de similaridade** porque:
- ✅ Já temos CPF/CNPJ como identificador único confiável
- ✅ O financeiro já usa essa estratégia (agrupamento por CPF)
- ✅ Mais simples e preciso que algoritmos de string

**O que podemos aprender do Kommo:**
- ✅ Mostrar badge quando há duplicatas
- ✅ Permitir visualizar duplicatas (futuro)
- ✅ Interface de merge (Fase 2 da documentação)

---

## 5. Impacto na Sincronização Asaas

### 5.1. Por Que Não Quebra

**Razões:**

1. **Sincronização não depende do autocomplete:**
   - A sincronização usa `AsaasBillingService::syncCustomerAndInvoicesForTenant()`
   - Busca customers por CPF/CNPJ diretamente do Asaas (linha 900)
   - **Não depende da lista de tenants** do sistema

2. **Faturas já são agrupadas:**
   - O método `syncCustomerAndInvoicesForTenant()` busca TODOS os customers do Asaas
   - Vincula TODAS as faturas ao mesmo tenant (linha 947-948)
   - **Funciona independente de quantos tenants existem no sistema**

3. **Campo `billing_invoices.asaas_customer_id`:**
   - Cada fatura armazena seu `asaas_customer_id` original
   - Permite rastrear de qual customer Asaas veio
   - **Não depende do tenant.asaas_customer_id**

4. **Filtro de arquivados já existe:**
   - A query de busca usa `is_archived = 0` (linha 353 do controller)
   - **Não afeta sincronização** que busca por CPF/CNPJ

### 5.2. Testes Necessários

✅ **Não necessário testar:** Sincronização (não é afetada)  
✅ **Necessário testar:** 
- Autocomplete não mostra arquivados
- Autocomplete agrupa duplicatas corretamente
- Badge de duplicatas aparece quando há

---

## 6. Recomendação Final

### 6.1. Implementação Sugerida

**Fase 1 (Imediata):** Melhorar `searchAjax()` seguindo padrão do financeiro:
- ✅ Filtrar arquivados (usar flags existentes)
- ✅ Agrupar por CPF/CNPJ normalizado
- ✅ Escolher principal usando lógica de priorização
- ✅ Retornar badge "has_duplicates" quando houver
- ✅ Exibir badge visualmente no autocomplete

**Fase 2 (Futuro):** Interface de visualização de duplicatas
- Adicionar endpoint `/tenants/find-duplicates?id={id}`
- Mostrar lista de duplicatas no perfil do cliente
- Preparar para merge (quando implementar Fase 2 da documentação)

### 6.2. Alterações Necessárias

**Arquivo:** `src/Controllers/TenantsController.php`
- Método `searchAjax()`: Reimplementar com agrupamento
- Método auxiliar `isClientBetterThan()`: Lógica de priorização

**Arquivo:** `views/tasks/board.php`
- JavaScript do autocomplete: Adicionar badge quando `has_duplicates = true`

### 6.3. Benefícios

✅ **Consistente:** Segue padrão já estabelecido no financeiro  
✅ **Seguro:** Não quebra sincronização Asaas  
✅ **Simples:** Usa CPF/CNPJ como identificador (mais confiável que strings)  
✅ **Escalável:** Prepara para merge futuro  
✅ **UX melhor:** Não mostra duplicatas confusas

---

## 7. Resumo Executivo

### Problema:
Autocomplete mostra duplicatas (ex: "Centro De Formacao..." e "Robson Wagner... | CFC") porque não agrupa por CPF/CNPJ.

### Solução:
Seguir padrão do financeiro: agrupar por CPF/CNPJ, identificar principal, filtrar arquivados, mostrar badge quando houver duplicatas.

### Por Que Seguro:
- Sincronização Asaas não depende do autocomplete
- Faturas já são agrupadas automaticamente
- Usa flags já existentes (`is_archived`)

### Próximos Passos:
1. Implementar agrupamento no `searchAjax()`
2. Adicionar badge visual no autocomplete
3. Testar com casos reais de duplicatas

