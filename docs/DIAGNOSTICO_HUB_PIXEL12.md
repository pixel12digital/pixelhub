# 📌 DIAGNÓSTICO HUB PIXEL12 - Respostas Técnicas

**Data:** 2025-01-31  
**Objetivo:** Mapear estrutura atual do sistema para planejamento de novas funcionalidades

---

## 1. ESTRUTURA ATUAL DO SISTEMA

### 1.1. Linguagem + Framework

**Resposta:** PHP 8.0+ puro (sem frameworks)

- **Stack:** PHP puro com padrão MVC simplificado customizado
- **Autoload:** PSR-4 (namespace `PixelHub\`)
- **Router:** Customizado (`src/Core/Router.php`)
- **Padrão:** MVC simplificado (Controller → Service → Database → View)
- **Frontend:** HTML5, CSS3, JavaScript Vanilla (sem frameworks pesados)

**Arquivos relevantes:**
- `composer.json` - apenas autoload PSR-4
- `public/index.php` - bootstrap e rotas
- `src/Core/` - classes core (Router, Auth, DB, Controller)

---

### 1.2. Banco de Dados

**Resposta:** MySQL/MariaDB

**Tabelas principais identificadas:**

**Núcleo:**
- `users` - Usuários do sistema (internos e clientes)
- `tenants` - Clientes da agência (CRM básico)
- `tenant_users` - Relação usuário-cliente (roles: admin_cliente, financeiro, suporte)

**Projetos & Tarefas:**
- `projects` - Projetos (internos e de clientes)
- `tasks` - Tarefas do Kanban
- `task_checklists` - Checklist de tarefas
- `task_attachments` - Anexos de tarefas (inclui gravações de tela)

**Hospedagem:**
- `hosting_accounts` - Contas de hospedagem
- `hosting_plans` - Planos de hospedagem
- `hosting_backups` - Backups de hospedagem
- `hosting_providers` - Provedores de hospedagem

**Financeiro:**
- `billing_invoices` - Faturas/cobranças (sincronizadas do Asaas)
- `billing_contracts` - Contratos de cobrança recorrente
- `billing_service_types` - Categorias de serviços (hospedagem, domínio, etc.)
- `billing_notifications` - Notificações WhatsApp de cobrança
- `asaas_webhook_logs` - Logs de webhooks do Asaas

**Outros:**
- `owner_shortcuts` - Acessos e links de infraestrutura
- `tenant_documents` - Documentos de clientes
- `whatsapp_templates` - Templates de WhatsApp genéricos
- `whatsapp_generic_logs` - Logs de interações WhatsApp
- `screen_recordings` - Biblioteca de gravações de tela

**Relacionamentos principais:**
- `tenants` (1) → (N) `projects`
- `projects` (1) → (N) `tasks`
- `tasks` (1) → (N) `task_checklists`
- `tasks` (1) → (N) `task_attachments`
- `tenants` (1) → (N) `hosting_accounts`
- `tenants` (1) → (N) `billing_invoices`
- `tenants` (1) → (1) `asaas_customer_id` (UNIQUE)

---

### 1.3. Modularização Atual

**Resposta:** Parcialmente modularizado, mas não totalmente separado

**Módulos identificados:**

✅ **Projetos** - Existe (`ProjectController`, `ProjectService`)
- Rotas: `/projects`, `/projects/board`
- Views: `views/projects/index.php`
- Service: `src/Services/ProjectService.php`

✅ **Quadros de Tarefas (Kanban)** - Existe (`TaskBoardController`, `TaskService`)
- Rotas: `/projects/board`, `/tasks/*`
- Views: `views/tasks/board.php`
- Service: `src/Services/TaskService.php`

❌ **Suporte/Tickets** - NÃO existe módulo dedicado
- Apenas campo `task_type` em `tasks` com valor `'client_ticket'`
- Não há tabela `tickets` ou controller específico
- Planejado na Fase 2 (conforme `docs/pixel-hub-plano-geral.md`)

✅ **Financeiro** - Existe (`BillingCollectionsController`, `AsaasBillingService`)
- Rotas: `/billing/collections`, `/billing/overview`
- Integração com Asaas via API
- Sincronização de faturas e webhooks
- Views: `views/billing_collections/`

✅ **Hosting/Clientes** - Existe (`HostingController`, `TenantsController`)
- Rotas: `/hosting/*`, `/tenants/*`
- Views: `views/hosting/`, `views/tenants/`

❌ **Agenda/Calendário** - NÃO existe
- Nenhuma referência a calendário ou agenda encontrada
- Apenas campo `due_date` em tarefas e projetos

**Status:** Sistema está em evolução, alguns módulos implementados, outros planejados.

---

## 2. MÓDULO DE PROJETOS

### 2.1. O que existe hoje?

**Campos da tabela `projects`:**

- `id` (PK)
- `tenant_id` (FK, nullable) - NULL = projeto interno
- `name` (VARCHAR 150)
- `description` (TEXT)
- `status` ('ativo' | 'arquivado')
- `priority` ('baixa' | 'media' | 'alta' | 'critica')
- `type` ('interno' | 'cliente')
- `is_customer_visible` (TINYINT) - 0 = só interno, 1 = pode aparecer para cliente
- `template` (VARCHAR 50, nullable) - Ex: 'migracao_wp'
- `due_date` (DATE)
- `created_by`, `updated_by` (FK users)
- `created_at`, `updated_at`
- Campos legados: `slug`, `external_project_id`, `base_url` (para integração futura)

**Status:** Sim, há status ('ativo' | 'arquivado')

**Etapas/Fases:** NÃO existe estrutura de fases/etapas ainda

**Vinculação de tarefas:** Sim, tarefas são vinculadas a projetos via `tasks.project_id` (obrigatório)

---

### 2.2. Estrutura de Fases/Etapas

**Resposta:** NÃO existe hoje

- Não há tabela `project_phases` ou `project_stages`
- Não há campo `phase` ou `stage` em `projects`
- Apenas status simples: 'ativo' ou 'arquivado'

**Estrutura desejada (a implementar):**
- Fase 1, Fase 2, etc.
- Entregue / Em manutenção
- Ainda não implementado

---

### 2.3. Múltiplos projetos simultâneos

**Como aparece na interface:**

- **Lista de projetos:** `views/projects/index.php`
- **Filtros disponíveis:**
  - Por cliente (`tenant_id`)
  - Por status ('ativo' | 'arquivado')
  - Por tipo ('interno' | 'cliente')
- **Quadro Kanban:** `views/tasks/board.php`
  - Filtro por projeto (`project_id`)
  - Filtro por cliente (`tenant_id`)
  - Filtro por tipo de projeto (`type`)
  - Busca por nome do cliente (`client_query`)

**Interface atual:** Lista simples com filtros, sem visualização de múltiplos projetos simultâneos em cards ou timeline.

---

## 3. QUADRO DE TAREFAS (KANBAN)

### 3.1. Estrutura Atual

**Resposta:** Sim, já tem colunas definidas

**Colunas do Kanban:**
1. **Backlog** (`status = 'backlog'`)
2. **Em Andamento** (`status = 'em_andamento'`)
3. **Aguardando Cliente** (`status = 'aguardando_cliente'`)
4. **Concluída** (`status = 'concluida'`)

**Implementação:**
- View: `views/tasks/board.php`
- Controller: `TaskBoardController@board`
- Service: `TaskService::getAllTasks()`
- Drag & drop implementado (JavaScript vanilla)

**Status:** Funcional, com 4 colunas fixas.

---

### 3.2. Campos das Tarefas

**Campos da tabela `tasks`:**

✅ **título** - `title` (VARCHAR 200, obrigatório)
✅ **descrição** - `description` (TEXT, nullable)
✅ **prioridade** - Não tem campo direto, mas pode herdar do projeto (`projects.priority`)
✅ **tipo** - `task_type` (VARCHAR 50) - 'internal' ou 'client_ticket'
✅ **prazo** - `due_date` (DATE, nullable)
✅ **projeto vinculado** - `project_id` (FK, obrigatório)
✅ **usuário responsável** - `assignee` (VARCHAR 150, nullable) - Nome/email do responsável
✅ **criado por** - `created_by` (FK users, nullable)

**Campos adicionais (lifecycle):**
- `start_date` (DATE, nullable) - Data de início
- `completed_at` (DATETIME, nullable) - Data/hora de conclusão
- `completed_by` (INT, nullable) - ID do usuário que concluiu
- `completion_note` (TEXT, nullable) - Resumo/feedback da conclusão
- `status` (VARCHAR 30) - Status atual (backlog, em_andamento, aguardando_cliente, concluida)
- `order` (INT) - Ordem dentro da coluna

**Checklist:** Tabela separada `task_checklists` vinculada a `tasks`

**Anexos:** Tabela `task_attachments` (suporta gravações de tela)

---

### 3.3. Integração Tarefa → Agenda

**Resposta:** NÃO existe integração automática

- Não há módulo de agenda/calendário
- Não há sincronização automática
- Tarefas têm `due_date`, mas não aparecem em calendário
- Tudo é manual hoje

---

## 4. SUPORTE / TICKETS

### 4.1. Existe módulo de suporte?

**Resposta:** NÃO existe módulo dedicado

**Situação atual:**
- Não há tabela `tickets`
- Não há `TicketController` ou `TicketService`
- Apenas campo `task_type = 'client_ticket'` em `tasks` para diferenciar tarefas de tickets
- Planejado na **Fase 2** (conforme documentação)

**Como funciona hoje:**
- Cliente não abre ticket diretamente
- Provavelmente manual (criação de tarefa com `task_type = 'client_ticket'`)
- Não há painel para tickets

---

### 4.2. Campos do Ticket

**Resposta:** Não existe estrutura de tickets

Como não há módulo de tickets, não há campos específicos. Apenas uso indireto via `tasks` com `task_type = 'client_ticket'`.

**Campos que poderiam existir (planejamento futuro):**
- ❌ prioridade (não tem)
- ❌ tags (não tem)
- ❌ projeto relacionado (via `project_id` da tarefa)
- ❌ tempo estimado (não tem)
- ❌ status (usa status da tarefa)

---

### 4.3. SLA

**Resposta:** NÃO existe SLA hoje

- Não há campos de SLA em nenhuma tabela
- Não há regras de SLA implementadas
- Tudo é "quando der" atualmente

---

## 5. AGENDA / CALENDÁRIO

### 5.1. Já existe um calendário no sistema?

**Resposta:** NÃO existe calendário

- Nenhuma referência a calendário encontrada no código
- Não há tabela `events` ou `calendar_events`
- Não há controller ou view de calendário
- Apenas campos `due_date` em tarefas e projetos (mas não são exibidos em calendário)

---

### 5.2. Sincronização externa

**Resposta:** NÃO existe integração com Google Calendar ou Outlook

- Nenhuma referência a Google Calendar ou Outlook
- Não há OAuth ou integração de calendário externo

---

### 5.3. Desejo de integração

**Resposta:** Não especificado no código, mas provavelmente desejado para o futuro

---

## 6. FINANCEIRO / COBRANÇAS

### 6.1. O que existe hoje no HUB?

**Resposta:** Sistema completo de integração com Asaas

**Funcionalidades implementadas:**

✅ **Visualização de clientes** - `TenantsController`
✅ **Integração com Asaas** - `AsaasBillingService`, `AsaasClient`
  - Sincronização de customers
  - Sincronização de faturas (payments)
  - Webhooks do Asaas
✅ **Notificação de atraso** - `BillingCollectionsController`
  - Central de cobranças (`/billing/collections`)
  - Envio de WhatsApp para inadimplentes
  - Templates de WhatsApp configuráveis
✅ **Carteira Recorrente** - `RecurringContractsController`
  - Contratos de cobrança recorrente
  - Categorização de serviços (`billing_service_types`)

**Tabelas financeiras:**
- `billing_invoices` - Faturas sincronizadas do Asaas
- `billing_contracts` - Contratos recorrentes
- `billing_notifications` - Histórico de envios WhatsApp
- `billing_service_types` - Categorias (hospedagem, domínio, etc.)

**Status financeiro do tenant:**
- Campo `billing_status` em `tenants`: 'sem_cobranca', 'em_dia', 'atrasado_parcial', 'atrasado_total'
- Atualizado automaticamente via `AsaasBillingService::refreshTenantBillingStatus()`

---

### 6.2. Desejos de automação

**Resposta:** Não implementado, mas possível de implementar

**Funcionalidades desejadas (a implementar):**
- ❌ Ver inadimplentes como "tarefas automáticas" - não existe
- ❌ Agendar automaticamente "bloco Flex" quando houver muitos inadimplentes - não existe
- ❌ Gerar alertas automáticos - não existe (apenas visualização manual)

**Dados disponíveis para automação:**
- `tenants.billing_status` - Status financeiro
- `billing_invoices.status = 'overdue'` - Faturas em atraso
- `billing_invoices.due_date` - Data de vencimento

---

## 7. CRM / LEADS

### 7.1. Existe módulo de CRM hoje?

**Resposta:** CRM básico via `tenants`, mas não completo

**O que existe:**
- ✅ Lista de clientes (`tenants`) - `TenantsController`
- ✅ Dados básicos do cliente (nome, CPF/CNPJ, email, telefone)
- ✅ Histórico de interações WhatsApp (`whatsapp_generic_logs`)
- ✅ Documentos do cliente (`tenant_documents`)
- ✅ Timeline de WhatsApp (parcial)

**O que NÃO existe:**
- ❌ Lista de leads (separada de clientes)
- ❌ Funis de vendas
- ❌ Pipeline de negócios
- ❌ CRM Kanban (planejado na Fase 5, mas não implementado)

**Status:** CRM básico (clientes), mas não CRM completo (leads + funis).

---

### 7.2. É só uma ideia?

**Resposta:** Planejado, mas não implementado

- Documentado em `docs/pixel-hub-plano-geral.md` como **Fase 5 - CRM (Kanban)**
- Status: 0% implementado
- Apenas estrutura básica de `tenants` existe

---

## 8. USUÁRIOS E PERFIS

### 8.1. Quem usa o HUB hoje?

**Resposta:** Sistema suporta múltiplos perfis, mas provavelmente só você usa

**Estrutura de usuários:**

**Tabela `users`:**
- `is_internal` (TINYINT) - 1 = usuário Pixel12, 0 = cliente
- Campos: `id`, `name`, `email`, `password_hash`

**Tabela `tenant_users`:**
- Relação usuário-cliente
- `role` - 'admin_cliente', 'financeiro', 'suporte'
- Permite que clientes tenham acesso limitado

**Autenticação:**
- `Auth::requireInternal()` - Apenas usuários internos
- `Auth::requireAuth()` - Qualquer usuário autenticado
- Sessão: `$_SESSION['user_id']`, `$_SESSION['is_internal']`

**Perfis suportados:**
- ✅ Usuário interno (Pixel12)
- ✅ Cliente com acesso (via `tenant_users`)
- ✅ Atendente (role 'suporte' em `tenant_users`)

**Uso real:** Provavelmente só você (interno) usa hoje, mas estrutura permite múltiplos usuários.

---

## 9. FUNCIONALIDADES DESEJADAS (Validação)

### 9.1. Blocos diários automáticos baseados no template?

**Resposta:** ❌ NÃO existe

- Não há conceito de "blocos diários" no sistema
- Não há tabela `daily_blocks` ou `time_blocks`
- Não há templates de blocos

---

### 9.2. Tarefas automaticamente ligadas aos blocos corretos?

**Resposta:** ❌ NÃO existe (blocos não existem)

---

### 9.3. Tickets automaticamente criando tarefas?

**Resposta:** ❌ NÃO existe (tickets não existem)

---

### 9.4. Tickets automaticamente recomendando bloco de agenda?

**Resposta:** ❌ NÃO existe (tickets e agenda não existem)

---

### 9.5. Relatórios semanais/mensais (horas + blocos + projetos)?

**Resposta:** ❌ NÃO existe

- Não há sistema de registro de horas
- Não há relatórios de produtividade
- Não há dashboard de métricas

---

### 9.6. Painel tipo ClickUp mostrando métricas?

**Resposta:** ❌ NÃO existe

**O que não tem:**
- ❌ Horas por projeto
- ❌ Blocos concluídos x planejados
- ❌ Tarefas por bloco
- ❌ Produtividade da semana

**O que tem:**
- ✅ Quadro Kanban básico
- ✅ Dashboard simples com contadores (`views/dashboard/index.php`)

---

### 9.7. Mapa de disponibilidade?

**Resposta:** ❌ NÃO existe

- Não há cálculo de disponibilidade
- Não há previsão de capacidade
- Não há agendamento automático

---

### 9.8. Previsão de entrega?

**Resposta:** ❌ NÃO existe

- Não há cálculo baseado em blocos FUTURE
- Não há estimativas de entrega
- Apenas `due_date` manual em projetos e tarefas

---

### 9.9. Dashboards inteligentes?

**Resposta:** ❌ NÃO existe (apenas dashboard básico)

**O que não tem:**
- ❌ "Hoje → o que fazer agora?"
- ❌ "Semana → blocos disponíveis"
- ❌ "Mês → distribuição por categoria"

**O que tem:**
- ✅ Dashboard simples com contadores (`tenantsCount`, `invoicesCount`, `pendingInvoices`)

---

## 10. COMO IMAGINA OPERAR NO DIA A DIA?

### 10.1. Quando iniciar um bloco, você quer:

**Resposta:** Não especificado no código (sistema não tem blocos ainda)

**Opções possíveis:**
- (1) Lista completa das tarefas daquele bloco automaticamente na tela?
- (2) Selecionar manualmente dentro das tarefas do dia?

**Status atual:** Sistema não tem conceito de "blocos", apenas tarefas em Kanban.

---

### 10.2. Ao finalizar o bloco:

**Resposta:** Não especificado no código

**Opções possíveis:**
- (1) Preencher um mini-resumo?
- (2) Só marcar um botão "Concluir bloco"?

**Status atual:** Tarefas têm `completion_note` (campo de resumo), mas não há fluxo de "blocos".

---

### 10.3. Acompanhar progresso no formato:

**Resposta:** Atualmente apenas Kanban

**O que existe:**
- ✅ **Kanban** - `views/tasks/board.php` (funcional)

**O que não existe:**
- ❌ **Calendário** - não há visualização em calendário
- ❌ **Dashboard** - apenas dashboard básico com contadores

**Status:** Apenas Kanban implementado. Calendário e dashboard avançado precisam ser desenvolvidos.

---

## 📊 RESUMO EXECUTIVO

### ✅ O que JÁ EXISTE:

1. **Estrutura base:** PHP puro, MVC simplificado, MySQL
2. **Projetos:** CRUD completo, vinculação com clientes, status e prioridades
3. **Tarefas (Kanban):** 4 colunas, drag & drop, checklist, anexos, gravações de tela
4. **Financeiro:** Integração completa com Asaas, cobranças, webhooks, WhatsApp
5. **Hospedagem:** Contas, planos, backups
6. **Clientes (CRM básico):** Cadastro, documentos, timeline WhatsApp
7. **Autenticação:** Suporte a múltiplos usuários e perfis

### ❌ O que NÃO EXISTE (precisa desenvolver):

1. **Sistema de Blocos Diários** - Conceito não existe
2. **Agenda/Calendário** - Não há módulo
3. **Tickets de Suporte** - Planejado (Fase 2), mas não implementado
4. **CRM Completo** - Apenas clientes básicos, sem leads/funis
5. **Relatórios/Métricas** - Apenas contadores básicos
6. **Dashboard Avançado** - Apenas dashboard simples
7. **SLA** - Não implementado
8. **Automações** - Poucas automações (apenas sincronização Asaas)

### 🎯 PRÓXIMOS PASSOS SUGERIDOS:

1. **Definir arquitetura de Blocos Diários** (nova funcionalidade)
2. **Implementar módulo de Agenda/Calendário**
3. **Criar sistema de Tickets** (Fase 2 planejada)
4. **Desenvolver Dashboard avançado** (métricas, produtividade)
5. **Implementar automações** (tickets → tarefas, inadimplentes → blocos)
6. **Criar relatórios** (horas, blocos, projetos)

---

**Arquivo gerado automaticamente pela análise do código-fonte.**  
**Última atualização:** 2025-01-31




