# Pixel Hub – Documentação Completa do Projeto

**Versão:** 2.0  
**Última Atualização:** 17/11/2025  
**Status:** Fase 0 + Fase 1 (Parcial) - Em Desenvolvimento Ativo

---

## 📋 Índice

1. [Visão Geral](#1-visão-geral)
2. [Estado Atual do Projeto](#2-estado-atual-do-projeto)
3. [Arquitetura do Sistema](#3-arquitetura-do-sistema)
4. [Estrutura de Banco de Dados](#4-estrutura-de-banco-de-dados)
5. [Estrutura de Código](#5-estrutura-de-código)
6. [Rotas e Endpoints](#6-rotas-e-endpoints)
7. [Funcionalidades Implementadas](#7-funcionalidades-implementadas)
8. [Como Começar a Desenvolver](#8-como-começar-a-desenvolver)
9. [Próximos Passos](#9-próximos-passos)

---

## 1. Visão Geral

### 1.1. Objetivo do Sistema

O **Pixel Hub** é o painel central da Pixel12 Digital para:

- **Concentrar** financeiro, CRM, tickets, chat, documentos, projetos e integrações
- **Integrar** com vários projetos externos e independentes (ImobSites, CFC, e-commerce, rádio, etc.) via API e webhooks, sem acoplar código
- **Dar ao CHARLINHO** (admin da Pixel12) um único painel diário de trabalho:
  - Ver novas cobranças e inadimplentes
  - Atender tickets e chats de todos os projetos
  - Acompanhar leads e negócios (CRM)
  - Enxergar tarefas/projetos em andamento
  - Ver documentos e status de clientes

### 1.2. Stack Tecnológica

- **Backend**: PHP 8.x (mini-framework customizado)
- **Banco de Dados**: MySQL (HostMídia)
- **Frontend**: HTML/CSS/JS padrão (sem frameworks pesados)
- **Autenticação**: Session-based (usuários internos e clientes)
- **Integrações**: Asaas (cobrança), WhatsApp Web (cobranças manuais)

### 1.3. Modelo Mental: Hub + Satélites

#### Pixel Hub (Central)
- Autenticação global (usuários internos e clientes)
- Tenants (clientes da agência)
- Financeiro + Asaas
- Hospedagem & Backups
- Cobranças via WhatsApp
- CRM (planejado)
- Tickets & chat unificado (planejado)
- Projetos e tarefas (planejado)
- Documentos (planejado)

#### Sistemas Satélites (Independentes)
- ImobSites (multi-tenant imobiliário)
- Sistema CFC (multi-tenant)
- Futuro e-commerce
- Rádio/streaming

**Comunicação**: API REST do Hub (satélites → Hub) + Webhooks (Hub → satélites)

---

## 2. Estado Atual do Projeto

### 2.1. Fase de Implementação

**Status:** ✅ **Fase 0 (Completa)** + ✅ **Fase 1 (Parcial - 80%)**

#### ✅ Fase 0 - Setup e Fundação (100% Completo)

- [x] Estrutura básica do projeto
- [x] Sistema de migrations
- [x] Autenticação básica (internos e clientes)
- [x] Layout base do painel
- [x] Sistema de rotas customizado
- [x] Helpers globais (URL, Storage, Money)
- [x] Sistema de logs

#### ✅ Fase 1 - Financeiro + Hospedagem + Cobranças (80% Completo)

**Implementado:**
- [x] Módulo de Clientes (Tenants) - CRUD completo com PF/PJ
- [x] Módulo de Hospedagem (Hosting Accounts) - CRUD completo
- [x] Módulo de Planos de Hospedagem - CRUD completo com opção anual
- [x] Sistema de Backups WordPress (.wpress)
- [x] Integração completa com Asaas:
  - [x] Configuração via `.env`
  - [x] Cliente HTTP (AsaasClient)
  - [x] Service de cobrança (AsaasBillingService)
  - [x] Sincronização manual de faturas
  - [x] Webhook para atualizações automáticas
- [x] Sistema de Cobranças via WhatsApp Web:
  - [x] Normalização de telefones
  - [x] Sugestão automática de estágio (pre_due, overdue_3d, overdue_7d)
  - [x] Mensagens pré-formatadas
  - [x] Histórico de cobranças
  - [x] Integração com aba Financeiro do cliente
- [x] Painel do Cliente (Tenant Hub) com abas:
  - [x] Visão Geral
  - [x] Hospedagem & Sites
  - [x] Docs & Backups
  - [x] Financeiro (com sincronização Asaas)

**Pendente:**
- [ ] Portal do Cliente (PWA) - apenas painel interno funcionando
- [ ] Criação automática de assinaturas no Asaas ao criar contratos
- [ ] Dashboard com métricas financeiras

#### ⏳ Fase 2 - Tickets de Suporte (0% - Planejado)

#### ⏳ Fase 3 - Integração de Tickets com Projetos Externos (0% - Planejado)

#### ⏳ Fase 4 - Chat Unificado (0% - Planejado)

#### ⏳ Fase 5 - CRM (Kanban) (0% - Planejado)

#### ⏳ Fase 6 - Projetos e Tarefas (0% - Planejado)

#### ⏳ Fase 7 - Documentos e Arquivos (0% - Planejado)

#### ⏳ Fase 8 - Conteúdo e Anúncios (0% - Planejado)

---

## 3. Arquitetura do Sistema

### 3.1. Estrutura de Pastas

```
painel.pixel12digital/
├── config/                    # Arquivos de configuração
│   └── asaas.php             # Configuração padrão do Asaas
├── database/                  # Migrations e seeds
│   ├── migrations/           # Todas as migrations do banco
│   ├── migrate.php           # Script para executar migrations
│   └── seed.php              # Script para popular dados iniciais
├── docs/                      # Documentação
│   └── pixel-hub-plano-geral.md
├── logs/                      # Logs da aplicação
│   └── pixelhub.log
├── public/                    # Ponto de entrada (web root)
│   └── index.php             # Router principal
├── src/                       # Código fonte
│   ├── Core/                 # Classes core do sistema
│   │   ├── Auth.php          # Autenticação e autorização
│   │   ├── Controller.php    # Controller base
│   │   ├── DB.php            # Conexão com banco (PDO)
│   │   ├── Env.php           # Carregamento de .env
│   │   ├── MoneyHelper.php   # Helpers para valores monetários
│   │   ├── Router.php        # Sistema de rotas
│   │   └── Storage.php       # Helpers para armazenamento de arquivos
│   ├── Controllers/          # Controllers da aplicação
│   │   ├── AsaasWebhookController.php
│   │   ├── AuthController.php
│   │   ├── BillingCollectionsController.php
│   │   ├── DashboardController.php
│   │   ├── HostingBackupController.php
│   │   ├── HostingController.php
│   │   ├── HostingPlanController.php
│   │   └── TenantsController.php
│   └── Services/             # Services de negócio
│       ├── AsaasBillingService.php
│       ├── AsaasClient.php
│       ├── AsaasConfig.php
│       ├── AsaasPlanMapper.php
│       └── WhatsAppBillingService.php
├── storage/                   # Armazenamento de arquivos
│   └── tenants/              # Arquivos por tenant
│       └── {tenant_id}/
│           └── backups/
│               └── {hosting_account_id}/
├── views/                     # Templates/Views
│   ├── auth/
│   │   └── login.php
│   ├── billing_collections/
│   │   ├── index.php
│   │   └── whatsapp_modal.php
│   ├── dashboard/
│   │   └── index.php
│   ├── hosting/
│   │   ├── backups.php
│   │   ├── form.php
│   │   └── index.php
│   ├── hosting_plans/
│   │   ├── form.php
│   │   └── index.php
│   ├── layout/
│   │   ├── auth.php
│   │   └── main.php
│   └── tenants/
│       ├── form.php
│       ├── index.php
│       └── view.php
├── .env                       # Variáveis de ambiente (não versionado)
├── .env.example               # Exemplo de .env
├── .gitignore
├── composer.json              # Dependências (opcional)
└── README.md
```

### 3.2. Fluxo de Requisição

```
1. Requisição HTTP → public/index.php
2. Carrega autoloader e .env
3. Define BASE_PATH (para subpastas)
4. Router::dispatch() → encontra rota
5. Controller::execute() → método do controller
6. Controller::view() → renderiza view
7. View usa layout/main.php → HTML final
```

### 3.3. Autenticação

- **Usuários Internos**: `users.is_internal = 1` → acesso completo
- **Usuários Cliente**: `users.is_internal = 0` + `tenant_users` → acesso limitado ao tenant
- **Sessão**: `$_SESSION['user_id']` e `$_SESSION['is_internal']`
- **Proteção**: `Auth::requireInternal()` ou `Auth::requireAuth()`

### 3.4. Sistema de Rotas

- **Router customizado** em `src/Core/Router.php`
- Rotas registradas em `public/index.php`
- Suporta: `GET`, `POST`, closures e `Controller@method`
- Base path automático para subpastas (XAMPP)

---

## 4. Estrutura de Banco de Dados

### 4.1. Tabelas Implementadas

#### 4.1.1. Núcleo do Sistema

**`users`**
- `id` (PK)
- `name`, `email`, `password_hash`
- `is_internal` (TINYINT) - 1 = usuário Pixel12, 0 = cliente
- `created_at`, `updated_at`

**`tenants`** (Clientes)
- `id` (PK)
- `person_type` (VARCHAR(2)) - 'pf' ou 'pj'
- `name` - Nome completo (PF) ou Razão Social (PJ)
- `cpf_cnpj` - CPF ou CNPJ
- `razao_social` - Apenas PJ
- `nome_fantasia` - Apenas PJ
- `responsavel_nome`, `responsavel_cpf` - Apenas PJ
- `email`, `phone`
- `status` - 'active', 'suspended'
- `asaas_customer_id` - ID do cliente no Asaas
- `billing_status` - 'sem_cobranca', 'em_dia', 'atrasado_parcial', 'atrasado_total'
- `billing_last_check_at` - Última sincronização com Asaas
- `created_at`, `updated_at`

**`tenant_users`**
- `id` (PK)
- `tenant_id` (FK → tenants)
- `user_id` (FK → users)
- `role` - 'admin_cliente', 'financeiro', 'suporte'
- `created_at`, `updated_at`

**`projects`**
- `id` (PK)
- `tenant_id` (FK → tenants, nullable)
- `name`, `slug`
- `external_project_id`, `base_url`
- `status` - 'active', 'suspended'
- `created_at`, `updated_at`

#### 4.1.2. Hospedagem

**`hosting_plans`** (Planos de Hospedagem)
- `id` (PK)
- `name`, `description`
- `amount` (DECIMAL) - Valor mensal
- `billing_cycle` - 'mensal', 'anual'
- `annual_enabled` (TINYINT) - Se tem opção anual
- `annual_monthly_amount` (DECIMAL) - Valor mensal equivalente (anual)
- `annual_total_amount` (DECIMAL) - Valor total anual
- `is_active` (TINYINT)
- `created_at`, `updated_at`

**`hosting_accounts`** (Contas de Hospedagem)
- `id` (PK)
- `tenant_id` (FK → tenants)
- `hosting_plan_id` (FK → hosting_plans, nullable)
- `domain`
- `plan_name`, `amount`, `billing_cycle` - Snapshot do plano
- `current_provider` - 'hostinger', 'hostmidia', etc.
- `hostinger_expiration_date` (DATE)
- `decision` - 'pendente', 'renovar', 'migrar', 'cancelar'
- `backup_status` - 'nenhum', 'completo'
- `last_backup_at` (DATETIME)
- `migration_status` - 'nao_iniciada', 'em_andamento', 'concluida'
- `notes` (TEXT)
- `created_at`, `updated_at`

**`hosting_backups`** (Backups WordPress)
- `id` (PK)
- `hosting_account_id` (FK → hosting_accounts)
- `type` - 'all_in_one_wp'
- `file_name`, `file_size`
- `stored_path` - Caminho relativo do arquivo
- `notes` (TEXT)
- `created_at`

#### 4.1.3. Financeiro / Asaas

**`billing_contracts`** (Contratos de Cobrança)
- `id` (PK)
- `tenant_id` (FK → tenants)
- `hosting_account_id` (FK → hosting_accounts, nullable)
- `hosting_plan_id` (FK → hosting_plans, nullable)
- `plan_snapshot_name` - Nome do plano no momento da contratação
- `billing_mode` - 'mensal', 'anual'
- `amount` (DECIMAL) - Valor mensal
- `annual_total_amount` (DECIMAL) - Se anual
- `asaas_subscription_id` - ID da assinatura no Asaas (quando implementado)
- `asaas_external_reference` - Referência externa
- `status` - 'ativo', 'suspenso', 'cancelado'
- `created_at`, `updated_at`

**`billing_invoices`** (Faturas)
- `id` (PK)
- `tenant_id` (FK → tenants)
- `billing_contract_id` (FK → billing_contracts, nullable)
- `asaas_payment_id` - ID do pagamento no Asaas
- `asaas_customer_id` - ID do cliente no Asaas
- `due_date` (DATE)
- `amount` (DECIMAL)
- `status` - 'pending', 'paid', 'overdue', 'canceled', 'refunded'
- `paid_at` (DATETIME)
- `invoice_url` - Link da fatura no Asaas
- `billing_type` - Tipo de cobrança
- `description` - Descrição da fatura
- `external_reference` - Referência externa
- `whatsapp_last_stage` - Último estágio de cobrança WhatsApp ('pre_due', 'overdue_3d', 'overdue_7d')
- `whatsapp_last_at` (DATETIME) - Data da última cobrança WhatsApp
- `whatsapp_total_messages` (INT) - Contador de mensagens enviadas
- `created_at`, `updated_at`

**`billing_notifications`** (Notificações de Cobrança)
- `id` (PK)
- `tenant_id` (FK → tenants)
- `invoice_id` (FK → billing_invoices, nullable)
- `channel` - 'whatsapp_web'
- `template` - 'pre_due', 'overdue_3d', 'overdue_7d'
- `status` - 'prepared', 'sent_manual', 'opened', 'skipped', 'failed'
- `message` (TEXT) - Mensagem enviada
- `phone_raw` - Telefone original
- `phone_normalized` - Telefone normalizado (wa.me)
- `created_at`, `updated_at`, `sent_at`
- `last_error` (TEXT)

**`asaas_webhook_logs`** (Logs de Webhooks)
- `id` (PK)
- `event` - Tipo de evento do Asaas
- `payload` (TEXT) - JSON do webhook
- `created_at`

#### 4.1.4. Tabelas Base (Planejadas, mas não totalmente utilizadas)

**`plans`** - Planos genéricos (legado)
**`tenant_subscriptions`** - Assinaturas genéricas (legado)
**`invoices`** - Faturas genéricas (legado)

> **Nota**: As tabelas `billing_*` são as versões atuais e devem ser usadas. As tabelas `plans`, `tenant_subscriptions` e `invoices` são legado e podem ser removidas no futuro.

### 4.2. Relacionamentos Principais

```
tenants (1) ──→ (N) hosting_accounts
tenants (1) ──→ (N) billing_contracts
tenants (1) ──→ (N) billing_invoices
tenants (1) ──→ (N) billing_notifications

hosting_plans (1) ──→ (N) hosting_accounts
hosting_accounts (1) ──→ (N) hosting_backups
hosting_accounts (1) ──→ (N) billing_contracts

billing_contracts (1) ──→ (N) billing_invoices
billing_invoices (1) ──→ (N) billing_notifications
```

---

## 5. Estrutura de Código

### 5.1. Core Classes

#### `src/Core/DB.php`
- **Responsabilidade**: Gerenciar conexão PDO com MySQL
- **Método principal**: `DB::getConnection(): PDO`
- **Singleton pattern**

#### `src/Core/Router.php`
- **Responsabilidade**: Sistema de rotas customizado
- **Métodos**: `get()`, `post()`, `dispatch()`, `executeHandler()`
- **Suporta**: Strings (`Controller@method`) e Closures

#### `src/Core/Auth.php`
- **Responsabilidade**: Autenticação e autorização
- **Métodos principais**:
  - `Auth::check(): bool` - Verifica se está logado
  - `Auth::user(): ?array` - Retorna dados do usuário
  - `Auth::requireAuth(): void` - Exige login
  - `Auth::requireInternal(): void` - Exige usuário interno

#### `src/Core/Controller.php`
- **Classe base** para todos os controllers
- **Métodos**:
  - `view(string $view, array $data): void` - Renderiza view
  - `json(array $data): void` - Retorna JSON
  - `redirect(string $path): void` - Redireciona (com BASE_PATH)

#### `src/Core/Env.php`
- **Responsabilidade**: Carregar variáveis de ambiente do `.env`
- **Métodos**: `load()`, `get()`, `isDebug()`
- **Tratamento especial**: Valores que começam com `$` (como API keys do Asaas)

#### `src/Core/Storage.php`
- **Responsabilidade**: Helpers para armazenamento de arquivos
- **Métodos**:
  - `getTenantBackupDir(int $tenantId, int $hostingAccountId): string`
  - `ensureDirExists(string $path): void`
  - `generateSafeFileName(string $originalName): string`
  - `formatFileSize(int $bytes): string`

#### `src/Core/MoneyHelper.php`
- **Responsabilidade**: Normalização de valores monetários
- **Método**: `normalizeAmount(string $input): float`
- **Converte**: "1.234,56" → 1234.56

### 5.2. Controllers

#### `AuthController`
- `loginForm()` - Exibe formulário de login
- `login()` - Processa login
- `logout()` - Faz logout

#### `DashboardController`
- `index()` - Dashboard principal (apenas internos)

#### `TenantsController`
- `index()` - Lista todos os clientes
- `create()` - Formulário de criação
- `store()` - Salva novo cliente
- `edit()` - Formulário de edição
- `update()` - Atualiza cliente
- `delete()` - Remove cliente (com validação)
- `show()` - Painel do cliente (com abas)
- `syncBilling()` - Sincroniza faturas com Asaas

#### `HostingController`
- `index()` - Lista contas de hospedagem
- `create()` - Formulário de criação
- `store()` - Salva nova conta

#### `HostingPlanController`
- `index()` - Lista planos
- `create()` - Formulário de criação
- `store()` - Salva novo plano
- `edit()` - Formulário de edição
- `update()` - Atualiza plano
- `toggleStatus()` - Ativa/desativa plano

#### `HostingBackupController`
- `index()` - Lista backups
- `upload()` - Processa upload de .wpress
- `download()` - Download de backup

#### `BillingCollectionsController`
- `index()` - Tela de cobranças (com filtros)
- `showWhatsAppModal()` - Modal/página de cobrança WhatsApp
- `markWhatsAppSent()` - Marca cobrança como enviada

#### `AsaasWebhookController`
- `handle()` - Processa webhooks do Asaas

### 5.3. Services

#### `AsaasConfig`
- **Responsabilidade**: Centralizar configuração do Asaas
- **Lê de**: `.env` (prioridade) e `config/asaas.php`
- **Valida**: API key obrigatória
- **Métodos**: `getConfig()`, `getApiKey()`, `getWebhookToken()`

#### `AsaasClient`
- **Responsabilidade**: Cliente HTTP para API do Asaas (cURL)
- **Métodos**:
  - `request()` - Requisição genérica
  - `findCustomerByCpfCnpj()` - Busca customer
  - `createCustomer()` - Cria customer
  - `updateCustomer()` - Atualiza customer
  - `createPayment()` - Cria pagamento
  - `createSubscription()` - Cria assinatura

#### `AsaasBillingService`
- **Responsabilidade**: Lógica de negócio para cobrança Asaas
- **Métodos**:
  - `ensureCustomerForTenant()` - Garante customer no Asaas
  - `createBillingContractForHosting()` - Cria contrato (local)
  - `refreshTenantBillingStatus()` - Atualiza status financeiro
  - `syncInvoicesForTenant()` - Sincroniza faturas do Asaas

#### `AsaasPlanMapper`
- **Responsabilidade**: Mapear planos para payloads do Asaas
- **Métodos**:
  - `buildMonthlySubscriptionPayload()` - Payload mensal
  - `buildYearlyPaymentPayload()` - Payload anual
  - `hasAnnualOption()` - Verifica se tem opção anual
  - `getMonthlyEquivalent()` - Valor mensal equivalente

#### `WhatsAppBillingService`
- **Responsabilidade**: Gerenciar cobranças via WhatsApp Web
- **Métodos**:
  - `normalizePhone()` - Normaliza telefone para wa.me
  - `suggestStageForInvoice()` - Sugere estágio de cobrança
  - `buildMessageForInvoice()` - Monta mensagem
  - `prepareNotificationForInvoice()` - Cria/atualiza notificação

---

## 6. Rotas e Endpoints

### 6.1. Autenticação

| Método | Rota | Controller | Descrição |
|--------|------|------------|-----------|
| GET | `/login` | `AuthController@loginForm` | Formulário de login |
| POST | `/login` | `AuthController@login` | Processa login |
| GET | `/logout` | `AuthController@logout` | Faz logout |

### 6.2. Dashboard

| Método | Rota | Controller | Descrição |
|--------|------|------------|-----------|
| GET | `/` | Closure | Redireciona para `/login` ou `/dashboard` |
| GET | `/dashboard` | `DashboardController@index` | Dashboard principal (interno) |

### 6.3. Clientes (Tenants)

| Método | Rota | Controller | Descrição |
|--------|------|------------|-----------|
| GET | `/tenants` | `TenantsController@index` | Lista clientes |
| GET | `/tenants/create` | `TenantsController@create` | Formulário de criação |
| POST | `/tenants/store` | `TenantsController@store` | Salva novo cliente |
| GET | `/tenants/edit` | `TenantsController@edit` | Formulário de edição |
| POST | `/tenants/update` | `TenantsController@update` | Atualiza cliente |
| POST | `/tenants/delete` | `TenantsController@delete` | Remove cliente |
| GET | `/tenants/view` | `TenantsController@show` | Painel do cliente |
| POST | `/tenants/sync-billing` | `TenantsController@syncBilling` | Sincroniza faturas Asaas |

### 6.4. Hospedagem

| Método | Rota | Controller | Descrição |
|--------|------|------------|-----------|
| GET | `/hosting` | `HostingController@index` | Lista contas de hospedagem |
| GET | `/hosting/create` | `HostingController@create` | Formulário de criação |
| POST | `/hosting/store` | `HostingController@store` | Salva nova conta |
| GET | `/hosting/backups` | `HostingBackupController@index` | Lista backups |
| POST | `/hosting/backups/upload` | `HostingBackupController@upload` | Upload de backup |
| GET | `/hosting/backups/download` | `HostingBackupController@download` | Download de backup |

### 6.5. Planos de Hospedagem

| Método | Rota | Controller | Descrição |
|--------|------|------------|-----------|
| GET | `/hosting-plans` | `HostingPlanController@index` | Lista planos |
| GET | `/hosting-plans/create` | `HostingPlanController@create` | Formulário de criação |
| POST | `/hosting-plans/store` | `HostingPlanController@store` | Salva novo plano |
| GET | `/hosting-plans/edit` | `HostingPlanController@edit` | Formulário de edição |
| POST | `/hosting-plans/update` | `HostingPlanController@update` | Atualiza plano |
| POST | `/hosting-plans/toggle-status` | `HostingPlanController@toggleStatus` | Ativa/desativa plano |

### 6.6. Cobranças / WhatsApp

| Método | Rota | Controller | Descrição |
|--------|------|------------|-----------|
| GET | `/billing/collections` | `BillingCollectionsController@index` | Tela de cobranças |
| GET | `/billing/whatsapp-modal` | `BillingCollectionsController@showWhatsAppModal` | Modal de cobrança WhatsApp |
| POST | `/billing/whatsapp-sent` | `BillingCollectionsController@markWhatsAppSent` | Marca como enviada |

### 6.7. Webhooks

| Método | Rota | Controller | Descrição |
|--------|------|------------|-----------|
| POST | `/webhook/asaas` | `AsaasWebhookController@handle` | Recebe webhooks do Asaas |

---

## 7. Funcionalidades Implementadas

### 7.1. Módulo de Clientes

✅ **CRUD Completo**
- Criar cliente (PF ou PJ)
- Editar cliente
- Excluir cliente (com validação de relacionamentos)
- Listar clientes
- Visualizar painel do cliente com abas

✅ **Separação PF/PJ**
- Campos específicos para cada tipo
- Validação diferenciada
- Exibição adequada no painel

✅ **Integração com Asaas**
- Sincronização manual de faturas
- Atualização automática via webhook
- Status financeiro do cliente

### 7.2. Módulo de Hospedagem

✅ **CRUD de Contas de Hospedagem**
- Criar conta vinculada a cliente
- Listar todas as contas
- Campos: domínio, provedor, data de expiração, decisão, status de backup

✅ **Sistema de Backups**
- Upload de arquivos .wpress (All-in-One WP Migration)
- Armazenamento organizado por tenant/hosting
- Download de backups
- Histórico de backups

✅ **Planos de Hospedagem**
- CRUD completo
- Opção mensal e anual
- Valores anuais com desconto
- Ativação/desativação de planos
- Integração com contas de hospedagem

### 7.3. Módulo Financeiro

✅ **Integração com Asaas**
- Configuração via `.env`
- Criação/atualização de customers
- Sincronização de faturas (manual e automática)
- Webhook para atualizações em tempo real
- Status financeiro dos clientes

✅ **Cobranças via WhatsApp Web**
- Normalização automática de telefones
- Sugestão inteligente de estágio (pre_due, overdue_3d, overdue_7d)
- Mensagens pré-formatadas por estágio
- Link direto para WhatsApp Web com mensagem pronta
- Histórico completo de cobranças
- Integração com aba Financeiro do cliente

✅ **Tela de Cobranças**
- Filtros por status de fatura e estágio WhatsApp
- Resumo financeiro (total em atraso, clientes em atraso, etc.)
- Lista completa de faturas com ações
- Badges visuais de status

### 7.4. Painel do Cliente (Tenant Hub)

✅ **Aba Visão Geral**
- Informações completas do cliente
- Dados PF/PJ
- Status e notas

✅ **Aba Hospedagem & Sites**
- Lista de contas de hospedagem
- Status de backup
- Ações rápidas

✅ **Aba Docs & Backups**
- Upload de backups WordPress
- Lista de backups por site
- Download de backups

✅ **Aba Financeiro**
- Resumo financeiro
- Sincronização com Asaas
- Lista de faturas
- Histórico de cobranças WhatsApp
- Botão para cobrar via WhatsApp

### 7.5. Sistema de Autenticação

✅ **Login/Logout**
- Autenticação por email/senha
- Sessão PHP
- Separação interno/cliente

✅ **Autorização**
- Proteção de rotas
- Verificação de usuário interno
- Redirecionamento automático

### 7.6. Helpers e Utilitários

✅ **Sistema de URLs**
- `BASE_PATH` automático para subpastas
- Função global `pixelhub_url()`
- Redirecionamentos consistentes

✅ **Sistema de Storage**
- Organização de arquivos por tenant
- Criação automática de diretórios
- Nomes de arquivo seguros

✅ **Normalização de Valores**
- Valores monetários (BR → decimal)
- Telefones (normalização para wa.me)

---

## 8. Como Começar a Desenvolver

### 8.1. Pré-requisitos

- PHP 8.x
- MySQL 5.7+ ou 8.0+
- XAMPP (ou Apache + PHP)
- Composer (opcional, para autoload PSR-4)

### 8.2. Instalação

1. **Clone o repositório** (ou copie os arquivos)

2. **Configure o `.env`**:
```bash
cp .env.example .env
```

Edite `.env` com suas credenciais:
```env
DB_HOST=localhost
DB_NAME=paine.pixel12digital
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

ADMIN_EMAIL=admin@pixel12.test
ADMIN_PASSWORD=123456

ASAAS_API_KEY="$sua_chave_aqui"
ASAAS_ENV=production
ASAAS_WEBHOOK_TOKEN=seu_token_seguro
```

> **Importante**: Valores que começam com `$` (como API keys do Asaas) devem estar entre aspas no `.env`.

3. **Execute as migrations**:
```bash
php database/migrate.php
```

4. **Execute o seed** (cria usuário admin padrão):
```bash
php database/seed.php
```

5. **Acesse o sistema**:
```
http://localhost/painel.pixel12digital/public/
```

### 8.3. Credenciais Padrão

- **Email**: `admin@pixel12.test`
- **Senha**: `123456`

### 8.4. Estrutura de Desenvolvimento

#### Criar uma Nova Funcionalidade

1. **Criar Migration** (se necessário):
```php
// database/migrations/YYYYMMDD_nome_da_migration.php
class NomeDaMigration
{
    public function up(PDO $db): void { /* ... */ }
    public function down(PDO $db): void { /* ... */ }
}
```

2. **Criar Controller**:
```php
// src/Controllers/NomeController.php
namespace PixelHub\Controllers;
use PixelHub\Core\Controller;
use PixelHub\Core\Auth;

class NomeController extends Controller
{
    public function index(): void
    {
        Auth::requireInternal();
        // Lógica aqui
        $this->view('nome.index', ['data' => $data]);
    }
}
```

3. **Criar View**:
```php
// views/nome/index.php
<?php ob_start(); ?>
<!-- HTML aqui -->
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout/main.php';
```

4. **Registrar Rota**:
```php
// public/index.php
$router->get('/nome', 'NomeController@index');
```

#### Criar um Service

```php
// src/Services/NomeService.php
namespace PixelHub\Services;

class NomeService
{
    public static function metodo(): void
    {
        // Lógica de negócio
    }
}
```

### 8.5. Convenções de Código

- **Namespaces**: `PixelHub\{Core|Controllers|Services}`
- **Controllers**: Estendem `Controller`, métodos públicos retornam `void`
- **Services**: Métodos estáticos, lógica de negócio
- **Views**: Buffer de output (`ob_start()`), require layout no final
- **Migrations**: Classes com `up()` e `down()`, executadas via `migrate.php`

### 8.6. Debugging

- **Logs**: `logs/pixelhub.log`
- **Error Log**: Configurado via `pixelhub_log()` (redireciona para `logs/pixelhub.log`)
- **Debug**: `APP_DEBUG=true` no `.env`

---

## 9. Próximos Passos

### 9.1. Curto Prazo (Fase 1 - Finalização)

- [ ] **Portal do Cliente (PWA)**
  - Tela de login para clientes
  - Dashboard do cliente
  - Visualização de faturas e pagamento
  - Manifest e Service Worker

- [ ] **Criação Automática de Assinaturas no Asaas**
  - Ao criar `billing_contract`, criar subscription no Asaas
  - Atualizar `asaas_subscription_id` no contrato

- [ ] **Dashboard com Métricas**
  - Total em atraso
  - Clientes em atraso
  - Faturas vencendo
  - Gráficos simples

### 9.2. Médio Prazo (Fase 2-4)

- [ ] **Sistema de Tickets**
  - CRUD de tickets
  - Mensagens/threads
  - Atribuição de responsáveis
  - Notificações

- [ ] **Integração de Tickets com Projetos Externos**
  - Endpoint `/api/tickets`
  - API Key authentication
  - Documentação para satélites

- [ ] **Chat Unificado**
  - Widget JS para sites
  - Conversas em tempo real
  - Painel de atendimento

### 9.3. Longo Prazo (Fase 5-8)

- [ ] **CRM (Kanban)**
  - Contatos
  - Pipelines e estágios
  - Deals (negócios)
  - Board Kanban

- [ ] **Projetos e Tarefas**
  - Projetos internos
  - Tarefas com status
  - Atribuição e prazos
  - Comentários

- [ ] **Documentos**
  - Upload de arquivos
  - Organização por categoria
  - Compartilhamento com clientes

- [ ] **Conteúdo e Anúncios**
  - Calendário de conteúdo
  - Campanhas de anúncios
  - Resultados e métricas

### 9.4. Melhorias Técnicas

- [ ] **Testes Automatizados**
  - Unit tests para Services
  - Integration tests para Controllers

- [ ] **API REST Completa**
  - Documentação Swagger/OpenAPI
  - Versionamento de API
  - Rate limiting

- [ ] **Cache**
  - Cache de queries frequentes
  - Cache de configurações

- [ ] **Queue System**
  - Processamento assíncrono
  - Retry de falhas
  - Logs de jobs

---

## 10. Informações Importantes para Desenvolvedores

### 10.1. Configuração do Asaas

O sistema está configurado para integrar com o Asaas. Para funcionar:

1. Configure no `.env`:
```env
ASAAS_API_KEY="$aact_prod_..."  # Com aspas e $ no início
ASAAS_ENV=production
ASAAS_WEBHOOK_TOKEN=token_seguro_aqui
```

2. **Importante**: Valores que começam com `$` devem estar entre aspas no `.env`.

3. O webhook do Asaas deve apontar para:
```
https://seu-dominio.com/webhook/asaas
```

### 10.2. Sistema de Cobranças WhatsApp

- **Fluxo Manual**: Usuário clica em "Cobrar", abre WhatsApp Web, envia mensagem, volta e marca como enviado
- **Normalização**: Telefones são normalizados automaticamente para formato wa.me (5511999999999)
- **Estágios**: Sistema sugere automaticamente o estágio baseado na fatura (pre_due, overdue_3d, overdue_7d)
- **Mensagens**: Templates pré-formatados, editáveis antes do envio

### 10.3. Estrutura de Armazenamento

Backups são armazenados em:
```
storage/tenants/{tenant_id}/backups/{hosting_account_id}/{file_name}.wpress
```

O sistema cria os diretórios automaticamente.

### 10.4. Migrations

- **Executar**: `php database/migrate.php`
- **Ordem**: Migrations são executadas em ordem alfabética (use prefixo de data)
- **Rollback**: Não implementado ainda (planejado)

### 10.5. Autenticação

- **Internos**: `users.is_internal = 1` → acesso completo
- **Clientes**: `users.is_internal = 0` + `tenant_users` → acesso limitado
- **Sessão**: `$_SESSION['user_id']` e `$_SESSION['is_internal']`

---

## 11. Contatos e Suporte

Para dúvidas sobre o projeto:
- Consulte este documento primeiro
- Verifique os logs em `logs/pixelhub.log`
- Revise as migrations em `database/migrations/`
- Analise os controllers e services para entender o fluxo

---

**Documento mantido por**: Equipe Pixel12 Digital  
**Última revisão**: 17/11/2025  
**Versão do documento**: 2.0
