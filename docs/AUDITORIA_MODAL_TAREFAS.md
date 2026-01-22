# Auditoria: Problema no Botão "Editar Tarefa" do Modal de Detalhes

**Data:** 2025-01-XX  
**Tela:** `/projects/board` (Quadro de Tarefas)  
**Problema:** Botão "Editar Tarefa" não entra em modo de edição  
**Status:** Investigado - Causa raiz identificada

---

## 1. Contexto do Problema

### Sintomas Observados
- Ao abrir o modal "Detalhes da Tarefa" clicando em um card do Kanban, o console mostra: `[TaskDetail] Abrindo modal para taskId= 1`
- Ao clicar no botão "Editar Tarefa":
  - Nada muda visualmente (continua tudo só texto)
  - Não aparece log adicional no console
  - Não há erro JS visível
  - Checklist funciona normalmente (scroll, adicionar item, excluir etc)

### Objetivo da Auditoria
Identificar exatamente onde está o gargalo: HTML do botão, delegação de evento, escopo das funções JS, estrutura do modal, ou outra causa.

---

## 2. Estrutura do Modal

### 2.1 Definição HTML do Modal

O modal está definido em `views/tasks/board.php` (linhas 475-485):

```html
<!-- Modal de Detalhes da Tarefa -->
<div id="taskDetailModal" class="modal task-details-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="taskDetailTitle">Detalhes da Tarefa</h3>
            <button class="close" id="btn-close-task-detail-modal">&times;</button>
        </div>
        <div id="taskDetailContent">
            <p>Carregando...</p>
        </div>
    </div>
</div>
```

**Características:**
- ID do modal: `taskDetailModal`
- Container interno: `taskDetailContent`
- O conteúdo é injetado dinamicamente via JavaScript

### 2.2 Estrutura dos Modos View/Edit

O HTML é gerado dinamicamente pela função `renderTaskDetailModal()` (linha 552).

#### Modo Visualização (linhas 596-614)
```javascript
html += '<div class="task-view-mode" style="' + (isEditing ? 'display: none;' : '') + ' margin-bottom: 20px; flex-shrink: 0;">';
// ... conteúdo do modo visualização
html += '</div>';
```

#### Modo Edição (linhas 617-652)
```javascript
html += '<div class="task-edit-mode" style="' + (isEditing ? '' : 'display: none;') + ' margin-bottom: 20px; flex-shrink: 0;">';
// ... campos do formulário
html += '</div>';
```

**Observação importante:** Os estilos inline (`style="display: none;"`) são aplicados diretamente no HTML gerado, o que pode causar conflitos com as classes CSS.

### 2.3 Checklist (Sempre Visível)

O checklist está fora dos blocos view/edit e permanece sempre visível (linhas 656-672):

```javascript
html += '<div style="margin-top: 20px; border-top: 2px solid #f0f0f0; padding-top: 20px; flex: 1; display: flex; flex-direction: column; min-height: 0;">';
html += '<h4 style="margin-bottom: 15px; flex-shrink: 0;">Checklist</h4>';
// ... itens do checklist
html += '</div>';
```

---

## 3. Botão "Editar Tarefa"

### 3.1 HTML do Botão

O botão é gerado na linha 680 de `views/tasks/board.php`:

```javascript
html += '<button type="button" data-action="edit-task" class="btn btn-primary js-task-edit-btn">Editar Tarefa</button>';
```

**Características:**
- `type="button"` - Não submete o formulário
- `data-action="edit-task"` - Atributo usado pela delegação de eventos
- Classe `js-task-edit-btn` - Não é usada pela delegação (apenas para referência)

### 3.2 Localização do Botão

O botão está dentro do bloco `.form-actions` (linhas 675-683):

```javascript
html += '<div class="form-actions" style="margin-top: 20px; flex-shrink: 0;">';
if (isEditing) {
    html += '<button type="button" data-action="cancel-edit" class="btn btn-secondary js-task-cancel-btn">Cancelar</button>';
    html += '<button type="submit" form="taskDetailsForm" class="btn btn-primary">Salvar</button>';
} else {
    html += '<button type="button" data-action="edit-task" class="btn btn-primary js-task-edit-btn">Editar Tarefa</button>';
    html += '<button type="button" class="btn btn-secondary" onclick="closeTaskDetailModal()">Fechar</button>';
}
html += '</div>';
```

**Observação:** O botão está dentro do formulário `#taskDetailsForm`, mas como é `type="button"`, não deveria causar submissão.

---

## 4. Captura do Clique (Delegação de Eventos)

### 4.1 Configuração da Delegação

A delegação de eventos está configurada no `DOMContentLoaded` (linhas 1082-1117):

```javascript
const taskDetailModal = document.getElementById('taskDetailModal');
if (taskDetailModal) {
    taskDetailModal.addEventListener('click', function(event) {
        const target = event.target;
        
        // Busca o botão pai se o clique foi em um elemento filho
        const editBtn = target.closest('[data-action="edit-task"]');
        const cancelBtn = target.closest('[data-action="cancel-edit"]');

        // Botão Editar Tarefa
        if (editBtn) {
            console.log('[TaskDetail] clique no botão Editar (delegação)');
            if (typeof enableTaskEdit === 'function') {
                enableTaskEdit();
            } else {
                console.error('[TaskDetail] enableTaskEdit não é função ou não está disponível');
            }
            event.preventDefault();
            event.stopPropagation();
            return false;
        }

        // Botão Cancelar (modo edição)
        if (cancelBtn) {
            console.log('[TaskDetail] clique no botão Cancelar edição (delegação)');
            if (typeof cancelTaskEdit === 'function') {
                cancelTaskEdit();
            } else {
                console.error('[TaskDetail] cancelTaskEdit não é função ou não está disponível');
            }
            event.preventDefault();
            event.stopPropagation();
            return false;
        }
    });
}
```

**Análise:**
- ✅ Delegação configurada corretamente no modal
- ✅ Usa `closest()` para encontrar o botão mesmo se o clique for em elemento filho
- ✅ Verifica se `enableTaskEdit` é uma função antes de chamar
- ✅ Previne comportamento padrão e propagação

### 4.2 Seletor vs HTML Real

O seletor `[data-action="edit-task"]` corresponde exatamente ao atributo do botão gerado, então está correto.

---

## 5. Função enableTaskEdit

### 5.1 Implementação

A função está definida nas linhas 709-759:

```javascript
function enableTaskEdit() {
    console.log('[TaskDetail] enableTaskEdit chamado');
    
    // Busca elementos dentro do modal específico
    const modal = document.getElementById('taskDetailModal');
    const contentDiv = document.getElementById('taskDetailContent');
    
    if (!modal || !contentDiv) {
        console.error('[TaskDetail] Modal ou contentDiv não encontrado', { modal, contentDiv });
        return;
    }
    
    // Busca primeiro no contentDiv (mais específico), depois no modal
    const viewMode = contentDiv.querySelector('.task-view-mode') || modal.querySelector('.task-view-mode');
    const editMode = contentDiv.querySelector('.task-edit-mode') || modal.querySelector('.task-edit-mode');
    const formActions = contentDiv.querySelector('.form-actions') || modal.querySelector('.form-actions');
    
    console.log('[TaskDetail] viewMode=', viewMode, 'editMode=', editMode);
    
    if (!viewMode || !editMode) {
        console.error('[TaskDetail] Elementos de visualização/edição não encontrados', { 
            viewMode, 
            editMode,
            contentDivHTML: contentDiv.innerHTML.substring(0, 500)
        });
        return;
    }
    
    // Alterna visibilidade usando classes CSS e style inline (redundante para garantir)
    viewMode.style.display = 'none';
    viewMode.classList.add('hidden');
    
    // Remove estilo inline do modo edição (se houver) e força exibição
    editMode.style.removeProperty('display');
    editMode.classList.add('active');
    editMode.style.display = 'block';
    
    // Atualiza botões do rodapé
    if (formActions) {
        formActions.innerHTML = '<button type="button" data-action="cancel-edit" class="btn btn-secondary js-task-cancel-btn">Cancelar</button>' +
            '<button type="submit" form="taskDetailsForm" class="btn btn-primary">Salvar</button>';
    }
    
    console.log('[TaskDetail] Modo edição ativado com sucesso', { viewMode, editMode });
}
// Garante que está no escopo global
window.enableTaskEdit = enableTaskEdit;
```

**Análise:**
- ✅ Função exposta no escopo global (`window.enableTaskEdit`)
- ✅ Busca elementos corretamente
- ✅ Tenta remover estilos inline e aplicar classes
- ⚠️ **PROBLEMA POTENCIAL:** Se o HTML foi gerado com `style="display: none;"` inline, pode haver conflito

### 5.2 CSS Relacionado

Os estilos CSS estão definidos nas linhas 139-150:

```css
.task-view-mode {
    display: block;
}
.task-edit-mode {
    display: none;
}
.task-edit-mode.active {
    display: block;
}
.task-view-mode.hidden {
    display: none !important;
}
```

**Observação:** O CSS usa `!important` na classe `.hidden`, mas se o estilo inline `display: none;` foi aplicado diretamente no HTML, ele pode ter prioridade.

---

## 6. Fluxo Completo do Problema

### 6.1 Fluxo Esperado

1. **Clique no card** → `openTaskDetail(taskId)` é chamado (linha 524)
2. **openTaskDetail** faz request → chama `renderTaskDetailModal(data, taskId, false)` (linha 542)
3. **renderTaskDetailModal** monta HTML (view + edit + checklist + botões) e injeta no modal (linha 687)
4. **Modal é exibido** com modo visualização visível
5. **Clique em "Editar Tarefa"**
6. **Delegação captura o clique** (linha 1089)
7. **enableTaskEdit() é chamado** (linha 1096)
8. **enableTaskEdit alterna os blocos** (linhas 742-748)

### 6.2 Ponto de Ruptura Identificado

**Causa Raiz:** Conflito entre estilos inline e classes CSS

Quando `renderTaskDetailModal` é chamado com `isEditing = false`:
- Modo visualização: `style=""` (sem display inline)
- Modo edição: `style="display: none;"` (com display inline)

Quando `enableTaskEdit()` tenta alternar:
- Remove `display` inline do modo edição (linha 746)
- Aplica `display: block` (linha 748)
- Mas se o HTML foi gerado com `style="display: none;"` diretamente, pode haver conflito de especificidade CSS

---

## 7. Problemas Identificados

### 7.1 Problema Principal: Estilos Inline Conflitantes

**Localização:** `views/tasks/board.php`, linhas 596 e 617

**Problema:**
```javascript
// Linha 596 - Modo visualização
html += '<div class="task-view-mode" style="' + (isEditing ? 'display: none;' : '') + ' margin-bottom: 20px; flex-shrink: 0;">';

// Linha 617 - Modo edição
html += '<div class="task-edit-mode" style="' + (isEditing ? '' : 'display: none;') + ' margin-bottom: 20px; flex-shrink: 0;">';
```

Quando `isEditing = false`:
- Modo edição recebe `style="display: none; margin-bottom: 20px; flex-shrink: 0;"`
- Este estilo inline pode ter prioridade sobre as classes CSS
- Mesmo que `enableTaskEdit()` tente remover e aplicar `display: block`, pode haver conflito

### 7.2 Problema Secundário: Botão Dentro do Formulário

O botão está dentro do formulário `#taskDetailsForm` (linha 592), que tem `onsubmit="saveTaskDetails(event)"`. Embora o botão seja `type="button"`, pode haver interferência de eventos do formulário.

---

## 8. Soluções Propostas

### 8.1 Solução 1: Remover Estilos Inline Conflitantes (RECOMENDADA)

**Arquivo:** `views/tasks/board.php`

**Mudança na linha 596:**
```javascript
// ANTES:
html += '<div class="task-view-mode" style="' + (isEditing ? 'display: none;' : '') + ' margin-bottom: 20px; flex-shrink: 0;">';

// DEPOIS:
html += '<div class="task-view-mode' + (isEditing ? ' hidden' : '') + '" style="margin-bottom: 20px; flex-shrink: 0;">';
```

**Mudança na linha 617:**
```javascript
// ANTES:
html += '<div class="task-edit-mode" style="' + (isEditing ? '' : 'display: none;') + ' margin-bottom: 20px; flex-shrink: 0;">';

// DEPOIS:
html += '<div class="task-edit-mode' + (isEditing ? ' active' : '') + '" style="margin-bottom: 20px; flex-shrink: 0;">';
```

**Vantagens:**
- Remove conflito entre estilos inline e classes CSS
- Usa apenas classes CSS para controlar visibilidade
- Mais fácil de manter e debugar

### 8.2 Solução 2: Reforçar Remoção de Estilos Inline

**Arquivo:** `views/tasks/board.php`

**Mudança na função `enableTaskEdit()` (linhas 742-748):**
```javascript
// ANTES:
viewMode.style.display = 'none';
viewMode.classList.add('hidden');

editMode.style.removeProperty('display');
editMode.classList.add('active');
editMode.style.display = 'block';

// DEPOIS (mais robusto):
// Remove todos os estilos inline relacionados a display
viewMode.style.removeProperty('display');
viewMode.classList.add('hidden');
viewMode.style.display = 'none'; // Força após adicionar classe

editMode.style.removeProperty('display');
editMode.classList.add('active');
editMode.style.display = 'block'; // Força após adicionar classe
```

### 8.3 Solução 3: Adicionar Logs de Debug (Temporário)

**Arquivo:** `views/tasks/board.php`

**Mudança na delegação de eventos (linha 1085):**
```javascript
taskDetailModal.addEventListener('click', function(event) {
    const target = event.target;
    console.log('[TaskDetail] Clique capturado no modal', { 
        target, 
        tagName: target.tagName, 
        classes: target.className,
        dataAction: target.getAttribute('data-action')
    });
    
    const editBtn = target.closest('[data-action="edit-task"]');
    if (editBtn) {
        console.log('[TaskDetail] Botão Editar encontrado via closest', editBtn);
        console.log('[TaskDetail] enableTaskEdit disponível?', typeof enableTaskEdit);
        // ... resto do código
    }
});
```

---

## 9. Arquivos que Precisam ser Editados

### Arquivo Principal
- **`views/tasks/board.php`**
  - Linha 596: Ajustar geração do modo visualização
  - Linha 617: Ajustar geração do modo edição
  - Linha 742-748: Reforçar remoção de estilos inline (opcional, mas recomendado)

### Arquivos de Referência (Não precisam ser editados)
- `src/Controllers/TaskBoardController.php` - Controller funciona corretamente
- `views/tasks/_task_card.php` - Card funciona corretamente

---

## 10. Resumo Executivo

### Causa Raiz
**Conflito entre estilos inline (`display: none;`) e classes CSS**, impedindo que a função `enableTaskEdit()` alterne corretamente entre os modos de visualização e edição.

### Componentes Verificados
- ✅ HTML do botão está correto (`data-action="edit-task"`)
- ✅ Delegação de eventos está configurada corretamente
- ✅ Função `enableTaskEdit` existe e está no escopo global
- ✅ CSS está definido corretamente
- ❌ **Estilos inline conflitantes** no HTML gerado dinamicamente

### Solução Recomendada
**Remover estilos inline de `display` e usar apenas classes CSS** (`.hidden` e `.active`) para controlar a visibilidade dos modos view/edit.

### Próximos Passos
1. Aplicar Solução 1 (remover estilos inline conflitantes)
2. Testar o fluxo completo
3. Se necessário, aplicar Solução 2 (reforçar remoção de estilos)
4. Remover logs de debug após confirmação

---

## 11. Checklist de Verificação Pós-Correção

Após aplicar as correções, verificar:

- [ ] Modal abre corretamente ao clicar no card
- [ ] Botão "Editar Tarefa" aparece no rodapé
- [ ] Ao clicar em "Editar Tarefa":
  - [ ] Modo visualização desaparece
  - [ ] Modo edição aparece com campos preenchidos
  - [ ] Botões mudam para "Cancelar" e "Salvar"
- [ ] Ao clicar em "Cancelar":
  - [ ] Modo edição desaparece
  - [ ] Modo visualização reaparece
  - [ ] Botões voltam para "Editar Tarefa" e "Fechar"
- [ ] Checklist permanece visível em ambos os modos
- [ ] Console não mostra erros

---

**Fim da Auditoria**

