# Validação de Encerramento de Blocos da Agenda

**Data:** 2025-01-25  
**Status:** Implementado e Corrigido  
**Arquivo:** `src/Services/AgendaService.php` - método `getPendingTasksForBlock()`

---

## 1. Contexto

O sistema de Agenda permite criar blocos de tempo e vincular tarefas do Quadro a esses blocos. Ao encerrar um bloco (via botão "Finalizar com resumo"), o sistema valida se existem tarefas pendentes vinculadas ao bloco antes de permitir o encerramento.

### 1.1. Problema Identificado

**Bug:** Mesmo após reagendar todas as tarefas de um bloco para outro bloco, ao tentar encerrar o bloco antigo, o sistema continuava bloqueando o encerramento e listando as tarefas como pendentes, mesmo elas já estando vinculadas ao novo bloco.

**Cenário de Reprodução:**
1. Bloco A (07:00-09:00) com 3 tarefas vinculadas
2. Reagendar todas as 3 tarefas para Bloco B (10:15-11:30)
3. Tentar encerrar Bloco A
4. **Resultado esperado:** Permitir encerrar (tarefas já foram reagendadas)
5. **Resultado observado:** Bloqueio com mensagem de erro listando as 3 tarefas como pendentes

---

## 2. Causa Raiz

O método `getPendingTasksForBlock()` estava verificando apenas:
- Se a tarefa está vinculada ao bloco específico (`abt.bloco_id = ?`)
- Se a tarefa não está concluída (`t.status != 'concluida'`)

**Problema:** Não verificava se a tarefa já havia sido reagendada para outro bloco ativo (futuro ou em andamento). Assim, mesmo após o reagendamento, se o vínculo antigo ainda existisse na tabela `agenda_block_tasks` (por algum motivo), a tarefa continuaria sendo considerada pendente.

**Observação:** O método `moveTaskToBlock()` já estava removendo corretamente os vínculos antigos ao reagendar. O problema estava na validação que não considerava tarefas já reagendadas.

---

## 3. Solução Implementada

### 3.1. Lógica Corrigida

Uma tarefa é considerada **pendente deste bloco** apenas se:
1. ✅ Está vinculada a este bloco específico (`abt.bloco_id = ?`)
2. ✅ Não está concluída (`t.status != 'concluida'`)
3. ✅ **NÃO possui outro vínculo ativo em outro bloco** (futuro ou em andamento)

### 3.2. Definição de "Bloco Ativo"

Um bloco é considerado **ativo** se:
- Status: `'planned'` ou `'ongoing'` **E**
- Uma das condições:
  - Data futura (`b2.data > CURDATE()`)
  - Bloco hoje que ainda não terminou (`b2.data = CURDATE() AND b2.hora_fim >= TIME(NOW())`)
  - Bloco em andamento (`b2.status = 'ongoing'`, independente da data)

### 3.3. Query SQL Final

```sql
SELECT 
    t.id,
    t.title,
    t.status,
    p.name as project_name
FROM tasks t
INNER JOIN agenda_block_tasks abt ON t.id = abt.task_id
INNER JOIN projects p ON t.project_id = p.id
WHERE abt.bloco_id = ? 
AND t.status != 'concluida'
AND t.deleted_at IS NULL  -- Se a coluna existir
AND NOT EXISTS (
    -- Verifica se a tarefa tem outro vínculo ativo em outro bloco
    SELECT 1
    FROM agenda_block_tasks abt2
    INNER JOIN agenda_blocks b2 ON abt2.bloco_id = b2.id
    WHERE abt2.task_id = t.id
    AND abt2.bloco_id != ?  -- Outro bloco (não o atual)
    AND b2.status IN ('planned', 'ongoing')
    AND (
        -- Bloco futuro
        b2.data > CURDATE()
        OR
        -- Bloco hoje que ainda não terminou
        (b2.data = CURDATE() AND b2.hora_fim >= TIME(NOW()))
        OR
        -- Bloco em andamento
        b2.status = 'ongoing'
    )
)
ORDER BY t.title ASC
```

### 3.4. Arquivos Modificados

- **`src/Services/AgendaService.php`**
  - Método: `getPendingTasksForBlock(int $blockId): array`
  - Linhas: ~1478-1532

---

## 4. Comportamento Esperado Após Correção

### 4.1. Cenário 1: Reagendamento Completo

1. Bloco A com tarefas vinculadas
2. Reagendar todas as tarefas para Bloco B
3. Tentar encerrar Bloco A
4. **Resultado:** ✅ Permite encerrar (nenhuma tarefa pendente)

### 4.2. Cenário 2: Tarefa Sem Outro Agendamento

1. Bloco A com tarefa vinculada
2. Tarefa não foi reagendada para outro bloco
3. Tentar encerrar Bloco A
4. **Resultado:** ❌ Bloqueia e lista a tarefa como pendente

### 4.3. Cenário 3: Tarefa Concluída

1. Bloco A com tarefa vinculada
2. Tarefa está com status `'concluida'`
3. Tentar encerrar Bloco A
4. **Resultado:** ✅ Permite encerrar (tarefa concluída não é considerada pendente)

### 4.4. Cenário 4: Tarefa Reagendada para Bloco Futuro

1. Bloco A (hoje) com tarefa vinculada
2. Reagendar tarefa para Bloco B (amanhã, status `'planned'`)
3. Tentar encerrar Bloco A
4. **Resultado:** ✅ Permite encerrar (tarefa já tem outro agendamento ativo)

---

## 5. Métodos Relacionados

### 5.1. `AgendaService::getPendingTasksForBlock(int $blockId)`

**Responsabilidade:** Retorna lista de tarefas pendentes de um bloco específico.

**Uso:** Chamado por `AgendaController::finish()` e `AgendaController::finishBlock()` antes de permitir o encerramento.

### 5.2. `AgendaService::moveTaskToBlock(int $newBlockId, int $taskId, ?int $oldBlockId = null)`

**Responsabilidade:** Move uma tarefa de um bloco para outro, removendo vínculos antigos.

**Comportamento:**
- Se `$oldBlockId` for fornecido: remove apenas o vínculo específico
- Se `$oldBlockId` for `null`: remove **todos** os vínculos da tarefa antes de adicionar o novo

**Uso:** Chamado por `AgendaController::attachTask()` quando detecta que a tarefa já está vinculada a outro bloco.

### 5.3. `AgendaController::finishBlock()`

**Responsabilidade:** Encerra um bloco com resumo obrigatório.

**Validação:**
```php
$pendingTasks = AgendaService::getPendingTasksForBlock($blockId);
if (count($pendingTasks) > 0) {
    // Bloqueia encerramento e exibe mensagem com lista de tarefas
}
```

---

## 6. Testes Recomendados

### 6.1. Teste Manual

1. Criar Bloco A e Bloco B na mesma manhã
2. Vincular 2-3 tarefas ao Bloco A
3. Reagendar todas as tarefas para Bloco B
4. Tentar finalizar Bloco A
   - ✅ **Esperado:** Não bloquear, permitir encerrar com resumo

5. Criar nova tarefa só em Bloco A, sem reagendar
6. Tentar encerrar Bloco A novamente
   - ❌ **Esperado:** Bloquear e listar apenas essa tarefa como pendente

### 6.2. Verificação de Query

Para debugar, execute diretamente no banco:

```sql
-- Substitua ? pelo ID do bloco que está tentando encerrar
SELECT 
    t.id,
    t.title,
    t.status,
    p.name as project_name
FROM tasks t
INNER JOIN agenda_block_tasks abt ON t.id = abt.task_id
INNER JOIN projects p ON t.project_id = p.id
WHERE abt.bloco_id = ?  -- ID do bloco
AND t.status != 'concluida'
AND NOT EXISTS (
    SELECT 1
    FROM agenda_block_tasks abt2
    INNER JOIN agenda_blocks b2 ON abt2.bloco_id = b2.id
    WHERE abt2.task_id = t.id
    AND abt2.bloco_id != ?  -- ID do bloco
    AND b2.status IN ('planned', 'ongoing')
    AND (
        b2.data > CURDATE()
        OR (b2.data = CURDATE() AND b2.hora_fim >= TIME(NOW()))
        OR b2.status = 'ongoing'
    )
)
ORDER BY t.title ASC;
```

---

## 7. Notas Técnicas

### 7.1. Compatibilidade com `deleted_at`

O código verifica se a coluna `deleted_at` existe na tabela `tasks` usando o método `hasDeletedAtColumn()`. Se existir, filtra tarefas soft-deletadas. Se não existir, funciona sem essa condição (compatibilidade com bancos antigos).

### 7.2. Performance

A query usa `NOT EXISTS` com subquery, que é eficiente para verificar ausência de registros. O índice recomendado:

```sql
-- Índices recomendados (se não existirem)
CREATE INDEX idx_agenda_block_tasks_task_id ON agenda_block_tasks(task_id);
CREATE INDEX idx_agenda_block_tasks_bloco_id ON agenda_block_tasks(bloco_id);
CREATE INDEX idx_agenda_blocks_status_data ON agenda_blocks(status, data);
```

### 7.3. Timezone

A query usa `CURDATE()` e `TIME(NOW())` que respeitam o timezone do servidor MySQL. O sistema está configurado para usar `America/Sao_Paulo` no PHP, mas as funções MySQL usam o timezone do servidor.

**Recomendação:** Garantir que o timezone do MySQL esteja configurado corretamente ou usar funções PHP para comparação de datas se necessário.

---

## 8. Histórico de Alterações

- **2025-01-25:** Correção do bug de validação que impedia encerramento de blocos após reagendamento de tarefas
- **Query corrigida:** Adicionada verificação de vínculos ativos em outros blocos via `NOT EXISTS`

---

## 9. Referências

- `src/Services/AgendaService.php` - método `getPendingTasksForBlock()`
- `src/Controllers/AgendaController.php` - métodos `finish()` e `finishBlock()`
- Tabela: `agenda_block_tasks` (pivot bloco ↔ tarefa)
- Tabela: `agenda_blocks` (blocos de tempo)
- Tabela: `tasks` (tarefas do quadro)










