# 📅 AGENDA + PRODUTIVIDADE + TICKETS - HUB PIXEL12

**Data de Implementação:** 2025-02-01  
**Status:** ✅ Implementado e em Produção  
**Última Atualização:** 2025-12-03

---

## 📋 SUMÁRIO EXECUTIVO

Este documento descreve a implementação completa do módulo de **Agenda + Produtividade + Tickets** no HUB Pixel12, integrado com Projetos, Tarefas, Financeiro e preparado para CRM futuro.

**Objetivo Principal:** Transformar o HUB Pixel12 no núcleo central de gestão de agenda, produtividade e tickets, com integração total entre projetos, tarefas, agenda, tickets e financeiro.

---

## 🎯 CONTEXTO E MOTIVAÇÃO

### Problema Original

Antes da implementação, o sistema tinha:
- ✅ Projetos e Tarefas (Kanban)
- ✅ Financeiro (integração com Asaas)
- ✅ Clientes (tenants)
- ❌ **Sem sistema de agenda/calendário**
- ❌ **Sem módulo de tickets**
- ❌ **Sem relatórios de produtividade**
- ❌ **Sem integração automática entre tarefas e agenda**
- ❌ **Sem visão de disponibilidade para novos projetos**

### Solução Implementada

Sistema completo de **Agenda baseada em blocos de tempo**, totalmente integrado com:
- Projetos e Tarefas (vinculação automática)
- Tickets de suporte (criação automática de tarefas)
- Financeiro (geração automática de tarefas de inadimplência)
- Relatórios de produtividade (semanal e mensal)
- Cálculo de disponibilidade para novos projetos e suporte

---

## 🗄️ ESTRUTURA DO BANCO DE DADOS

### Tabelas Criadas

#### 1. `agenda_block_types`

Define os tipos de blocos de agenda.

**Campos:**
- `id` (INT UNSIGNED, PK)
- `nome` (VARCHAR 120) - Nome do tipo (ex: "FUTURE", "CLIENTES")
- `codigo` (VARCHAR 30, UNIQUE) - Código único para referência
- `cor_hex` (VARCHAR 10, NULL) - Cor hexadecimal para exibição
- `descricao` (TEXT, NULL)
- `ativo` (TINYINT(1), DEFAULT 1)
- `created_at`, `updated_at` (DATETIME)

**Tipos pré-cadastrados:**
- **FUTURE** (#4CAF50) - Produtos/sistemas internos escaláveis
- **CLIENTES** (#2196F3) - Projetos de clientes
- **COMERCIAL** (#FF9800) - Vendas, criativos, tráfego
- **SUPORTE** (#9C27B0) - Dúvidas rápidas e micro-ajustes
- **ADMIN** (#F44336) - Financeiro, contabilidade, planejamento
- **PESSOAL** (#00BCD4) - Caminhada, família, natação, etc.
- **FLEX** (#795548) - Bloco coringa para comercial/admin/financeiro pesado

**Migration:** `20250201_01_create_agenda_block_types_table.php`

---

#### 2. `agenda_block_templates`

Define o template semanal de blocos (segunda a sexta).

**Campos:**
- `id` (INT UNSIGNED, PK)
- `dia_semana` (TINYINT) - 1=Segunda, 2=Terça, 3=Quarta, 4=Quinta, 5=Sexta, 6=Sábado, 7=Domingo
- `hora_inicio` (TIME)
- `hora_fim` (TIME)
- `tipo_id` (INT UNSIGNED, FK → agenda_block_types.id)
- `descricao_padrao` (VARCHAR 255, NULL)
- `ativo` (TINYINT(1), DEFAULT 1)
- `created_at`, `updated_at` (DATETIME)

**Template padrão (Segunda a Sexta):**
- 07:00-09:00 → FUTURE
- 09:00-10:00 → CLIENTES (Atendimento / Leads, triagem)
- 10:15-11:30 → FUTURE
- 11:30-12:00 → COMERCIAL (leve)
- 13:00-14:30 → CLIENTES (entrega pesada)
- 14:30-16:00 → COMERCIAL (forte) / **FLEX (quarta-feira)**
- 16:15-17:30 → SUPORTE
- 17:30-18:00 → ADMIN

**Migration:** `20250201_02_create_agenda_block_templates_table.php`

---

#### 3. `agenda_blocks`

Instâncias diárias de blocos baseadas no template.

**Campos:**
- `id` (INT UNSIGNED, PK)
- `data` (DATE)
- `hora_inicio` (TIME)
- `hora_fim` (TIME)
- `tipo_id` (INT UNSIGNED, FK → agenda_block_types.id)
- `status` (ENUM: 'planned', 'ongoing', 'completed', 'partial', 'canceled', DEFAULT 'planned')
- `motivo_cancelamento` (VARCHAR 255, NULL)
- `resumo` (TEXT, NULL)
- `projeto_foco_id` (INT UNSIGNED, NULL, FK → projects.id)
- `duracao_planejada` (INT) - Duração em minutos
- `duracao_real` (INT, NULL) - Duração real em minutos
- `created_at`, `updated_at` (DATETIME)

**Constraints:**
- UNIQUE KEY `unique_block_datetime` (data, hora_inicio, hora_fim) - Evita blocos duplicados
- FOREIGN KEY `tipo_id` → `agenda_block_types(id)` ON DELETE RESTRICT
- FOREIGN KEY `projeto_foco_id` → `projects(id)` ON DELETE SET NULL

**Migration:** `20250201_03_create_agenda_blocks_table.php`

---

#### 4. `agenda_block_tasks`

Relaciona blocos de agenda com tarefas.

**Campos:**
- `id` (INT UNSIGNED, PK)
- `bloco_id` (INT UNSIGNED, FK → agenda_blocks.id, ON DELETE CASCADE)
- `task_id` (INT UNSIGNED, FK → tasks.id, ON DELETE CASCADE)
- `created_at` (DATETIME)

**Constraints:**
- UNIQUE KEY `unique_block_task` (bloco_id, task_id) - Evita duplicação
- FOREIGN KEY `bloco_id` → `agenda_blocks(id)` ON DELETE CASCADE
- FOREIGN KEY `task_id` → `tasks(id)` ON DELETE CASCADE

**Migration:** `20250201_04_create_agenda_block_tasks_table.php`

---

#### 5. `tickets`

Módulo de suporte/tickets.

**Campos:**
- `id` (INT UNSIGNED, PK)
- `tenant_id` (INT UNSIGNED, NULL, FK → tenants.id)
- `project_id` (INT UNSIGNED, NULL, FK → projects.id)
- `task_id` (INT UNSIGNED, NULL, FK → tasks.id)
- `titulo` (VARCHAR 200)
- `descricao` (TEXT, NULL)
- `prioridade` (ENUM: 'baixa', 'media', 'alta', 'critica', DEFAULT 'media')
- `status` (ENUM: 'aberto', 'em_atendimento', 'aguardando_cliente', 'resolvido', DEFAULT 'aberto')
- `origem` (ENUM: 'cliente', 'interno', 'whatsapp', 'automatico', DEFAULT 'cliente')
- `prazo_sla` (DATETIME, NULL)
- `data_resolucao` (DATETIME, NULL)
- `created_by` (INT UNSIGNED, NULL, FK → users.id)
- `created_at`, `updated_at` (DATETIME)

**Migration:** `20250201_05_create_tickets_table.php`

---

## 🔧 ARQUITETURA E SERVIÇOS

### Estrutura de Arquivos

```
src/
├── Controllers/
│   ├── AgendaController.php          ✅ Criado
│   └── TicketController.php          ✅ Criado
├── Services/
│   ├── AgendaService.php             ✅ Criado
│   ├── TicketService.php             ✅ Criado
│   ├── FinancialTaskService.php      ✅ Criado
│   ├── TaskService.php               🔄 Modificado (integração com agenda)
│   └── ProjectService.php            ✅ Existente (usado)
└── Core/
    ├── DB.php                         ✅ Existente
    ├── Auth.php                      ✅ Existente
    └── Controller.php                ✅ Existente

views/
├── agenda/
│   ├── index.php                     ✅ Criado (visão diária)
│   ├── semana.php                    ✅ Criado (visão semanal)
│   ├── show.php                      ✅ Criado (modo de trabalho do bloco)
│   ├── edit_block.php                ✅ Criado (edição de bloco)
│   ├── create_block.php              ✅ Criado (criação de bloco extra)
│   ├── weekly_report.php             ✅ Criado
│   └── monthly_report.php            ✅ Criado
└── tickets/
    ├── index.php                     ✅ Criado (listagem)
    ├── create.php                    ✅ Criado (formulário)
    └── show.php                      ✅ Criado (detalhes)

database/migrations/
├── 20250201_01_create_agenda_block_types_table.php        ✅ Criado
├── 20250201_02_create_agenda_block_templates_table.php   ✅ Criado
├── 20250201_03_create_agenda_blocks_table.php            ✅ Criado
├── 20250201_04_create_agenda_block_tasks_table.php       ✅ Criado
└── 20250201_05_create_tickets_table.php                  ✅ Criado
```

---

### Serviços Implementados

#### 1. `AgendaService`

**Localização:** `src/Services/AgendaService.php`

**Responsabilidades:**
- Gerenciar blocos de agenda
- Gerar blocos diários a partir do template
- Vincular tarefas a blocos
- Calcular disponibilidade
- Gerar relatórios de produtividade

**Principais métodos:**

| Método | Descrição | Retorno |
|--------|-----------|---------|
| `generateDailyBlocks(\DateTime $data)` | Gera blocos do dia a partir do template | `int` (quantidade criada) |
| `getBlocksByDate($data)` | Busca blocos de uma data específica | `array` |
| `getBlocksForPeriod(\DateTimeInterface $dataInicio, \DateTimeInterface $dataFim)` | Busca blocos de um período (agrupados por data) | `array<string, array>` |
| `getBlockById(int $id)` | Busca um bloco por ID com informações completas | `array\|null` |
| `findBlock(int $id)` | Alias para getBlockById (compatibilidade) | `array\|null` |
| `getTasksByBlock(int $blocoId)` | Busca tarefas vinculadas a um bloco | `array` |
| `linkTaskToBlock(int $blocoId, int $taskId)` | Vincula uma tarefa a um bloco | `bool` |
| `updateBlockStatus(int $id, string $status, array $data)` | Atualiza status de um bloco | `bool` |
| `updateBlock(int $id, array $dados)` | Atualiza dados de um bloco (horário, tipo) | `void` |
| `createManualBlock(\DateTime $data, array $dados)` | Cria um bloco manual extra (não baseado em template) | `int` (ID do bloco) |
| `findNextAvailableBlock(string $tipoCodigo)` | Busca próximo bloco disponível | `array\|null` |
| `determineBlockTypeForTask(array $task, ?array $project)` | Determina tipo de bloco para uma tarefa | `string` |
| `getWeeklyReport(\DateTime $dataInicio)` | Relatório semanal de produtividade | `array` |
| `getMonthlyReport(int $ano, int $mes)` | Relatório mensal de produtividade | `array` |
| `getAvailabilityForNewProject(int $blocosNecessarios)` | Disponibilidade para novos projetos | `array` |
| `getAvailabilityForSupport()` | Disponibilidade para suporte | `array` |

---

#### 2. `TicketService`

**Localização:** `src/Services/TicketService.php`

**Responsabilidades:**
- Gerenciar tickets de suporte
- Criar tarefas automaticamente ao criar ticket
- Vincular tickets a blocos automaticamente

**Principais métodos:**

| Método | Descrição | Retorno |
|--------|-----------|---------|
| `getAllTickets(array $filters)` | Lista todos os tickets com filtros | `array` |
| `findTicket(int $id)` | Busca um ticket por ID | `array\|null` |
| `createTicket(array $data)` | Cria ticket + tarefa + vincula a bloco | `int` (ID do ticket) |
| `updateTicket(int $id, array $data)` | Atualiza um ticket existente | `bool` |

**Integração automática:**
- Ao criar um ticket, automaticamente:
  1. Cria o ticket
  2. Cria uma tarefa relacionada (`task_type = 'client_ticket'`)
  3. Determina o tipo de bloco adequado (SUPORTE ou CLIENTES)
  4. Vincula a tarefa ao bloco automaticamente

---

#### 3. `FinancialTaskService`

**Localização:** `src/Services/FinancialTaskService.php`

**Responsabilidades:**
- Gerar tarefas automáticas para inadimplentes
- Criar projetos financeiros quando necessário
- Vincular tarefas a blocos ADMIN/FLEX

**Principais métodos:**

| Método | Descrição | Retorno |
|--------|-----------|---------|
| `generateTasksForOverdue(int $diasAtrasoMinimo)` | Gera tarefas para inadimplentes | `array` ['created' => int, 'skipped' => int] |

**Funcionamento:**
1. Identifica tenants com faturas vencidas há mais de X dias
2. Verifica se já existe tarefa recente (evita duplicação)
3. Cria projeto financeiro se não existir
4. Cria tarefa do tipo `finance_overdue`
5. Vincula tarefa a bloco ADMIN ou FLEX

---

## 🎮 CONTROLLERS E ROTAS

### AgendaController

**Localização:** `src/Controllers/AgendaController.php`

**Rotas registradas em `public/index.php`:**

| Método | Rota | Método Controller | Descrição |
|--------|------|-------------------|-----------|
| GET | `/agenda` | `index()` | Exibe a agenda (visão diária) |
| GET | `/agenda/semana` | `semana()` | Exibe a agenda (visão semanal) |
| GET | `/agenda/bloco` | `show()` | Exibe modo de trabalho de um bloco |
| GET | `/agenda/bloco/editar` | `editBlock()` | Exibe formulário de edição de bloco |
| POST | `/agenda/bloco/editar` | `updateBlock()` | Atualiza um bloco existente |
| GET | `/agenda/bloco/novo` | `createBlock()` | Exibe formulário de criação de bloco extra |
| POST | `/agenda/bloco/novo` | `storeBlock()` | Cria um bloco extra manual |
| POST | `/agenda/start` | `start()` | Inicia um bloco (status = ongoing) |
| POST | `/agenda/finish` | `finish()` | Finaliza um bloco (status = completed ou partial) |
| POST | `/agenda/cancel` | `cancel()` | Cancela um bloco |
| POST | `/agenda/update-project-focus` | `updateProjectFocus()` | Atualiza projeto foco de um bloco |
| POST | `/agenda/generate-blocks` | `generateBlocks()` | Gera blocos do dia manualmente |
| GET | `/agenda/weekly-report` | `weeklyReport()` | Relatório semanal de produtividade |
| GET | `/agenda/monthly-report` | `monthlyReport()` | Relatório mensal de produtividade |

**Autenticação:** Todas as rotas exigem `Auth::requireInternal()`

---

### TicketController

**Localização:** `src/Controllers/TicketController.php`

**Rotas registradas em `public/index.php`:**

| Método | Rota | Método Controller | Descrição |
|--------|------|-------------------|-----------|
| GET | `/tickets` | `index()` | Lista todos os tickets |
| GET | `/tickets/create` | `create()` | Exibe formulário de criação de ticket |
| POST | `/tickets/store` | `store()` | Cria um novo ticket |
| GET | `/tickets/show` | `show()` | Exibe detalhes de um ticket |
| POST | `/tickets/update` | `update()` | Atualiza um ticket |

**Autenticação:** Todas as rotas exigem `Auth::requireInternal()`

---

## 🔄 INTEGRAÇÕES AUTOMÁTICAS

### 1. Tarefas → Blocos

**Quando:** Ao criar uma nova tarefa via `TaskService::createTask()`

**Fluxo:**
1. Tarefa é criada no banco
2. Sistema determina o tipo de bloco adequado:
   - `task_type = 'internal'` + `project.type = 'interno'` → **FUTURE**
   - `task_type = 'client_ticket'` + prioridade baixa/média → **SUPORTE**
   - `task_type = 'client_ticket'` + prioridade alta/crítica → **CLIENTES**
   - `project.type = 'cliente'` → **CLIENTES**
   - Tarefa financeira → **ADMIN**
3. Busca próximo bloco disponível daquele tipo
4. Vincula automaticamente a tarefa ao bloco

**Código:** `src/Services/TaskService.php` (linha ~234)

---

### 2. Tickets → Tarefas → Blocos

**Quando:** Ao criar um novo ticket via `TicketService::createTicket()`

**Fluxo:**
1. Ticket é criado no banco
2. Tarefa relacionada é criada automaticamente:
   - `task_type = 'client_ticket'`
   - `title` = título do ticket
   - `description` = descrição do ticket
   - `project_id` = projeto do ticket (ou projeto genérico criado)
3. Determina tipo de bloco:
   - Prioridade baixa/média → **SUPORTE**
   - Prioridade alta/crítica → **CLIENTES**
4. Vincula tarefa ao bloco automaticamente

**Código:** `src/Services/TicketService.php` (método `createTicket()`)

---

### 3. Inadimplência → Tarefas Financeiras → Blocos

**Quando:** Ao executar `FinancialTaskService::generateTasksForOverdue()`

**Fluxo:**
1. Identifica tenants com faturas vencidas há mais de X dias
2. Para cada tenant:
   - Verifica se já existe tarefa recente (evita duplicação)
   - Busca ou cria projeto financeiro
   - Cria tarefa do tipo `finance_overdue`
   - Vincula tarefa a bloco ADMIN ou FLEX

**Uso:**
```php
use PixelHub\Services\FinancialTaskService;

// Gerar tarefas para inadimplentes (faturas vencidas há mais de 7 dias)
$result = FinancialTaskService::generateTasksForOverdue(7);
// Retorna: ['created' => int, 'skipped' => int]
```

**Código:** `src/Services/FinancialTaskService.php`

---

## 📊 RELATÓRIOS DE PRODUTIVIDADE

### Relatório Semanal

**Rota:** `GET /agenda/weekly-report?data={data}`

**Indicadores:**
- **Horas por tipo de bloco:**
  - Total de blocos por tipo
  - Blocos concluídos vs parciais vs cancelados
  - Minutos/horas totais por tipo
- **Tarefas concluídas por tipo de bloco:**
  - Quantidade de tarefas concluídas vinculadas a blocos
- **Horas por projeto:**
  - Quando `projeto_foco_id` está preenchido
  - Agrupado por projeto
- **Blocos cancelados:**
  - Lista de blocos cancelados com motivos

**View:** `views/agenda/weekly_report.php`

---

### Relatório Mensal

**Rota:** `GET /agenda/monthly-report?ano={ano}&mes={mes}`

**Indicadores:** Mesmos do relatório semanal, agregados por mês

**View:** `views/agenda/monthly_report.php`

---

## 🎯 DISPONIBILIDADE PARA NOVOS PROJETOS E SUPORTE

### Disponibilidade para Novos Projetos

**Método:** `AgendaService::getAvailabilityForNewProject(int $blocosNecessarios = 10)`

**Retorna:**
```php
[
    'proxima_janela' => [
        'data' => '2025-02-05',
        'hora' => '09:00:00'
    ],
    'blocos_disponiveis' => 15,
    'ritmo_atual' => 8, // blocos por semana
    'semanas_estimadas' => 2 // semanas para conclusão
]
```

**Uso:** Pode ser chamado antes de aceitar um novo projeto para estimar prazo de entrega.

---

### Disponibilidade para Suporte

**Método:** `AgendaService::getAvailabilityForSupport()`

**Retorna:**
```php
[
    'proximo_bloco' => [
        'data' => '2025-02-01',
        'hora' => '16:15:00'
    ]
]
```

**Uso:** Pode ser exibido ao criar um ticket para informar quando o suporte estará disponível.

---

## 🚀 FLUXOS PRINCIPAIS

### Fluxo 1: Criar Tarefa

```
1. Usuário cria tarefa no Kanban
   ↓
2. TaskService::createTask() é chamado
   ↓
3. Tarefa é salva no banco
   ↓
4. Sistema determina tipo de bloco adequado
   ↓
5. Busca próximo bloco disponível
   ↓
6. Vincula tarefa ao bloco automaticamente
   ↓
7. Tarefa aparece no bloco quando usuário abrir
```

---

### Fluxo 2: Criar Ticket

```
1. Usuário cria ticket
   ↓
2. TicketService::createTicket() é chamado
   ↓
3. Ticket é salvo no banco
   ↓
4. Tarefa relacionada é criada automaticamente
   ↓
5. Tarefa é vinculada a bloco (SUPORTE ou CLIENTES)
   ↓
6. Ticket e tarefa aparecem nas respectivas telas
```

---

### Fluxo 3: Trabalhar com Bloco

```
1. Usuário acessa /agenda
   ↓
2. Sistema gera blocos do dia automaticamente (se não existirem)
   ↓
3. Usuário clica em "Abrir Bloco"
   ↓
4. Visualiza tarefas vinculadas ao bloco
   ↓
5. Clica em "Iniciar Bloco" → status = ongoing
   ↓
6. Trabalha nas tarefas
   ↓
7. Clica em "Finalizar Bloco" → preenche resumo e duração real → status = completed
```

---

### Fluxo 4: Gerar Tarefas de Inadimplência

```
1. Sistema executa FinancialTaskService::generateTasksForOverdue(7)
   ↓
2. Identifica tenants com faturas vencidas há mais de 7 dias
   ↓
3. Para cada tenant:
   - Verifica se já existe tarefa recente
   - Cria projeto financeiro (se não existir)
   - Cria tarefa do tipo finance_overdue
   - Vincula tarefa a bloco ADMIN ou FLEX
```

---

## 🎨 INTERFACE DO USUÁRIO

### Menu Lateral

**Localização:** `views/layout/main.php`

Item "Agenda" adicionado no menu lateral:
- Posicionado entre "Clientes" e "Financeiro"
- Link: `/agenda`
- Classe `active` quando a rota atual contém `/agenda`

---

### Página Principal da Agenda (Visão Diária)

**Rota:** `GET /agenda?data={YYYY-MM-DD}`

**Funcionalidades:**
- Visualização de blocos do dia
- Navegação entre dias (anterior/seguinte/hoje)
- **Input de data para navegação rápida** (novo)
- **Link para visão semanal** (novo)
- Filtros por tipo de bloco e status
- Botão para gerar blocos manualmente
- **Botão para adicionar bloco extra manual** (novo)
- Cards de blocos mostrando:
  - Horário (ex: 07:00-09:00)
  - Tipo de bloco (com cor)
  - Status (planned, ongoing, completed, etc.)
  - Contador de tarefas vinculadas
  - **Destaque visual do bloco atual** (se estiver no horário)
  - Ações (Abrir, **Editar**, Iniciar, Finalizar, Cancelar)
- **Mensagens amigáveis:**
  - Sucesso ao gerar blocos
  - Erro de configuração (dia útil sem template)
  - Dia livre (fim de semana sem template)
- **Tratamento de fim de semana:** Sábado/domingo sem template não é tratado como erro

**View:** `views/agenda/index.php`

---

### Visão Semanal da Agenda

**Rota:** `GET /agenda/semana?data={YYYY-MM-DD}`

**Funcionalidades:**
- **Grade de 7 colunas** (Segunda a Domingo)
- **Navegação entre semanas:**
  - Botão "Semana Anterior"
  - Botão "Esta Semana"
  - Botão "Próxima Semana"
  - Input de data para navegação rápida
- **Cabeçalho mostrando período da semana** (ex: "Semana de 01/12/2025 a 07/12/2025")
- **Cada coluna exibe:**
  - Título do dia com link para visão diária (ex: "Quarta — 03/12")
  - Lista de blocos do dia ordenados por horário
  - Destaque visual para o dia atual (borda azul e fundo claro)
- **Cards de blocos:**
  - Borda colorida (cor do tipo)
  - Horário (ex: 07:00–09:00)
  - Tipo de bloco
  - Status
  - Contador de tarefas
  - **Destaque para bloco atual** (se estiver no horário)
  - Clique abre detalhes do bloco
- **Layout responsivo:**
  - Desktop: 7 colunas
  - Tablet: 4 colunas
  - Mobile: 2 colunas
  - Mobile pequeno: 1 coluna
- **Somente leitura:** Não gera blocos automaticamente (apenas exibe os existentes)
- **Mensagem quando não há blocos:** "Sem blocos cadastrados"

**View:** `views/agenda/semana.php`

---

### Edição de Bloco

**Rota:** `GET /agenda/bloco/editar?id={id}` (formulário)  
**Rota:** `POST /agenda/bloco/editar` (atualização)

**Funcionalidades:**
- Formulário para editar bloco existente
- Campos editáveis:
  - Horário de início
  - Horário de fim
  - Tipo de bloco
- **Validações:**
  - Horário de início < horário de fim
  - Não permite conflito com outros blocos do mesmo dia
  - Mensagens de erro amigáveis
- Redireciona para `/agenda?data={data}` após salvar

**View:** `views/agenda/edit_block.php`

---

### Criação de Bloco Extra Manual

**Rota:** `GET /agenda/bloco/novo?data={YYYY-MM-DD}` (formulário)  
**Rota:** `POST /agenda/bloco/novo` (criação)

**Funcionalidades:**
- Formulário para criar bloco extra manual (não baseado em template)
- Campos:
  - Data (pré-preenchida, não editável)
  - Horário de início
  - Horário de fim
  - Tipo de bloco
- **Validações:**
  - Horário de início < horário de fim
  - Não permite conflito com outros blocos do mesmo dia
  - Mensagens de erro amigáveis
- Permite criar blocos em qualquer dia (inclusive fim de semana)
- Redireciona para `/agenda?data={data}` após criar

**View:** `views/agenda/create_block.php`

---

### Modo de Trabalho do Bloco

**Rota:** `GET /agenda/bloco?id={id}`

**Funcionalidades:**
- Visualização completa do bloco
- Lista de tarefas vinculadas
- Informações do bloco (duração, projeto foco, resumo)
- Ações (Iniciar, Finalizar, Cancelar)
- Seleção de projeto foco

**View:** `views/agenda/show.php`

---

### Listagem de Tickets

**Rota:** `GET /tickets`

**Funcionalidades:**
- Lista todos os tickets
- Filtros por cliente, status, prioridade
- Cards mostrando:
  - Título e descrição
  - Prioridade e status
  - Cliente e projeto relacionados
  - Tarefa vinculada (se houver)
  - Data de criação e resolução

**View:** `views/tickets/index.php`

---

## ⚙️ CONFIGURAÇÃO E USO

### 1. Executar Migrações

```bash
php database/migrate.php
```

Isso criará todas as novas tabelas e preencherá os dados iniciais (tipos de blocos e templates).

**Ordem de execução (garantida por prefixo numérico):**
1. `20250201_01_create_agenda_block_types_table.php`
2. `20250201_02_create_agenda_block_templates_table.php`
3. `20250201_03_create_agenda_blocks_table.php`
4. `20250201_04_create_agenda_block_tasks_table.php`
5. `20250201_05_create_tickets_table.php`

---

### 2. Gerar Blocos do Dia

**Automático:**
- Ao acessar `/agenda`, o sistema verifica se existem blocos para o dia
- Se não existirem, gera automaticamente baseado no template

**Manual:**
- Acesse `/agenda` e clique em "Gerar Blocos do Dia"
- Ou via API: `POST /agenda/generate-blocks` com `data={data}`

---

### 3. Criar Tickets

1. Acesse `/tickets/create`
2. Preencha os dados do ticket
3. Sistema cria automaticamente:
   - Ticket
   - Tarefa relacionada
   - Vínculo com bloco adequado

---

### 4. Gerar Tarefas de Inadimplência

Execute periodicamente (ex: via cron):

```php
use PixelHub\Services\FinancialTaskService;

$result = FinancialTaskService::generateTasksForOverdue(7);
echo "Criadas: {$result['created']}, Ignoradas: {$result['skipped']}";
```

**Sugestão:** Criar um job/cron que execute diariamente.

---

## 🔮 PREPARAÇÃO PARA CRM FUTURO

O sistema está preparado para integração futura com CRM:

### 1. Tarefas de Lead/Comercial

- `task_type` pode ser estendido para:
  - `'lead_followup'` - Acompanhamento de lead
  - `'crm_followup'` - Acompanhamento CRM
  - `'crm_opportunity'` - Oportunidade de negócio

- Por padrão, tais tarefas irão para blocos **COMERCIAL**

**Código:** `src/Services/AgendaService.php` (método `determineBlockTypeForTask()`)

---

### 2. Extensibilidade

- Método `AgendaService::determineBlockTypeForTask()` pode ser estendido
- Novos tipos de blocos podem ser adicionados em `agenda_block_types`
- Novos templates podem ser criados em `agenda_block_templates`

---

## ⚠️ OBSERVAÇÕES IMPORTANTES

### 1. Timezone

- Sistema usa `America/Sao_Paulo` para todas as operações de data/hora
- Configurado em `public/index.php` (linha 83)

---

### 2. Geração Automática de Blocos

- Blocos são gerados apenas quando necessário (não duplica se já existirem)
- Template é baseado em segunda a sexta-feira
- **Tratamento de fim de semana:**
  - Sábado e domingo sem template não são tratados como erro
  - Sistema exibe mensagem amigável "Dia livre de blocos"
  - Usuário pode criar blocos manuais extras mesmo em fim de semana
  - Dia útil sem template é tratado como erro de configuração

---

### 3. Vínculo Automático de Tarefas

- Se não houver bloco disponível do tipo adequado, a tarefa fica sem vínculo
- Pode ser vinculada manualmente depois pela UI
- Sistema loga quando não encontra bloco disponível (para debug)

---

### 4. Tarefas de Inadimplência

- Sistema evita criar tarefas duplicadas (verifica se já existe tarefa recente)
- Tarefas são vinculadas a projetos financeiros específicos do tenant
- Projetos financeiros são criados automaticamente se não existirem

---

### 5. Ordem de Execução das Migrações

- As migrações usam prefixo numérico (`_01_`, `_02_`, etc.) para garantir ordem
- O script `database/migrate.php` foi ajustado para reconhecer esse prefixo

---

## 🆕 FUNCIONALIDADES RECÉM-IMPLEMENTADAS

### Fase 1: Melhorias na Visão Diária (2025-12-03)

✅ **Tratamento de Fim de Semana:**
- Sábado/domingo sem template não é mais tratado como erro
- Mensagem amigável "Dia livre de blocos" para fins de semana
- Dia útil sem template continua mostrando erro de configuração
- **Código:** `src/Services/AgendaService.php` (método `generateDailyBlocks()`)

✅ **Mensagens Amigáveis:**
- Removidos termos técnicos das mensagens ao usuário
- Cards de feedback visual (verde para sucesso, vermelho para erro, azul para info)
- Mensagens em português claro e objetivo
- **Código:** `src/Controllers/AgendaController.php` e `views/agenda/index.php`

✅ **Navegação por Data:**
- Input `type="date"` para navegação rápida entre dias
- Botão "Ir" para carregar data específica
- Link para visão semanal adicionado
- **Código:** `views/agenda/index.php`

### Fase 2: Edição e Criação de Blocos (2025-12-03)

✅ **Edição de Bloco:**
- Formulário completo para editar horário e tipo de bloco
- Validação de conflitos de horário (não permite sobreposição)
- Mensagens de erro amigáveis
- **Rotas:** `GET /agenda/bloco/editar?id={id}` e `POST /agenda/bloco/editar`
- **Código:** 
  - `src/Services/AgendaService.php` (método `updateBlock()`)
  - `src/Controllers/AgendaController.php` (métodos `editBlock()` e `updateBlock()`)
  - `views/agenda/edit_block.php`

✅ **Criação de Bloco Extra Manual:**
- Permite criar blocos manuais sem depender de template
- Funciona em qualquer dia (inclusive fim de semana)
- Validação de conflitos de horário
- **Rotas:** `GET /agenda/bloco/novo?data={YYYY-MM-DD}` e `POST /agenda/bloco/novo`
- **Código:**
  - `src/Services/AgendaService.php` (método `createManualBlock()`)
  - `src/Controllers/AgendaController.php` (métodos `createBlock()` e `storeBlock()`)
  - `views/agenda/create_block.php`

### Fase 3: Visão Semanal (2025-12-03)

✅ **Grade Semanal Tipo Calendário:**
- 7 colunas (Segunda a Domingo)
- Navegação entre semanas (anterior, próxima, esta semana)
- Input de data para navegação rápida
- Destaque visual do dia atual (borda azul e fundo claro)
- Destaque do bloco atual (se aplicável)
- Links para visão diária em cada dia
- Layout responsivo (desktop: 7 colunas, tablet: 4, mobile: 2, mobile pequeno: 1)
- Somente leitura (não gera blocos automaticamente)
- **Rota:** `GET /agenda/semana?data={YYYY-MM-DD}`
- **Código:**
  - `src/Services/AgendaService.php` (método `getBlocksForPeriod()`)
  - `src/Controllers/AgendaController.php` (método `semana()` e helper `formatarLabelDia()`)
  - `views/agenda/semana.php`

**Validação de Conflitos de Horário:**
- Implementada em `updateBlock()` e `createManualBlock()`
- Usa lógica de sobreposição de intervalos: `a1 < b2 AND a2 < b1`
- Mensagens de erro amigáveis quando há conflito

---

## 📈 SUGESTÕES DE MELHORIAS

### Curto Prazo (1-2 semanas)

1. **Automação de Geração de Blocos:**
   - Criar job/cron para gerar blocos da semana automaticamente
   - Executar toda segunda-feira às 00:00

2. **Notificações:**
   - Alertar quando bloco está prestes a começar (15 min antes)
   - Notificar sobre tarefas não agendadas
   - Email/WhatsApp quando ticket é criado

3. **Dashboard de Produtividade:**
   - Criar dashboard com métricas consolidadas
   - Gráficos de horas por tipo de bloco
   - Comparativo semana atual vs semana anterior

4. **Melhorias na UI:**
   - Drag & drop de tarefas entre blocos
   - Visualização em calendário mensal
   - Filtros avançados na listagem de blocos
   - Melhorias visuais na grade semanal (cores, animações)

---

### Médio Prazo (1-2 meses)

1. **Integração com CRM:**
   - Quando CRM for implementado, integrar leads com blocos COMERCIAL
   - Pipeline de vendas vinculado a blocos

2. **Relatórios Avançados:**
   - Exportação para PDF/Excel
   - Gráficos interativos
   - Análise de produtividade por período

3. **Multi-usuário:**
   - Suporte a múltiplos usuários trabalhando em blocos
   - Compartilhamento de agenda
   - Colaboração em blocos

4. **SLA e Prazos:**
   - Alertas de SLA para tickets
   - Notificações de prazos próximos
   - Dashboard de tickets em risco

---

### Longo Prazo (3-6 meses)

1. **Inteligência Artificial:**
   - Sugestão automática de blocos baseada em histórico
   - Previsão de tempo necessário para tarefas
   - Otimização automática da agenda

2. **Integração Externa:**
   - Sincronização com Google Calendar
   - Integração com ferramentas de time tracking
   - API REST para integrações externas

3. **Mobile:**
   - App mobile para visualização rápida da agenda
   - Notificações push
   - Criação rápida de tickets

---

## 🐛 TROUBLESHOOTING

### Problema: Erro ao gerar blocos

**Sintoma:** Erro "Foreign key constraint is incorrectly formed"

**Solução:**
1. Verificar se as migrações foram executadas na ordem correta
2. Verificar se a tabela `agenda_block_types` existe e tem dados
3. Verificar se a tabela `agenda_block_templates` existe

---

### Problema: Tarefas não são vinculadas automaticamente

**Sintoma:** Tarefas criadas não aparecem em blocos

**Solução:**
1. Verificar logs (`logs/pixelhub.log`)
2. Verificar se existem blocos disponíveis do tipo adequado
3. Verificar se o método `AgendaService::determineBlockTypeForTask()` está retornando tipo válido

---

### Problema: Blocos não são gerados automaticamente

**Sintoma:** Ao acessar `/agenda`, não aparecem blocos

**Solução:**
1. Verificar se o template está configurado (`agenda_block_templates`)
2. Verificar se há templates para o dia da semana atual
3. Clicar em "Gerar Blocos do Dia" manualmente

---

## 📚 REFERÊNCIAS E DOCUMENTAÇÃO RELACIONADA

- **Diagnóstico do Sistema:** `docs/DIAGNOSTICO_HUB_PIXEL12.md`
- **Plano Geral:** `docs/pixel-hub-plano-geral.md`
- **Estrutura de Projetos:** `docs/ANALISE_INTEGRACAO_CLIENTE_TAREFAS.md`

---

## 👥 PARA DESENVOLVEDORES

### Como Começar a Trabalhar no Projeto

1. **Leia este documento completamente**
2. **Execute as migrações:**
   ```bash
   php database/migrate.php
   ```
3. **Explore o código:**
   - Comece por `src/Services/AgendaService.php`
   - Veja as views em `views/agenda/`
   - Entenda as rotas em `public/index.php`
4. **Teste localmente:**
   - Acesse `/agenda`
   - Crie alguns tickets
   - Gere relatórios

### Padrões de Código

- **PSR-4 Autoload:** Namespace `PixelHub\`
- **MVC Simplificado:** Controller → Service → Database → View
- **Timezone:** Sempre `America/Sao_Paulo`
- **Tratamento de Erros:** Try-catch com logs em `logs/pixelhub.log`

### Estrutura de Commits

```
feat(agenda): adiciona funcionalidade X
fix(agenda): corrige bug Y
refactor(agenda): refatora código Z
docs(agenda): atualiza documentação
```

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

### Implementação Inicial (2025-02-01)
- [x] Migrações criadas e executadas
- [x] Services implementados
- [x] Controllers criados
- [x] Views criadas
- [x] Rotas registradas
- [x] Menu lateral atualizado
- [x] Integração com TaskService
- [x] Integração com TicketService
- [x] Integração com FinancialTaskService
- [x] Relatórios implementados
- [x] Documentação completa

### Fase 1: Melhorias Visão Diária (2025-12-03)
- [x] Tratamento de fim de semana
- [x] Mensagens amigáveis
- [x] Navegação por data (input date)
- [x] Link para visão semanal

### Fase 2: Edição e Criação de Blocos (2025-12-03)
- [x] Formulário de edição de bloco
- [x] Validação de conflitos de horário
- [x] Formulário de criação de bloco extra
- [x] Rotas e controllers implementados

### Fase 3: Visão Semanal (2025-12-03)
- [x] Método getBlocksForPeriod no AgendaService
- [x] Controller semana() implementado
- [x] View semana.php criada
- [x] Grade responsiva de 7 colunas
- [x] Navegação entre semanas
- [x] Destaque do dia atual e bloco atual
- [x] Links para visão diária

---

**Documentação criada em:** 2025-02-01  
**Última atualização:** 2025-12-03  
**Versão:** 2.0.0
