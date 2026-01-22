# Arquitetura - Hospedagem & Financeiro

**Data da Auditoria:** 2025-01-XX  
**Objetivo:** Mapear a arquitetura atual relacionada a Clientes, Hospedagem, Planos e Financeiro para evitar duplicações em futuras implementações.

---

## 1. Tabelas de Banco de Dados Relacionadas

### 1.1. Clientes / Tenants

#### Tabela: `tenants`

**Campos principais:**
- `id` (INT UNSIGNED, PK)
- `person_type` (VARCHAR(2)) - 'pf' ou 'pj'
- `name` (VARCHAR(255)) - Nome do cliente
- `cpf_cnpj` (VARCHAR(20)) - CPF ou CNPJ
- `document` (VARCHAR(20)) - Campo legado, mantém compatibilidade com cpf_cnpj
- `razao_social` (VARCHAR(255)) - Para PJ
- `nome_fantasia` (VARCHAR(255)) - Para PJ
- `responsavel_nome` (VARCHAR(255)) - Para PJ
- `responsavel_cpf` (VARCHAR(20)) - Para PJ
- `email` (VARCHAR(255))
- `phone` (VARCHAR(20)) - WhatsApp
- `status` (VARCHAR(50)) - 'active' ou 'inactive'
- `asaas_customer_id` (VARCHAR(100), UNIQUE) - ID do customer no Asaas
- `billing_status` (VARCHAR(30)) - 'sem_cobranca', 'em_dia', 'atrasado_parcial', 'atrasado_total'
- `billing_last_check_at` (DATETIME) - Última verificação financeira
- `internal_notes` (TEXT) - Observações internas
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Relacionamentos:**
- **Com contas de hospedagem:** Campo `tenant_id` na tabela `hosting_accounts`
- **Com faturas/cobranças:** Campo `tenant_id` na tabela `billing_invoices`
- **Com Asaas:** Campo `asaas_customer_id` (único) - vincula o tenant ao customer no Asaas

**Índices:**
- `idx_status` (status)
- `idx_asaas_customer_id_unique` (asaas_customer_id) - UNIQUE

**Arquivo de migração:** `database/migrations/20251117_create_tenants_table.php`  
**Alterações:** `20251117_alter_tenants_add_person_type.php`, `20251118_alter_tenants_add_billing_fields.php`, `20251120_alter_tenants_add_unique_asaas_customer_id.php`, `20251121_alter_tenants_add_internal_notes.php`

---

### 1.2. Hospedagem & Sites

#### Tabela: `hosting_accounts`

**Campos principais:**
- `id` (INT UNSIGNED, PK)
- `tenant_id` (INT UNSIGNED, NOT NULL) - FK para tenants
- `domain` (VARCHAR(255), NOT NULL) - Domínio do site
- `hosting_plan_id` (INT UNSIGNED, NULL) - FK para hosting_plans (opcional)
- `plan_name` (VARCHAR(100), NULL) - Nome do plano (texto livre)
- `amount` (DECIMAL(10,2), NULL) - Valor da hospedagem
- `billing_cycle` (VARCHAR(50), NULL) - 'mensal', 'trimestral', 'semestral', 'anual'
- `current_provider` (VARCHAR(50)) - 'hostinger', 'hostweb', 'externo'
- `provider` (VARCHAR(100)) - Campo legado, mantém compatibilidade
- `hostinger_expiration_date` (DATE, NULL) - Data de vencimento na Hostinger
- `decision` (VARCHAR(50)) - 'pendente', 'migrar_pixel', 'hostinger_afiliado', 'encerrar'
- `backup_status` (VARCHAR(50)) - 'nenhum', 'completo', etc.
- `last_backup_at` (DATETIME, NULL) - Data do último backup
- `migration_status` (VARCHAR(50)) - 'nao_iniciada', 'em_andamento', 'concluida'
- `notes` (TEXT, NULL) - Observações
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Relacionamentos:**
- **Com tenants:** `tenant_id` → `tenants.id`
- **Com planos de hospedagem:** `hosting_plan_id` → `hosting_plans.id` (opcional, pode ser NULL)
- **Com backups:** Tabela `hosting_backups` referencia `hosting_account_id`
- **Com contratos:** Tabela `billing_contracts` pode referenciar `hosting_account_id` (opcional)

**Índices:**
- `idx_tenant_id` (tenant_id)
- `idx_domain` (domain)
- `idx_current_provider` (current_provider)
- `idx_hosting_plan_id` (hosting_plan_id)
- `idx_decision` (decision)
- `idx_backup_status` (backup_status)
- `idx_migration_status` (migration_status)

**Arquivo de migração:** `database/migrations/20251117_create_hosting_accounts_table.php`  
**Alterações:** `20251117_alter_hosting_accounts_add_fields.php`, `20251117_alter_hosting_accounts_add_plan_id.php`

**Observações importantes:**
- O campo `hosting_plan_id` é opcional (pode ser NULL)
- Existe também `plan_name` como texto livre (não normalizado)
- O valor e ciclo de cobrança são armazenados diretamente na conta de hospedagem (`amount`, `billing_cycle`)
- **Não há vínculo direto com faturas** - as faturas são vinculadas apenas ao `tenant_id`

---

#### Tabela: `hosting_backups`

**Campos principais:**
- `id` (INT UNSIGNED, PK)
- `hosting_account_id` (INT UNSIGNED, NOT NULL) - FK para hosting_accounts
- `type` (VARCHAR(50)) - 'all_in_one_wp' (padrão)
- `file_name` (VARCHAR(255), NOT NULL)
- `file_size` (BIGINT UNSIGNED, NULL)
- `stored_path` (VARCHAR(500), NOT NULL)
- `notes` (TEXT, NULL)
- `created_at` (DATETIME)

**Relacionamentos:**
- **Com contas de hospedagem:** `hosting_account_id` → `hosting_accounts.id`

**Índices:**
- `idx_hosting_account_id` (hosting_account_id)
- `idx_type` (type)
- `idx_created_at` (created_at)

**Arquivo de migração:** `database/migrations/20251117_create_hosting_backups_table.php`

---

### 1.3. Planos de Hospedagem

#### Tabela: `hosting_plans`

**Campos principais:**
- `id` (INT UNSIGNED, PK)
- `name` (VARCHAR(255), NOT NULL) - Nome do plano
- `amount` (DECIMAL(10,2), NOT NULL) - Valor mensal
- `billing_cycle` (VARCHAR(20), NOT NULL) - 'mensal', 'trimestral', etc.
- `annual_enabled` (TINYINT(1)) - Se permite cobrança anual
- `annual_monthly_amount` (DECIMAL(10,2), NULL) - Valor mensal equivalente no plano anual
- `annual_total_amount` (DECIMAL(10,2), NULL) - Valor total do plano anual
- `description` (TEXT, NULL)
- `is_active` (TINYINT(1)) - Se o plano está ativo
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Relacionamentos:**
- **Com contas de hospedagem:** Campo `hosting_plan_id` na tabela `hosting_accounts` (opcional)
- **Com contratos:** Campo `hosting_plan_id` na tabela `billing_contracts` (opcional)

**Índices:**
- `idx_is_active` (is_active)
- `idx_billing_cycle` (billing_cycle)

**Arquivo de migração:** `database/migrations/20251117_create_hosting_plans_table.php`  
**Alterações:** `20251117_alter_hosting_plans_add_annual.php`

**Observações importantes:**
- **Esta tabela é usada APENAS para hospedagem** - não há campo de "tipo de serviço" ou generalização
- O relacionamento com `hosting_accounts` é opcional (pode ser NULL)
- Existe suporte para planos anuais (campos `annual_*`)

---

### 1.4. Cobranças / Faturas

#### Tabela: `billing_invoices`

**Campos principais:**
- `id` (INT UNSIGNED, PK)
- `tenant_id` (INT UNSIGNED, NOT NULL) - FK para tenants
- `billing_contract_id` (INT UNSIGNED, NULL) - FK para billing_contracts (opcional, não usado atualmente)
- `asaas_payment_id` (VARCHAR(100), NOT NULL) - ID do payment no Asaas (único)
- `asaas_customer_id` (VARCHAR(100), NULL) - ID do customer no Asaas
- `due_date` (DATE, NOT NULL) - Data de vencimento
- `amount` (DECIMAL(10,2), NOT NULL) - Valor da fatura
- `status` (VARCHAR(20), NOT NULL) - 'pending', 'paid', 'overdue', 'canceled', 'refunded'
- `is_deleted` (TINYINT(1)) - Se a cobrança foi deletada no Asaas (soft delete)
- `paid_at` (DATETIME, NULL) - Data/hora do pagamento
- `invoice_url` (VARCHAR(512), NULL) - URL da fatura no Asaas
- `billing_type` (VARCHAR(20), NULL) - Tipo de cobrança do Asaas (ex: 'BOLETO', 'CREDIT_CARD')
- `description` (VARCHAR(255), NULL) - Descrição da cobrança
- `external_reference` (VARCHAR(255), NULL) - Referência externa (pode conter ID de contrato)
- `whatsapp_last_stage` (VARCHAR(50), NULL) - Último estágio de cobrança via WhatsApp
- `whatsapp_last_at` (DATETIME, NULL) - Data/hora da última cobrança via WhatsApp
- `whatsapp_total_messages` (INT UNSIGNED) - Total de mensagens enviadas
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Relacionamentos:**
- **Com tenants:** `tenant_id` → `tenants.id` (obrigatório)
- **Com contratos:** `billing_contract_id` → `billing_contracts.id` (opcional, campo existe mas não é usado ativamente)
- **Com Asaas:** 
  - `asaas_payment_id` → ID do payment no Asaas (único)
  - `asaas_customer_id` → ID do customer no Asaas

**Índices:**
- `idx_tenant_id` (tenant_id)
- `idx_asaas_payment_id` (asaas_payment_id)
- `idx_billing_contract_id` (billing_contract_id)
- `idx_is_deleted` (is_deleted)

**Arquivo de migração:** `database/migrations/20251118_create_billing_invoices_table.php`  
**Alterações:** `20251118_alter_billing_invoices_add_whatsapp_fields.php`, `20251119_alter_billing_invoices_add_is_deleted.php`

**Observações importantes:**
- **Não há vínculo direto com contas de hospedagem** - as faturas são vinculadas apenas ao tenant
- O campo `billing_contract_id` existe mas não é usado ativamente no código atual
- O campo `description` é texto livre e pode conter qualquer informação sobre a cobrança
- Não há campo de "tipo de serviço" ou "categoria" - apenas `description` textual
- O campo `external_reference` pode conter referências a contratos (formato: "PIXEL_CONTRACT:...")

---

#### Tabela: `billing_notifications`

**Campos principais:**
- `id` (INT UNSIGNED, PK)
- `tenant_id` (INT UNSIGNED, NOT NULL)
- `invoice_id` (INT UNSIGNED, NULL) - FK para billing_invoices (opcional)
- `channel` (VARCHAR(50)) - 'whatsapp_web'
- `template` (VARCHAR(50)) - 'pre_due', 'overdue_3d', 'overdue_7d', 'bulk_reminder'
- `status` (VARCHAR(50)) - 'prepared', 'sent_manual', 'opened', 'skipped', 'failed'
- `message` (TEXT) - Mensagem enviada
- `phone_raw` (VARCHAR(20), NULL)
- `phone_normalized` (VARCHAR(20), NULL)
- `sent_at` (DATETIME, NULL)
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Relacionamentos:**
- **Com tenants:** `tenant_id` → `tenants.id`
- **Com faturas:** `invoice_id` → `billing_invoices.id` (opcional)

**Arquivo de migração:** `database/migrations/20251118_create_billing_notifications_table.php`

---

### 1.5. Tabelas que se Parecem com "Contrato" ou "Assinatura"

#### Tabela: `billing_contracts`

**Status:** Existe no banco, mas **NÃO está sendo usada ativamente** no código atual.

**Campos principais:**
- `id` (INT UNSIGNED, PK)
- `tenant_id` (INT UNSIGNED, NOT NULL) - FK para tenants
- `hosting_account_id` (INT UNSIGNED, NULL) - FK para hosting_accounts (opcional)
- `hosting_plan_id` (INT UNSIGNED, NULL) - FK para hosting_plans (opcional)
- `plan_snapshot_name` (VARCHAR(255), NOT NULL) - Nome do plano no momento da criação
- `billing_mode` (VARCHAR(20), NOT NULL) - 'mensal' ou 'anual'
- `amount` (DECIMAL(10,2), NOT NULL) - Valor do contrato
- `annual_total_amount` (DECIMAL(10,2), NULL) - Valor total anual (se billing_mode = 'anual')
- `asaas_subscription_id` (VARCHAR(100), NULL) - ID da subscription no Asaas (não usado ainda)
- `asaas_external_reference` (VARCHAR(255), NULL) - Referência externa
- `status` (VARCHAR(20)) - 'ativo', 'cancelado', etc.
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Relacionamentos:**
- **Com tenants:** `tenant_id` → `tenants.id`
- **Com contas de hospedagem:** `hosting_account_id` → `hosting_accounts.id` (opcional)
- **Com planos:** `hosting_plan_id` → `hosting_plans.id` (opcional)
- **Com faturas:** Campo `billing_contract_id` na tabela `billing_invoices` (opcional, não usado)

**Índices:**
- `idx_tenant_id` (tenant_id)
- `idx_asaas_subscription_id` (asaas_subscription_id)
- `idx_hosting_account_id` (hosting_account_id)

**Arquivo de migração:** `database/migrations/20251118_create_billing_contracts_table.php`

**Uso no código:**
- Existe um método `AsaasBillingService::createBillingContractForHosting()` que cria contratos, mas **não é chamado em nenhum lugar do código atual**
- O método tem TODOs indicando que a integração com Asaas (criação de subscriptions) ainda não foi implementada
- A tabela `billing_invoices` tem o campo `billing_contract_id`, mas ele não é preenchido durante a sincronização

**Conclusão:** Esta tabela foi criada com a intenção de implementar contratos/assinaturas, mas **não está sendo usada ativamente**. É uma estrutura pronta para ser utilizada no futuro.

---

#### Tabela: `tenant_subscriptions`

**Status:** Existe no banco, mas **NÃO está sendo usada** no código atual.

**Campos principais:**
- `id` (INT UNSIGNED, PK)
- `tenant_id` (INT UNSIGNED, NOT NULL) - FK para tenants
- `plan_id` (INT UNSIGNED, NOT NULL) - FK para `plans` (não `hosting_plans`)
- `status` (VARCHAR(50)) - 'active', 'canceled', etc.
- `started_at` (DATETIME, NULL)
- `ends_at` (DATETIME, NULL)
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Relacionamentos:**
- **Com tenants:** `tenant_id` → `tenants.id`
- **Com planos:** `plan_id` → `plans.id` (tabela diferente de `hosting_plans`)

**Arquivo de migração:** `database/migrations/20251117_create_tenant_subscriptions_table.php`

**Observação:** Esta tabela referencia a tabela `plans` (não `hosting_plans`), que também existe mas não é usada.

---

#### Tabela: `plans`

**Status:** Existe no banco, mas **NÃO está sendo usada** no código atual.

**Campos principais:**
- `id` (INT UNSIGNED, PK)
- `name` (VARCHAR(255), NOT NULL)
- `description` (TEXT, NULL)
- `price` (DECIMAL(10,2)) - Valor do plano
- `billing_cycle` (VARCHAR(50)) - Ciclo de cobrança
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Arquivo de migração:** `database/migrations/20251117_create_plans_table.php`

**Observação:** Esta tabela parece ter sido criada para planos genéricos (não apenas hospedagem), mas não está sendo usada. A tabela `hosting_plans` é a que está em uso ativo.

---

## 2. Controllers, Services e Rotas Relevantes

### 2.1. Conta de Hospedagem

#### Controller: `HostingController`

**Arquivo:** `src/Controllers/HostingController.php`

**Métodos principais:**

1. **`index()`** - Lista todas as contas de hospedagem
   - **Rota:** `GET /hosting`
   - **Query:** Busca `hosting_accounts` com JOIN em `tenants` para exibir nome do cliente
   - **View:** `views/hosting/index.php`

2. **`create()`** - Exibe formulário de criação
   - **Rota:** `GET /hosting/create`
   - **Parâmetros GET:** `tenant_id` (opcional), `redirect_to` (opcional)
   - **Dados carregados:**
     - Lista de tenants: `SELECT id, name FROM tenants ORDER BY name ASC`
     - Lista de planos de hospedagem: `SELECT id, name, amount, billing_cycle FROM hosting_plans WHERE is_active = 1 ORDER BY name`
   - **View:** `views/hosting/form.php`

3. **`store()`** - Salva nova conta de hospedagem
   - **Rota:** `POST /hosting/store`
   - **Dados recebidos (POST):**
     - `tenant_id` (obrigatório)
     - `domain` (obrigatório)
     - `hosting_plan_id` (opcional)
     - `plan_name` (opcional, texto livre)
     - `amount` (opcional)
     - `billing_cycle` (opcional, padrão: 'mensal')
     - `current_provider` (opcional, padrão: 'hostinger')
     - `hostinger_expiration_date` (opcional)
     - `decision` (opcional, padrão: 'pendente')
     - `migration_status` (opcional, padrão: 'nao_iniciada')
     - `notes` (opcional)
     - `redirect_to` (opcional)
   - **Tabela escrita:** `hosting_accounts`
   - **Campos inseridos:** Todos os campos acima + `backup_status = 'nenhum'`, `created_at`, `updated_at`
   - **Redirecionamento:** 
     - Se `redirect_to = 'tenant'`: `/tenants/view?id={tenant_id}&tab=hosting&success=created`
     - Senão: `/hosting?success=created`

**Observações importantes:**
- O plano de hospedagem (`hosting_plan_id`) é opcional - pode criar conta sem plano
- O valor e ciclo são armazenados diretamente na conta (`amount`, `billing_cycle`), não apenas referenciando o plano
- **Não há criação automática de faturas ou contratos** ao criar uma conta de hospedagem

---

### 2.2. Planos de Hospedagem

#### Controller: `HostingPlanController`

**Arquivo:** `src/Controllers/HostingPlanController.php`

**Métodos principais:**

1. **`index()`** - Lista todos os planos
   - **Rota:** `GET /hosting-plans`
   - **Query:** `SELECT * FROM hosting_plans ORDER BY is_active DESC, name ASC`
   - **View:** `views/hosting_plans/index.php`

2. **`create()`** - Exibe formulário de criação
   - **Rota:** `GET /hosting-plans/create`
   - **View:** `views/hosting_plans/form.php`

3. **`store()`** - Salva novo plano
   - **Rota:** `POST /hosting-plans/store`
   - **Dados recebidos (POST):**
     - `name` (obrigatório)
     - `amount` (obrigatório, > 0)
     - `billing_cycle` (opcional, padrão: 'mensal')
     - `description` (opcional)
     - `is_active` (opcional, checkbox)
     - `annual_enabled` (opcional, checkbox)
     - `annual_monthly_amount` (opcional, se annual_enabled)
     - `annual_total_amount` (opcional, se annual_enabled)
   - **Tabela escrita:** `hosting_plans`

4. **`edit()`** - Exibe formulário de edição
   - **Rota:** `GET /hosting-plans/edit?id={id}`

5. **`update()`** - Atualiza plano existente
   - **Rota:** `POST /hosting-plans/update`

6. **`toggleStatus()`** - Alterna status ativo/inativo
   - **Rota:** `POST /hosting-plans/toggle-status`

**Observações importantes:**
- **Esta tabela é usada APENAS para hospedagem** - não há campo de "tipo de serviço"
- Os planos podem ter suporte a cobrança anual (campos `annual_*`)
- Não há relacionamento direto com faturas - os planos são apenas referenciados em `hosting_accounts` e `billing_contracts`

---

### 2.3. Painel do Cliente / Abas

#### Controller: `TenantsController`

**Arquivo:** `src/Controllers/TenantsController.php`

**Método:** `show()` - Visualiza detalhes do tenant

**Rota:** `GET /tenants/view?id={id}&tab={tab}`

**Parâmetros:**
- `id` (obrigatório) - ID do tenant
- `tab` (opcional) - 'overview', 'hosting', 'docs_backups', 'financial' (padrão: 'overview')

**Dados carregados por aba:**

1. **Aba "overview" (Visão Geral):**
   - Dados do tenant (todos os campos)

2. **Aba "hosting" (Hospedagem & Sites):**
   - **Query:** `SELECT * FROM hosting_accounts WHERE tenant_id = ? ORDER BY domain ASC`
   - **Tabela:** `hosting_accounts`
   - **Dados adicionais:** Backups relacionados (via JOIN com `hosting_backups`)

3. **Aba "docs_backups" (Docs & Backups):**
   - Lista de backups: `SELECT hb.*, ha.domain, ha.id as hosting_account_id FROM hosting_backups hb INNER JOIN hosting_accounts ha ON hb.hosting_account_id = ha.id WHERE hb.hosting_account_id IN (...) ORDER BY hb.created_at DESC`
   - **Tabelas:** `hosting_backups`, `hosting_accounts`

4. **Aba "financial" (Financeiro):**
   - **Faturas:**
     - **Query:** `SELECT * FROM billing_invoices WHERE tenant_id = ? AND (is_deleted IS NULL OR is_deleted = 0) ORDER BY due_date DESC, created_at DESC`
     - **Tabela:** `billing_invoices`
   - **Contagem de faturas em atraso:**
     - **Query:** `SELECT COUNT(*) FROM billing_invoices WHERE tenant_id = ? AND status = 'overdue' AND (is_deleted IS NULL OR is_deleted = 0)`
   - **Notificações WhatsApp:**
     - **Query:** `SELECT bn.*, bi.due_date, bi.amount FROM billing_notifications bn LEFT JOIN billing_invoices bi ON bn.invoice_id = bi.id WHERE bn.tenant_id = ? ORDER BY bn.sent_at DESC, bn.created_at DESC LIMIT 5`
   - **Customers Asaas (apenas nesta aba):**
     - Busca todos os customers no Asaas com o mesmo CPF/CNPJ do tenant
     - **Service:** `AsaasClient::findCustomersByCpfCnpj($cpfCnpjNormalizado)`
     - **Observação:** Esta busca é feita apenas quando `activeTab === 'financial'` para evitar chamadas desnecessárias à API

**View:** `views/tenants/view.php`

**Método adicional:** `syncBilling()` - Sincroniza faturas do tenant com Asaas
- **Rota:** `POST /tenants/sync-billing`
- **Service usado:** `AsaasBillingService::syncCustomerAndInvoicesForTenant()` ou `syncInvoicesForTenant()`

---

### 2.4. Sincronização com Asaas

#### Service: `AsaasBillingService`

**Arquivo:** `src/Services/AsaasBillingService.php`

**Métodos principais:**

1. **`ensureCustomerForTenant(array $tenant): string`**
   - Garante que o tenant possui `asaas_customer_id`
   - Se não tiver, busca ou cria customer no Asaas
   - **Tabelas impactadas:** `tenants` (campo `asaas_customer_id`)

2. **`syncInvoicesForTenant(int $tenantId): array`**
   - Sincroniza faturas de um tenant com o Asaas
   - Busca todos os payments do customer no Asaas
   - Cria ou atualiza registros em `billing_invoices`
   - **Tabelas impactadas:** `billing_invoices`, `tenants` (atualiza `billing_status`)

3. **`syncCustomerAndInvoicesForTenant(int $tenantId): array`**
   - Sincronização completa: atualiza customer e todas as faturas
   - Busca todos os customers do Asaas com o mesmo CPF/CNPJ
   - Sincroniza faturas de todos os customers encontrados
   - **Tabelas impactadas:** `tenants`, `billing_invoices`

4. **`syncAllCustomersAndInvoices(): array`**
   - Sincroniza todos os customers do Asaas e suas faturas
   - Usado na rota `POST /billing/sync-all-from-asaas`
   - **Tabelas impactadas:** `tenants`, `billing_invoices`

5. **`refreshTenantBillingStatus(int $tenantId): void`**
   - Atualiza o campo `billing_status` do tenant com base nas faturas
   - **Tabelas impactadas:** `tenants`

6. **`createBillingContractForHosting(...): int`**
   - **Status:** Método existe mas **NÃO é chamado em nenhum lugar**
   - Cria registro em `billing_contracts` (sem chamar Asaas ainda)
   - **Tabelas impactadas:** `billing_contracts`

**Controller que usa:** `BillingCollectionsController::syncAllFromAsaas()`

**Observações importantes:**
- A sincronização é feita via API do Asaas (`AsaasClient`)
- As faturas são identificadas pelo `asaas_payment_id` (único)
- Faturas deletadas no Asaas são marcadas com `is_deleted = 1` e `status = 'canceled'`
- O status financeiro do tenant é atualizado automaticamente após sincronização

---

## 3. Fluxos Principais (Desenho Textual)

### 3.1. Fluxo 1 – Criação de Conta de Hospedagem

**Do momento em que clico em "Nova conta de hospedagem" até o registro salvo no banco:**

1. **Usuário acessa:** `GET /hosting/create?tenant_id={id}&redirect_to=tenant`
2. **Controller `HostingController::create()` executa:**
   - Busca lista de tenants: `SELECT id, name FROM tenants ORDER BY name ASC`
   - Busca planos de hospedagem ativos: `SELECT id, name, amount, billing_cycle FROM hosting_plans WHERE is_active = 1 ORDER BY name`
   - Renderiza view `views/hosting/form.php` com os dados
3. **Usuário preenche formulário:**
   - Seleciona tenant (ou já vem pré-selecionado via `tenant_id`)
   - Informa domínio
   - **Opcionalmente** seleciona um plano de hospedagem (dropdown)
   - Se selecionar plano, JavaScript preenche automaticamente: `plan_name`, `amount`, `billing_cycle`
   - Usuário pode editar manualmente esses campos
   - Informa provedor, data de vencimento, decisão, status de migração, notas
4. **Usuário submete formulário:** `POST /hosting/store`
5. **Controller `HostingController::store()` executa:**
   - Valida `tenant_id` e `domain` (obrigatórios)
   - Valida se tenant existe
   - Normaliza `amount` usando `MoneyHelper::normalizeAmount()`
   - **Insere na tabela `hosting_accounts`:**
     - `tenant_id`, `domain`, `hosting_plan_id` (pode ser NULL), `plan_name`, `amount`, `billing_cycle`, `current_provider`, `hostinger_expiration_date`, `decision`, `migration_status`, `notes`, `backup_status = 'nenhum'`, `created_at`, `updated_at`
6. **Redirecionamento:**
   - Se `redirect_to = 'tenant'`: `/tenants/view?id={tenant_id}&tab=hosting&success=created`
   - Senão: `/hosting?success=created`

**Tabelas que recebem dados:**
- ✅ `hosting_accounts` (INSERT)

**Como o plano de hospedagem entra nessa relação:**
- O plano é **opcional** - pode criar conta sem plano
- Se selecionado, o `hosting_plan_id` é salvo na conta
- O `plan_name`, `amount` e `billing_cycle` são salvos diretamente na conta (podem ser editados manualmente)
- **Não há validação** que garanta que esses valores correspondam ao plano selecionado

**Vínculo direto entre conta de hospedagem e faturas do Financeiro:**
- ❌ **NÃO existe vínculo direto**
- As faturas são vinculadas apenas ao `tenant_id`
- Não há campo `hosting_account_id` na tabela `billing_invoices`
- O campo `billing_contract_id` existe mas não é usado

---

### 3.2. Fluxo 2 – Como as Faturas Aparecem na Aba Financeiro do Cliente

**Quando abro a aba Financeiro (`/tenants/view?id={id}&tab=financial`):**

1. **Controller `TenantsController::show()` executa:**
   - Busca dados do tenant: `SELECT * FROM tenants WHERE id = ?`
   - **Se `activeTab === 'financial'`:**
     - Busca faturas: `SELECT * FROM billing_invoices WHERE tenant_id = ? AND (is_deleted IS NULL OR is_deleted = 0) ORDER BY due_date DESC, created_at DESC`
     - Conta faturas em atraso: `SELECT COUNT(*) FROM billing_invoices WHERE tenant_id = ? AND status = 'overdue' AND (is_deleted IS NULL OR is_deleted = 0)`
     - Busca notificações WhatsApp: `SELECT bn.*, bi.due_date, bi.amount FROM billing_notifications bn LEFT JOIN billing_invoices bi ON bn.invoice_id = bi.id WHERE bn.tenant_id = ? ORDER BY bn.sent_at DESC, bn.created_at DESC LIMIT 5`
     - **Busca customers Asaas por CPF/CNPJ:**
       - Normaliza CPF/CNPJ do tenant (remove formatação)
       - Chama `AsaasClient::findCustomersByCpfCnpj($cpfCnpjNormalizado)`
       - Exibe lista de todos os customers encontrados no Asaas
2. **View `views/tenants/view.php` renderiza:**
   - Resumo financeiro (status de cobrança, última verificação, faturas em atraso)
   - Seção "Cadastros no Asaas para este CPF" (se houver customers)
   - Tabela de faturas com colunas: Vencimento, Valor, Status, Tipo de Cobrança, Descrição, Ações
   - Histórico de cobranças via WhatsApp

**Consulta feita:**
- **Tabela:** `billing_invoices`
- **Filtros:**
  - `tenant_id = ?` (obrigatório)
  - `(is_deleted IS NULL OR is_deleted = 0)` (ignora faturas deletadas)
- **Ordenação:** `due_date DESC, created_at DESC`

**Como a tabela de faturas se relaciona com o tenant:**
- Via campo `tenant_id` (obrigatório, FK para `tenants.id`)

**Referência a plano/serviço:**
- ❌ **Não há referência direta a plano ou serviço**
- Apenas campo `description` (texto livre) que pode conter qualquer informação
- Campo `billing_contract_id` existe mas não é usado
- Campo `external_reference` pode conter referências (ex: "PIXEL_CONTRACT:...") mas não é processado

**Consulta de clientes/charges no Asaas:**
- Feita apenas na aba financeira (para evitar chamadas desnecessárias)
- Busca todos os customers do Asaas com o mesmo CPF/CNPJ
- Exibe lista para o usuário verificar se há múltiplos cadastros

---

### 3.3. Fluxo 3 – Hospedagem x Financeiro

**Ligação direta entre conta de hospedagem e faturas:**

❌ **NÃO existe ligação direta no código atual**

**Evidências:**
1. A tabela `billing_invoices` **não possui** campo `hosting_account_id`
2. A tabela `hosting_accounts` **não possui** campo relacionado a faturas
3. As faturas são vinculadas apenas ao `tenant_id`
4. O campo `billing_contract_id` existe em `billing_invoices`, mas:
   - Não é preenchido durante a sincronização com Asaas
   - Não é usado em nenhuma query ou view
   - A tabela `billing_contracts` existe mas não está sendo usada ativamente

**Campo de "tipo de serviço" ou "categoria" na tabela de faturas:**

❌ **Não existe campo específico**

**Campos disponíveis:**
- `description` (VARCHAR(255)) - Texto livre, pode conter qualquer informação
- `billing_type` (VARCHAR(20)) - Tipo de cobrança do Asaas (ex: 'BOLETO', 'CREDIT_CARD'), não é tipo de serviço
- `external_reference` (VARCHAR(255)) - Pode conter referências, mas não é processado

**Conclusão:**
- O financeiro "não sabe" qual fatura pertence a qual hospedagem
- Não há forma de identificar, a partir de uma fatura, qual conta de hospedagem gerou aquela cobrança
- A única ligação possível seria via `external_reference` (se contiver ID de contrato), mas isso não é processado automaticamente

---

## 4. Análise de Possíveis Pontos de Extensão (Sem Implementar)

### 4. Oportunidades para Implementar "Contratos & Planos" sem Duplicar Nada

#### 4.1. Estruturas Existentes que Podem Ser Reaproveitadas

**✅ Tabela `billing_contracts` - Pronta para uso:**
- Esta tabela já existe e tem a estrutura necessária para contratos
- Campos relevantes:
  - `tenant_id` (obrigatório)
  - `hosting_account_id` (opcional - pode ser usado para outros serviços no futuro)
  - `hosting_plan_id` (opcional - pode ser generalizado)
  - `plan_snapshot_name` (nome do plano no momento da criação)
  - `billing_mode` ('mensal' ou 'anual')
  - `amount`, `annual_total_amount`
  - `asaas_subscription_id` (pronto para integração com Asaas)
  - `status` ('ativo', 'cancelado', etc.)
- **Vantagem:** Não precisa criar nova tabela
- **Desvantagem:** Nome sugere apenas hospedagem, mas pode ser generalizado

**✅ Tabela `hosting_plans` - Pode ser generalizada:**
- Atualmente usada apenas para hospedagem
- Estrutura simples e funcional
- **Opção 1:** Adicionar campo `service_type` (ex: 'hosting', 'domain', 'email', etc.)
- **Opção 2:** Criar tabela genérica `plans` e manter `hosting_plans` como alias/view
- **Opção 3:** Manter `hosting_plans` apenas para hospedagem e criar `service_plans` para outros serviços

**✅ Campo `billing_contract_id` em `billing_invoices`:**
- Já existe e está indexado
- Pode ser usado para vincular faturas a contratos
- **Ação necessária:** Preencher este campo durante a criação/sincronização de faturas

---

#### 4.2. Pontos Seguros para Estender

**✅ Seguro estender:**

1. **Preencher `billing_contract_id` em `billing_invoices`:**
   - Campo já existe e está indexado
   - Não quebra funcionalidade existente
   - Permite vincular faturas a contratos no futuro

2. **Usar `billing_contracts` para criar contratos:**
   - Tabela já existe e não está sendo usada
   - Método `AsaasBillingService::createBillingContractForHosting()` já existe (não é chamado)
   - Pode ser chamado durante criação de conta de hospedagem ou em fluxo separado

3. **Adicionar campo `service_type` em `hosting_plans`:**
   - Não quebra funcionalidade existente (pode ter valor padrão 'hosting')
   - Permite generalizar para outros serviços no futuro

4. **Criar interface/tela para gerenciar contratos:**
   - Não interfere com código existente
   - Pode usar `billing_contracts` como base

---

#### 4.3. Pontos Arriscados para Mexer

**⚠️ Cuidado ao mexer:**

1. **Sincronização com Asaas (`AsaasBillingService`):**
   - Lógica complexa e crítica
   - Qualquer mudança pode afetar todas as faturas
   - **Recomendação:** Testar extensivamente antes de modificar

2. **Tela Financeiro (`views/tenants/view.php` - aba financial):**
   - Usada ativamente pelos usuários
   - Qualquer mudança pode quebrar a visualização
   - **Recomendação:** Manter compatibilidade com estrutura atual

3. **Campo `description` em `billing_invoices`:**
   - Pode estar sendo usado para armazenar informações importantes
   - Mudanças podem afetar relatórios ou integrações
   - **Recomendação:** Não remover, apenas adicionar novos campos

4. **Tabela `hosting_accounts`:**
   - Estrutura já está em uso
   - Adicionar campos é seguro, mas remover ou modificar pode quebrar funcionalidades
   - **Recomendação:** Adicionar campos opcionais, não modificar existentes

---

#### 4.4. Sugestões de Arquitetura para "Contratos & Planos"

**Opção A: Reaproveitar `billing_contracts` (Recomendada)**

**Vantagens:**
- Tabela já existe e tem estrutura adequada
- Não precisa criar nova tabela
- Campo `billing_contract_id` em `billing_invoices` já está pronto

**Ações necessárias:**
1. Ativar uso de `billing_contracts`:
   - Chamar `AsaasBillingService::createBillingContractForHosting()` ao criar conta de hospedagem (opcional)
   - Ou criar fluxo separado para criar contratos
2. Preencher `billing_contract_id` em `billing_invoices`:
   - Durante sincronização com Asaas, verificar `external_reference` e vincular ao contrato
   - Ou criar faturas vinculadas a contratos diretamente
3. Criar interface para gerenciar contratos:
   - Listar contratos do tenant
   - Criar/editar/cancelar contratos
   - Visualizar faturas vinculadas

**Generalização futura:**
- Campo `hosting_account_id` pode ser renomeado para `service_account_id` (ou adicionar campo genérico)
- Campo `hosting_plan_id` pode ser renomeado para `plan_id` e referenciar tabela genérica de planos

---

**Opção B: Generalizar `hosting_plans` para `service_plans`**

**Vantagens:**
- Permite criar planos para diferentes tipos de serviços
- Estrutura já está testada e funcionando

**Ações necessárias:**
1. Adicionar campo `service_type` em `hosting_plans`:
   - Valores: 'hosting', 'domain', 'email', 'ssl', etc.
   - Valor padrão: 'hosting' (para manter compatibilidade)
2. Atualizar queries para filtrar por `service_type` quando necessário
3. Criar interface para gerenciar planos por tipo de serviço

**Desvantagens:**
- Nome da tabela sugere apenas hospedagem
- Pode ser confuso no futuro

---

**Opção C: Criar estrutura complementar**

**Vantagens:**
- Não mexe em estruturas existentes
- Permite design limpo desde o início

**Ações necessárias:**
1. Criar tabela `service_contracts` (genérica):
   - Similar a `billing_contracts`, mas com campos genéricos
   - `service_type` (VARCHAR(50)) - 'hosting', 'domain', etc.
   - `service_account_id` (INT UNSIGNED, NULL) - Referência genérica
   - `plan_id` (INT UNSIGNED, NULL) - Referência a tabela genérica de planos
2. Criar tabela `service_plans` (genérica):
   - Similar a `hosting_plans`, mas com campo `service_type`
3. Migrar dados existentes (opcional):
   - Copiar `hosting_plans` para `service_plans` com `service_type = 'hosting'`
   - Copiar `billing_contracts` para `service_contracts`

**Desvantagens:**
- Duplica estruturas existentes
- Requer migração de dados
- Mais complexo

---

#### 4.5. Recomendação Final

**Para implementar "Contratos & Planos" sem duplicar:**

1. **Reaproveitar `billing_contracts`:**
   - Tabela já existe e tem estrutura adequada
   - Ativar uso chamando métodos existentes ou criando novos
   - Preencher `billing_contract_id` em `billing_invoices`

2. **Manter `hosting_plans` para hospedagem:**
   - Não generalizar agora (pode ser feito no futuro se necessário)
   - Focar em fazer contratos funcionarem primeiro

3. **Criar interface de gerenciamento:**
   - Listar contratos do tenant
   - Criar contratos vinculados a contas de hospedagem
   - Visualizar faturas vinculadas a contratos

4. **Integração com Asaas (futuro):**
   - Implementar criação de subscriptions no Asaas
   - Preencher `asaas_subscription_id` em `billing_contracts`
   - Vincular faturas geradas automaticamente aos contratos

**Pontos de atenção:**
- Não modificar lógica de sincronização com Asaas sem testes extensivos
- Manter compatibilidade com estrutura atual de faturas
- Adicionar campos opcionais, não remover existentes

---

## 5. Resumo Executivo

### Tabelas Principais

| Tabela | Uso Atual | Status |
|--------|-----------|--------|
| `tenants` | ✅ Ativo | Clientes/tenants |
| `hosting_accounts` | ✅ Ativo | Contas de hospedagem |
| `hosting_plans` | ✅ Ativo | Planos de hospedagem |
| `billing_invoices` | ✅ Ativo | Faturas/cobranças |
| `billing_contracts` | ⚠️ Existe mas não usado | Contratos (pronto para uso) |
| `hosting_backups` | ✅ Ativo | Backups WordPress |
| `billing_notifications` | ✅ Ativo | Notificações WhatsApp |
| `tenant_subscriptions` | ❌ Não usado | Assinaturas (não usado) |
| `plans` | ❌ Não usado | Planos genéricos (não usado) |

### Relacionamentos Críticos

- ✅ `tenants` ↔ `hosting_accounts` (via `tenant_id`)
- ✅ `tenants` ↔ `billing_invoices` (via `tenant_id`)
- ✅ `hosting_accounts` ↔ `hosting_plans` (via `hosting_plan_id`, opcional)
- ❌ `hosting_accounts` ↔ `billing_invoices` (NÃO existe vínculo direto)
- ⚠️ `billing_contracts` ↔ `billing_invoices` (campo existe mas não é usado)
- ⚠️ `billing_contracts` ↔ `hosting_accounts` (campo existe mas não é usado)

### Fluxos Principais

1. **Criação de conta de hospedagem:** Cria apenas registro em `hosting_accounts`, não cria faturas nem contratos
2. **Visualização de faturas:** Busca apenas por `tenant_id`, não há vínculo com hospedagem
3. **Sincronização com Asaas:** Cria/atualiza faturas em `billing_invoices`, não cria contratos

### Oportunidades

- ✅ Reaproveitar `billing_contracts` para implementar contratos
- ✅ Preencher `billing_contract_id` em `billing_invoices` para vincular faturas
- ✅ Criar interface de gerenciamento de contratos
- ⚠️ Generalizar `hosting_plans` para outros serviços (futuro)

---

**Fim do Documento**

