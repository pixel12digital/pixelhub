# Implementação: Exclusão de Tarefas + Prevenção de Duplicidade

**Data:** 2025-01-26  
**Objetivo:** Implementar exclusão de tarefas com soft delete e prevenir criação duplicada de tarefas

---

## 📋 Resumo das Funcionalidades

### 1. Exclusão de Tarefas (Soft Delete)
- Adicionada coluna `deleted_at` na tabela `tasks`
- Todas as queries de listagem filtram tarefas deletadas (`WHERE deleted_at IS NULL`)
- Endpoint `/tasks/delete` para exclusão via AJAX
- Botão "Excluir Tarefa" no modal de detalhes com confirmação
- Card da tarefa é removido do DOM após exclusão

### 2. Prevenção de Duplicidade
- **Frontend:** Botão de submit é desabilitado imediatamente ao criar tarefa
- **Backend:** Verificação de tarefa duplicada antes de inserir
  - Considera: mesmo `project_id`, mesmo `title`, mesmas datas (`start_date`, `due_date`)
  - Janela de tempo: 60 segundos
  - Se duplicada detectada, retorna ID da tarefa existente em vez de criar nova

---

## 📁 Arquivos Alterados

### Backend

1. **Migration: Adiciona `deleted_at`**
   - `database/migrations/20250126_add_deleted_at_to_tasks.php`
   - Adiciona coluna `deleted_at DATETIME NULL` com índice

2. **Service: TaskService**
   - `src/Services/TaskService.php`
   - ✅ Adicionado `WHERE deleted_at IS NULL` em todas as queries de listagem:
     - `getTasksByProject()`
     - `getAllTasks()`
     - `findTask()`
     - `getProjectSummary()`
     - Queries de `moveTask()` (atualização de ordem)
   - ✅ Novo método `deleteTask(int $id, ?int $projectId = null)`: realiza soft delete
   - ✅ Verificação de duplicidade em `createTask()`:
     - Verifica tarefas criadas nos últimos 60 segundos
     - Compara: `project_id`, `title`, `start_date`, `due_date`
     - Se duplicada encontrada, retorna ID da tarefa existente

3. **Controller: TaskBoardController**
   - `src/Controllers/TaskBoardController.php`
   - ✅ Novo método `delete()`: endpoint para exclusão via POST
     - Valida `task_id` e opcionalmente `project_id`
     - Retorna JSON com sucesso/erro

4. **Router**
   - `public/index.php`
   - ✅ Adicionada rota: `POST /tasks/delete` → `TaskBoardController@delete`

### Frontend

5. **View: Board de Tarefas**
   - `views/tasks/board.php`
   - ✅ Botão "Excluir Tarefa" no modal de detalhes (modo visualização)
   - ✅ Função JavaScript `deleteTask()`:
     - Confirmação com `confirm()`
     - Requisição AJAX para `/tasks/delete`
     - Remove card do DOM após exclusão
     - Fecha modal
   - ✅ Prevenção de duplicidade no formulário:
     - Botão de submit desabilitado imediatamente ao submeter
     - Texto muda para "Salvando..."
     - Reabilita botão em caso de erro
   - ✅ Estilo CSS para botão de perigo (`.btn-danger`)

---

## 🔍 Detalhes de Implementação

### Soft Delete

**Migration:**
```sql
ALTER TABLE tasks
ADD COLUMN deleted_at DATETIME NULL AFTER updated_at,
ADD INDEX idx_deleted_at (deleted_at)
```

**Método de Exclusão:**
```php
TaskService::deleteTask(int $id, ?int $projectId = null): bool
```
- Define `deleted_at = NOW()`
- Valida existência da tarefa
- Valida pertencimento ao projeto (se fornecido)

**Filtros em Queries:**
- Todas as queries de listagem incluem: `WHERE deleted_at IS NULL`
- `findTask()` também filtra tarefas deletadas

### Prevenção de Duplicidade

**Regra de Negócio:**
- Tarefa é considerada duplicada se:
  - Mesmo `project_id`
  - Mesmo `title` (case-sensitive)
  - Mesmas datas (`start_date` e `due_date`, ou ambas NULL)
  - Criada nos últimos **60 segundos**

**Comportamento:**
- Se duplicada detectada: retorna ID da tarefa existente (não cria nova)
- Frontend recarrega página normalmente (não há indicativo de duplicidade)

### Relacionamentos Preservados

**Agenda Blocks (`agenda_block_tasks`):**
- Relacionamento mantido (tabela tem `ON DELETE CASCADE`, mas com soft delete não é acionado)
- Queries de agenda continuam funcionando normalmente
- Tarefas deletadas simplesmente não aparecem nas listagens do Kanban

**Checklists:**
- Mantidos intactos
- Não há necessidade de ajuste, pois a tarefa não é deletada fisicamente

**Anexos:**
- Mantidos intactos
- Não há necessidade de ajuste

---

## ✅ Validações Implementadas

1. **Exclusão:**
   - ✅ Validação de `task_id` obrigatório
   - ✅ Validação de existência da tarefa
   - ✅ Validação de pertencimento ao projeto (opcional)
   - ✅ Tratamento de erro se tarefa já estiver deletada

2. **Duplicidade:**
   - ✅ Verificação no backend antes de INSERT
   - ✅ Proteção no frontend (botão desabilitado)
   - ✅ Janela de tempo de 60 segundos

---

## 🧪 Testes Sugeridos

### Exclusão
1. ✅ Excluir tarefa pelo modal de detalhes
2. ✅ Verificar que tarefa não aparece mais no Kanban
3. ✅ Verificar que relacionamentos com agenda são mantidos
4. ✅ Tentar excluir tarefa já deletada (deve retornar erro)

### Duplicidade
1. ✅ Criar tarefa e clicar duas vezes rapidamente
2. ✅ Criar tarefa e recarregar página (F5) após submit
3. ✅ Criar tarefas similares com mais de 60 segundos de diferença (deve criar normalmente)
4. ✅ Criar tarefas com mesmo título mas datas diferentes (deve criar normalmente)

---

## 📝 Observações

- **Soft Delete:** Tarefas não são deletadas fisicamente, apenas marcadas com `deleted_at`
- **Performance:** Índice em `deleted_at` garante queries rápidas
- **Segurança:** Exclusão requer autenticação (`Auth::requireInternal()`)
- **Compatibilidade:** Alterações não quebram funcionalidades existentes
- **Duplicidade:** A verificação é "silenciosa" - se duplicada, retorna ID existente sem aviso ao usuário (comportamento pode ser ajustado futuramente)

---

## 🚀 Próximos Passos (Opcional)

1. **Notificação de Duplicidade:** Exibir mensagem quando tarefa duplicada for detectada
2. **Restauração:** Implementar funcionalidade para restaurar tarefas deletadas
3. **Histórico:** Log de exclusões para auditoria
4. **Período de Duplicidade:** Permitir configurar janela de tempo (atualmente fixo em 60 segundos)











