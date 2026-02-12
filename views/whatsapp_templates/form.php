<?php
ob_start();

$isEdit = !empty($template);
$template = $template ?? [];
$variables = !empty($template['variables']) ? json_decode($template['variables'], true) : [];
$variablesList = !empty($variables) ? implode(', ', $variables) : '';

// Variáveis padrão disponíveis
$defaultVariables = [
    'nome' => 'Nome do cliente',
    'clientName' => 'Nome do cliente (alias)',
    'dominio' => 'Domínio principal do cliente',
    'domain' => 'Domínio (alias)',
    'valor' => 'Valor do plano de hospedagem',
    'amount' => 'Valor (alias)',
    'linkAfiliado' => 'Link de afiliado',
    'affiliateLink' => 'Link de afiliado (alias)',
    'email' => 'Email do cliente',
    'telefone' => 'Telefone do cliente',
    'phone' => 'Telefone (alias)',
];
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2><?= $isEdit ? 'Editar Template' : 'Novo Template' ?></h2>
        <p><?= $isEdit ? 'Editar template de WhatsApp' : 'Criar novo template de WhatsApp' ?></p>
    </div>
    <div>
        <a href="<?= pixelhub_url('/settings/whatsapp-templates') ?>" 
           style="background: #6c757d; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
            Voltar
        </a>
    </div>
</div>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?php
            $error = $_GET['error'];
            if ($error === 'missing_name') {
                echo 'O nome do template é obrigatório.';
            } elseif ($error === 'missing_content') {
                echo 'O conteúdo do template é obrigatório.';
            } elseif ($error === 'database_error') {
                echo 'Erro ao salvar template. Tente novamente.';
            } else {
                echo 'Erro desconhecido.';
            }
            ?>
        </p>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= pixelhub_url($isEdit ? '/settings/whatsapp-templates/update' : '/settings/whatsapp-templates/store') ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= $template['id'] ?>">
        <?php endif; ?>

        <div style="margin-bottom: 20px;">
            <label for="name" style="display: block; margin-bottom: 5px; font-weight: 600;">Nome do Template *</label>
            <input type="text" 
                   id="name" 
                   name="name" 
                   value="<?= htmlspecialchars($template['name'] ?? '') ?>" 
                   required 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            <small style="color: #666; font-size: 12px;">Nome amigável para identificar o template</small>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="code" style="display: block; margin-bottom: 5px; font-weight: 600;">Código (opcional)</label>
            <input type="text" 
                   id="code" 
                   name="code" 
                   value="<?= htmlspecialchars($template['code'] ?? '') ?>" 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            <small style="color: #666; font-size: 12px;">Código único para referência (pode ser NULL para templates genéricos)</small>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="category_id" style="display: block; margin-bottom: 5px; font-weight: 600;">Categoria *</label>
            <select id="category_id" 
                    name="category_id" 
                    required 
                    style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                <option value="">Selecione...</option>
                <?php
                $allCategories = $allCategories ?? [];
                $currentCategoryId = $template['category_id'] ?? null;
                // Separa pais e filhos
                $parents = array_filter($allCategories, fn($c) => empty($c['parent_id']));
                $children = array_filter($allCategories, fn($c) => !empty($c['parent_id']));
                foreach ($parents as $parent):
                    $selected = ($currentCategoryId == $parent['id']) ? 'selected' : '';
                ?>
                    <option value="<?= $parent['id'] ?>" <?= $selected ?>><?= htmlspecialchars($parent['name']) ?></option>
                    <?php foreach ($children as $child):
                        if ($child['parent_id'] != $parent['id']) continue;
                        $selectedChild = ($currentCategoryId == $child['id']) ? 'selected' : '';
                    ?>
                        <option value="<?= $child['id'] ?>" <?= $selectedChild ?>>&nbsp;&nbsp;&nbsp;└ <?= htmlspecialchars($child['name']) ?></option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
            <small style="color: #666; font-size: 12px;">
                <a href="<?= pixelhub_url('/settings/whatsapp-templates/categories') ?>" style="color: #023A8D;">Gerenciar categorias</a>
            </small>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="description" style="display: block; margin-bottom: 5px; font-weight: 600;">Descrição (opcional)</label>
            <textarea id="description" 
                      name="description" 
                      rows="2" 
                      style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; resize: vertical;"><?= htmlspecialchars($template['description'] ?? '') ?></textarea>
            <small style="color: #666; font-size: 12px;">Descrição curta do propósito do template</small>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="content" style="display: block; margin-bottom: 5px; font-weight: 600;">Conteúdo do Template *</label>
            <textarea id="content" 
                      name="content" 
                      rows="10" 
                      required 
                      style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; font-family: monospace; resize: vertical;"><?= htmlspecialchars($template['content'] ?? '') ?></textarea>
            <small style="color: #666; font-size: 12px;">Use variáveis no formato {variavel}, por exemplo: {nome}, {dominio}, {valor}</small>
        </div>

        <!-- Painel de Ajuda: Variáveis Disponíveis -->
        <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #023A8D;">
            <h4 style="margin: 0 0 10px 0; color: #023A8D;">Variáveis Disponíveis</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px; font-size: 13px;">
                <?php foreach ($defaultVariables as $var => $desc): ?>
                    <div style="padding: 8px; background: white; border-radius: 4px;">
                        <code style="background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-weight: 600;">{<?= htmlspecialchars($var) ?>}</code>
                        <span style="color: #666; margin-left: 8px;"><?= htmlspecialchars($desc) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($variablesList)): ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                    <strong>Variáveis detectadas neste template:</strong>
                    <code style="background: #e9ecef; padding: 4px 8px; border-radius: 3px; margin-left: 8px;"><?= htmlspecialchars($variablesList) ?></code>
                </div>
            <?php endif; ?>
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" 
                       name="is_active" 
                       value="1" 
                       <?= ($template['is_active'] ?? 1) ? 'checked' : '' ?> 
                       style="width: 18px; height: 18px;">
                <span style="font-weight: 600;">Template ativo</span>
            </label>
            <small style="color: #666; font-size: 12px; display: block; margin-left: 26px;">Templates inativos não aparecem na lista de seleção</small>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" 
                    style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
                <?= $isEdit ? 'Atualizar Template' : 'Criar Template' ?>
            </button>
            <a href="<?= pixelhub_url('/settings/whatsapp-templates') ?>" 
               style="background: #6c757d; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
                Cancelar
            </a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
$title = ($isEdit ? 'Editar' : 'Novo') . ' Template WhatsApp';
require __DIR__ . '/../layout/main.php';
?>

