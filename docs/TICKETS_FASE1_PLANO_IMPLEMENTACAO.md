# Plano de Implementação - Tickets Fase 1

**Data:** 2025-01-25  
**Objetivo:** Melhorar integração Ticket ↔ Tarefa ↔ Agenda e UX básica

## 1. Mapeamento do Estado Atual

### URLs e Views Existentes

**Tickets:**
- `/tickets` → `views/tickets/index.php` - Listagem de tickets
- `/tickets/create` → `views/tickets/create.php` - Formulário de criação
- `/tickets/show?id=X` → `views/tickets/show.php` - Detalhes do ticket

**Rotas configuradas:**
- `GET /tickets` → `TicketController@index`
- `GET /tickets/create` → `TicketController@create`
- `POST /tickets/store` → `TicketController@store`
- `GET /tickets/show` → `TicketController@show`
- `POST /tickets/update` → `TicketController@update`

### Relacionamentos Atuais

**Ticket → Tarefa:**
- Campo `tickets.task_id` (FK para `tasks.id`) - OPCIONAL
- Relacionamento unidirecional (ticket pode ter tarefa, mas tarefa não tem `ticket_id`)
- Tarefa pode ter `task_type = 'client_ticket'` para indicar que é de ticket

**Ticket → Agenda:**
- Método `AgendaService::getTaskBlockType()` verifica `task_type = 'client_ticket'`
- Tarefas de ticket são direcionadas para blocos CLIENTES/SUPORTE baseado em prioridade
- Método `TicketService::findOpenTickets()` existe mas não é usado na interface

### Arquivos que Serão Alterados/Adicionados

**Alterados:**
1. `src/Services/TicketService.php` - Adicionar métodos de vínculo e criação de tarefa
2. `src/Controllers/TicketController.php` - Adicionar métodos `edit()` e `createTaskFromTicket()`
3. `views/tickets/show.php` - Adicionar seção de relacionamentos, botão criar tarefa, seção de anexos
4. `views/tickets/create.php` - Adaptar para modo edição
5. `views/tasks/board.php` - Adicionar botão "Novo Ticket"
6. `views/tasks/_task_card.php` - Melhorar badge de ticket com link
7. `src/Controllers/TaskBoardController.php` - Adicionar sincronização de status
8. `public/index.php` - Adicionar novas rotas

**Adicionados:**
1. `docs/TICKETS_FASE1_MANUAL_USO.md` - Manual do usuário

## 2. Implementações Planejadas

### Tarefa 1: ✅ Documentação e Mapeamento
- [x] Ler auditoria completa
- [x] Confirmar estrutura atual
- [x] Criar este documento de plano

### Tarefa 2: Fluxo Ticket ↔ Tarefa
- [ ] Criar `TicketService::linkTaskToTicket()`
- [ ] Criar `TicketService::createTaskFromTicket()`
- [ ] Adicionar botão "Criar/Abrir tarefa" em `views/tickets/show.php`
- [ ] Criar rota e método `TicketController::createTaskFromTicket()`

### Tarefa 3: Sincronização de Status
- [ ] Criar `TicketService::markTicketResolvedFromTask()`
- [ ] Criar `TicketService::syncTaskFromTicketStatus()`
- [ ] Integrar no `TaskBoardController::updateTaskStatus()`
- [ ] Integrar no `TicketController::update()`

### Tarefa 4: Edição de Tickets
- [ ] Criar método `TicketController::edit()`
- [ ] Adaptar `views/tickets/create.php` para modo edição
- [ ] Adicionar rota `GET /tickets/edit`
- [ ] Adicionar botão "Editar" em `views/tickets/show.php`

### Tarefa 5: Pontos de Entrada
- [ ] Verificar/ajustar botão em `views/tenants/view.php`
- [ ] Verificar/ajustar botão em `views/projects/index.php`
- [ ] Adicionar botão "Novo Ticket" em `views/tasks/board.php`

### Tarefa 6: Anexos (via Tarefa)
- [ ] Criar `TicketService::getAttachmentsForTicket()`
- [ ] Adicionar seção de anexos em `views/tickets/show.php`

### Tarefa 7: Ajustes Visuais
- [ ] Melhorar badge de ticket em `views/tasks/_task_card.php`
- [ ] Adicionar links diretos para tickets

### Tarefa 8: Documentação Final
- [ ] Criar `docs/TICKETS_FASE1_MANUAL_USO.md`
- [ ] Atualizar este documento com implementações concluídas

## 3. Implementações Concluídas

### ✅ Tarefa 1: Documentação e Mapeamento
- [x] Lida auditoria completa
- [x] Confirmada estrutura atual
- [x] Criado documento de plano

### ✅ Tarefa 2: Fluxo Ticket ↔ Tarefa
- [x] Criado `TicketService::linkTaskToTicket()` - vincula tarefa existente a ticket
- [x] Criado `TicketService::createTaskFromTicket()` - cria tarefa a partir de ticket
- [x] Criado `TicketService::findTicketsByTaskId()` - busca tickets por task_id
- [x] Adicionado botão "Criar/Abrir tarefa" em `views/tickets/show.php`
- [x] Criado método `TicketController::createTaskFromTicket()`
- [x] Adicionada rota `POST /tickets/create-task`

### ✅ Tarefa 3: Sincronização de Status
- [x] Criado `TicketService::markTicketResolvedFromTask()` - marca ticket como resolvido quando tarefa é concluída
- [x] Criado `TicketService::syncTaskFromTicketStatus()` - sincroniza tarefa quando ticket é resolvido/cancelado
- [x] Integrado no `TaskBoardController::updateTaskStatus()` - sincroniza ao concluir tarefa
- [x] Integrado no `TicketController::update()` - sincroniza ao resolver/cancelar ticket
- [x] Funciona tanto no Quadro quanto na Agenda (ambos usam o mesmo endpoint)

### ✅ Tarefa 4: Edição de Tickets
- [x] Criado método `TicketController::edit()` - exibe formulário de edição
- [x] Adaptado `views/tickets/create.php` para suportar modo edição
- [x] Adicionada rota `GET /tickets/edit`
- [x] Adicionado botão "Editar Ticket" em `views/tickets/show.php`
- [x] Campo cliente não pode ser alterado (disabled no formulário de edição)
- [x] Campo status disponível apenas na edição

### ✅ Tarefa 5: Pontos de Entrada
- [x] Verificado botão em `views/tenants/view.php` - já existe e está funcional
- [x] Verificado botão em `views/projects/index.php` - já existe e está funcional
- [x] Adicionado botão "🎫 Novo Ticket" em `views/tasks/board.php`
- [x] Botão no board pré-seleciona project_id e tenant_id quando aplicável

### ✅ Tarefa 6: Anexos (via Tarefa)
- [x] Criado `TicketService::getAttachmentsForTicket()` - busca anexos via tarefa vinculada
- [x] Adicionada seção "Anexos" em `views/tickets/show.php`
- [x] Lista anexos quando ticket tem task_id
- [x] Mostra mensagem quando ticket não tem task_id
- [x] Link "Gerenciar Anexos na Tarefa" quando há task_id

### ✅ Tarefa 7: Ajustes Visuais
- [x] Adicionada seção "Tickets Relacionados" no modal de detalhes da tarefa
- [x] Badge `[TCK]` já existe no card da tarefa (mantido)
- [x] Links diretos para tickets no modal de detalhes da tarefa
- [x] Seção "Relacionamentos" melhorada em `views/tickets/show.php` com links clicáveis

### ✅ Tarefa 8: Documentação Final
- [x] Criado `docs/TICKETS_FASE1_MANUAL_USO.md` - manual completo do usuário
- [x] Atualizado este documento com implementações concluídas

## 4. Testes Manuais

### Teste 1: Criar Ticket e Tarefa
1. ✅ Criar ticket via `/tickets/create?tenant_id=X`
2. ✅ Abrir ticket e criar tarefa
3. ✅ Verificar que tarefa foi criada com título `[Ticket #ID] ...`
4. ✅ Verificar que tarefa tem `task_type = 'client_ticket'`
5. ✅ Verificar que ticket tem `task_id` preenchido

### Teste 2: Sincronização de Status
1. ✅ Concluir tarefa no Quadro → Verificar que ticket foi marcado como resolvido
2. ✅ Resolver ticket → Verificar que tarefa foi marcada como concluída
3. ✅ Alterar status na Agenda → Verificar sincronização

### Teste 3: Anexos
1. ✅ Criar ticket e tarefa
2. ✅ Anexar arquivo na tarefa
3. ✅ Verificar que anexo aparece na tela do ticket

### Teste 4: Edição
1. ✅ Editar ticket e alterar prioridade/status
2. ✅ Verificar que alterações foram salvas
3. ✅ Verificar que cliente não pode ser alterado

### Teste 5: Pontos de Entrada
1. ✅ Verificar botão em tela de cliente
2. ✅ Verificar botão em tela de projetos
3. ✅ Verificar botão no Quadro de Tarefas

## 5. Arquivos Modificados

### Services
- `src/Services/TicketService.php` - Adicionados 6 novos métodos

### Controllers
- `src/Controllers/TicketController.php` - Adicionados 2 novos métodos
- `src/Controllers/TaskBoardController.php` - Adicionada sincronização de status

### Views
- `views/tickets/show.php` - Adicionadas seções de relacionamentos, anexos e botões
- `views/tickets/create.php` - Adaptado para modo edição
- `views/tasks/board.php` - Adicionado botão "Novo Ticket" e seção de tickets no modal
- `views/tasks/_task_card.php` - Mantido badge `[TCK]` (sem alterações)

### Rotas
- `public/index.php` - Adicionadas 2 novas rotas

### Documentação
- `docs/TICKETS_FASE1_PLANO_IMPLEMENTACAO.md` - Criado
- `docs/TICKETS_FASE1_MANUAL_USO.md` - Criado

## 6. Observações Técnicas

### Sincronização de Status

A sincronização funciona da seguinte forma:

1. **Tarefa → Ticket:**
   - Quando tarefa muda para `concluida`
   - Se ticket está em `aberto`, `em_atendimento` ou `aguardando_cliente`
   - Ticket é marcado como `resolvido` e `data_resolucao` é preenchida

2. **Ticket → Tarefa:**
   - Quando ticket muda para `resolvido` ou `cancelado`
   - Se ticket tem `task_id`
   - Tarefa é marcada como `concluida` e `completed_at` é preenchido

### Criação de Tarefa

- Requer que ticket tenha `project_id` (tarefas precisam de projeto)
- Título da tarefa: `[Ticket #ID] {título do ticket}`
- Status inicial: `em_andamento`
- Tipo: `client_ticket`

### Anexos

- Tickets não têm sistema próprio de anexos
- Anexos são gerenciados através da tarefa vinculada
- Se ticket não tem tarefa, mostra mensagem orientando a criar tarefa

## 7. Fase 2 — Integração Visual com Agenda e Fluxo Tarefa → Ticket

### ✅ Tarefa 1: Tickets visíveis na Agenda
- [x] Adicionado badge de ticket nas listas de tarefas do modo de trabalho do bloco (`views/agenda/show.php`)
- [x] Badge exibe `🎫 TCK-#ID` com link direto para o ticket
- [x] Tooltip mostra título e status do ticket ao passar o mouse

### ✅ Tarefa 1.2: Blocos de Agenda relacionados na tela do ticket
- [x] Criada seção "Blocos de Agenda relacionados" em `views/tickets/show.php`
- [x] Lista todos os blocos onde a tarefa vinculada está agendada
- [x] Exibe data, horário, tipo de bloco e status
- [x] Link "Abrir bloco" para cada bloco relacionado
- [x] Mensagem amigável quando não há blocos agendados
- [x] Botão "Agendar tarefa na Agenda" quando há tarefa mas não há blocos

### ✅ Tarefa 2: Criar ticket a partir de tarefa
- [x] Adicionado botão "Criar ticket a partir desta tarefa" no modal de detalhes da tarefa
- [x] Criado método `TicketController::createFromTask()`
- [x] Criada rota `GET /tickets/create-from-task`
- [x] Formulário pré-preenchido com dados da tarefa (título sugerido: `[Suporte] {título}`, descrição com contexto)
- [x] Após criar, ticket é automaticamente vinculado à tarefa via `TicketService::linkTaskToTicket()`
- [x] Redireciona para a tela do ticket criado

### ✅ Tarefa 3: Atalho para agendar ticket
- [x] Botão "Agendar tarefa na Agenda" na seção de blocos relacionados
- [x] Aparece quando ticket tem tarefa mas não está em nenhum bloco
- [x] Link para o quadro de tarefas com `task_id` (abre modal de agendamento)

### ✅ Tarefa 4: Melhorias de UX e sinalização
- [x] Badge de ticket na agenda melhorado: `🎫 TCK-#ID` com tooltip informativo
- [x] Consistência visual entre Agenda e Quadro
- [x] Links diretos para tickets em todas as visualizações

### Arquivos Modificados na Fase 2

**Services:**
- Nenhum (reutilizou métodos da Fase 1)

**Controllers:**
- `src/Controllers/TicketController.php` - Adicionado `createFromTask()` e modificado `show()` e `store()`

**Views:**
- `views/agenda/show.php` - Adicionado badge de ticket nas tarefas
- `views/tickets/show.php` - Adicionada seção de blocos relacionados
- `views/tickets/create.php` - Adaptado para criar a partir de tarefa
- `views/tasks/board.php` - Adicionado botão de criar ticket no modal

**Rotas:**
- `public/index.php` - Adicionada rota `GET /tickets/create-from-task`

## 8. Próximos Passos (Futuro)

### Fase 3 (Sugerido)
- Sistema de comentários/mensagens nos tickets
- Histórico de mudanças
- Anexos diretos aos tickets (sem depender de tarefa)
- Notificações

### Melhorias de UX
- Dashboard de tickets com métricas
- Filtros avançados
- Listagem de tickets na tela do cliente
- Listagem de tickets na tela do projeto

