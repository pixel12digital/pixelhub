# Auditoria Completa - Módulo de Tickets no PixelHub

**Data da Auditoria:** 2025-01-25

## 1. Tabelas e Models de Tickets

### Tabela `tickets`

**Schema completo:**
```sql
CREATE TABLE tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,              -- OBRIGATÓRIO (FK tenants.id)
    project_id INT UNSIGNED NULL,                  -- OPCIONAL (FK projects.id)
    task_id INT UNSIGNED NULL,                    -- OPCIONAL (FK tasks.id)
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT NULL,
    prioridade ENUM('baixa', 'media', 'alta', 'critica') NOT NULL DEFAULT 'media',
    status ENUM('aberto', 'em_atendimento', 'aguardando_cliente', 'resolvido', 'cancelado') NOT NULL DEFAULT 'aberto',
    origem ENUM('cliente', 'interno', 'whatsapp', 'automatico') NOT NULL DEFAULT 'cliente',
    prazo_sla DATETIME NULL,
    data_resolucao DATETIME NULL,
    created_by INT UNSIGNED NULL,                 -- FK users.id
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_project_id (project_id),
    INDEX idx_task_id (task_id),
    INDEX idx_status (status),
    INDEX idx_prioridade (prioridade),
    INDEX idx_created_by (created_by),
    
    -- Foreign Keys
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
)
```

**Campos principais:**
- `tenant_id`: **OBRIGATÓRIO** - Todo ticket deve estar vinculado a um cliente
- `project_id`: **OPCIONAL** - Ticket pode existir sem projeto
- `task_id`: **OPCIONAL** - Ticket pode ter uma tarefa vinculada (relacionamento unidirecional)
- `titulo`: Título do ticket (máx. 200 caracteres)
- `descricao`: Descrição detalhada (TEXT)
- `prioridade`: Baixa, Média, Alta, Crítica
- `status`: Aberto, Em Atendimento, Aguardando Cliente, Resolvido, Cancelado
- `origem`: Cliente, Interno, WhatsApp, Automático
- `prazo_sla`: Prazo de SLA (opcional)
- `data_resolucao`: Preenchido automaticamente quando status = 'resolvido' ou 'cancelado'

**Migrations:**
1. `20250201_create_tickets_table.php` - Criação inicial da tabela
2. `20251201_alter_tickets_make_tenant_required_and_add_cancelado_status.php` - Torna tenant_id obrigatório e adiciona status 'cancelado'

### Tabelas Relacionadas

**NÃO EXISTEM:**
- ❌ `ticket_attachments` - Não há tabela de anexos específica para tickets
- ❌ `ticket_comments` - Não há tabela de comentários/mensagens de tickets
- ❌ `ticket_history` - Não há tabela de histórico de mudanças

**EXISTEM (mas não são específicas de tickets):**
- ✅ `task_attachments` - Anexos de tarefas (pode ser usado indiretamente se ticket tiver task_id)
- ✅ `tasks` - Tarefas podem ter `task_type = 'client_ticket'` para indicar que são relacionadas a tickets

## 2. Controllers e Services Relacionados

### Controller: `TicketController`

**Arquivo:** `src/Controllers/TicketController.php`

**Métodos implementados:**
1. `index()` - Lista todos os tickets com filtros (tenant_id, project_id, status, prioridade)
2. `create()` - Exibe formulário de criação (aceita tenant_id e project_id via GET)
3. `store()` - Cria novo ticket via POST
4. `show()` - Exibe detalhes de um ticket
5. `update()` - Atualiza ticket existente

**Rotas configuradas** (`public/index.php`):
- `GET /tickets` → `TicketController@index`
- `GET /tickets/create` → `TicketController@create`
- `POST /tickets/store` → `TicketController@store`
- `GET /tickets/show` → `TicketController@show`
- `POST /tickets/update` → `TicketController@update`

**Faltando:**
- ❌ Método `delete()` - Não há exclusão de tickets
- ❌ Método para vincular/desvincular tarefa a ticket
- ❌ Método para criar tarefa a partir de ticket

### Service: `TicketService`

**Arquivo:** `src/Services/TicketService.php`

**Métodos implementados:**
1. `getAllTickets(array $filters)` - Lista tickets com filtros opcionais
2. `findTicket(int $id)` - Busca ticket por ID (com joins para tenant, project, task, user)
3. `createTicket(array $data)` - Cria novo ticket (valida tenant_id obrigatório)
4. `updateTicket(int $id, array $data)` - Atualiza ticket existente
5. `findOpenTickets(array $filters)` - **NOVO** - Busca tickets abertos para integração com agenda

**Observações importantes:**
- ✅ `createTicket()` **NÃO cria tarefa automaticamente** (comentário no código indica que foi removido)
- ✅ `findOpenTickets()` retorna tickets com status: 'aberto', 'em_atendimento', 'aguardando_cliente'
- ✅ Ordena por prioridade (critica → baixa) e data de criação

**Faltando:**
- ❌ Método para vincular tarefa a ticket
- ❌ Método para criar tarefa a partir de ticket
- ❌ Método para buscar tickets por task_id
- ❌ Método para histórico de mudanças

## 3. Views e Telas onde Tickets Aparecem

### 3.1. Listagem de Tickets (`views/tickets/index.php`)

**Funcionalidades:**
- ✅ Lista todos os tickets em cards
- ✅ Filtros: Cliente, Status, Prioridade
- ✅ Exibe: Título, Prioridade, Status, Cliente, Projeto, Tarefa (se houver), Datas
- ✅ Botão "Novo Ticket"
- ✅ Link para detalhes do ticket
- ✅ Link para ver tarefa no Kanban (se task_id existir)

**Colunas exibidas:**
- Título
- Prioridade (badge colorido)
- Status (badge colorido)
- Cliente
- Projeto (se houver)
- Tarefa (se houver) com status da tarefa
- Data de criação
- Data de resolução (se resolvido)

### 3.2. Criação de Ticket (`views/tickets/create.php`)

**Funcionalidades:**
- ✅ Formulário completo de criação
- ✅ Campo Cliente (obrigatório) - pré-selecionado se vier via GET
- ✅ Campo Projeto (opcional) - pré-selecionado se vier via GET
- ✅ Campos: Título, Descrição, Prioridade, Origem
- ✅ Validação no frontend (required)

**Pontos de entrada:**
- ✅ Botão "🎫 Novo Ticket" na tela de Clientes (`views/tenants/view.php`)
- ✅ Botão "🎫 Abrir ticket" na tela de Projetos (`views/projects/index.php`) - aparece apenas se projeto tem tenant_id

### 3.3. Detalhes do Ticket (`views/tickets/show.php`)

**Funcionalidades:**
- ✅ Exibe todos os dados do ticket
- ✅ Badges de prioridade e status
- ✅ Informações: Cliente, Projeto, Tarefa relacionada, Origem, Datas
- ✅ Link para voltar à lista
- ✅ Link para ver tarefa no Kanban (se task_id existir)

**Faltando:**
- ❌ Seção de anexos (não existe)
- ❌ Seção de comentários/histórico (não existe)
- ❌ Botão para editar ticket (não há formulário de edição)
- ❌ Botão para vincular/desvincular tarefa

### 3.4. Quadro de Tarefas (`views/tasks/board.php`)

**Integração com Tickets:**
- ✅ Campo `task_type` pode ser `'client_ticket'` para indicar tarefa relacionada a ticket
- ✅ Badge `[TCK]` exibido no card da tarefa quando `task_type = 'client_ticket'` (em `views/tasks/_task_card.php`)
- ✅ Select de tipo de tarefa inclui opção "Ticket / Problema de cliente"
- ✅ Modal de detalhes exibe o tipo de tarefa

**Faltando:**
- ❌ Não há indicação visual de qual ticket está vinculado à tarefa
- ❌ Não há link direto do card da tarefa para o ticket
- ❌ Não há filtro específico para tarefas de tickets
- ❌ Não há badge ou indicador quando tarefa tem `task_id` vinculado a um ticket

### 3.5. Agenda / Blocos de Agenda

**Integração atual:**
- ✅ Método `getTaskBlockType()` em `AgendaService` verifica `task_type = 'client_ticket'`
- ✅ Tarefas de ticket com prioridade alta/crítica → bloco CLIENTES
- ✅ Tarefas de ticket com prioridade baixa/média → bloco SUPORTE
- ✅ Método `findOpenTickets()` existe no `TicketService` para buscar tickets abertos

**Faltando:**
- ❌ Não há exibição de tickets diretamente na Agenda
- ❌ Não há vínculo direto entre tickets e blocos de agenda
- ❌ Não há listagem de tickets abertos no bloco SUPORTE
- ❌ Não há criação automática de tarefa quando ticket é criado
- ❌ Não há sincronização de status entre ticket e tarefa

## 4. Integração Tickets ↔ Tarefas ↔ Agenda

### 4.1. Relacionamento Ticket → Tarefa

**Estrutura atual:**
- ✅ Tabela `tickets` tem campo `task_id` (FK para `tasks.id`)
- ✅ Relacionamento é **unidirecional**: Ticket pode ter uma tarefa, mas tarefa não tem campo `ticket_id`
- ✅ Tarefa pode ter `task_type = 'client_ticket'` para indicar que é relacionada a ticket (mas não há FK)

**Problemas identificados:**
1. **Duplicidade de conceito:**
   - `tickets.task_id` → vincula ticket a uma tarefa existente
   - `tasks.task_type = 'client_ticket'` → indica que tarefa é de ticket (mas não indica qual ticket)
   - Não há forma de saber qual ticket está vinculado a uma tarefa (apenas o contrário)

2. **Criação de tarefa a partir de ticket:**
   - ❌ Não há método para criar tarefa automaticamente quando ticket é criado
   - ❌ Não há botão na tela de ticket para "Criar Tarefa"
   - ❌ Não há sincronização automática

3. **Sincronização de status:**
   - ❌ Status do ticket não sincroniza com status da tarefa
   - ❌ Status da tarefa não sincroniza com status do ticket
   - ❌ Não há lógica de atualização cruzada

### 4.2. Relacionamento Ticket → Projeto

**Estrutura atual:**
- ✅ Tabela `tickets` tem campo `project_id` (FK para `projects.id`)
- ✅ Campo é **OPCIONAL** - ticket pode existir sem projeto
- ✅ Relacionamento é **unidirecional**: Ticket pode ter um projeto, mas projeto não tem lista de tickets

**Funcionalidades:**
- ✅ Filtro por projeto na listagem de tickets
- ✅ Exibição do projeto na tela de detalhes do ticket
- ✅ Pré-seleção de projeto no formulário de criação

**Faltando:**
- ❌ Não há listagem de tickets na tela de detalhes do projeto
- ❌ Não há contador de tickets por projeto

### 4.3. Relacionamento Ticket → Cliente (Tenant)

**Estrutura atual:**
- ✅ Tabela `tickets` tem campo `tenant_id` (FK para `tenants.id`) - **OBRIGATÓRIO**
- ✅ Foreign Key com `ON DELETE RESTRICT` (protege integridade)

**Funcionalidades:**
- ✅ Filtro por cliente na listagem de tickets
- ✅ Exibição do cliente na tela de detalhes do ticket
- ✅ Pré-seleção de cliente no formulário de criação
- ✅ Botão "Novo Ticket" na tela de detalhes do cliente

**Faltando:**
- ❌ Não há listagem de tickets na tela de detalhes do cliente
- ❌ Não há contador de tickets por cliente
- ❌ Não há filtro de tickets por status na tela do cliente

### 4.4. Integração com Agenda

**Estrutura atual:**
- ✅ Método `findOpenTickets()` no `TicketService` busca tickets abertos
- ✅ Método `getTaskBlockType()` em `AgendaService` verifica `task_type = 'client_ticket'`
- ✅ Tarefas de ticket são direcionadas para blocos CLIENTES ou SUPORTE baseado na prioridade

**Faltando:**
- ❌ Não há exibição de tickets diretamente no bloco SUPORTE da agenda
- ❌ Não há criação automática de tarefa quando ticket é criado
- ❌ Não há vínculo direto entre ticket e bloco de agenda
- ❌ Não há listagem de tickets pendentes no modo de trabalho do bloco

## 5. Funcionalidades Já Prontas

### ✅ CRUD Básico de Tickets
- Criar ticket (com validação de tenant_id obrigatório)
- Listar tickets (com filtros)
- Ver detalhes do ticket
- Atualizar ticket (status, prioridade, etc.)

### ✅ Relacionamentos Básicos
- Ticket → Cliente (obrigatório)
- Ticket → Projeto (opcional)
- Ticket → Tarefa (opcional, unidirecional)

### ✅ Filtros e Busca
- Filtro por cliente
- Filtro por projeto
- Filtro por status
- Filtro por prioridade

### ✅ Integração com Tarefas (Parcial)
- Tarefas podem ter `task_type = 'client_ticket'`
- Badge visual `[TCK]` no card da tarefa
- Direcionamento para blocos CLIENTES/SUPORTE baseado em prioridade

### ✅ Pontos de Entrada
- Botão "Novo Ticket" na tela de Clientes
- Botão "Abrir ticket" na tela de Projetos
- Listagem dedicada de tickets (`/tickets`)

### ✅ Método para Agenda
- `findOpenTickets()` - busca tickets abertos ordenados por prioridade

## 6. Funcionalidades Incompletas ou Ausentes

### ❌ Anexos de Tickets
- **Não existe** tabela `ticket_attachments`
- **Não existe** controller para anexos de tickets
- **Não existe** interface para upload de anexos em tickets
- **Workaround possível:** Usar anexos de tarefas se ticket tiver `task_id`

### ❌ Comentários/Histórico de Tickets
- **Não existe** tabela `ticket_comments` ou `ticket_messages`
- **Não existe** sistema de threads/conversas
- **Não existe** histórico de mudanças de status
- **Não existe** log de quem fez o quê e quando

### ❌ Sincronização Ticket ↔ Tarefa
- **Não existe** criação automática de tarefa quando ticket é criado
- **Não existe** sincronização de status entre ticket e tarefa
- **Não existe** botão para "Criar Tarefa" a partir de ticket
- **Não existe** método para vincular tarefa existente a ticket
- **Não existe** método para buscar tickets por `task_id`

### ❌ Integração Completa com Agenda
- **Não existe** exibição de tickets no bloco SUPORTE
- **Não existe** criação automática de tarefa e vínculo com bloco quando ticket é criado
- **Não existe** listagem de tickets pendentes no modo de trabalho do bloco
- **Não existe** vínculo direto entre ticket e bloco de agenda

### ❌ Exclusão de Tickets
- **Não existe** método `delete()` no controller
- **Não existe** soft delete (campo `deleted_at`)
- **Não existe** validação para impedir exclusão de tickets com tarefas vinculadas

### ❌ Edição de Tickets
- **Não existe** formulário de edição (apenas método `update()` via POST)
- **Não existe** interface visual para editar ticket
- **Não existe** validação de permissões para edição

### ❌ Relatórios e Estatísticas
- **Não existe** dashboard de tickets
- **Não existe** métricas de SLA
- **Não existe** relatório de tickets por cliente
- **Não existe** relatório de tickets por período

### ❌ Notificações
- **Não existe** sistema de notificações para novos tickets
- **Não existe** alertas de SLA próximo do vencimento
- **Não existe** notificação quando ticket muda de status

## 7. Pontos Fracos / Limitações Atuais

### 7.1. Duplicidade de Conceito: Ticket vs Tarefa

**Problema identificado:**
- Existem **dois conceitos separados** que se sobrepõem:
  1. **Ticket** (`tickets` table) - Entidade dedicada com status próprio, prioridade, SLA
  2. **Tarefa de Ticket** (`tasks` com `task_type = 'client_ticket'`) - Tarefa que representa um ticket

**Inconsistências:**
- Ticket pode ter `task_id`, mas tarefa não tem `ticket_id`
- Tarefa pode ter `task_type = 'client_ticket'` sem estar vinculada a um ticket real
- Não há garantia de que ticket e tarefa estejam sincronizados
- Status do ticket e status da tarefa são independentes

**Riscos:**
- Dados podem ficar dessincronizados
- Pode haver tickets sem tarefas e tarefas de ticket sem tickets
- Difícil rastrear qual ticket está relacionado a qual tarefa

### 7.2. Falta de Histórico e Rastreabilidade

**Problema:**
- Não há registro de quem fez o quê e quando
- Não há histórico de mudanças de status
- Não há log de comentários/mensagens
- Não há auditoria de ações

**Impacto:**
- Impossível rastrear evolução do ticket
- Impossível saber quem respondeu o quê
- Impossível gerar relatórios de atendimento
- Impossível medir tempo de resposta

### 7.3. Integração Incompleta com Agenda

**Problema:**
- Tickets existem isoladamente da Agenda
- Não há forma de trabalhar tickets diretamente na Agenda
- Não há criação automática de tarefa quando ticket é criado
- Não há sincronização entre status do ticket e status da tarefa na Agenda

**Impacto:**
- Tickets não aparecem no fluxo de trabalho da Agenda
- Necessário criar tarefa manualmente para trabalhar ticket na Agenda
- Duplicação de trabalho (criar ticket + criar tarefa)

### 7.4. Falta de Anexos e Documentação

**Problema:**
- Não há sistema de anexos para tickets
- Não há forma de anexar arquivos diretamente ao ticket
- Dependência de anexos de tarefas (se ticket tiver task_id)

**Impacto:**
- Impossível anexar evidências diretamente ao ticket
- Necessário criar tarefa para ter anexos
- Perda de contexto quando ticket não tem tarefa

### 7.5. Interface Limitada

**Problema:**
- Não há formulário de edição visual
- Não há seção de comentários na tela de detalhes
- Não há seção de anexos na tela de detalhes
- Não há histórico de mudanças na tela de detalhes

**Impacto:**
- Experiência do usuário limitada
- Necessário usar API direta para algumas ações
- Falta de contexto visual

## 8. Sugestão de Próximo Passo Técnico (Alto Nível)

### Prioridade 1: Integração Ticket ↔ Tarefa ↔ Agenda

**Objetivo:** Criar fluxo completo onde ticket pode gerar tarefa automaticamente e trabalhar na Agenda

**Ações sugeridas:**
1. Adicionar campo `ticket_id` na tabela `tasks` (FK para `tickets.id`)
2. Criar método `TicketService::createTaskFromTicket(int $ticketId, ?int $projectId)` 
3. Adicionar botão "Criar Tarefa" na tela de detalhes do ticket
4. Sincronizar status entre ticket e tarefa (quando um muda, atualiza o outro)
5. Exibir tickets abertos no bloco SUPORTE da Agenda
6. Permitir criar tarefa diretamente do ticket e vincular ao bloco SUPORTE

### Prioridade 2: Sistema de Comentários/Histórico

**Objetivo:** Permitir comunicação e rastreabilidade dentro do ticket

**Ações sugeridas:**
1. Criar tabela `ticket_comments` (id, ticket_id, user_id, message, created_at)
2. Criar `TicketCommentsController` com métodos CRUD
3. Adicionar seção de comentários na tela de detalhes do ticket
4. Criar tabela `ticket_history` para log de mudanças (status, prioridade, etc.)
5. Exibir histórico na tela de detalhes do ticket

### Prioridade 3: Sistema de Anexos

**Objetivo:** Permitir anexar arquivos diretamente ao ticket

**Ações sugeridas:**
1. Criar tabela `ticket_attachments` (similar a `task_attachments`)
2. Criar `TicketAttachmentsController` com upload/download
3. Adicionar seção de anexos na tela de detalhes do ticket
4. Integrar com sistema de storage existente

### Prioridade 4: Melhorias de Interface

**Objetivo:** Melhorar experiência do usuário

**Ações sugeridas:**
1. Criar formulário de edição visual de tickets
2. Adicionar listagem de tickets na tela de detalhes do cliente
3. Adicionar listagem de tickets na tela de detalhes do projeto
4. Adicionar filtros avançados (período, SLA, etc.)
5. Adicionar dashboard de tickets com métricas

---

## Resumo Executivo

### O que está implementado:
- ✅ CRUD básico de tickets
- ✅ Relacionamentos básicos (cliente, projeto, tarefa)
- ✅ Filtros e listagem
- ✅ Integração parcial com tarefas (task_type)
- ✅ Método para buscar tickets abertos

### O que está parcialmente implementado:
- ⚠️ Integração com tarefas (existe campo task_id, mas não há sincronização)
- ⚠️ Integração com agenda (existe método, mas não há interface)
- ⚠️ Tipo de tarefa client_ticket (existe, mas não há vínculo bidirecional)

### O que não existe:
- ❌ Sistema de comentários/histórico
- ❌ Sistema de anexos
- ❌ Sincronização automática ticket ↔ tarefa
- ❌ Interface completa de edição
- ❌ Exibição de tickets na Agenda
- ❌ Relatórios e métricas

### Riscos identificados:
- 🔴 Duplicidade de conceito (Ticket vs Tarefa de Ticket)
- 🔴 Falta de sincronização entre ticket e tarefa
- 🔴 Dados podem ficar inconsistentes
- 🔴 Falta de rastreabilidade

### Recomendação:
**Focar primeiro na integração completa Ticket ↔ Tarefa ↔ Agenda**, pois é o fluxo principal de trabalho. Depois, adicionar comentários e anexos para completar o módulo.

