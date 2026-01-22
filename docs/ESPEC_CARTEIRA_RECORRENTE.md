# Especificação - Carteira Recorrente

**Data:** 2025-01-XX  
**Status:** Especificação conceitual (sem implementação)  
**Referência:** `docs/ARQUITETURA_HOSPEDAGEM_FINANCEIRO.md`

---

## 1. Objetivo de Negócio

A **Carteira Recorrente** é uma visão gerencial consolidada de todos os contratos e assinaturas recorrentes da Pixel12Digital com seus clientes. Ela permite ao dono da agência e à equipe administrativa:

### 1.1. Visão Unificada de Contratos Recorrentes

Ver todos os contratos recorrentes em um só lugar, independentemente do tipo de serviço:
- **Hospedagem de sites** (sites rodando em servidor próprio ou de terceiros)
- **Sistemas SaaS recorrentes** (ex: ImobSites, CFC - licenças mensais/anuais)
- **Outros serviços recorrentes** (ex: manutenção mensal de site, gestão de tráfego pago, SEO mensal, etc.)

### 1.2. Diferenciação por Tipo de Serviço

Conseguir diferenciar claramente:
- **O que é apenas hospedagem**: site rodando em servidor (infraestrutura)
- **O que é sistema SaaS**: licença de software recorrente (ex: ImobSites, CFC)
- **O que são outros serviços recorrentes**: manutenção, gestão, consultoria, etc.

### 1.3. Análise Financeira Recorrente

Conseguir visualizar e analisar:
- **Receita recorrente estimada**: valor total que a agência recebe mensalmente/anualmente de todos os contratos ativos
- **Receita por ciclo**: separação entre receita mensal, trimestral, semestral e anual
- **Custo recorrente estimado**: custos fixos associados a cada contrato (ex: custo da hospedagem no provedor, licença de terceiro, etc.)
- **Margem estimada**: diferença entre receita e custo (receita - custo)
- **Projeção de receita**: estimativa de receita futura baseada em contratos ativos

### 1.4. Relação com Funcionalidades Existentes

**Importante:** A Carteira Recorrente **não substitui** as telas atuais de Financeiro nem a Central de Cobranças. Ela é uma **camada de visão gerencial** em cima do que já existe:

- **Aba Financeiro do Cliente** (`/tenants/view?tab=financial`): continua mostrando faturas individuais, histórico de pagamentos e status de cobrança
- **Central de Cobranças** (`/billing-collections`): continua sendo o lugar para gerenciar cobranças via WhatsApp e sincronizar com Asaas
- **Carteira Recorrente**: será uma nova visão que agrupa contratos recorrentes e permite análises de receita recorrente, margem e projeções

---

## 2. Definição de "Contrato Recorrente"

### 2.1. Conceito

Um **contrato recorrente** no sistema representa um relacionamento contínuo entre a Pixel12Digital e um cliente, caracterizado por:

- **Cliente (tenant)**: quem recebe o serviço
- **Tipo de serviço**: categoria do serviço (hospedagem, SaaS ImobSites, SaaS CFC, outro)
- **Valor recorrente (receita)**: quanto a agência recebe por ciclo
- **Custo recorrente (opcional)**: quanto custa para a agência fornecer esse serviço
- **Ciclo de cobrança**: frequência da cobrança (mensal, trimestral, semestral, anual, outro)
- **Status**: se está ativo, congelado, cancelado ou em período de teste
- **Datas importantes**: início do contrato, fim (se houver), próxima revisão, etc.

### 2.2. Independência de Faturas Individuais

**Ponto crítico:** Um contrato recorrente **não depende diretamente de uma única fatura no Asaas**. Ele é uma visão de contrato/assinatura de longo prazo, mesmo que:

- A cobrança seja feita via boletos individuais (não via subscription do Asaas)
- As faturas sejam criadas manualmente ou via sincronização
- Haja múltiplas faturas relacionadas ao mesmo contrato ao longo do tempo

**Exemplo prático:**
- Cliente tem contrato de hospedagem mensal de R$ 50,00
- Todo mês, uma fatura é gerada no Asaas (via boleto ou cartão)
- O contrato recorrente representa a "assinatura" de R$ 50,00/mês
- As faturas individuais são vinculadas ao contrato via `billing_contract_id` (quando possível)

### 2.3. Relação com Faturas

As faturas (`billing_invoices`) podem ser usadas para:
- **Validar se o contrato está sendo cobrado**: verificar se há faturas recentes vinculadas ao contrato
- **Alimentar gráficos e análises**: comparar receita prevista (do contrato) com receita realizada (das faturas pagas)
- **Identificar problemas**: se um contrato está ativo mas não há faturas sendo geradas, pode indicar problema na cobrança

---

## 3. Mapeamento com as Tabelas Existentes

### 3.1. Tabela `tenants` (Clientes)

**Como o contrato recorrente referencia o cliente:**

- Campo `billing_contracts.tenant_id` → `tenants.id` (obrigatório)
- Um tenant pode ter múltiplos contratos recorrentes (ex: hospedagem + ImobSites + manutenção)
- A Carteira Recorrente deve permitir filtrar por tenant e agrupar contratos por cliente

**Campos relevantes do tenant:**
- `name`: nome do cliente (para exibição)
- `status`: se o cliente está ativo ou inativo
- `billing_status`: status financeiro atual (pode ser usado para alertas na carteira)

---

### 3.2. Tabela `hosting_accounts` (Contas de Hospedagem)

**Quando o contrato recorrente for apenas hospedagem ou incluir hospedagem:**

- Campo `billing_contracts.hosting_account_id` → `hosting_accounts.id` (opcional)
- Um contrato de hospedagem pode referenciar uma conta de hospedagem específica
- Isso permite saber qual domínio/site está vinculado ao contrato

**Observação importante:**
- Nem todo contrato recorrente precisa ter `hosting_account_id`
- Contratos de SaaS (ImobSites, CFC) ou outros serviços não têm hospedagem vinculada
- O campo é opcional e deve ser usado apenas quando fizer sentido

**Campos relevantes de `hosting_accounts`:**
- `domain`: domínio do site (para exibição)
- `amount`: valor da hospedagem (pode ser usado como referência, mas o valor do contrato é a fonte da verdade)
- `billing_cycle`: ciclo de cobrança (pode ser usado como referência)

---

### 3.3. Tabela `hosting_plans` (Planos de Hospedagem)

**Como o plano de hospedagem pode ser usado como referência de "produto":**

- Campo `billing_contracts.hosting_plan_id` → `hosting_plans.id` (opcional)
- Um contrato pode referenciar um plano de hospedagem específico
- Isso permite saber qual "produto" foi contratado

**Limitação atual:**
- A tabela `hosting_plans` é específica para hospedagem
- Para contratos de SaaS ou outros serviços, não há tabela de planos equivalente
- O campo `plan_snapshot_name` em `billing_contracts` armazena o nome do plano no momento da criação (snapshot)

**Campos relevantes de `hosting_plans`:**
- `name`: nome do plano (para exibição)
- `amount`: valor mensal do plano (referência)
- `billing_cycle`: ciclo de cobrança do plano (referência)

---

### 3.4. Tabela `billing_invoices` (Faturas/Cobranças)

**Como as faturas existentes podem ser usadas:**

#### 3.4.1. Validação de Cobrança

- Verificar se há faturas recentes vinculadas ao contrato via `billing_invoices.billing_contract_id`
- Se um contrato está ativo mas não há faturas nos últimos ciclos, pode indicar problema

#### 3.4.2. Alimentar Gráficos e Análises

- **Receita prevista**: soma dos valores dos contratos ativos (`billing_contracts.amount`)
- **Receita realizada**: soma dos valores das faturas pagas (`billing_invoices.amount WHERE status = 'paid'`)
- Comparar previsto vs. realizado para identificar discrepâncias

#### 3.4.3. Status Atual

**Campo `billing_contract_id` em `billing_invoices`:**
- ✅ Campo já existe e está indexado
- ❌ Não é preenchido durante a sincronização atual com Asaas
- ⚠️ Não é usado em nenhuma query ou view atualmente

**Ação futura necessária:**
- Preencher `billing_contract_id` quando possível (ex: se `external_reference` contiver ID de contrato)
- Criar fluxo para vincular faturas existentes a contratos manualmente ou automaticamente

**Campos relevantes de `billing_invoices`:**
- `tenant_id`: cliente da fatura
- `billing_contract_id`: contrato vinculado (quando preenchido)
- `amount`: valor da fatura
- `status`: status da fatura (paid, pending, overdue, etc.)
- `due_date`: data de vencimento
- `paid_at`: data de pagamento (para cálculos de receita realizada)

---

### 3.5. Tabela `billing_contracts` (Contratos - Candidata Natural)

**Esta tabela será a base para representar contratos recorrentes.**

#### 3.5.1. Campos Existentes Hoje

Com base na migration `20251118_create_billing_contracts_table.php`, a tabela possui:

| Campo | Tipo | Descrição | Reaproveitável? |
|-------|------|-----------|-----------------|
| `id` | INT UNSIGNED | ID único do contrato | ✅ Sim |
| `tenant_id` | INT UNSIGNED | Cliente (obrigatório) | ✅ Sim |
| `hosting_account_id` | INT UNSIGNED | Conta de hospedagem (opcional) | ⚠️ Parcial - só para hospedagem |
| `hosting_plan_id` | INT UNSIGNED | Plano de hospedagem (opcional) | ⚠️ Parcial - só para hospedagem |
| `plan_snapshot_name` | VARCHAR(255) | Nome do plano no momento da criação | ✅ Sim (pode ser usado como "nome do contrato") |
| `billing_mode` | VARCHAR(20) | 'mensal' ou 'anual' | ✅ Sim (pode ser expandido) |
| `amount` | DECIMAL(10,2) | Valor do contrato | ✅ Sim (receita recorrente) |
| `annual_total_amount` | DECIMAL(10,2) | Valor total anual (se billing_mode = 'anual') | ✅ Sim |
| `asaas_subscription_id` | VARCHAR(100) | ID da subscription no Asaas | ✅ Sim (para integração futura) |
| `asaas_external_reference` | VARCHAR(255) | Referência externa | ✅ Sim |
| `status` | VARCHAR(20) | Status do contrato | ✅ Sim (pode ser expandido) |
| `created_at` | DATETIME | Data de criação | ✅ Sim |
| `updated_at` | DATETIME | Data de atualização | ✅ Sim |

#### 3.5.2. Campos que Podem Ser Reaproveitados

**✅ Totalmente reaproveitáveis:**
- `tenant_id`: identifica o cliente
- `plan_snapshot_name`: pode ser usado como "nome do contrato" ou "título"
- `billing_mode`: ciclo de cobrança (pode ser expandido para incluir trimestral, semestral, etc.)
- `amount`: receita recorrente
- `annual_total_amount`: receita anual (quando aplicável)
- `status`: status do contrato (ativo, cancelado, etc.)
- `asaas_subscription_id`: para integração futura com Asaas
- `asaas_external_reference`: referência externa

**⚠️ Parcialmente reaproveitáveis:**
- `hosting_account_id`: útil apenas para contratos de hospedagem
- `hosting_plan_id`: útil apenas para contratos de hospedagem

#### 3.5.3. Lacunas Identificadas

**Campos que faltam para representar completamente um contrato recorrente:**

1. **`service_type`** (VARCHAR(50)): tipo de serviço
   - Valores possíveis: 'hospedagem_site', 'saas_imobsites', 'saas_cfc', 'manutencao_site', 'gestao_trafego', 'outro_recorrente'
   - **Necessário para:** filtrar e agrupar contratos por tipo de serviço

2. **`cost_amount`** (DECIMAL(10,2), NULL): custo recorrente estimado
   - Valor que a agência paga para fornecer o serviço (ex: custo da hospedagem no provedor)
   - **Necessário para:** calcular margem (receita - custo)

3. **`title` ou `name`** (VARCHAR(255)): nome amigável do contrato
   - Atualmente `plan_snapshot_name` pode servir, mas para contratos não-hospedagem pode não fazer sentido
   - **Necessário para:** exibição clara do contrato na interface

4. **`description`** (TEXT, NULL): detalhes resumidos do contrato
   - Informações adicionais sobre o que está incluído no contrato
   - **Necessário para:** contexto adicional na visualização

5. **`start_date`** (DATE, NULL): data de início do contrato
   - Quando o contrato começou a valer
   - **Necessário para:** cálculos de receita acumulada e projeções

6. **`end_date`** (DATE, NULL): data de fim do contrato (opcional)
   - Se o contrato tem data de término (ex: contrato anual com renovação)
   - **Necessário para:** identificar contratos que estão próximos do fim

7. **`next_review_date`** (DATE, NULL): próxima data de revisão
   - Data em que o contrato deve ser revisado (ex: renegociação de preço)
   - **Necessário para:** alertas e planejamento

8. **`reference_type` e `reference_id`** (genéricos): para ligar com outros módulos
   - Atualmente só há `hosting_account_id` e `hosting_plan_id` (específicos para hospedagem)
   - **Necessário para:** vincular contratos a outros tipos de serviços no futuro (ex: ID de projeto, ID de sistema SaaS)

9. **`billing_cycle`** (VARCHAR(50)): ciclo de cobrança expandido
   - Atualmente `billing_mode` só aceita 'mensal' ou 'anual'
   - **Necessário para:** suportar trimestral, semestral, etc.

10. **`internal_notes`** (TEXT, NULL): observações internas
    - Notas da equipe sobre o contrato
    - **Necessário para:** contexto interno

---

## 4. Proposta de Campos Necessários em `billing_contracts` (Conceitual)

### 4.1. Modelo Ideal de Contrato Recorrente

Abaixo está a lista de campos ideais que um contrato recorrente deveria ter. **Campos marcados com ✅ já existem**, campos marcados com ➕ seriam novos:

| Campo | Tipo | Obrigatório? | Status | Descrição |
|-------|------|--------------|--------|-----------|
| `id` | INT UNSIGNED | Sim | ✅ Existe | ID único do contrato |
| `tenant_id` | INT UNSIGNED | Sim | ✅ Existe | Cliente (FK para tenants) |
| `service_type` | VARCHAR(50) | Sim | ➕ Novo | Tipo de serviço (hospedagem_site, saas_imobsites, etc.) |
| `title` | VARCHAR(255) | Sim | ➕ Novo | Nome amigável do contrato |
| `description` | TEXT | Não | ➕ Novo | Detalhes resumidos do contrato |
| `hosting_account_id` | INT UNSIGNED | Não | ✅ Existe | Conta de hospedagem (quando aplicável) |
| `hosting_plan_id` | INT UNSIGNED | Não | ✅ Existe | Plano de hospedagem (quando aplicável) |
| `reference_type` | VARCHAR(50) | Não | ➕ Novo | Tipo de referência genérica (ex: 'hosting_account', 'saas_imobsites', 'project') |
| `reference_id` | INT UNSIGNED | Não | ➕ Novo | ID da referência genérica |
| `billing_cycle` | VARCHAR(50) | Sim | ➕ Novo | Ciclo de cobrança (mensal, trimestral, semestral, anual) |
| `billing_mode` | VARCHAR(20) | Sim | ✅ Existe | Modo de cobrança (mensal, anual) - pode ser mantido para compatibilidade |
| `revenue_amount` | DECIMAL(10,2) | Sim | ✅ Existe (como `amount`) | Valor recorrente bruto (receita) |
| `cost_amount` | DECIMAL(10,2) | Não | ➕ Novo | Custo recorrente estimado |
| `annual_total_amount` | DECIMAL(10,2) | Não | ✅ Existe | Valor total anual (se billing_cycle = 'anual') |
| `status` | VARCHAR(20) | Sim | ✅ Existe | Status (ativo, congelado, cancelado, teste) |
| `start_date` | DATE | Não | ➕ Novo | Data de início do contrato |
| `end_date` | DATE | Não | ➕ Novo | Data de fim do contrato (opcional) |
| `next_review_date` | DATE | Não | ➕ Novo | Próxima data de revisão |
| `asaas_subscription_id` | VARCHAR(100) | Não | ✅ Existe | ID da subscription no Asaas |
| `asaas_external_reference` | VARCHAR(255) | Não | ✅ Existe | Referência externa |
| `internal_notes` | TEXT | Não | ➕ Novo | Observações internas |
| `created_at` | DATETIME | Sim | ✅ Existe | Data de criação |
| `updated_at` | DATETIME | Sim | ✅ Existe | Data de atualização |

### 4.2. Observações sobre Campos Existentes

**Campo `amount` → `revenue_amount`:**
- O campo `amount` já existe e representa o valor recorrente
- Pode ser renomeado para `revenue_amount` para deixar mais claro, ou mantido como `amount` e adicionar `cost_amount` como complemento
- **Recomendação:** Manter `amount` como está e adicionar `cost_amount` (não renomear para evitar breaking changes)

**Campo `billing_mode` vs `billing_cycle`:**
- `billing_mode` atualmente só aceita 'mensal' ou 'anual'
- `billing_cycle` seria mais completo (mensal, trimestral, semestral, anual)
- **Recomendação:** Adicionar `billing_cycle` e manter `billing_mode` para compatibilidade (ou migrar dados)

**Campo `plan_snapshot_name`:**
- Pode ser usado como "nome do contrato" temporariamente
- Idealmente seria substituído por `title` (mais genérico)
- **Recomendação:** Adicionar `title` e manter `plan_snapshot_name` para compatibilidade

**Campos `hosting_account_id` e `hosting_plan_id`:**
- Úteis apenas para contratos de hospedagem
- Para generalizar, seria ideal ter `reference_type` e `reference_id` genéricos
- **Recomendação:** Adicionar campos genéricos e manter os específicos para compatibilidade

### 4.3. Estratégia de Migração Incremental

**Fase 1 - Campos Essenciais (Mínimo Viável):**
- ➕ `service_type` (obrigatório para diferenciar tipos de serviço)
- ➕ `cost_amount` (opcional, para calcular margem)
- ➕ `start_date` (opcional, para cálculos de receita acumulada)

**Fase 2 - Campos de Organização:**
- ➕ `title` (para exibição clara)
- ➕ `description` (para contexto)
- ➕ `billing_cycle` (expandir além de mensal/anual)

**Fase 3 - Campos Avançados:**
- ➕ `end_date` (para contratos com término)
- ➕ `next_review_date` (para planejamento)
- ➕ `reference_type` e `reference_id` (para generalização)
- ➕ `internal_notes` (para observações internas)

---

## 5. Tipos de Serviço e Categorização

### 5.1. Proposta de Tipos de Serviço

Para a Carteira Recorrente, propõe-se os seguintes tipos de serviço:

| Tipo | Código | Descrição | Exemplo |
|------|--------|-----------|---------|
| Hospedagem de Site | `hospedagem_site` | Hospedagem de site WordPress ou estático | Site do cliente rodando em servidor |
| SaaS ImobSites | `saas_imobsites` | Licença mensal/anual do sistema ImobSites | Cliente imobiliária usando ImobSites |
| SaaS CFC | `saas_cfc` | Licença mensal/anual do sistema CFC | Cliente autoescola usando sistema CFC |
| Manutenção de Site | `manutencao_site` | Manutenção mensal de site (atualizações, backups, etc.) | Manutenção mensal do site do cliente |
| Gestão de Tráfego | `gestao_trafego` | Gestão de campanhas de tráfego pago (Google Ads, Facebook Ads) | Gestão mensal de anúncios |
| SEO Mensal | `seo_mensal` | Serviço de SEO recorrente | Otimização mensal de SEO |
| Outro Recorrente | `outro_recorrente` | Outros serviços recorrentes não categorizados | Consultoria mensal, suporte técnico, etc. |

### 5.2. Como os Tipos Ajudam nos Filtros

**Na futura tela de Carteira Recorrente, os tipos permitirão:**

1. **Filtrar por tipo de serviço:**
   - Ver apenas contratos de hospedagem
   - Ver apenas contratos de SaaS
   - Ver apenas outros serviços

2. **Agrupar por tipo:**
   - Receita total por tipo de serviço
   - Quantidade de contratos por tipo
   - Margem média por tipo

3. **Relatórios específicos:**
   - "Receita recorrente de hospedagem vs SaaS"
   - "Crescimento de contratos SaaS nos últimos 6 meses"
   - "Margem por tipo de serviço"

### 5.3. Como os Tipos Ajudam em Relatórios

**Exemplos de relatórios possíveis:**

1. **Receita por tipo de serviço:**
   - Hospedagem: R$ 5.000/mês
   - SaaS ImobSites: R$ 3.000/mês
   - SaaS CFC: R$ 2.000/mês
   - Outros: R$ 1.000/mês
   - **Total:** R$ 11.000/mês

2. **Margem por tipo:**
   - Hospedagem: Receita R$ 5.000 - Custo R$ 2.000 = Margem R$ 3.000 (60%)
   - SaaS ImobSites: Receita R$ 3.000 - Custo R$ 500 = Margem R$ 2.500 (83%)
   - **Total:** Margem R$ 5.500

3. **Crescimento por tipo:**
   - Quantos novos contratos de cada tipo foram criados no último mês/trimestre
   - Qual tipo de serviço está crescendo mais rápido

---

## 6. Cenários de Uso

### 6.1. Cenário 1: Cliente que só tem Hospedagem

**Situação:**
- Cliente: "João Silva"
- Serviço: Hospedagem de site WordPress
- Domínio: `joaosilva.com.br`
- Valor: R$ 50,00/mês
- Custo: R$ 20,00/mês (custo da hospedagem no provedor)

**Como ficaria em `billing_contracts` (conceitualmente):**

```sql
-- Registro único em billing_contracts
{
  tenant_id: 1,  -- João Silva
  service_type: 'hospedagem_site',
  title: 'Hospedagem - joaosilva.com.br',
  description: 'Hospedagem WordPress',
  hosting_account_id: 10,  -- Referência à conta de hospedagem
  hosting_plan_id: 5,  -- Referência ao plano (opcional)
  billing_cycle: 'mensal',
  revenue_amount: 50.00,
  cost_amount: 20.00,
  status: 'ativo',
  start_date: '2024-01-15',
  ...
}
```

**Observações:**
- Um único registro representa o contrato recorrente
- Vinculado à conta de hospedagem via `hosting_account_id`
- Margem: R$ 30,00/mês (50 - 20)

---

### 6.2. Cenário 2: Cliente que tem Hospedagem + ImobSites

**Situação:**
- Cliente: "Imobiliária ABC"
- Serviço 1: Hospedagem de site
  - Domínio: `imobiliariaabc.com.br`
  - Valor: R$ 80,00/mês
  - Custo: R$ 30,00/mês
- Serviço 2: SaaS ImobSites
  - Licença mensal do sistema ImobSites
  - Valor: R$ 200,00/mês
  - Custo: R$ 50,00/mês (licença de terceiro ou custo de infraestrutura)

**Como ficaria em `billing_contracts` (conceitualmente):**

```sql
-- Registro 1: Hospedagem
{
  tenant_id: 2,  -- Imobiliária ABC
  service_type: 'hospedagem_site',
  title: 'Hospedagem - imobiliariaabc.com.br',
  hosting_account_id: 15,
  billing_cycle: 'mensal',
  revenue_amount: 80.00,
  cost_amount: 30.00,
  status: 'ativo',
  ...
}

-- Registro 2: SaaS ImobSites
{
  tenant_id: 2,  -- Imobiliária ABC
  service_type: 'saas_imobsites',
  title: 'Licença ImobSites - Imobiliária ABC',
  description: 'Sistema de gestão imobiliária',
  billing_cycle: 'mensal',
  revenue_amount: 200.00,
  cost_amount: 50.00,
  status: 'ativo',
  ...
}
```

**Observações:**
- Dois registros separados (um para cada tipo de serviço)
- Ambos vinculados ao mesmo `tenant_id`
- Receita total recorrente: R$ 280,00/mês
- Custo total: R$ 80,00/mês
- Margem total: R$ 200,00/mês

---

### 6.3. Cenário 3: Cliente que tem apenas ImobSites (Hospedagem Externa)

**Situação:**
- Cliente: "Imobiliária XYZ"
- Serviço: SaaS ImobSites
  - Licença mensal do sistema ImobSites
  - Valor: R$ 200,00/mês
  - Custo: R$ 50,00/mês
- **Observação:** O site está hospedado em outro provedor (não pela Pixel12Digital)

**Como ficaria em `billing_contracts` (conceitualmente):**

```sql
-- Registro único: SaaS ImobSites
{
  tenant_id: 3,  -- Imobiliária XYZ
  service_type: 'saas_imobsites',
  title: 'Licença ImobSites - Imobiliária XYZ',
  description: 'Sistema de gestão imobiliária',
  hosting_account_id: NULL,  -- Não há hospedagem na Pixel12Digital
  hosting_plan_id: NULL,  -- Não há plano de hospedagem
  billing_cycle: 'mensal',
  revenue_amount: 200.00,
  cost_amount: 50.00,
  status: 'ativo',
  ...
}
```

**Observações:**
- Um único registro (apenas SaaS)
- `hosting_account_id` e `hosting_plan_id` são NULL (não aplicável)
- Receita recorrente: R$ 200,00/mês
- Margem: R$ 150,00/mês

---

### 6.4. Cenário 4: Cliente com Múltiplos Serviços Recorrentes

**Situação:**
- Cliente: "Autoescola Progresso"
- Serviço 1: SaaS CFC
  - Licença mensal do sistema CFC
  - Valor: R$ 150,00/mês
  - Custo: R$ 40,00/mês
- Serviço 2: Manutenção de Site
  - Manutenção mensal do site (atualizações, backups)
  - Valor: R$ 100,00/mês
  - Custo: R$ 20,00/mês (tempo da equipe)
- Serviço 3: Gestão de Tráfego
  - Gestão de campanhas Google Ads
  - Valor: R$ 300,00/mês (taxa de gestão)
  - Custo: R$ 0,00 (apenas tempo da equipe, já considerado na margem)

**Como ficaria em `billing_contracts` (conceitualmente):**

```sql
-- Registro 1: SaaS CFC
{
  tenant_id: 4,  -- Autoescola Progresso
  service_type: 'saas_cfc',
  title: 'Licença CFC - Autoescola Progresso',
  billing_cycle: 'mensal',
  revenue_amount: 150.00,
  cost_amount: 40.00,
  status: 'ativo',
  ...
}

-- Registro 2: Manutenção
{
  tenant_id: 4,
  service_type: 'manutencao_site',
  title: 'Manutenção Mensal - Autoescola Progresso',
  billing_cycle: 'mensal',
  revenue_amount: 100.00,
  cost_amount: 20.00,
  status: 'ativo',
  ...
}

-- Registro 3: Gestão de Tráfego
{
  tenant_id: 4,
  service_type: 'gestao_trafego',
  title: 'Gestão Google Ads - Autoescola Progresso',
  billing_cycle: 'mensal',
  revenue_amount: 300.00,
  cost_amount: 0.00,  -- Apenas tempo da equipe
  status: 'ativo',
  ...
}
```

**Observações:**
- Três registros separados (um para cada serviço)
- Todos vinculados ao mesmo `tenant_id`
- Receita total recorrente: R$ 550,00/mês
- Custo total: R$ 60,00/mês
- Margem total: R$ 490,00/mês

---

## 7. Observações Finais

### 7.1. Esta Especificação é Intermediária

**Importante:** Esta especificação é um **passo intermediário** antes de qualquer mudança real no código ou banco de dados. Ela serve para:

- Documentar o entendimento do negócio
- Validar a abordagem com o usuário
- Garantir que não haverá duplicação de estruturas
- Planejar as mudanças de forma incremental e compatível

### 7.2. Próximos Passos (Após Validação)

Após a validação desta especificação, os próximos passos serão:

1. **Desenhar a primeira tela de Carteira Recorrente:**
   - Tela somente leitura (sem edição ainda)
   - Lista de contratos recorrentes
   - Filtros por tipo de serviço, cliente, status
   - Gráficos de receita recorrente, margem, etc.
   - **Não mexer nas telas atuais de financeiro**

2. **Pensar em migrations incrementais:**
   - Adicionar campos novos em `billing_contracts` de forma compatível
   - Não remover campos existentes
   - Garantir que dados existentes continuem funcionando

3. **Criar fluxo de vinculação de faturas:**
   - Preencher `billing_contract_id` em `billing_invoices` quando possível
   - Criar interface para vincular faturas existentes a contratos manualmente

4. **Integração com Asaas (futuro):**
   - Criar subscriptions no Asaas quando criar contratos
   - Preencher `asaas_subscription_id` em `billing_contracts`
   - Vincular faturas geradas automaticamente aos contratos

### 7.3. Princípios que Devem Ser Mantidos

- ✅ **Não criar tabelas duplicadas** - reaproveitar `billing_contracts`
- ✅ **Não alterar comportamentos críticos** - manter sincronização Asaas intacta
- ✅ **Não quebrar a aba Financeiro atual** - adicionar, não substituir
- ✅ **Preferir extensão à substituição** - adicionar campos opcionais
- ✅ **Manter compatibilidade** - não quebrar dados existentes

---

**Fim do Documento**

