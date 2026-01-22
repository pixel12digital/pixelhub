# RELATÓRIO DE INVESTIGAÇÃO - Modal de Detalhes da Tarefa

## Data: 2025-01-XX
## Problemas Investigados:
1. Descrição da tarefa não está sendo salva quando editada no modal de detalhes
2. Modal está sendo cortado na parte inferior (footer não aparece completamente)

---

## 1. MAPEAMENTO DO FLUXO COMPLETO

### 1.1. Arquivos Envolvidos

#### Frontend (Visualização e Interação):
- **`views/tasks/board.php`** (linhas 504-514, 626-847, 944-999)
  - Contém o HTML do modal de detalhes (`#taskDetailModal`)
  - Função `renderTaskDetailModal()` - renderiza o conteúdo do modal
  - Função `saveTaskDetails()` - handler do submit do formulário
  - Função `enableTaskEdit()` - ativa modo edição
  - Função `openTaskDetail()` - abre o modal e carrega dados

#### Backend (API e Processamento):
- **`src/Controllers/TaskBoardController.php`** (linhas 97-183, 237-278)
  - Método `update()` - recebe requisição POST e chama TaskService
  - Método `show()` - retorna dados da tarefa em JSON (incluindo checklist)

- **`src/Services/TaskService.php`** (linhas 240-369)
  - Método `updateTask()` - processa e salva atualizações no banco
  - Método `findTask()` - busca tarefa por ID

#### Banco de Dados:
- **`database/migrations/20251123_create_tasks_table.php`**
  - Tabela: `tasks`
  - Coluna: `description TEXT NULL`

---

### 1.2. Fluxo de Carregamento da Tarefa

```
1. Usuário clica no card da tarefa
   ↓
2. openTaskDetail(taskId) é chamado (board.php:598)
   ↓
3. Fetch GET para '/tasks/{id}' (board.php:603)
   ↓
4. TaskBoardController::show() processa (TaskBoardController.php:237)
   ↓
5. TaskService::findTask() busca no banco (TaskService.php:463)
   ↓
6. Retorna JSON com dados da tarefa (TaskBoardController.php:273)
   ↓
7. renderTaskDetailModal(data, taskId, false) renderiza HTML (board.php:616)
   ↓
8. Modal exibe dados em modo visualização
```

**Trecho relevante - Carregamento:**
```javascript
// board.php:598-622
function openTaskDetail(taskId) {
    document.getElementById('taskDetailModal').style.display = 'block';
    document.getElementById('taskDetailContent').innerHTML = '<p>Carregando...</p>';
    
    fetch('<?= pixelhub_url('/tasks') ?>/' + taskId)
        .then(response => response.json())
        .then(data => {
            window.currentTaskData = data;  // Salva dados globalmente
            window.currentTaskId = taskId;
            renderTaskDetailModal(data, taskId, false);  // Modo visualização
        });
}
```

---

### 1.3. Fluxo de Edição e Salvamento

```
1. Usuário clica em "Editar Tarefa"
   ↓
2. enableTaskEdit() é chamado (board.php:856)
   ↓
3. Alterna classes CSS: view-mode hidden, edit-mode active (board.php:885-886)
   ↓
4. Usuário edita campos (incluindo descrição)
   ↓
5. Usuário clica em "Salvar"
   ↓
6. saveTaskDetails(event) é chamado (board.php:944)
   ↓
7. FormData é criado do formulário #taskDetailsForm (board.php:948)
   ↓
8. Fetch POST para '/tasks/update' (board.php:956)
   ↓
9. TaskBoardController::update() recebe $_POST (TaskBoardController.php:97)
   ↓
10. TaskService::updateTask() processa dados (TaskService.php:240)
    ↓
11. UPDATE no banco (TaskService.php:344-366)
    ↓
12. Retorna JSON com tarefa atualizada (TaskBoardController.php:151-174)
    ↓
13. Frontend atualiza window.currentTaskData (board.php:971-975)
    ↓
14. renderTaskDetailModal() é chamado novamente (board.php:990)
    ↓
15. Modal volta para modo visualização com dados atualizados
```

**Trecho relevante - Handler do botão Salvar:**
```javascript
// board.php:944-999
function saveTaskDetails(event) {
    event.preventDefault();
    
    const form = document.getElementById('taskDetailsForm');
    const formData = new FormData(form);  // Monta payload do formulário
    
    fetch('<?= pixelhub_url('/tasks/update') ?>', {
        method: 'POST',
        body: formData  // Envia FormData (inclui description)
    })
    .then(response => response.json())
    .then(data => {
        if (data.task) {
            window.currentTaskData = {
                ...window.currentTaskData,
                ...data.task  // Atualiza dados locais
            };
        }
        // PROBLEMA POTENCIAL: renderiza modal novamente
        renderTaskDetailModal(window.currentTaskData, window.currentTaskId, false);
    });
}
```

**Trecho relevante - Montagem do campo description no HTML:**
```javascript
// board.php:749-752
html += '<div class="form-group">';
html += '<label for="task_edit_description">Descrição</label>';
html += '<textarea name="description" id="task_edit_description" ...>' + 
        escapeHtml(data.description || '') + '</textarea>';
html += '</div>';
```

**Trecho relevante - Endpoint que recebe:**
```php
// TaskBoardController.php:97-109
public function update(): void
{
    Auth::requireInternal();
    
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    
    if ($id <= 0) {
        $this->json(['error' => 'ID inválido'], 400);
        return;
    }
    
    try {
        TaskService::updateTask($id, $_POST);  // Passa $_POST diretamente
        // ...
    }
}
```

**Trecho relevante - Processamento no Service:**
```php
// TaskService.php:271
$description = isset($data['description']) ? (trim($data['description']) ?: null) : $task['description'];
```

**Trecho relevante - UPDATE no banco:**
```php
// TaskService.php:344-366
$stmt = $db->prepare("
    UPDATE tasks 
    SET title = ?, description = ?, status = ?, `order` = ?, assignee = ?, 
        due_date = ?, start_date = ?, task_type = ?, 
        completed_at = ?, completed_by = ?, completion_note = ?, 
        updated_at = NOW()
    WHERE id = ?
");

$stmt->execute([
    $title,
    $description,  // Campo description é incluído aqui
    $status,
    // ... outros campos
    $id,
]);
```

---

## 2. VERIFICAÇÃO SE A DESCRIÇÃO ESTÁ SENDO ENVIADA

### 2.1. Campo no Formulário

**Localização:** `views/tasks/board.php:750-751`

```html
<textarea name="description" id="task_edit_description" 
          style="...">[valor]</textarea>
```

✅ **Campo existe e tem `name="description"` correto**

### 2.2. Montagem do Payload

**Localização:** `views/tasks/board.php:944-948`

```javascript
function saveTaskDetails(event) {
    event.preventDefault();
    const form = document.getElementById('taskDetailsForm');
    const formData = new FormData(form);  // FormData captura TODOS os campos do form
    // ...
}
```

✅ **FormData captura automaticamente todos os campos com `name`, incluindo `description`**

### 2.3. Verificação no Backend

**Localização:** `src/Services/TaskService.php:271`

```php
$description = isset($data['description']) ? (trim($data['description']) ?: null) : $task['description'];
```

**Análise:**
- Se `$_POST['description']` existe e não está vazio após trim → usa o valor (ou null se vazio)
- Se `$_POST['description']` não existe → mantém valor antigo da tarefa

⚠️ **POSSÍVEL PROBLEMA:** Se o campo description não for enviado no FormData (por algum motivo), o backend mantém o valor antigo. Mas isso não deveria acontecer, pois o campo está no formulário.

### 2.4. Verificação de Conflito de Campos

**Formulário do Modal de Detalhes:**
- ID do formulário: `taskDetailsForm` (board.php:697)
- Campo description: `name="description"`, `id="task_edit_description"` (board.php:751)

**Formulário do Modal de Criar/Editar (outro modal):**
- ID do formulário: `taskForm` (board.php:431)
- Campo description: `name="description"`, `id="task_description"` (board.php:458)

✅ **Não há conflito:** São formulários diferentes com IDs diferentes. O `taskDetailsForm` é o correto para o modal de detalhes.

---

## 3. VERIFICAÇÃO COMO O BACKEND TRATA A DESCRIÇÃO

### 3.1. Recepção dos Dados

**Localização:** `src/Controllers/TaskBoardController.php:97-109`

```php
public function update(): void
{
    // ...
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    // ...
    TaskService::updateTask($id, $_POST);  // Passa $_POST completo
}
```

✅ **Backend recebe `$_POST` completo, incluindo `description`**

### 3.2. Processamento e Validação

**Localização:** `src/Services/TaskService.php:270-271`

```php
// Processa dados
$description = isset($data['description']) ? (trim($data['description']) ?: null) : $task['description'];
```

**Lógica:**
- Se `$data['description']` existe → faz trim
  - Se trim retorna string vazia → converte para `null`
  - Se trim retorna texto → usa o texto
- Se `$data['description']` não existe → mantém `$task['description']` (valor antigo)

⚠️ **OBSERVAÇÃO:** A lógica permite que descrição vazia seja salva como `NULL`, o que é correto.

### 3.3. Salvamento no Banco

**Localização:** `src/Services/TaskService.php:344-366`

```php
$stmt = $db->prepare("
    UPDATE tasks 
    SET title = ?, description = ?, status = ?, ...
    WHERE id = ?
");

$stmt->execute([
    $title,
    $description,  // Campo description é incluído no UPDATE
    // ...
]);
```

✅ **Campo `description` está sendo incluído no UPDATE**

**Tabela e Coluna:**
- Tabela: `tasks`
- Coluna: `description` (TEXT NULL)
- Migration: `database/migrations/20251123_create_tasks_table.php:15`

### 3.4. Diferença entre Edição no Modal vs. Tela Principal

**Modal de Detalhes (problema reportado):**
- Formulário: `#taskDetailsForm`
- Handler: `saveTaskDetails(event)`
- Endpoint: `/tasks/update`
- Processamento: Mesmo `TaskService::updateTask()`

**Modal de Criar/Editar (tela principal):**
- Formulário: `#taskForm`
- Handler: Submit direto do form (board.php:1192-1215)
- Endpoint: `/tasks/update` (se edição) ou `/tasks/store` (se criação)
- Processamento: Mesmo `TaskService::updateTask()`

✅ **Ambos usam o mesmo endpoint e service. Não há diferença no processamento.**

---

## 4. INVESTIGAÇÃO DO PROBLEMA VISUAL (MODAL CORTO)

### 4.1. Estrutura HTML do Modal

**Localização:** `views/tasks/board.php:504-514`

```html
<div id="taskDetailModal" class="modal task-details-modal">
    <div class="modal-content">
        <div class="modal-header">...</div>
        <div id="taskDetailContent">
            <!-- Conteúdo renderizado dinamicamente -->
        </div>
    </div>
</div>
```

### 4.2. CSS do Modal

**Localização:** `views/tasks/board.php:80-137`

```css
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    overflow-y: auto;  /* Modal principal tem scroll */
}

.task-details-modal {
    overflow-y: auto;  /* Modal de detalhes também tem scroll */
}

.modal-content {
    background-color: white;
    margin: 3% auto;
    padding: 30px;
    border-radius: 8px;
    width: 90%;
    max-width: 800px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

/* Modal de detalhes da tarefa */
.task-details-modal .modal-content {
    display: flex;
    flex-direction: column;
    max-height: 90vh;  /* ⚠️ LIMITA ALTURA MÁXIMA */
    margin: 1.75rem auto;
    overflow: hidden;  /* ⚠️ ESCONDE OVERFLOW - SEM SCROLL NO CONTAINER */
}

.task-details-modal #taskDetailContent {
    display: flex;
    flex-direction: column;
    gap: 16px;
    overflow: hidden;  /* ⚠️ SEM SCROLL NO CONTEÚDO PRINCIPAL */
    flex: 1;
    min-height: 0;
}

/* Wrapper do checklist com scroll */
.task-details-checklist-wrapper {
    margin-top: 8px;
    padding-right: 4px;
    flex: 1;
    min-height: 0;
    overflow-y: auto;  /* ✅ APENAS O CHECKLIST TEM SCROLL */
}
```

### 4.3. Estrutura do Conteúdo Renderizado

**Localização:** `views/tasks/board.php:626-830` (função `renderTaskDetailModal`)

A estrutura renderizada é:
```
#taskDetailContent (overflow: hidden, flex: 1)
  ├─ Mensagens de erro/sucesso
  ├─ Formulário #taskDetailsForm
  │   ├─ Modo visualização (.task-view-mode)
  │   └─ Modo edição (.task-edit-mode)
  ├─ Seção Checklist (flex: 1, display: flex, flex-direction: column)
  │   ├─ Título "Checklist" (flex-shrink: 0)
  │   ├─ .task-details-checklist-wrapper (overflow-y: auto, flex: 1)
  │   │   └─ #checklist-items
  │   └─ Input + Botão "Adicionar" (flex-shrink: 0)
  └─ .form-actions (footer com botões) (flex-shrink: 0, margin-top: 20px)
```

### 4.4. Análise do Problema

**Problema Identificado:**

1. **Container principal sem scroll:**
   - `.task-details-modal .modal-content` tem `overflow: hidden` (linha 109)
   - `#taskDetailContent` tem `overflow: hidden` (linha 115)
   - Apenas `.task-details-checklist-wrapper` tem `overflow-y: auto` (linha 125)

2. **Altura limitada:**
   - `.modal-content` tem `max-height: 90vh` (linha 107)
   - Com `overflow: hidden`, conteúdo que excede é cortado

3. **Footer fora da área visível:**
   - O footer (`.form-actions`) está dentro de `#taskDetailContent`
   - Se o conteúdo total (formulário + checklist + footer) exceder `90vh`, o footer fica cortado
   - Como não há scroll no container principal, não é possível rolar até o footer

**Propriedades CSS Suspeitas:**
- `overflow: hidden` em `.task-details-modal .modal-content` (linha 109)
- `overflow: hidden` em `#taskDetailContent` (linha 115)
- `max-height: 90vh` sem scroll no container principal

---

## 5. CONCLUSÕES

### 5.1. Por que a Descrição Não Está Sendo Persistida?

**Análise Técnica:**

Após investigação completa, o código **parece estar correto** em todos os pontos:

1. ✅ Campo `description` existe no formulário com `name="description"`
2. ✅ FormData captura o campo corretamente
3. ✅ Backend recebe `$_POST['description']`
4. ✅ Service processa e salva no banco
5. ✅ UPDATE inclui o campo `description`

**PROVÁVEL CAUSA RAIZ:**

O problema está na **linha 990 de `board.php`**:

```javascript
// Após salvar, renderiza o modal novamente
renderTaskDetailModal(window.currentTaskData, window.currentTaskId, false);
```

**Hipótese:** Quando o modal é renderizado novamente após salvar, ele usa `window.currentTaskData` que pode não ter sido atualizado corretamente com a descrição nova. 

**Verificação necessária:**
- O endpoint `/tasks/update` retorna `data.task.description` corretamente?
- A linha 975 atualiza `window.currentTaskData.description` corretamente?

**Trecho suspeito:**
```javascript
// board.php:971-975
if (data.task) {
    window.currentTaskData = {
        ...window.currentTaskData,  // Spread do objeto antigo
        ...data.task  // Spread do objeto novo (deveria sobrescrever)
    };
}
```

Se `data.task.description` não vier no response, o spread não atualiza a descrição.

**Ponto Fraco Identificado:**
- **Frontend não atualiza dados locais corretamente** OU
- **Backend não retorna `description` no response** OU
- **Campo description está sendo enviado, mas há algum problema na atualização do estado local**

### 5.2. Análise do Problema do Modal Cortado

**Causa Técnica:**

O modal está cortado porque:

1. **Container principal sem scroll:**
   - `.modal-content` tem `overflow: hidden` (sem scroll)
   - `#taskDetailContent` também tem `overflow: hidden`

2. **Altura fixa:**
   - `max-height: 90vh` limita a altura
   - Conteúdo que excede é cortado (não aparece scroll)

3. **Footer fora da área:**
   - Footer está dentro de `#taskDetailContent`
   - Se conteúdo total > 90vh, footer fica invisível
   - Não há como rolar até ele

**Solução Necessária:**
- Adicionar `overflow-y: auto` em `#taskDetailContent` OU
- Mover footer para fora de `#taskDetailContent` OU
- Ajustar estrutura flex para permitir scroll no container correto

---

## 6. RECOMENDAÇÕES PARA CORREÇÃO

### 6.1. Descrição Não Salva

1. **Verificar response do backend:**
   - Adicionar `console.log(data.task)` na linha 961 para ver o que está vindo
   - Verificar se `data.task.description` está presente

2. **Garantir atualização correta:**
   - Verificar se a linha 975 está atualizando `window.currentTaskData.description`
   - Considerar atualizar explicitamente: `window.currentTaskData.description = data.task.description`

3. **Verificar se campo está sendo enviado:**
   - Adicionar `console.log(Array.from(formData.entries()))` na linha 948 para ver todos os campos enviados

### 6.2. Modal Cortado

1. **Adicionar scroll no container principal:**
   - Mudar `overflow: hidden` para `overflow-y: auto` em `#taskDetailContent`

2. **Ajustar estrutura flex:**
   - Garantir que footer fique sempre visível ou acessível via scroll

3. **Testar com conteúdo longo:**
   - Verificar se scroll funciona corretamente com muita descrição e muitos itens de checklist

---

## 7. ARQUIVOS E CAMINHOS COMPLETOS

### 7.1. Carregamento da Tarefa
- `views/tasks/board.php` (linhas 598-622, 626-847)
- `src/Controllers/TaskBoardController.php` (linhas 237-278)
- `src/Services/TaskService.php` (linhas 463-479)

### 7.2. Edição da Tarefa
- `views/tasks/board.php` (linhas 856-898, 944-999)
- `src/Controllers/TaskBoardController.php` (linhas 97-183)
- `src/Services/TaskService.php` (linhas 240-369)

### 7.3. Salvamento da Descrição
- `views/tasks/board.php` (linhas 944-999) - Frontend
- `src/Controllers/TaskBoardController.php` (linhas 97-109) - Endpoint
- `src/Services/TaskService.php` (linhas 271, 344-366) - Processamento e UPDATE

### 7.4. CSS do Modal
- `views/tasks/board.php` (linhas 80-137, 104-126)

---

**Fim do Relatório**

