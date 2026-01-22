# Correção do Encerramento de Blocos da Agenda

**Data:** 2025-12-01

## Problema Identificado

Mesmo após reagendar tarefas para outro bloco, o sistema ainda bloqueava o encerramento do bloco original, informando que existiam tarefas pendentes vinculadas.

## Causa Raiz

1. **Reagendamento não removia vínculos antigos**: Quando uma tarefa era vinculada a um novo bloco, o sistema apenas adicionava o novo vínculo sem remover os vínculos antigos na tabela `agenda_block_tasks`. Isso fazia com que uma tarefa pudesse estar vinculada a múltiplos blocos simultaneamente.

2. **Validação estava correta, mas dados estavam inconsistentes**: O método `getPendingTasksForBlock()` estava correto (buscava apenas tarefas do bloco específico), mas como os vínculos antigos não eram removidos, a tarefa ainda aparecia como vinculada ao bloco antigo.

3. **Mensagem de erro pouco amigável**: A mensagem de erro não ajudava o usuário a entender o que fazer.

## Soluções Implementadas

### 1. Método `moveTaskToBlock()` Criado

**Arquivo:** `src/Services/AgendaService.php`

Criado novo método `moveTaskToBlock()` que remove vínculos antigos antes de adicionar o novo:

```php
public static function moveTaskToBlock(int $newBlockId, int $taskId, ?int $oldBlockId = null): void
```

- Remove vínculo antigo (se `$oldBlockId` fornecido) ou todos os vínculos da tarefa
- Adiciona novo vínculo com o bloco especificado
- Garante que a tarefa fique vinculada apenas ao novo bloco

### 2. Método `attachTaskToBlock()` Aprimorado

**Arquivo:** `src/Services/AgendaService.php`

Adicionado parâmetro opcional `$removeOldLinks`:

```php
public static function attachTaskToBlock(int $blockId, int $taskId, bool $removeOldLinks = false): void
```

- Se `$removeOldLinks = true`, remove todos os vínculos antigos antes de adicionar o novo
- Mantém compatibilidade com código existente (padrão é `false`)

### 3. Controller `attachTask()` Atualizado

**Arquivo:** `src/Controllers/AgendaController.php`

Modificado para suportar reagendamento automático:

- **Detecção automática**: Verifica se a tarefa já está vinculada a outro bloco
- Se a tarefa já está vinculada a outro bloco, **remove automaticamente** o vínculo antigo antes de adicionar o novo
- Aceita parâmetro opcional `remove_old` via POST para forçar remoção de vínculos antigos
- Garante que ao reagendar uma tarefa, ela fique vinculada apenas ao novo bloco

### 4. Validação de Tarefas Pendentes Aprimorada

**Arquivo:** `src/Services/AgendaService.php`

Método `getPendingTasksForBlock()` documentado e validado:

- **Garantia**: Busca APENAS tarefas vinculadas ao bloco específico (`WHERE abt.bloco_id = ?`)
- Não considera tarefas de outros blocos, mesmo que sejam do mesmo projeto
- Filtra apenas tarefas com status diferente de 'concluida'

### 5. Mensagem de Erro Melhorada

**Arquivo:** `src/Controllers/AgendaController.php` (método `finishBlock()`)

Mensagem de erro mais amigável:

**Antes:**
```
Não é possível encerrar este bloco porque existem tarefas não concluídas:
[Tarefa 1] (Em Andamento)
[Tarefa 2] (Aguardando Cliente)
Conclua ou reagende essas tarefas para outro bloco antes de encerrar.
```

**Depois:**
```
Este bloco ainda tem tarefas em andamento vinculadas. Conclua ou reagende as tarefas para outro bloco antes de encerrar.

Tarefas pendentes:
• [Tarefa 1] (Em Andamento)
• [Tarefa 2] (Aguardando Cliente)
```

### 6. Validação Unificada em `finish()` e `finishBlock()`

**Arquivo:** `src/Controllers/AgendaController.php`

Ambos os métodos agora usam a mesma validação:

- `finishBlock()`: Valida tarefas pendentes antes de encerrar (com resumo obrigatório)
- `finish()`: Valida tarefas pendentes quando status = 'completed' (não valida para 'partial')

## Arquivos Modificados

1. **src/Services/AgendaService.php**
   - Método `attachTaskToBlock()`: Adicionado parâmetro `$removeOldLinks`
   - Método `moveTaskToBlock()`: Criado (novo)
   - Método `getPendingTasksForBlock()`: Documentação aprimorada

2. **src/Controllers/AgendaController.php**
   - Método `finishBlock()`: Mensagem de erro melhorada
   - Método `finish()`: Validação de tarefas pendentes adicionada
   - Método `attachTask()`: Suporte a reagendamento (parâmetro `remove_old`)

## Como Funciona o Reagendamento

### Detecção Automática (Recomendado)

O sistema **detecta automaticamente** quando uma tarefa já está vinculada a outro bloco e remove o vínculo antigo:

```javascript
// Ao vincular uma tarefa a um novo bloco, o sistema automaticamente:
// 1. Detecta se a tarefa já está vinculada a outro bloco
// 2. Remove o vínculo antigo
// 3. Adiciona o novo vínculo
fetch('/agenda/attach-task', {
    method: 'POST',
    body: 'block_id=' + newBlockId + '&task_id=' + taskId
});
```

### Opção Manual: Usar `moveTaskToBlock()` diretamente

```php
// Remove todos os vínculos antigos e adiciona novo
AgendaService::moveTaskToBlock($newBlockId, $taskId);

// Remove vínculo de um bloco específico e adiciona novo
AgendaService::moveTaskToBlock($newBlockId, $taskId, $oldBlockId);
```

### Opção Manual: Usar `attachTaskToBlock()` com `$removeOldLinks = true`

```php
// Remove todos os vínculos antigos antes de adicionar novo
AgendaService::attachTaskToBlock($blockId, $taskId, true);
```

## Validação de Encerramento

A validação funciona da seguinte forma:

```php
// Busca tarefas pendentes APENAS do bloco específico
$pendingTasks = AgendaService::getPendingTasksForBlock($blockId);

// Query SQL usada:
SELECT t.id, t.title, t.status, p.name as project_name
FROM tasks t
INNER JOIN agenda_block_tasks abt ON t.id = abt.task_id
INNER JOIN projects p ON t.project_id = p.id
WHERE abt.bloco_id = ?  -- APENAS este bloco
AND t.status != 'concluida'
ORDER BY t.title ASC
```

**Importante**: A query filtra por `abt.bloco_id = ?`, garantindo que apenas tarefas vinculadas ao bloco específico sejam consideradas.

## Testes Esperados

### Cenário A – Tarefa Reagendada Corretamente ✅

1. Criar bloco A (07h–09h) e bloco B (09h–10h)
2. Vincular tarefa em andamento ao bloco A
3. Reagendar tarefa para o bloco B usando `moveTaskToBlock()` ou `attachTask()` com `remove_old=1`
4. **Resultado esperado:**
   - Na tela de detalhes da tarefa, apenas o bloco B aparece em "Blocos de Agenda relacionados"
   - Na tabela `agenda_block_tasks`, não existe mais vínculo da tarefa com o bloco A
   - Ao tentar encerrar o bloco A, o sistema permite encerrar normalmente

### Cenário B – Tarefa Ainda Pendente no Bloco ✅

1. Criar bloco C (14h–16h) com 2 tarefas em andamento
2. NÃO reagendar nenhuma
3. Tentar encerrar o bloco C
4. **Resultado esperado:**
   - Sistema NÃO encerra
   - Exibe mensagem amigável listando as tarefas pendentes
   - Sugere concluir ou reagendar as tarefas

### Cenário C – Todas as Tarefas Concluídas no Bloco ✅

1. Criar bloco D com 2 tarefas
2. Marcar ambas como concluídas (sem reagendar)
3. Tentar encerrar o bloco D
4. **Resultado esperado:**
   - Encerramento permitido normalmente
   - Nenhuma validação bloqueia

## Exemplo de Validação

```php
// Exemplo de como a validação funciona
$blockId = 123;

// Busca tarefas pendentes APENAS deste bloco
$pendingTasks = AgendaService::getPendingTasksForBlock($blockId);

if (count($pendingTasks) > 0) {
    // Bloqueia encerramento e lista tarefas
    $taskList = [];
    foreach ($pendingTasks as $task) {
        $taskList[] = '• ' . $task['title'] . ' (' . $task['status_label'] . ')';
    }
    
    $errorMsg = 'Este bloco ainda tem tarefas em andamento vinculadas. ' .
                'Conclua ou reagende as tarefas para outro bloco antes de encerrar.' . "\n\n" .
                'Tarefas pendentes:' . "\n" .
                implode("\n", $taskList);
    
    // Retorna erro com lista de tarefas
    throw new \RuntimeException($errorMsg);
}

// Se não há tarefas pendentes, permite encerramento
AgendaService::finishBlock($blockId, null, $resumo);
```

## Resumo das Alterações

| Item | Status | Descrição |
|------|--------|-----------|
| Validação de tarefas pendentes | ✅ Corrigida | Busca apenas tarefas do bloco específico |
| Reagendamento remove vínculo antigo | ✅ Implementado | Método `moveTaskToBlock()` criado |
| Mensagem de erro melhorada | ✅ Implementado | Mensagem mais amigável com lista de tarefas |
| Validação unificada | ✅ Implementado | `finish()` e `finishBlock()` usam mesma validação |

## Próximos Passos (Opcional)

1. Adicionar interface visual para reagendamento (botão "Reagendar" que remove vínculo antigo automaticamente)
2. Adicionar confirmação antes de remover vínculo antigo no reagendamento
3. Adicionar log de reagendamentos para auditoria

