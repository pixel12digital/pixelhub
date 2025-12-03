# Resumo do Módulo de Tickets - Revisão Completa

**Data da revisão:** 2025-12-01

## Estado Atual do Módulo de Tickets

### Estrutura da Tabela `tickets`

**Campos principais:**
- `id` (PK)
- `tenant_id` (INT UNSIGNED NOT NULL) - **OBRIGATÓRIO** - FK para `tenants.id`
- `project_id` (INT UNSIGNED NULL) - **OPCIONAL** - FK para `projects.id`
- `task_id` (INT UNSIGNED NULL) - FK para `tasks.id` (opcional)
- `titulo` (VARCHAR 200) - Obrigatório
- `descricao` (TEXT) - Opcional
- `prioridade` (ENUM: 'baixa', 'media', 'alta', 'critica') - Padrão: 'media'
- `status` (ENUM: 'aberto', 'em_atendimento', 'aguardando_cliente', 'resolvido', 'cancelado') - Padrão: 'aberto'
- `origem` (ENUM: 'cliente', 'interno', 'whatsapp', 'automatico') - Padrão: 'cliente'
- `prazo_sla` (DATETIME) - Opcional
- `data_resolucao` (DATETIME) - Preenchido automaticamente quando status = 'resolvido' ou 'cancelado'
- `created_by` (INT UNSIGNED NULL) - FK para `users.id`
- `created_at`, `updated_at`

**Índices:**
- `idx_tenant_id`
- `idx_project_id`
- `idx_task_id`
- `idx_status`
- `idx_prioridade`
- `idx_created_by`

**Foreign Keys:**
- `fk_tickets_tenant` → `tenants.id` ON DELETE RESTRICT (não permite deletar tenant com tickets)
- `project_id` → `projects.id` ON DELETE SET NULL
- `task_id` → `tasks.id` ON DELETE SET NULL
- `created_by` → `users.id` ON DELETE SET NULL

### Relacionamentos

1. **Ticket → Cliente (Tenant)** - **OBRIGATÓRIO**
   - `tickets.tenant_id` NOT NULL
   - Relacionamento: `belongsTo` (um ticket pertence a um cliente)
   - Cada ticket DEVE estar vinculado a um cliente

2. **Ticket → Projeto** - **OPCIONAL**
   - `tickets.project_id` NULL permitido
   - Relacionamento: `belongsTo` (um ticket pode pertencer a um projeto, mas não é obrigatório)
   - Usado apenas quando o chamado está claramente ligado a um projeto maior

3. **Ticket → Tarefa** - **OPCIONAL**
   - `tickets.task_id` NULL permitido
   - Relacionamento: `belongsTo` (um ticket pode ter uma tarefa vinculada)

### Componentes do Módulo

#### Controller
- **Arquivo:** `src/Controllers/TicketController.php`
- **Métodos:**
  - `index()` - Lista todos os tickets com filtros
  - `create()` - Exibe formulário de criação (aceita `tenant_id` e `project_id` via GET)
  - `store()` - Cria novo ticket
  - `show()` - Exibe detalhes de um ticket
  - `update()` - Atualiza ticket existente

#### Service
- **Arquivo:** `src/Services/TicketService.php`
- **Métodos principais:**
  - `getAllTickets(array $filters)` - Lista tickets com filtros
  - `findTicket(int $id)` - Busca ticket por ID
  - `createTicket(array $data)` - Cria novo ticket (valida tenant_id obrigatório)
  - `updateTicket(int $id, array $data)` - Atualiza ticket
  - `findOpenTickets(array $filters)` - **NOVO** - Busca tickets abertos para integração com agenda

#### Views
- `views/tickets/index.php` - Lista de tickets
- `views/tickets/create.php` - Formulário de criação
- `views/tickets/show.php` - Detalhes do ticket

### Ajustes Realizados para Alinhar ao Fluxo

#### 1. **tenant_id Obrigatório**
- ✅ Migration criada: `20251201_alter_tickets_make_tenant_required_and_add_cancelado_status.php`
- ✅ Campo `tenant_id` alterado de NULL para NOT NULL
- ✅ Foreign Key alterada para ON DELETE RESTRICT (protege integridade)
- ✅ Validação no `TicketService::createTicket()` para garantir tenant_id obrigatório
- ✅ Campo marcado como `required` no formulário de criação

#### 2. **project_id Opcional**
- ✅ Campo permanece NULL (já estava correto)
- ✅ Removida lógica de criação automática de projeto genérico no `TicketService`
- ✅ Tickets podem existir sem projeto (comportamento desejado)

#### 3. **Status 'cancelado' Adicionado**
- ✅ Migration adiciona 'cancelado' ao ENUM de status
- ✅ Validações atualizadas no `TicketService` para incluir 'cancelado'
- ✅ `data_resolucao` preenchido automaticamente quando status = 'cancelado'

#### 4. **Método findOpenTickets() para Agenda**
- ✅ Método criado no `TicketService`
- ✅ Retorna tickets com status: 'aberto', 'em_atendimento', 'aguardando_cliente'
- ✅ Ordenado por prioridade (critica → baixa) e data de criação
- ✅ Pronto para integração com bloco SUPORTE da agenda

#### 5. **Pontos de Entrada para Criação de Tickets**

**Na tela de Clientes:**
- ✅ Botão "🎫 Novo Ticket" adicionado na view `tenants/view.php`
- ✅ Redireciona para `/tickets/create?tenant_id={id}` com cliente pré-selecionado

**Na tela de Projetos:**
- ✅ Botão "🎫 Abrir ticket" adicionado na view `projects/index.php`
- ✅ Aparece apenas quando projeto tem `tenant_id` (cliente associado)
- ✅ Redireciona para `/tickets/create?project_id={id}&tenant_id={tenant_id}` com ambos pré-selecionados

**Formulário de Criação:**
- ✅ Aceita `tenant_id` e `project_id` via GET
- ✅ Campos pré-selecionados quando fornecidos
- ✅ Campo `tenant_id` marcado como obrigatório (required)

#### 6. **Documentação Adicionada**

**TicketController:**
- ✅ Comentário explicando fluxo Tickets vs Projetos
- ✅ Documentação sobre quando usar tickets vs projetos

**ProjectController:**
- ✅ Comentário explicando fluxo Projetos vs Tickets
- ✅ Documentação sobre não criar projetos para chamados de suporte

**TicketService:**
- ✅ Comentário no topo da classe explicando fluxo de negócio
- ✅ Documentação sobre relacionamentos e uso

### Fluxo de Negócio Definido

**Projetos:**
- Coisas grandes e recorrentes (ex: desenvolvimento de site, migração, etc.)
- Podem ser internos (sem tenant_id) ou de cliente (com tenant_id)
- Podem ter múltiplas tarefas vinculadas

**Tickets:**
- Chamados pontuais de suporte vinculados ao cliente
- **SEMPRE** vinculados a um cliente (tenant_id obrigatório)
- **OPCIONALMENTE** vinculados a um projeto (project_id opcional)
- Podem existir sem projeto (não criar projetos genéricos para tickets)
- Trabalhados no bloco SUPORTE da agenda

### Integração com Agenda (Preparação Estrutural)

O módulo está preparado para integração com o bloco SUPORTE da agenda:

```php
// Exemplo de uso futuro:
$openTickets = TicketService::findOpenTickets([
    'tenant_id' => $tenantId, // opcional
    'prioridade' => 'alta'     // opcional
]);
```

O método `findOpenTickets()` retorna tickets com status abertos, ordenados por prioridade, prontos para serem exibidos no bloco SUPORTE da agenda.

### Migrations Criadas

1. **20251201_alter_tickets_make_tenant_required_and_add_cancelado_status.php**
   - Torna `tenant_id` NOT NULL
   - Adiciona status 'cancelado' ao ENUM
   - Ajusta Foreign Key para ON DELETE RESTRICT

### Próximos Passos (Opcional)

1. Integrar `findOpenTickets()` com o bloco SUPORTE da agenda
2. Adicionar filtros de tickets na view de detalhes do cliente
3. Adicionar listagem de tickets relacionados na view de projetos
4. Considerar adicionar comentários/respostas aos tickets (futuro)

---

**Resumo Executivo:**

O módulo de tickets está agora alinhado ao fluxo de negócio:
- ✅ Tickets sempre vinculados a cliente (tenant_id obrigatório)
- ✅ Projeto opcional (project_id nullable)
- ✅ Sem criação automática de projetos genéricos
- ✅ Pontos de entrada claros (botões nas telas de cliente e projeto)
- ✅ Preparado para integração com agenda (método findOpenTickets)
- ✅ Documentação completa sobre fluxo Tickets vs Projetos








