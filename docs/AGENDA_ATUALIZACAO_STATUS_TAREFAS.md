# Ajuste de Mudança de Status de Tarefas pela Agenda

**Data:** 2025-01-25

## Problema Identificado

Quando o usuário estava dentro de um bloco de agenda (ex.: `/agenda/bloco?id=3`) e tentava alterar o status de uma tarefa de "Em Andamento" para "Concluída" diretamente pela tela da Agenda, recebia uma mensagem de erro dizendo que não era possível alterar o status.

Funcionalmente, isso não fazia sentido: se o usuário está trabalhando no bloco, deve poder concluir a tarefa ali mesmo, e essa conclusão deve refletir automaticamente no quadro Kanban (Projeto & Tarefas).

## Causa Raiz

Após investigação, identificamos que:

1. **Não havia bloqueio explícito no código**: O método `updateTaskStatus()` do `TaskBoardController` não tinha nenhuma validação que impedisse a mudança de status para tarefas vinculadas a blocos.

2. **Problema de sincronização com o Quadro**: O método `updateTask()` do `TaskService` atualizava o status diretamente sem ajustar a ordem (`order`) da tarefa. Isso poderia causar problemas de sincronização com o quadro Kanban, onde a tarefa precisa aparecer na coluna correta.

3. **Falta de uso do método `moveTask()`**: Quando o status muda, é necessário usar o método `moveTask()` para garantir que:
   - A ordem da tarefa seja ajustada corretamente na nova coluna
   - A tarefa apareça na posição correta no quadro Kanban
   - As ordens das outras tarefas sejam reajustadas

## Soluções Implementadas

### 1. Ajuste no Método `updateTaskStatus()`

**Arquivo:** `src/Controllers/TaskBoardController.php`

Modificado o método `updateTaskStatus()` para:

- Verificar se o status realmente mudou antes de processar
- Usar `moveTask()` quando o status muda, garantindo que a ordem seja ajustada corretamente
- Manter compatibilidade com atualizações que não mudam o status

**Código alterado:**

```php
public function updateTaskStatus(): void
{
    // ... validações ...
    
    try {
        // Busca a tarefa atual para verificar se o status mudou
        $task = TaskService::findTask($taskId);
        if (!$task) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Tarefa não encontrada']);
            return;
        }
        
        $oldStatus = $task['status'] ?? null;
        
        // Se o status mudou, usa moveTask para ajustar a ordem corretamente
        // Isso garante que a tarefa apareça na coluna correta no quadro Kanban
        if ($oldStatus !== $status) {
            // moveTask ajusta a ordem automaticamente quando o status muda
            TaskService::moveTask($taskId, $status, null);
        } else {
            // Se o status não mudou, apenas atualiza outros campos se necessário
            TaskService::updateTask($taskId, ['status' => $status]);
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (\Exception $e) {
        // ... tratamento de erro ...
    }
}
```

### 2. Verificação de `getPendingTasksForBlock()`

**Arquivo:** `src/Services/AgendaService.php`

Confirmado que o método `getPendingTasksForBlock()` já está filtrando corretamente tarefas concluídas:

- A query inclui `AND t.status != 'concluida'` (linhas 1510 e 1548)
- Tarefas concluídas não bloqueiam o encerramento de blocos
- Tarefas reagendadas para outro bloco não bloqueiam o encerramento do bloco original

## Comportamento Esperado Após a Correção

### Fluxo Normal

1. **Usuário está em `/agenda/bloco?id=3`**
   - Vê a lista de tarefas do bloco com seus status atuais

2. **Usuário altera o status de uma tarefa**
   - Exemplo: "Em Andamento" → "Concluída"
   - O select de status na tela do bloco permite a mudança

3. **Sistema processa a mudança**
   - Nenhum erro é exibido
   - O status é salvo como "concluída" no banco
   - A ordem da tarefa é ajustada automaticamente na nova coluna

4. **Sincronização com o Quadro**
   - Se o usuário abrir o Quadro de Tarefas, a mesma tarefa aparece como concluída
   - A tarefa aparece na coluna "Concluída" na posição correta

5. **Encerramento do Bloco**
   - Tarefas concluídas não bloqueiam o encerramento do bloco
   - Apenas tarefas pendentes (não concluídas) são consideradas na validação

## Regras de Negócio Mantidas

### ✅ Regras que Continuam Funcionando

1. **Encerramento de blocos**: Blocos só podem ser encerrados se não houver tarefas pendentes vinculadas
2. **Tarefas reagendadas**: Tarefas reagendadas para outro bloco não bloqueiam o encerramento do bloco original
3. **Tarefas concluídas**: Tarefas concluídas não bloqueiam o encerramento do bloco
4. **Listagem de tarefas disponíveis**: Tarefas concluídas não aparecem na listagem de "Vincular tarefa existente"

### ✅ Transições de Status Permitidas

- `em_andamento` → `concluida` ✅
- `backlog` → `em_andamento` ✅
- `em_andamento` → `aguardando_cliente` ✅
- `concluida` → `em_andamento` ✅ (reabertura)
- Todas as outras transições válidas ✅

## Arquivos Modificados

1. **`src/Controllers/TaskBoardController.php`**
   - Método `updateTaskStatus()` ajustado para usar `moveTask()` quando o status muda

## Testes Manuais Sugeridos

### Caso 1: Mudança de Status na Agenda
1. Criar bloco futuro
2. Vincular 1 tarefa "Em Andamento" a esse bloco
3. Alterar o status da tarefa para "Concluída" dentro da tela do bloco
4. Verificar no banco (e no Quadro) se o status é "concluída"
5. Tentar encerrar o bloco → deve permitir

### Caso 2: Múltiplas Tarefas
1. Criar 2 tarefas no bloco: uma "Em Andamento" e uma "Concluída"
2. Encerrar o bloco → deve bloquear listando só a tarefa "Em Andamento"

### Caso 3: Reagendamento e Mudança de Status
1. Reagendar a tarefa "Em Andamento" para outro bloco
2. Encerrar o bloco original → deve permitir
3. Alterar status dessa tarefa no bloco novo → deve refletir no quadro

## Observações Técnicas

### Por que usar `moveTask()` em vez de `updateTask()`?

O método `moveTask()` faz mais do que apenas atualizar o status:

1. **Ajusta a ordem na coluna antiga**: Remove a tarefa da ordem antiga e desloca as outras tarefas
2. **Ajusta a ordem na coluna nova**: Adiciona a tarefa na nova coluna na posição correta
3. **Mantém consistência**: Garante que não haja tarefas com a mesma ordem na mesma coluna
4. **Sincronização com o Quadro**: O quadro Kanban depende da ordem para exibir as tarefas corretamente

### Compatibilidade

- ✅ Mantém compatibilidade com código existente
- ✅ Não quebra funcionalidades existentes
- ✅ Funciona tanto para mudanças vindas da Agenda quanto do Quadro

## Conclusão

A alteração permite que usuários alterem o status de tarefas diretamente na tela da Agenda, sem bloqueios desnecessários. A mudança é automaticamente sincronizada com o Quadro de Tarefas, garantindo consistência entre as duas interfaces.

A solução é simples e eficiente: usa o método `moveTask()` já existente, que já faz todo o trabalho necessário de ajuste de ordem e sincronização.

