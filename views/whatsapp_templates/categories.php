<?php
ob_start();
$allCategories = $categories ?? [];
$treeData = $tree ?? [];
$parents = array_filter($allCategories, fn($c) => empty($c['parent_id']));
?>

<style>
.cat-tree { list-style: none; padding: 0; margin: 0; min-height: 40px; }
.cat-tree .cat-tree { padding-left: 28px; min-height: 24px; border-left: 2px solid #e5e7eb; margin-left: 12px; }
.cat-item { margin-bottom: 6px; }
.cat-row {
    display: flex; align-items: center; gap: 10px; padding: 10px 14px;
    background: white; border: 1px solid #e5e7eb; border-radius: 8px;
    cursor: grab; user-select: none; transition: all 0.15s;
}
.cat-row:active { cursor: grabbing; }
.cat-row.drag-over { border-color: #023A8D; background: #f0f5ff; box-shadow: 0 0 0 2px #023A8D33; }
.cat-row.dragging { opacity: 0.4; }
.cat-row .cat-grip { color: #bbb; font-size: 16px; flex-shrink: 0; }
.cat-row .cat-name { flex: 1; font-weight: 600; font-size: 14px; color: #333; }
.cat-row .cat-badge { font-size: 11px; color: #999; white-space: nowrap; }
.cat-row .cat-actions { display: flex; gap: 5px; flex-shrink: 0; }
.cat-row .cat-actions button { padding: 3px 8px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600; }
.cat-row .cat-actions .btn-edit { background: #023A8D; color: white; }
.cat-row .cat-actions .btn-del { background: #dc3545; color: white; }
.cat-drop-zone { min-height: 4px; border-radius: 4px; transition: all 0.15s; margin: 2px 0; }
.cat-drop-zone.drop-active { min-height: 32px; background: #e8f0fe; border: 2px dashed #023A8D; border-radius: 8px; }
.cat-sub-drop { min-height: 6px; margin: 2px 0 2px 40px; border-radius: 4px; transition: all 0.15s; }
.cat-sub-drop.drop-active { min-height: 28px; background: #e8f0fe; border: 2px dashed #6b9bd2; border-radius: 6px; }
.cat-save-bar { display: none; padding: 10px 0; text-align: center; }
.cat-save-bar.visible { display: block; }
</style>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2>Categorias de Templates</h2>
        <p>Arraste para reorganizar. Solte sobre uma categoria para torná-la subcategoria.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="<?= pixelhub_url('/settings/whatsapp-templates') ?>" 
           style="background: #6c757d; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px;">
            ← Templates
        </a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #efe; border-left: 4px solid #3c3; margin-bottom: 20px;">
        <p style="color: #3c3; margin: 0;">
            <?php
            if ($_GET['success'] === 'created') echo 'Categoria criada com sucesso!';
            elseif ($_GET['success'] === 'updated') echo 'Categoria atualizada com sucesso!';
            elseif ($_GET['success'] === 'deleted') echo 'Categoria excluída com sucesso!';
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?php
            if ($_GET['error'] === 'missing_name') echo 'O nome da categoria é obrigatório.';
            elseif ($_GET['error'] === 'database_error') echo 'Erro ao salvar. Tente novamente.';
            elseif ($_GET['error'] === 'delete_failed') echo 'Erro ao excluir categoria.';
            else echo 'Erro desconhecido.';
            ?>
        </p>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 350px 1fr; gap: 20px;">
    <!-- Formulário -->
    <div class="card" style="align-self: start;">
        <h3 style="margin: 0 0 16px 0; font-size: 16px; color: #333;" id="formTitle">Nova Categoria</h3>
        <form method="POST" action="<?= pixelhub_url('/settings/whatsapp-templates/categories/store') ?>" id="categoryForm">
            <input type="hidden" name="id" id="catFormId" value="">
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Nome *</label>
                <input type="text" name="name" id="catFormName" required 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;"
                       placeholder="Ex: E-commerce, Saudação...">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Categoria pai</label>
                <select name="parent_id" id="catFormParent" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    <option value="">Nenhuma (raiz)</option>
                    <?php foreach ($parents as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #666; font-size: 11px;">Ou arraste na lista ao lado</small>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" style="background: #023A8D; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
                    Salvar
                </button>
                <button type="button" onclick="resetCatForm()" style="background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; display: none;" id="catFormCancel">
                    Cancelar
                </button>
            </div>
        </form>
    </div>

    <!-- Lista drag-and-drop -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h3 style="margin: 0; font-size: 16px; color: #333;">Categorias</h3>
            <div id="catSaveStatus" style="font-size: 12px; color: #999;"></div>
        </div>

        <div id="catTreeRoot">
            <?php if (empty($treeData)): ?>
                <p style="color: #666; text-align: center; padding: 30px;">Nenhuma categoria cadastrada.</p>
            <?php else: ?>
                <ul class="cat-tree" id="catTreeList">
                    <?php foreach ($treeData as $cat): ?>
                        <li class="cat-item" data-id="<?= $cat['id'] ?>" data-parent="">
                            <div class="cat-row" draggable="true">
                                <span class="cat-grip">⠿</span>
                                <span class="cat-name"><?= htmlspecialchars($cat['name']) ?></span>
                                <div class="cat-actions">
                                    <button class="btn-edit" onclick="editCat(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['name'])) ?>', '')">Editar</button>
                                    <button class="btn-del" onclick="deleteCat(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['name'])) ?>')">×</button>
                                </div>
                            </div>
                            <?php if (!empty($cat['children'])): ?>
                                <ul class="cat-tree">
                                    <?php foreach ($cat['children'] as $child): ?>
                                        <li class="cat-item" data-id="<?= $child['id'] ?>" data-parent="<?= $cat['id'] ?>">
                                            <div class="cat-row" draggable="true">
                                                <span class="cat-grip">⠿</span>
                                                <span class="cat-name"><?= htmlspecialchars($child['name']) ?></span>
                                                <div class="cat-actions">
                                                    <button class="btn-edit" onclick="editCat(<?= $child['id'] ?>, '<?= htmlspecialchars(addslashes($child['name'])) ?>', '<?= $cat['id'] ?>')">Editar</button>
                                                    <button class="btn-del" onclick="deleteCat(<?= $child['id'] ?>, '<?= htmlspecialchars(addslashes($child['name'])) ?>')">×</button>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// ===== Formulário =====
function editCat(id, name, parentId) {
    document.getElementById('catFormId').value = id;
    document.getElementById('catFormName').value = name;
    document.getElementById('catFormParent').value = parentId || '';
    document.getElementById('formTitle').textContent = 'Editar Categoria';
    document.getElementById('categoryForm').action = '<?= pixelhub_url('/settings/whatsapp-templates/categories/update') ?>';
    document.getElementById('catFormCancel').style.display = 'inline-block';
    document.getElementById('catFormName').focus();
}

function resetCatForm() {
    document.getElementById('catFormId').value = '';
    document.getElementById('catFormName').value = '';
    document.getElementById('catFormParent').value = '';
    document.getElementById('formTitle').textContent = 'Nova Categoria';
    document.getElementById('categoryForm').action = '<?= pixelhub_url('/settings/whatsapp-templates/categories/store') ?>';
    document.getElementById('catFormCancel').style.display = 'none';
}

function deleteCat(id, name) {
    if (!confirm('Excluir "' + name + '"?\n\nTemplates serão movidos para "Sem categoria".')) return;
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= pixelhub_url('/settings/whatsapp-templates/categories/delete') ?>';
    var input = document.createElement('input');
    input.type = 'hidden'; input.name = 'id'; input.value = id;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

// ===== Drag and Drop =====
var draggedItem = null;

document.addEventListener('DOMContentLoaded', function() {
    var list = document.getElementById('catTreeList');
    if (!list) return;

    list.addEventListener('dragstart', function(e) {
        var item = e.target.closest('.cat-item');
        if (!item) return;
        draggedItem = item;
        item.querySelector('.cat-row').classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', item.dataset.id);
    });

    list.addEventListener('dragend', function(e) {
        draggedItem = null;
        document.querySelectorAll('.cat-row.dragging').forEach(function(el) { el.classList.remove('dragging'); });
        document.querySelectorAll('.cat-row.drag-over').forEach(function(el) { el.classList.remove('drag-over'); });
    });

    list.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (!draggedItem) return;

        var targetRow = e.target.closest('.cat-row');
        if (!targetRow) return;
        var targetItem = targetRow.closest('.cat-item');
        if (!targetItem || targetItem === draggedItem) return;
        if (draggedItem.contains(targetItem)) return;

        document.querySelectorAll('.cat-row.drag-over').forEach(function(el) { el.classList.remove('drag-over'); });
        targetRow.classList.add('drag-over');
    });

    list.addEventListener('dragleave', function(e) {
        var targetRow = e.target.closest('.cat-row');
        if (targetRow) targetRow.classList.remove('drag-over');
    });

    list.addEventListener('drop', function(e) {
        e.preventDefault();
        document.querySelectorAll('.cat-row.drag-over').forEach(function(el) { el.classList.remove('drag-over'); });
        if (!draggedItem) return;

        var targetRow = e.target.closest('.cat-row');
        if (!targetRow) return;
        var targetItem = targetRow.closest('.cat-item');
        if (!targetItem || targetItem === draggedItem) return;
        if (draggedItem.contains(targetItem)) return;

        var rect = targetRow.getBoundingClientRect();
        var y = e.clientY - rect.top;
        var h = rect.height;

        // Zona superior (30%): inserir antes
        // Zona central (40%): tornar filho
        // Zona inferior (30%): inserir depois
        if (y < h * 0.3) {
            targetItem.parentNode.insertBefore(draggedItem, targetItem);
            draggedItem.dataset.parent = targetItem.dataset.parent;
        } else if (y > h * 0.7) {
            // Inserir depois
            var next = targetItem.nextElementSibling;
            targetItem.parentNode.insertBefore(draggedItem, next);
            draggedItem.dataset.parent = targetItem.dataset.parent;
        } else {
            // Tornar filho: verifica se target é raiz (não subcategoria de subcategoria)
            if (targetItem.dataset.parent !== '') {
                // Target já é filho, insere ao lado
                targetItem.parentNode.insertBefore(draggedItem, targetItem.nextElementSibling);
                draggedItem.dataset.parent = targetItem.dataset.parent;
            } else {
                // Tornar subcategoria
                var subList = targetItem.querySelector(':scope > ul.cat-tree');
                if (!subList) {
                    subList = document.createElement('ul');
                    subList.className = 'cat-tree';
                    targetItem.appendChild(subList);
                }
                subList.appendChild(draggedItem);
                draggedItem.dataset.parent = targetItem.dataset.id;
            }
        }

        saveOrder();
    });
});

function saveOrder() {
    var status = document.getElementById('catSaveStatus');
    if (status) { status.textContent = 'Salvando...'; status.style.color = '#e67e22'; }

    var items = [];
    var rootList = document.getElementById('catTreeList');
    if (!rootList) return;

    // Percorre itens raiz
    var rootItems = rootList.querySelectorAll(':scope > .cat-item');
    rootItems.forEach(function(item, idx) {
        items.push({ id: parseInt(item.dataset.id), parent_id: null, sort_order: idx });
        item.dataset.parent = '';
        // Filhos
        var subList = item.querySelector(':scope > ul.cat-tree');
        if (subList) {
            var subItems = subList.querySelectorAll(':scope > .cat-item');
            subItems.forEach(function(sub, sidx) {
                items.push({ id: parseInt(sub.dataset.id), parent_id: parseInt(item.dataset.id), sort_order: sidx });
                sub.dataset.parent = item.dataset.id;
            });
        }
    });

    fetch('<?= pixelhub_url('/settings/whatsapp-templates/categories/reorder') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ items: items })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (status) {
            if (data.success) {
                status.textContent = '✓ Salvo';
                status.style.color = '#28a745';
                setTimeout(function() { status.textContent = ''; }, 2000);
            } else {
                status.textContent = '✗ Erro ao salvar';
                status.style.color = '#dc3545';
            }
        }
    })
    .catch(function() {
        if (status) { status.textContent = '✗ Erro de rede'; status.style.color = '#dc3545'; }
    });
}
</script>

<?php
$content = ob_get_clean();
$title = 'Categorias de Templates';
require __DIR__ . '/../layout/main.php';
?>
