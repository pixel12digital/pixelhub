# Auditoria: Tracking de Tempo e Blocos de Agenda

**Objetivo:** Mapear o que já existe para suportar "multi-projeto por bloco com pausa/retomada".  
**Data:** 03/02/2026

---

## 1. Tabelas e colunas existentes

### 1.1 `agenda_blocks` (bloco principal)

| Coluna | Tipo | Função |
|--------|------|--------|
| `id` | INT | PK |
| `data` | DATE | Data do bloco |
| `hora_inicio` | TIME | Horário planejado início |
| `hora_fim` | TIME | Horário planejado fim |
| `tipo_id` | INT | FK agenda_block_types |
| `status` | ENUM | planned, ongoing, completed, partial, canceled |
| `motivo_cancelamento` | VARCHAR | Opcional |
| `resumo` | TEXT | Obrigatório ao finalizar |
| `projeto_foco_id` | INT NULL | **1 único projeto por bloco** |
| `duracao_planejada` | INT | Minutos |
| `duracao_real` | INT NULL | Minutos (preenchido ao finalizar) |
| `hora_inicio_real` | TIME NULL | Preenchido ao iniciar bloco |
| `hora_fim_real` | TIME NULL | Preenchido ao finalizar bloco |
| `focus_task_id` | INT NULL | Tarefa foco do bloco |
| `created_at`, `updated_at` | DATETIME | |

**Conclusão:** O bloco registra início/fim do bloco inteiro. Não há registro de períodos (start/end) por projeto dentro do bloco. `projeto_foco_id` é único por bloco.

### 1.2 `agenda_block_tasks` (pivot bloco ↔ tarefa)

| Coluna | Tipo | Função |
|--------|------|--------|
| `id` | INT | PK |
| `bloco_id` | INT | FK agenda_blocks |
| `task_id` | INT | FK tasks |
| `created_at` | DATETIME | |

**Conclusão:** Apenas vínculo N:N. Sem `started_at`, `ended_at`, `duration_seconds` ou status de segmento.

### 1.3 Outras tabelas verificadas

| Tabela | Relação com tracking de tempo |
|--------|------------------------------|
| `agenda_block_types` | Tipos de bloco (CLIENTES, SUPORTE, etc.) |
| `agenda_block_templates` | Templates semanal |
| `agenda_manual_items` | Itens manuais (reuniões, etc.) — sem relação com blocos |
| `tasks` | `completed_at` existe (conclusão da tarefa), não tracking por bloco |
| `screen_recordings` | `duration_seconds` — gravação de tela, não agenda |

**Não existem:** `time_entries`, `work_sessions`, `block_logs`, `activity_log`, `timer` (para agenda).

---

## 2. Endpoints e handlers que movem o status do bloco

| Rota | Método | Handler | Função |
|------|--------|---------|--------|
| `/agenda/start` | POST | AgendaController@start | Inicia bloco (status → ongoing, hora_inicio_real) |
| `/agenda/bloco/finish` | POST | AgendaController@finishBlock | Encerra bloco (status → completed, hora_fim_real, resumo) |
| `/agenda/bloco/reopen` | POST | AgendaController@reopenBlock | Reabre bloco concluído (status → planned) |
| `/agenda/cancel` | POST | AgendaController@cancel | Cancela bloco (status → canceled) |
| `/agenda/update-project-focus` | POST | AgendaController@updateProjectFocus | Atualiza `projeto_foco_id` do bloco |
| `/agenda/bloco/delete` | POST | AgendaController@delete | Exclui bloco |

**Não existe:** endpoint para pausar/retomar projeto dentro do bloco.

---

## 3. Registro de períodos (start_at/end_at) por entidade

| Entidade | Existe? | Onde |
|----------|---------|------|
| Bloco (início/fim) | ✅ Sim | `agenda_blocks.hora_inicio_real`, `hora_fim_real` |
| Período por projeto dentro do bloco | ❌ Não | — |
| Período por tarefa dentro do bloco | ❌ Não | — |
| Segmentos de trabalho (running/paused/done) | ❌ Não | — |

---

## 4. Fluxo atual do "Iniciar Bloco"

1. Usuário clica "Iniciar Bloco" na tela do bloco (`/agenda/bloco?id=...`)
2. `AgendaService::startBlock($blockId)`:
   - Valida que não há outro bloco `ongoing`
   - Preenche `hora_inicio_real` (se vazio) com hora atual
   - `updateBlockStatus($blockId, 'ongoing', ['hora_inicio_real' => ...])`
3. O bloco fica `ongoing` até o usuário clicar "Finalizar com resumo"
4. `finishBlock` preenche `hora_fim_real`, `resumo`, `duracao_real` (implícito) e status `completed`

**Pausa/retomada:** Não existe. O bloco é binário: planned → ongoing → completed.

---

## 5. Relatório de produtividade (horas por projeto)

**Fonte:** `AgendaService::getWeeklyReport()` → `horas_por_projeto`

```sql
SELECT p.id, p.name as projeto_nome,
       SUM(COALESCE(b.duracao_real, b.duracao_planejada)) as minutos_total
FROM agenda_blocks b
INNER JOIN projects p ON b.projeto_foco_id = p.id
WHERE b.data BETWEEN ? AND ?
AND b.projeto_foco_id IS NOT NULL
GROUP BY p.id, p.name
```

**Limitação:** 1 bloco = 1 projeto. Se o usuário trabalhou em CFC e PixelHub no mesmo bloco, só conta o `projeto_foco_id` (um deles). Não há como distribuir o tempo entre os dois.

---

## 6. Resumo da auditoria

| Item | Existe? |
|------|---------|
| Início/fim do bloco | ✅ `hora_inicio_real`, `hora_fim_real` |
| Pausa/retomada dentro do bloco | ❌ Não |
| Projeto foco do bloco | ✅ `projeto_foco_id` (1 apenas) |
| Vínculo de tarefas no bloco | ✅ `agenda_block_tasks` |
| Registro de segmentos (períodos) por projeto | ❌ Não |
| Tabelas time_entries, work_sessions, block_logs | ❌ Não existem |

---

## 7. O que foi criado (Fase 2 + refinamento multi-projeto)

### 7.0 Nova tabela: `agenda_block_projects` (pré-vínculo)

Permite vincular múltiplos projetos ao bloco antes de iniciar (mesmo com bloco planejado).

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `id` | INT | PK |
| `block_id` | INT | FK agenda_blocks |
| `project_id` | INT | FK projects |
| `created_at` | DATETIME | |

**Regra:** Projeto Foco (`projeto_foco_id`) permanece em `agenda_blocks`; esta tabela complementa com projetos adicionais.

---

## 7.1 O que será criado/alterado (Fase 2)

### 7.1 Nova tabela: `agenda_block_segments`

Registra períodos de trabalho por projeto dentro de um bloco.

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `id` | INT UNSIGNED | PK |
| `block_id` | INT UNSIGNED | FK agenda_blocks |
| `user_id` | INT UNSIGNED NULL | Opcional (multi-usuário futuro) |
| `project_id` | INT UNSIGNED NULL | FK projects (opcional) |
| `task_id` | INT UNSIGNED NULL | FK tasks (fallback) |
| `status` | ENUM | running, paused, done |
| `started_at` | DATETIME | Início do segmento |
| `ended_at` | DATETIME NULL | Fim (quando pausado/finalizado) |
| `duration_seconds` | INT NULL | Calculado (opcional) |
| `created_at` | DATETIME | |

**Índices:** `block_id`, `status`, `(block_id, status)` para validar "só 1 running por block".

### 7.2 Novos endpoints (incrementais)

| Rota | Método | Função |
|------|--------|--------|
| `/agenda/bloco/segment/start` | POST | Iniciar segmento (projeto) no bloco |
| `/agenda/bloco/segment/pause` | POST | Pausar segmento atual |
| `/agenda/bloco/segment/resume` | POST | Retomar projeto (cria novo segmento) |
| `/agenda/bloco/segments` | GET | Listar segmentos do bloco (para UI) |

### 7.3 Alterações em código existente (mínimas)

- **AgendaService::finishBlock:** Ao finalizar bloco, fechar automaticamente qualquer segmento `running` (ended_at = now, status = done).
- **AgendaController::finishBlock:** Validar que não há segmento running OU fechar antes de permitir encerrar.
- **getWeeklyReport:** Incluir `horas_por_projeto` baseado em `agenda_block_segments` (soma de segmentos) com fallback para `projeto_foco_id` quando não houver segmentos.

### 7.4 UI (view `agenda/show.php`)

- Manter "Projeto Foco" (select atual) — sem quebrar.
- Adicionar seção "Projetos neste bloco" com:
  - Lista de segmentos do bloco
  - Botões: Iniciar / Pausar (1 ativo por vez)
  - Tempo acumulado por projeto

---

## 8. Critérios de aceite (resumo)

1. Dentro do mesmo bloco: iniciar A → pausar → iniciar B → pausar → retomar A → tempo acumulado correto.
2. Tentar iniciar B com A rodando → bloquear com mensagem.
3. Encerrar bloco → não pode ficar segmento running aberto (fechar automático ou impedir).
4. Relatório do dia → mostrar tempo por projeto consolidado (segmentos + fallback).
