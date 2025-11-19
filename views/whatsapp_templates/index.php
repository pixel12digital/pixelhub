<?php
ob_start();
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2>Mensagens WhatsApp</h2>
        <p>Gerenciar templates genéricos de WhatsApp</p>
    </div>
    <div>
        <a href="<?= pixelhub_url('/settings/whatsapp-templates/create') ?>" 
           style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; display: inline-block;">
            Novo Template
        </a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #efe; border-left: 4px solid #3c3; margin-bottom: 20px;">
        <p style="color: #3c3; margin: 0;">
            <?php
            if ($_GET['success'] === 'created') {
                echo 'Template criado com sucesso!';
            } elseif ($_GET['success'] === 'updated') {
                echo 'Template atualizado com sucesso!';
            } elseif ($_GET['success'] === 'deleted') {
                echo 'Template excluído com sucesso!';
            } elseif ($_GET['success'] === 'toggled') {
                echo 'Status do template alterado com sucesso!';
            }
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?php
            $error = $_GET['error'];
            if ($error === 'not_found') {
                echo 'Template não encontrado.';
            } elseif ($error === 'delete_failed') {
                echo 'Erro ao excluir template.';
            } else {
                echo 'Erro desconhecido.';
            }
            ?>
        </p>
    </div>
<?php endif; ?>

<!-- Filtro por categoria -->
<div class="card" style="margin-bottom: 20px;">
    <form method="get" action="<?= pixelhub_url('/settings/whatsapp-templates') ?>" style="display: flex; gap: 10px; align-items: center;">
        <label style="font-weight: 600;">Categoria:</label>
        <select name="category" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="">Todas</option>
            <option value="comercial" <?= ($category ?? '') === 'comercial' ? 'selected' : '' ?>>Comercial</option>
            <option value="campanha" <?= ($category ?? '') === 'campanha' ? 'selected' : '' ?>>Campanha</option>
            <option value="geral" <?= ($category ?? '') === 'geral' ? 'selected' : '' ?>>Geral</option>
        </select>
        <button type="submit" style="background: #023A8D; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
            Filtrar
        </button>
        <?php if (!empty($category)): ?>
            <a href="<?= pixelhub_url('/settings/whatsapp-templates') ?>" 
               style="background: #6c757d; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600;">
                Limpar
            </a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <?php if (empty($templates)): ?>
        <p style="color: #666; text-align: center; padding: 40px 20px;">
            Nenhum template encontrado.
        </p>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f5f5f5;">
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Nome</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Categoria</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Código</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Status</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $template): ?>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <strong><?= htmlspecialchars($template['name']) ?></strong>
                        <?php if (!empty($template['description'])): ?>
                            <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                <?= htmlspecialchars($template['description']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php
                        $categoryLabels = [
                            'comercial' => 'Comercial',
                            'campanha' => 'Campanha',
                            'geral' => 'Geral',
                        ];
                        $catLabel = $categoryLabels[$template['category']] ?? $template['category'];
                        echo htmlspecialchars($catLabel);
                        ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= $template['code'] ? '<code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">' . htmlspecialchars($template['code']) . '</code>' : '-' ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php
                        $statusColor = $template['is_active'] ? '#3c3' : '#999';
                        $statusLabel = $template['is_active'] ? 'Ativo' : 'Inativo';
                        echo '<span style="color: ' . $statusColor . '; font-weight: 600;">' . $statusLabel . '</span>';
                        ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <a href="<?= pixelhub_url('/settings/whatsapp-templates/edit?id=' . $template['id']) ?>" 
                               style="background: #023A8D; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block;">
                                Editar
                            </a>
                            <form method="POST" action="<?= pixelhub_url('/settings/whatsapp-templates/toggle-status') ?>" style="display: inline-block; margin: 0;">
                                <input type="hidden" name="id" value="<?= $template['id'] ?>">
                                <button type="submit" 
                                        style="background: <?= $template['is_active'] ? '#6c757d' : '#3c3' ?>; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">
                                    <?= $template['is_active'] ? 'Desativar' : 'Ativar' ?>
                                </button>
                            </form>
                            <form method="POST" action="<?= pixelhub_url('/settings/whatsapp-templates/delete') ?>" 
                                  onsubmit="return confirm('Tem certeza que deseja excluir este template?');" 
                                  style="display: inline-block; margin: 0;">
                                <input type="hidden" name="id" value="<?= $template['id'] ?>">
                                <button type="submit" 
                                        style="background: #c33; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">
                                    Excluir
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
$title = 'Mensagens WhatsApp';
require __DIR__ . '/../layout/main.php';
?>

