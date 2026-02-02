# Investigação: Quadro de Tarefas e Relatório de Tarefas Concluídas

**Objetivo:** Analisar se o sistema atual permite cadastrar e concluir tarefas (ex.: otimização implementada) e utilizá-las em relatório de tarefas concluídas no final da semana. Confrontar com padrões de sistemas profissionais.

**Status:** Investigação concluída. **Implementações aplicadas** (ver commits relacionados).

**Cenário do usuário:** "Eu implementei alterações/otimizações em projetos. Quero registrar essa tarefa, concluí-la e usar no relatório de tarefas concluídas no final da semana."

---

## 1. O que temos hoje

### 1.1 Quadro de Gerenciamento de Tarefas (Kanban)

**Rota:** `GET /projects/board`  
**Localização:** `views/tasks/board.php`, `TaskBoardController@board`

**Estrutura:**
- 4 colunas: **Backlog** | **Em andamento** | **Aguardando cliente** | **Concluída**
- **Criação de tarefa:** Quick-add em cada coluna ou modal completo
- **Projeto obrigatório:** Toda tarefa exige `project_id` — não há tarefas sem projeto
- **Conclusão:** Arrastar para coluna "Concluída" ou alterar status no modal
- **Dados de conclusão:** `completed_at` e `completed_by` preenchidos automaticamente ao mover para "Concluída"

**Fluxo para o cenário do usuário:**
1. Acessar Quadro Kanban (`/projects/board`)
2. Clicar em "Adicionar" na coluna "Concluída" (ou em outra e depois arrastar)
3. Escolher **projeto** (ex.: "PixelHub — Roadmap Geral da Plataforma", "Sistema Prestadores de Serviços")
4. Informar título (ex.: "Filtro padrão Ativo - ocultar projetos arquivados na tela Projetos")
5. Salvar
6. Se criou em outra coluna, arrastar para "Concluída"

**Funcional:** Sim, o fluxo é possível. O usuário consegue criar e concluir a tarefa.

---

### 1.2 Relatórios existentes

| Relatório | Rota | Menu | Conteúdo |
|-----------|------|------|----------|
| **Resumo Semanal** | `/agenda/stats` | Agenda → Resumo Semanal | Blocos por tipo, ocupação, horas totais (baseado em `agenda_blocks`) |
| **Relatório Semanal de Produtividade** | `/agenda/weekly-report` | **Não está no menu** | Horas por tipo de bloco, **tarefas concluídas por tipo de bloco**, horas por projeto, blocos cancelados |

**Relatório Semanal de Produtividade** (`getWeeklyReport`):

- **Horas por tipo de bloco:** blocos completados, parciais, cancelados
- **Tarefas concluídas por tipo de bloco:** contagem de tarefas concluídas **vinculadas a blocos** (`agenda_block_tasks`)
- **Horas por projeto:** via `projeto_foco_id` dos blocos
- **Blocos cancelados:** com motivo

**Restrição crítica:** A query de tarefas concluídas usa:

```sql
FROM tasks t
INNER JOIN agenda_block_tasks abt ON t.id = abt.task_id
INNER JOIN agenda_blocks b ON abt.bloco_id = b.id
...
WHERE b.data BETWEEN ? AND ?
AND t.status = 'concluida'
```

Ou seja: **só entram tarefas vinculadas a blocos de agenda**. A data da semana é a data do bloco (`b.data`), não da conclusão (`completed_at`).

---

### 1.3 Conclusão: o cenário funciona?

| Etapa | Funcional? | Observação |
|-------|-------------|------------|
| Cadastrar tarefa | ✅ Sim | No Kanban, escolhendo um projeto |
| Concluir tarefa | ✅ Sim | Arrastar para "Concluída" ou alterar status |
| Aparecer no relatório semanal | ❌ **Não** | A tarefa só entra se estiver vinculada a um bloco de agenda |

**Fluxo atual:**  
Tarefa criada e concluída no Kanban sem vínculo com bloco → **não aparece** no relatório de tarefas concluídas.

Para aparecer no relatório, o usuário precisaria:
1. Criar a tarefa no Kanban
2. Ir à Agenda
3. Vincular a tarefa a um bloco manualmente ("Vincular tarefa existente" ou "Agendar na Agenda")
4. Concluir a tarefa no Kanban
5. O bloco precisa ter `data` na semana do relatório

**Problema:** O relatório é centrado em blocos de agenda, não em tarefas concluídas por período. A data usada é a do bloco, não a de conclusão da tarefa.

---

## 2. Sistema profissional vs. PixelHub

### 2.1 Padrões de mercado

| Sistema | Relatório de tarefas concluídas | Filtro por período |
|---------|--------------------------------|--------------------|
| **Asana** | Export CSV, Advanced Search, digest semanal (Zapier) | Por data de conclusão |
| **Jira** | Dashboards, relatórios nativos | Por data de conclusão/resolução |
| **Trello** | Sem relatório nativo de concluídas | — |
| **Weekdone** | Integração com Asana/Jira/Basecamp | Relatório semanal por email/PDF |
| **Monday.com** | Relatórios e dashboards | Por data de conclusão |

**Padrão:** Relatórios de tarefas concluídas usam **data de conclusão** (`completed_at`), não data de agendamento.

### 2.2 Tarefas sem projeto

| Sistema | Tarefas sem projeto |
|---------|---------------------|
| **Asana** | "My Tasks" / Inbox para tarefas sem projeto |
| **Trello** | Cards podem existir em listas sem board específico |
| **PixelHub** | ❌ Toda tarefa exige `project_id` |

Para tarefas internas (ex.: "otimização implementada"), o usuário precisa escolher um projeto (ex.: "PixelHub — Roadmap Geral da Plataforma"). Isso é aceitável; o gap é o relatório, não a obrigatoriedade de projeto.

### 2.3 Comparativo

| Aspecto | PixelHub | Sistema típico |
|---------|----------|----------------|
| Criar tarefa | ✅ Kanban com projeto obrigatório | Similar (projeto ou "My Tasks") |
| Concluir tarefa | ✅ Arrastar ou status | Similar |
| Relatório por data de conclusão | ❌ Não existe | Por `completed_at` |
| Relatório por data de bloco | ✅ Existe | Menos comum |
| Tarefas no relatório sem vínculo com agenda | ❌ Não | Não aplicável |

---

## 3. Lacunas identificadas

### 3.1 Relatório de tarefas concluídas

**Situação atual:**  
O relatório semanal (`/agenda/weekly-report`) mostra apenas tarefas concluídas **vinculadas a blocos** e usa a **data do bloco**, não a data de conclusão.

**Lacuna:**  
Não há relatório que liste todas as tarefas concluídas na semana (ou em um período) com base em `completed_at`.

### 3.2 Acesso ao relatório semanal

**Situação:** `/agenda/weekly-report` **não está no menu**.  
O menu tem "Resumo Semanal" (`/agenda/stats`), que é outro relatório (blocos, ocupação, sem lista de tarefas concluídas).

### 3.3 Fluxo de trabalho

Para o cenário "registrar otimização e ver no relatório":

- **Criar e concluir:** ✅ Funciona
- **Ver no relatório:** ❌ Só se a tarefa for vinculada a um bloco e o bloco estiver na semana do relatório
- **Usar data de conclusão:** ❌ Não existe

---

## 4. Sugestões de otimização (sem implementar)

### 4.1 Curto prazo (uso do que já existe)

1. **Usar o relatório atual:**  
   - Criar tarefa no Kanban → concluir  
   - Ir à Agenda → vincular a um bloco do dia  
   - A tarefa passa a aparecer no relatório, desde que o bloco esteja na semana

2. **Projeto sugerido:**  
   Usar projetos internos como "PixelHub — Roadmap Geral da Plataforma" ou "Sistema Prestadores de Serviços" para tarefas de otimização.

3. **Link no menu:**  
   Incluir link para `/agenda/weekly-report` no menu (ex.: em Agenda → "Relatório Semanal de Produtividade") para facilitar o acesso.

### 4.2 Médio prazo (evolução futura)

1. **Relatório por data de conclusão:**  
   Nova view ou seção que liste tarefas com `status = 'concluida'` e `completed_at` no período.

2. **Unificação de relatórios:**  
   - Manter relatório atual (blocos + tarefas vinculadas)  
   - Adicionar seção "Tarefas concluídas por data de conclusão" (independente de agenda)

3. **Filtro por período:**  
   Permitir escolher semana (ou intervalo) no relatório.

---

## 5. Respostas às questões

### "Será que está funcional?"

**Sim:**  
- Cadastrar tarefa: ✅  
- Concluir tarefa: ✅  

**Não:**  
- Usar no relatório de tarefas concluídas no final da semana: ❌  
  - Só se a tarefa for vinculada a um bloco de agenda e o bloco estiver na semana do relatório
  - Sem vínculo com bloco, a tarefa não entra no relatório

### "Será que está alinhado com sistemas profissionais?"

**Parcialmente:**
- Quadro Kanban e criação de tarefas: ✅  
- Conclusão: ✅  
- Relatório de tarefas concluídas por data de conclusão: ❌  
- Relatório de tarefas concluídas por data de conclusão: ❌  
- Relatório centrado em blocos de agenda, não em tarefas concluídas por período

### "O cenário ideal está atendido?"

**Não.**  
O cenário ideal seria: registrar a tarefa, concluí-la e vê-la em um relatório de tarefas concluídas no final da semana, filtrado por data de conclusão. Hoje isso não é possível sem vincular manualmente a tarefa a um bloco de agenda.

---

## 6. Referências no código

| Componente | Arquivo |
|------------|---------|
| Quadro Kanban | `views/tasks/board.php` |
| TaskBoardController | `src/Controllers/TaskBoardController.php` |
| TaskService | `src/Services/TaskService.php` |
| Relatório semanal | `AgendaService::getWeeklyReport()` em `src/Services/AgendaService.php` (linhas 1900–1988) |
| Query de tarefas concluídas | `AgendaService.php` linhas 1931–1945 |
| View do relatório | `views/agenda/weekly_report.php` |
| Menu / rotas | `views/layout/main.php`, `public/index.php` |
