# Planejamento: Agenda Unificada e Integração com Projetos

**Data:** 02/02/2026  
**Status:** ✅ Implementado (02/02/2026)  
**Objetivo:** Consolidar visões da agenda, reduzir redundância e integrar visualmente com prazos de projetos.

---

## 1. Função de cada tela hoje

### 1.1 Agenda Diária (`/agenda`)

| Aspecto | Descrição |
|---------|-----------|
| **Propósito** | Blocos de tempo de **um único dia** |
| **Dados** | Lista de blocos (hora início/fim, tipo, status, tarefas vinculadas) |
| **Ações** | Gerar blocos do dia, adicionar bloco extra, iniciar/encerrar bloco, vincular tarefas |
| **Filtros** | Tipo de bloco, status |
| **Navegação** | Dia anterior, dia seguinte, hoje, seletor de data, link "Ver Semana" |

### 1.2 Agenda Semanal (`/agenda/semana`)

| Aspecto | Descrição |
|---------|-----------|
| **Propósito** | Visão da **semana inteira** (domingo a sábado) em grid |
| **Dados** | Mesmos blocos da agenda diária, agrupados por dia em 7 colunas |
| **Ações** | Clicar no bloco abre detalhe; clicar no dia abre agenda diária |
| **Comportamento** | Auto-gera blocos da semana via `ensureBlocksForWeek()` |
| **Navegação** | Semana anterior, esta semana, próxima semana, seletor de data |

### 1.3 Resumo Semanal (`/agenda/stats`)

| Aspecto | Descrição |
|---------|-----------|
| **Propósito** | **Estatísticas agregadas** da semana (segunda a domingo) |
| **Dados** | Total de horas, horas ocupadas, horas livres, % ocupação por tipo de bloco |
| **Visualização** | Cards de resumo + tabela por tipo (horas totais, ocupadas, livres, %) |
| **Ações** | Apenas leitura |
| **Navegação** | Semana anterior, esta semana, próxima semana |

### 1.4 Relatório de Produtividade (`/agenda/weekly-report`)

| Aspecto | Descrição |
|---------|-----------|
| **Propósito** | **Relatório de produtividade** da semana (segunda a domingo) |
| **Dados** | Horas por tipo de bloco, tarefas concluídas (por data e por tipo), horas por projeto, blocos cancelados |
| **Visualização** | Tabelas e listas |
| **Ações** | Apenas leitura |
| **Navegação** | Semana anterior, hoje, próxima semana |

---

## 2. Redundância e sobreposição

| Tela | Base de dados | Período | Foco |
|------|---------------|---------|------|
| Agenda Diária | `agenda_blocks` | 1 dia | Operação (trabalhar nos blocos) |
| Agenda Semanal | `agenda_blocks` | 7 dias | Visão geral da semana |
| Resumo Semanal | `agenda_blocks` | 7 dias | Métricas de ocupação |
| Relatório de Produtividade | `agenda_blocks` + `tasks` | 7 dias | Métricas de produtividade |

**Sobreposição:**
- Agenda Diária e Agenda Semanal usam os mesmos dados; a semanal é essencialmente 7× a diária.
- Resumo Semanal e Relatório de Produtividade são ambos visões agregadas da semana, com métricas diferentes (ocupação vs produtividade).

---

## 3. Como sistemas profissionais gerenciam

### 3.1 ClickUp

- **Calendar View:** Diário, semanal, mensal em uma única tela com toggle.
- **Gantt Chart:** Timeline visual de tarefas/projetos com arrastar-e-soltar.
- **Integração:** Tarefas com data aparecem automaticamente no calendário; não há vínculo manual tarefa↔bloco.
- **Auto-scheduling:** Bloqueia tempo de foco e reagenda tarefas automaticamente.

### 3.2 Asana

- **Timeline:** Gantt por projeto, com dependências e arrastar para reagendar.
- **Calendar:** Tarefas com due date aparecem no calendário.
- **Separação:** Timeline para planejamento de projeto; Calendar para execução diária.

### 3.3 Monday.com

- **Calendar View:** Por board, baseado em colunas de data.
- **Calendar Widget:** Dashboard que agrega vários boards em uma visão.
- **Timeline:** Visão Gantt por item.

### 3.4 Padrões observados

1. **Uma tela, múltiplas visões:** Dia / Semana / Mês como filtros ou abas, não como páginas separadas.
2. **Tarefas com data = aparecem no calendário:** Sem vínculo manual bloco↔tarefa.
3. **Gantt/Timeline para projetos:** Visualização de início/fim, dependências, prazo.
4. **Separação clara:** Operação (trabalhar hoje) vs planejamento (ver prazos, projetos).

---

## 4. Estrutura atual de dados (projetos e tarefas)

| Entidade | Campos de data | Uso |
|----------|----------------|-----|
| **projects** | `due_date` (prazo) | Prazo do projeto |
| **projects** | `created_at` | Não há `start_date` explícito |
| **tasks** | `start_date`, `due_date` | Início e prazo da tarefa |
| **tasks** | `completed_at` | Data de conclusão |
| **agenda_blocks** | `data`, `hora_inicio`, `hora_fim` | Bloco de tempo |
| **agenda_block_tasks** | pivot | Vínculo manual tarefa ↔ bloco |

**Problema de redundância:** A tarefa já pertence a um projeto e pode ter `start_date`/`due_date`. Para aparecer no relatório "por tipo de bloco", é necessário vincular manualmente ao bloco. O usuário precisa fazer duas coisas: (1) criar tarefa no projeto e (2) agendar no bloco.

---

## 5. Visão proposta (sem implementação)

### 5.1 Unificação: Agenda Diária + Semanal + Resumo

**Ideia:** Uma única tela "Agenda" com:

- **Filtro de período:** Dia | Semana | Mês (como ClickUp/Google Calendar).
- **Filtros:** Tipo de bloco, status (já existem na diária).
- **Modo de exibição:**
  - **Dia:** Lista de blocos (como hoje).
  - **Semana:** Grid 7 colunas (como hoje).
  - **Mês:** Visão mensal simplificada (novo).
- **Resumo/estatísticas:** Cards ou painel lateral com total de horas, ocupação, etc., visíveis em qualquer modo.

**Benefício:** Menos itens no menu, mesma base de dados, experiência mais fluida.

### 5.2 Integração visual com projetos

**Ideia:** Exibir prazos de projetos e tarefas de forma visual (tipo Gantt/Timeline).

- **Projetos:** Barra horizontal com `due_date` (e eventual `start_date` se for adicionado).
- **Tarefas:** Barras com `start_date` e `due_date`.
- **Relação com agenda:** Ver se os blocos da semana "cobrem" os prazos dos projetos.

**Exemplo de uso:** "O projeto X vence em 15/02. Esta semana tenho 8h de blocos CLIENTES. As tarefas do projeto X estão distribuídas nos blocos?"

### 5.3 Redução da redundância tarefa ↔ bloco

**Cenários possíveis:**

1. **Inferência por projeto:** Se a tarefa tem `start_date`/`due_date` e o bloco é do tipo CLIENTES e tem `projeto_foco_id` = projeto da tarefa, considerar "implícito" para relatórios (sem `agenda_block_tasks`).
2. **Sugestão automática:** Ao criar tarefa com data, sugerir blocos compatíveis (tipo + data) para vincular em um clique.
3. **Duas fontes no relatório:** Manter "Tarefas por tipo de bloco" (vinculadas) e adicionar "Tarefas por projeto/prazo" (baseado em `start_date`/`due_date` e projeto), sem exigir vínculo.

**Trade-off:** Simplifica para o usuário, mas pode exigir regras de negócio e mudanças no modelo de dados.

---

## 6. Resumo e próximos passos

| Item | Situação atual | Direção proposta |
|------|----------------|------------------|
| Agenda Diária, Semanal, Resumo | 3 telas separadas | 1 tela com filtros Dia/Semana/Mês + painel de resumo |
| Relatório de Produtividade | Tela separada | Manter como relatório específico ou integrar como aba "Relatório" na agenda unificada |
| Projetos na agenda | Não exibidos | Visão Timeline/Gantt com prazos de projetos e tarefas |
| Vínculo tarefa↔bloco | Manual (agenda_block_tasks) | Avaliar inferência, sugestão ou exibição paralela por projeto/prazo |

**Próximos passos sugeridos (quando for implementar):**

1. Definir escopo da "Agenda Unificada" (quais modos, quais filtros).
2. Avaliar se adicionar `start_date` em projetos.
3. Definir formato da visão Timeline/Gantt (biblioteca, escopo inicial).
4. Tarefas aparecem por `due_date` na agenda; vínculo a bloco permanece opcional (ver seção 7).

---

## 7. Blocos: impacto mínimo e simplificação

### 7.1 O que são os blocos hoje

| Componente | Função atual |
|------------|--------------|
| `agenda_block_types` | Tipos (FUTURE, CLIENTES, COMERCIAL, SUPORTE, ADMIN, PESSOAL, FLEX) |
| `agenda_block_templates` | Template semanal (seg–sex): horários fixos por tipo |
| `agenda_blocks` | Instâncias diárias geradas do template (data + hora início/fim) |
| `agenda_block_tasks` | Pivot tarefa ↔ bloco (vínculo manual) |

**Onde são usados:**
- Agenda diária/semanal (listagem de blocos)
- Tickets (vinculação automática ao bloco SUPORTE)
- Relatório de produtividade (tarefas por tipo de bloco)
- Quadro Kanban (badge "Na Agenda" / "Sem Agenda")

### 7.2 Referências de mercado: blocos como recurso opcional

| Ferramenta | Abordagem |
|------------|-----------|
| **Google Calendar** | Eventos (reuniões) + Tasks (to-dos). Tasks podem bloquear tempo opcionalmente. Não exige vínculo. |
| **Todoist** | Lista de tarefas por data. Time blocking opcional no Today view. Tarefas aparecem por due date. |
| **ClickUp** | Tarefas com data aparecem no calendário automaticamente. Time blocking é feature adicional (bloquear foco). |
| **Notion Calendar** | Eventos + time blocking com cores. Tarefas vêm de integrações. |
| **Asana** | Calendar mostra tarefas por due date. Timeline para planejamento. Sem blocos rígidos. |

**Padrão:** A lista "o que fazer" (por data) é primária. Blocos/time blocking são opcionais para quem quer reservar horário específico.

### 7.3 Estratégia: menor impacto, máxima simplificação

**Princípio:** Lapidar o que existe. Não remover blocos; torná-los opcionais e simplificados.

| Aspecto | Hoje | Proposto |
|---------|------|----------|
| **Agenda principal** | Lista de blocos do dia | Lista "o que fazer" (tarefas + projetos + itens manuais) por hoje/semana |
| **Blocos** | Obrigatórios para "ter agenda" | Opcionais: para time blocking ou agendamentos manuais (CRM, reuniões) |
| **Vínculo tarefa↔bloco** | Manual (agenda_block_tasks) | Opcional; tarefas aparecem na agenda por due_date sem vínculo |
| **Templates** | Geram blocos automaticamente | Manter para quem quiser; não exigir "Gerar Blocos" para ver a agenda |
| **Tickets** | Vinculam a bloco SUPORTE | Manter: ticket cria tarefa; tarefa pode ser sugerida em bloco SUPORTE (não obrigatório) |

### 7.4 O que manter vs simplificar

**Manter (sem quebrar):**
- Tabelas `agenda_blocks`, `agenda_block_types`, `agenda_block_templates`, `agenda_block_tasks`
- Geração de blocos por template (para quem usa)
- Bloco como "slot de tempo" para agendamentos manuais e CRM futuro
- Integração tickets → tarefa (o vínculo a bloco pode ficar opcional)

**Simplificar:**
- Nova tela "Agenda" = listagem por hoje/semana (tarefas + projetos + itens manuais)
- Blocos como aba ou seção "Time blocking" / "Horários reservados" (secundária)
- Remover obrigatoriedade de vincular tarefa ao bloco para ver no relatório
- Unificar Agenda Diária + Semanal + Resumo em uma tela com filtros

**Não duplicar:**
- Uma única fonte de "o que fazer": tarefas (due_date) + projetos (due_date) + itens manuais
- Relatório de produtividade usa tarefas concluídas (completed_at) + opcionalmente blocos vinculados

### 7.5 Fluxo proposto (resumido)

1. **Agenda (principal):** Hoje | Esta semana. Lista: tarefas com prazo + projetos com prazo + itens manuais.
2. **Blocos (opcional):** Quem quiser time blocking vê/gera blocos e pode vincular tarefas. Quem não quiser ignora.
3. **Visão macro:** Gráfico/timeline com projetos e prazos.
4. **Relatório:** Tarefas concluídas (por completed_at) + horas em blocos (se houver).

---

## 8. Resumo executivo para implementação

| Diretriz | Ação |
|----------|------|
| **Referências** | Seguir padrão ClickUp/Todoist/Google: lista por data primária, blocos opcionais |
| **Impacto** | Manter tabelas e serviços de blocos; não remover, apenas reorganizar UX |
| **Redundância** | Eliminar: uma agenda unificada em vez de 3 telas; tarefas aparecem por prazo, não só por vínculo |
| **Simplificação** | Agenda = "o que fazer hoje/semana"; blocos = recurso opcional para time blocking e agendamentos manuais |
| **CRM futuro** | Mesma agenda aceita itens manuais (contatos, follow-ups, reuniões) |

---

---

## 9. Implementação (02/02/2026)

| Item | Implementado |
|------|--------------|
| Agenda unificada | `/agenda` — "O que fazer" (Hoje \| Esta semana) |
| Blocos opcionais | `/agenda/blocos` — Blocos de tempo do dia |
| Visão macro | `/agenda/timeline` — Projetos e prazos |
| Itens manuais | Tabela `agenda_manual_items` + métodos em AgendaService |
| Sidebar | O que fazer, Blocos de tempo, Blocos semana, Visão macro, Resumo Semanal, Relatório |
| Migration | `20260202_create_agenda_manual_items_table.php` |
