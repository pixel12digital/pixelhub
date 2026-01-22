# Análise: Importação de Cliente do Asaas para o Sistema

**Data:** 2026-01-07  
**Cenário:** Cliente existe no Asaas (fez Pix, já pagou), mas não está cadastrado no sistema. Precisa cadastrar para criar projeto/serviço sem duplicatas.

---

## 1. Situação Atual

### 1.1. O Que Já Existe no Sistema

**Métodos Disponíveis:**

1. **`AsaasBillingService::syncAllCustomersAndInvoices()`**
   - Importa TODOS os customers do Asaas em batch
   - Verifica duplicatas por:
     - `asaas_customer_id` (prioridade 1)
     - CPF/CNPJ (prioridade 2)
     - Email (prioridade 3)
   - Se encontrar tenant existente, atualiza
   - Se não encontrar, cria novo tenant
   - **Problema:** É para importação em massa, não para caso individual

2. **`AsaasBillingService::ensureCustomerForTenant(array $tenant)`**
   - Garante que tenant tem `asaas_customer_id`
   - Se não tem, busca no Asaas por CPF/CNPJ
   - Se encontra, vincula
   - Se não encontra, cria customer no Asaas
   - **Problema:** Precisamos do contrário - tenant do Asaas → sistema

3. **`AsaasClient::getCustomer(string $customerId)`**
   - Busca customer específico no Asaas pelo ID
   - Retorna dados completos do customer

4. **`AsaasClient::findCustomerByCpfCnpj(string $cpfCnpj)`**
   - Busca customer no Asaas por CPF/CNPJ
   - Retorna primeiro resultado encontrado

---

## 2. Cenário Real

### 2.1. Situação
- **Cliente:** "Falcon Securitizadora Sa" (exemplo)
- **No Asaas:** ✅ Já existe, tem customer_id, fez Pix, pagamento confirmado
- **No Sistema:** ❌ Ainda não cadastrado
- **Ação Necessária:** Cadastrar cliente no sistema e criar projeto de "Cartão de Visita"

### 2.2. Problemas a Evitar

**Duplicatas:**
- ❌ Criar tenant sem verificar se já existe no Asaas
- ❌ Criar múltiplos tenants para mesmo cliente
- ❌ Não vincular `asaas_customer_id` corretamente

**Dados Inconsistentes:**
- ❌ Dados diferentes entre sistema e Asaas
- ❌ Perder histórico de pagamentos
- ❌ Não sincronizar faturas existentes

---

## 3. Estratégias Possíveis

### 3.1. Estratégia A: Buscar por CPF/CNPJ no Wizard

**Fluxo:**
1. No wizard de criação de projeto, ao selecionar "Criar novo cliente"
2. Solicitar CPF/CNPJ primeiro
3. Buscar no Asaas: `AsaasClient::findCustomerByCpfCnpj($cpfCnpj)`
4. Se encontrar:
   - Perguntar: "Cliente encontrado no Asaas. Deseja importar dados?"
   - Mostrar preview: Nome, Email, Telefone
   - Se confirmar: Importa e cria tenant com `asaas_customer_id` já vinculado
5. Se não encontrar: Cria cliente normalmente (sem `asaas_customer_id` ainda)

**Vantagens:**
- ✅ Detecta cliente existente antes de criar
- ✅ Evita duplicatas proativamente
- ✅ Importa dados automaticamente (economiza tempo)
- ✅ Já vincula `asaas_customer_id` desde o início

**Desvantagens:**
- ⚠️ Exige CPF/CNPJ no cadastro (pode ser indesejado)
- ⚠️ Pode confundir usuário se houver múltiplos cadastros no Asaas

**Implementação:**
- Adicionar campo CPF/CNPJ no modal de criação de cliente (wizard)
- Chamar API do Asaas antes de criar tenant
- Se encontrar, importar dados e preencher formulário
- Salvar tenant já com `asaas_customer_id`

---

### 3.2. Estratégia B: Botão "Importar do Asaas" na Lista de Clientes

**Fluxo:**
1. Na página `/tenants` (lista de clientes)
2. Botão "Importar do Asaas"
3. Modal com campo para buscar:
   - CPF/CNPJ OU
   - Customer ID do Asaas OU
   - Email (se o Asaas permitir busca por email)
4. Busca no Asaas e mostra resultados
5. Usuário seleciona qual importar
6. Mostra preview dos dados
7. Confirma importação
8. Cria tenant com dados importados + `asaas_customer_id`
9. Sincroniza faturas automaticamente

**Vantagens:**
- ✅ Não atrapalha o fluxo do wizard
- ✅ Permite importação sob demanda
- ✅ Útil para importar clientes antigos do Asaas
- ✅ Mostra múltiplos resultados se houver duplicatas no Asaas

**Desvantagens:**
- ⚠️ Requer ação manual separada
- ⚠️ Não previne duplicata no wizard (pode criar mesmo assim)

**Implementação:**
- Novo método no controller: `importFromAsaas()`
- Modal na lista de clientes
- Busca no Asaas e exibe resultados
- Confirmação e importação

---

### 3.3. Estratégia C: Híbrida - Verificação Automática + Importação Manual

**Fluxo:**
1. **No Wizard (Estratégia A):** Busca automática por CPF/CNPJ
2. **Na Lista (Estratégia B):** Botão de importação manual
3. **Pós-Criação:** Se cliente foi criado sem `asaas_customer_id`, mas depois descobriu que existe no Asaas, pode vincular manualmente

**Vantagens:**
- ✅ Melhor dos dois mundos
- ✅ Previne duplicatas no wizard
- ✅ Oferece importação manual para casos especiais
- ✅ Permite correção posterior se necessário

**Desvantagens:**
- ⚠️ Mais complexa (duas funcionalidades)
- ⚠️ Pode confundir usuário sobre qual usar

---

## 4. Análise de Duplicatas

### 4.1. Como o Sistema Verifica Duplicatas Atualmente

**No método `syncAllCustomersAndInvoices()` (linhas 1059-1082):**

1. **Busca por `asaas_customer_id`:**
   ```php
   SELECT * FROM tenants WHERE asaas_customer_id = ?
   ```
   - **Eficácia:** ✅ 100% - Garante vínculo único

2. **Busca por CPF/CNPJ:**
   ```php
   SELECT * FROM tenants WHERE cpf_cnpj = ? OR document = ?
   ```
   - **Eficácia:** ✅ Alta - CPF/CNPJ deve ser único por pessoa/empresa
   - **Risco:** Se cliente tiver múltiplos cadastros no Asaas (mesmo CPF), pode criar múltiplos tenants

3. **Busca por Email:**
   ```php
   SELECT * FROM tenants WHERE email = ?
   ```
   - **Eficácia:** ⚠️ Média - Email pode não ser único (pessoa pode ter múltiplos emails)
   - **Risco:** Falso positivo (email igual mas pessoas diferentes)

### 4.2. Proteções Existentes

**Índice Único:**
- `asaas_customer_id` tem índice único na tabela `tenants`
- **Protege contra:** Múltiplos tenants com mesmo `asaas_customer_id`
- **Não protege contra:** Tenant criado manualmente sem `asaas_customer_id`, depois alguém tenta vincular customer já vinculado a outro tenant

**Flag de Arquivamento:**
- Clientes duplicados podem ser arquivados (`is_archived = 1`)
- **Protege contra:** Duplicatas visuais na lista
- **Não resolve:** Duplicatas no banco de dados

---

## 5. Recomendação para o Cenário

### 5.1. Solução Recomendada: **Estratégia C (Híbrida)**

**FASE 1 - Prevenção no Wizard (Curto Prazo):**

1. **Adicionar campo CPF/CNPJ no modal de criação de cliente:**
   - Campo opcional, mas recomendado
   - Ao preencher, busca automática no Asaas
   - Se encontrar: mostra preview e oferece importar
   - Se não encontrar: cria normalmente

2. **Método de importação no controller:**
   ```php
   public function importFromAsaasByCpfCnpj(): void
   {
       $cpfCnpj = $_POST['cpf_cnpj'] ?? '';
       $cpfCnpj = preg_replace('/[^0-9]/', '', $cpfCnpj);
       
       // Verifica se já existe no sistema
       $existingTenant = $this->findTenantByCpfCnpj($cpfCnpj);
       if ($existingTenant) {
           // Retorna erro: cliente já existe
           return $this->json(['error' => 'Cliente já cadastrado no sistema']);
       }
       
       // Busca no Asaas
       $asaasCustomer = AsaasClient::findCustomerByCpfCnpj($cpfCnpj);
       if (!$asaasCustomer) {
           // Retorna erro: não encontrado no Asaas
           return $this->json(['error' => 'Cliente não encontrado no Asaas']);
       }
       
       // Cria tenant com dados do Asaas
       $tenantId = $this->createTenantFromAsaas($asaasCustomer);
       
       // Sincroniza faturas
       AsaasBillingService::syncInvoicesForTenant($tenantId);
       
       return $this->json(['success' => true, 'tenant_id' => $tenantId]);
   }
   ```

**FASE 2 - Importação Manual (Médio Prazo):**

1. **Botão "Importar do Asaas" na lista de clientes**
2. **Busca por CPF/CNPJ ou Customer ID**
3. **Mostra preview e confirma importação**

---

## 6. Fluxo Recomendado para o Caso Real

### 6.1. Processo Manual (Agora - Sem Implementação)

**Passo a Passo:**

1. **Identificar Customer ID no Asaas:**
   - Ir no Asaas → Cliente → Copiar ID (ex: `155212744`)

2. **Usar Wizard de Projeto:**
   - Ir em "Novo Projeto (Assistente)"
   - Selecionar "Criar novo cliente"
   - Preencher dados manualmente (nome, CPF/CNPJ, email)
   - Criar cliente

3. **Vincular Customer ID do Asaas:**
   - Ir em Clientes → Editar cliente criado
   - **NOVO:** Adicionar campo "Customer ID do Asaas" (input manual)
   - Preencher com ID copiado do Asaas
   - Salvar

4. **Sincronizar Faturas:**
   - Ir em Cliente → Aba "Financeiro"
   - Clicar em "Sincronizar com Asaas"
   - Sistema busca todas as faturas do customer e importa

5. **Criar Projeto:**
   - Voltar ao wizard ou criar projeto normalmente
   - Selecionar cliente já vinculado

**Problema:** Passo 3 requer implementação de campo manual para `asaas_customer_id`

---

### 6.2. Processo Automatizado (Após Implementação)

**Passo a Passo:**

1. **No Wizard:**
   - Selecionar "Criar novo cliente"
   - Modal abre
   - **Preencher CPF/CNPJ primeiro**
   - Sistema busca automaticamente no Asaas
   - Se encontrar: mostra preview e botão "Importar do Asaas"
   - Clica em "Importar"
   - Sistema importa dados + vincula `asaas_customer_id` automaticamente
   - Cliente criado já com histórico de faturas

2. **Criar Projeto:**
   - Continuar no wizard
   - Selecionar serviço "Cartão de Visita"
   - Finalizar criação

**Vantagem:** Tudo em um único fluxo, sem etapas manuais

---

## 7. Implementação Técnica Sugerida

### 7.1. Método de Importação

**Arquivo:** `src/Services/TenantImportService.php` (NOVO)

```php
<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PixelHub\Services\AsaasClient;
use PixelHub\Services\AsaasBillingService;

class TenantImportService
{
    /**
     * Importa cliente do Asaas para o sistema
     * 
     * @param string $cpfCnpj CPF/CNPJ do cliente (apenas números)
     * @return array ['success' => bool, 'tenant_id' => int|null, 'message' => string]
     */
    public static function importFromAsaas(string $cpfCnpj): array
    {
        $db = DB::getConnection();
        
        // Normaliza CPF/CNPJ
        $cpfCnpj = preg_replace('/[^0-9]/', '', $cpfCnpj);
        
        if (empty($cpfCnpj)) {
            return ['success' => false, 'message' => 'CPF/CNPJ inválido'];
        }
        
        // Verifica se já existe no sistema
        $stmt = $db->prepare("
            SELECT * FROM tenants 
            WHERE cpf_cnpj = ? OR document = ? OR asaas_customer_id IS NOT NULL
        ");
        $stmt->execute([$cpfCnpj, $cpfCnpj]);
        $existingTenant = $stmt->fetch();
        
        if ($existingTenant) {
            // Se já tem asaas_customer_id, não precisa importar
            if (!empty($existingTenant['asaas_customer_id'])) {
                return [
                    'success' => false, 
                    'message' => 'Cliente já cadastrado e vinculado ao Asaas',
                    'tenant_id' => $existingTenant['id']
                ];
            }
            
            // Se existe mas não tem asaas_customer_id, pode vincular
            return [
                'success' => false,
                'message' => 'Cliente já existe no sistema, mas não está vinculado ao Asaas',
                'tenant_id' => $existingTenant['id'],
                'needs_link' => true
            ];
        }
        
        // Busca no Asaas
        $asaasCustomer = AsaasClient::findCustomerByCpfCnpj($cpfCnpj);
        
        if (!$asaasCustomer) {
            return ['success' => false, 'message' => 'Cliente não encontrado no Asaas'];
        }
        
        // Verifica se o customer_id já está vinculado a outro tenant
        $asaasCustomerId = $asaasCustomer['id'] ?? null;
        if ($asaasCustomerId) {
            $stmt = $db->prepare("SELECT * FROM tenants WHERE asaas_customer_id = ?");
            $stmt->execute([$asaasCustomerId]);
            $linkedTenant = $stmt->fetch();
            
            if ($linkedTenant) {
                return [
                    'success' => false,
                    'message' => 'Este cliente do Asaas já está vinculado a outro cliente no sistema',
                    'tenant_id' => $linkedTenant['id']
                ];
            }
        }
        
        // Cria tenant com dados do Asaas
        $tenantId = self::createTenantFromAsaas($asaasCustomer);
        
        // Sincroniza faturas
        try {
            AsaasBillingService::syncInvoicesForTenant($tenantId);
        } catch (\Exception $e) {
            error_log("Erro ao sincronizar faturas após importação: " . $e->getMessage());
            // Não falha a importação se sincronização falhar
        }
        
        return [
            'success' => true,
            'tenant_id' => $tenantId,
            'message' => 'Cliente importado com sucesso do Asaas'
        ];
    }
    
    /**
     * Cria tenant no sistema com dados do Asaas
     */
    private static function createTenantFromAsaas(array $asaasCustomer): int
    {
        $db = DB::getConnection();
        
        // Prepara dados
        $cpfCnpj = preg_replace('/[^0-9]/', '', $asaasCustomer['cpfCnpj'] ?? '');
        $personType = ($asaasCustomer['personType'] ?? 'FISICA') === 'COMPANY' ? 'pj' : 'pf';
        $name = $asaasCustomer['name'] ?? '';
        $email = $asaasCustomer['email'] ?? null;
        $phone = $asaasCustomer['phone'] ?? $asaasCustomer['mobilePhone'] ?? null;
        $asaasCustomerId = $asaasCustomer['id'] ?? null;
        
        // Remove formatação do telefone
        if ($phone) {
            $phone = preg_replace('/[^0-9]/', '', $phone);
        }
        
        // Para PJ, extrai dados adicionais
        $razaoSocial = null;
        $nomeFantasia = null;
        
        if ($personType === 'pj') {
            $razaoSocial = $asaasCustomer['companyName'] ?? $name;
            $nomeFantasia = $name;
        }
        
        // Endereço (se disponível no Asaas)
        $addressCep = preg_replace('/[^0-9]/', '', $asaasCustomer['postalCode'] ?? '');
        $addressStreet = $asaasCustomer['address'] ?? null;
        $addressNumber = $asaasCustomer['addressNumber'] ?? null;
        $addressComplement = $asaasCustomer['complement'] ?? null;
        $addressNeighborhood = $asaasCustomer['province'] ?? null;
        $addressCity = $asaasCustomer['city'] ?? null;
        $addressState = strtoupper($asaasCustomer['state'] ?? '');
        
        // Insere tenant
        $stmt = $db->prepare("
            INSERT INTO tenants 
            (person_type, name, cpf_cnpj, document, razao_social, nome_fantasia,
             email, phone, asaas_customer_id,
             address_cep, address_street, address_number, address_complement,
             address_neighborhood, address_city, address_state,
             status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ");
        
        $stmt->execute([
            $personType,
            $name,
            $cpfCnpj,
            $cpfCnpj, // document também
            $razaoSocial,
            $nomeFantasia,
            $email,
            $phone,
            $asaasCustomerId,
            $addressCep ?: null,
            $addressStreet,
            $addressNumber,
            $addressComplement,
            $addressNeighborhood,
            $addressCity,
            $addressState ?: null,
        ]);
        
        return (int) $db->lastInsertId();
    }
}
```

---

## 8. Conclusão e Próximos Passos

### 8.1. Recomendação Final

**Implementar Estratégia C (Híbrida):**

1. **FASE 1 (Curto Prazo):** Adicionar busca por CPF/CNPJ no wizard de criação de cliente
2. **FASE 2 (Médio Prazo):** Botão "Importar do Asaas" na lista de clientes

### 8.2. Para o Caso Real (Agora)

**Solução Temporária:**
1. Cadastrar cliente manualmente no wizard (preenchendo todos os dados)
2. Ir em Clientes → Editar cliente criado
3. Copiar `asaas_customer_id` do Asaas e colar em campo manual (precisa implementar)
4. Salvar
5. Ir em Financeiro → Sincronizar com Asaas

**Solução Ideal (Após Implementação):**
1. No wizard, ao criar cliente, preencher CPF/CNPJ
2. Sistema busca no Asaas automaticamente
3. Se encontrar, oferece importar
4. Cliente criado já com tudo vinculado

### 8.3. Proteções Contra Duplicatas

**Já Implementadas:**
- ✅ Índice único em `asaas_customer_id`
- ✅ Verificação por CPF/CNPJ antes de criar
- ✅ Verificação por email (secundária)

**A Implementar:**
- ✅ Busca automática no Asaas antes de criar
- ✅ Alerta se cliente já existe
- ✅ Importação automática de dados

---

**Fim da Análise**




