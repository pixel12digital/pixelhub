<?php
ob_start();
$allCategories = $categories ?? [];
$parents = array_filter($allCategories, fn($c) => empty($c['parent_id']));
$children = array_filter($allCategories, fn($c) => !empty($c['parent_id']));
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2>Categorias de Templates</h2>
        <p>Organize seus templates em categorias e subcategorias</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="<?= pixelhub_url('/settings/whatsapp-templates') ?>" 
           style="background: #6c757d; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
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

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Formulário de criação -->
    <div class="card">
        <h3 style="margin: 0 0 16px 0; font-size: 16px; color: #333;" id="formTitle">Nova Categoria</h3>
        <form method="POST" action="<?= pixelhub_url('/settings/whatsapp-templates/categories/store') ?>" id="categoryForm">
            <input type="hidden" name="id" id="catFormId" value="">
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Nome *</label>
                <input type="text" name="name" id="catFormName" required 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;"
                       placeholder="Ex: E-commerce, Saudação, Cobrança...">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Categoria pai (opcional)</label>
                <select name="parent_id" id="catFormParent" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    <option value="">Nenhuma (categoria raiz)</option>
                    <?php foreach ($parents as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #666; font-size: 12px;">Se selecionada, esta será uma subcategoria</small>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Ordem</label>
                <input type="number" name="sort_order" id="catFormOrder" value="0" min="0"
                       style="width: 100px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" 
                        style="background: #023A8D; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
                    Salvar
                </button>
                <button type="button" onclick="resetCategoryForm()" 
                        style="background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; display: none;" id="catFormCancel">
                    Cancelar
                </button>
            </div>
        </form>
    </div>

    <!-- Lista de categorias -->
    <div class="card">
        <h3 style="margin: 0 0 16px 0; font-size: 16px; color: #333;">Categorias Existentes</h3>
        
        <?php if (empty($parents)): ?>
            <p style="color: #666; text-align: center; padding: 20px;">Nenhuma categoria cadastrada.</p>
        <?php else: ?>
            <?php foreach ($parents as $parent): 
                $subs = array_filter($children, fn($c) => $c['parent_id'] == $parent['id']);
                $tplCount = $parent['template_count'] ?? 0;
                $subCount = $parent['subcategory_count'] ?? 0;
            ?>
                <div style="border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 12px; overflow: hidden;">
                    <!-- Categoria pai -->
                    <div style="padding: 12px 16px; background: #f8f9fa; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="font-size: 14px; color: #333;"><?= htmlspecialchars($parent['name']) ?></strong>
                            <span style="font-size: 11px; color: #666; margin-left: 8px;">
                                <?= $tplCount ?> template<?= $tplCount != 1 ? 's' : '' ?>
                                <?php if ($subCount > 0): ?>
                                    · <?= $subCount ?> subcategoria<?= $subCount != 1 ? 's' : '' ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div style="display: flex; gap: 6px;">
                            <button onclick="editCategory(<?= $parent['id'] ?>, '<?= htmlspecialchars(addslashes($parent['name'])) ?>', '', <?= (int)($parent['sort_order'] ?? 0) ?>)" 
                                    style="background: #023A8D; color: white; padding: 4px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                Editar
                            </button>
                            <form method="POST" action="<?= pixelhub_url('/settings/whatsapp-templates/categories/delete') ?>" 
                                  onsubmit="return confirm('Excluir categoria &quot;<?= htmlspecialchars(addslashes($parent['name'])) ?>&quot;?\n\nTemplates serão movidos para &quot;Sem categoria&quot;.');" 
                                  style="display: inline; margin: 0;">
                                <input type="hidden" name="id" value="<?= $parent['id'] ?>">
                                <button type="submit" style="background: #c33; color: white; padding: 4px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                    Excluir
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Subcategorias -->
                    <?php if (!empty($subs)): ?>
                        <?php foreach ($subs as $sub): 
                            $subTplCount = $sub['template_count'] ?? 0;
                        ?>
                            <div style="padding: 10px 16px 10px 36px; border-top: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <span style="color: #999; margin-right: 4px;">└</span>
                                    <span style="font-size: 13px; color: #555;"><?= htmlspecialchars($sub['name']) ?></span>
                                    <span style="font-size: 11px; color: #999; margin-left: 6px;"><?= $subTplCount ?> template<?= $subTplCount != 1 ? 's' : '' ?></span>
                                </div>
                                <div style="display: flex; gap: 6px;">
                                    <button onclick="editCategory(<?= $sub['id'] ?>, '<?= htmlspecialchars(addslashes($sub['name'])) ?>', '<?= $sub['parent_id'] ?>', <?= (int)($sub['sort_order'] ?? 0) ?>)" 
                                            style="background: #023A8D; color: white; padding: 3px 8px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;">
                                        Editar
                                    </button>
                                    <form method="POST" action="<?= pixelhub_url('/settings/whatsapp-templates/categories/delete') ?>" 
                                          onsubmit="return confirm('Excluir subcategoria &quot;<?= htmlspecialchars(addslashes($sub['name'])) ?>&quot;?');" 
                                          style="display: inline; margin: 0;">
                                        <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                        <button type="submit" style="background: #c33; color: white; padding: 3px 8px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;">
                                            Excluir
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function editCategory(id, name, parentId, sortOrder) {
    document.getElementById('catFormId').value = id;
    document.getElementById('catFormName').value = name;
    document.getElementById('catFormParent').value = parentId || '';
    document.getElementById('catFormOrder').value = sortOrder || 0;
    document.getElementById('formTitle').textContent = 'Editar Categoria';
    document.getElementById('categoryForm').action = '<?= pixelhub_url('/settings/whatsapp-templates/categories/update') ?>';
    document.getElementById('catFormCancel').style.display = 'inline-block';
    document.getElementById('catFormName').focus();
}

function resetCategoryForm() {
    document.getElementById('catFormId').value = '';
    document.getElementById('catFormName').value = '';
    document.getElementById('catFormParent').value = '';
    document.getElementById('catFormOrder').value = '0';
    document.getElementById('formTitle').textContent = 'Nova Categoria';
    document.getElementById('categoryForm').action = '<?= pixelhub_url('/settings/whatsapp-templates/categories/store') ?>';
    document.getElementById('catFormCancel').style.display = 'none';
}
</script>

<?php
$content = ob_get_clean();
$title = 'Categorias de Templates';
require __DIR__ . '/../layout/main.php';
?>
