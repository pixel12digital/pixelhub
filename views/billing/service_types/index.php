<?php
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header">
    <h2>Categorias de Contratos</h2>
    <p style="color: #666; font-size: 14px; margin-top: 5px;">
        Gerencie as categorias de tipos de serviço para contratos recorrentes.
    </p>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #d4edda; border-left: 4px solid #28a745; margin-bottom: 20px;">
        <p style="color: #155724; margin: 0;">
            <?php
            $success = $_GET['success'];
            if ($success === 'created') echo 'Categoria criada com sucesso.';
            elseif ($success === 'updated') echo 'Categoria atualizada com sucesso.';
            elseif ($success === 'toggled') echo 'Status da categoria alterado com sucesso.';
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?php
            $error = $_GET['error'];
            if ($error === 'not_found') echo 'Categoria não encontrada.';
            elseif ($error === 'database_error') echo 'Erro ao salvar no banco de dados.';
            else echo 'Erro desconhecido.';
            ?>
        </p>
    </div>
<?php endif; ?>

<div style="margin-bottom: 20px;">
    <a href="<?= pixelhub_url('/billing/service-types/create') ?>" 
       style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; display: inline-block;">
        + Nova Categoria
    </a>
</div>

<div class="card">
    <?php if (empty($serviceTypes)): ?>
        <div style="padding: 40px; text-align: center; color: #6c757d;">
            <p style="font-size: 16px; margin-bottom: 10px;">Nenhuma categoria cadastrada.</p>
            <p style="font-size: 14px;">Crie a primeira categoria para começar.</p>
        </div>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Nome</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Slug</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Status</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Ordem</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($serviceTypes as $st): ?>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 12px; font-weight: 500;">
                            <?= htmlspecialchars($st['name']) ?>
                        </td>
                        <td style="padding: 12px; color: #6c757d; font-family: monospace; font-size: 13px;">
                            <?= htmlspecialchars($st['slug']) ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <span class="badge <?= $st['is_active'] ? 'badge-success' : 'badge-default' ?>">
                                <?= $st['is_active'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td style="padding: 12px; text-align: center; color: #6c757d;">
                            <?= (int) $st['sort_order'] ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <div style="display: flex; gap: 8px; justify-content: center;">
                                <a href="<?= pixelhub_url('/billing/service-types/edit?id=' . $st['id']) ?>" 
                                   style="color: #023A8D; text-decoration: none; font-size: 13px; font-weight: 500;">
                                    Editar
                                </a>
                                <form method="POST" action="<?= pixelhub_url('/billing/service-types/toggle-status') ?>" 
                                      style="display: inline; margin: 0;">
                                    <input type="hidden" name="id" value="<?= $st['id'] ?>">
                                    <button type="submit" 
                                            style="background: none; border: none; color: #023A8D; cursor: pointer; font-size: 13px; font-weight: 500; padding: 0;">
                                        <?= $st['is_active'] ? 'Desativar' : 'Ativar' ?>
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

<style>
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.badge-default {
    background: #f8f9fa;
    color: #6c757d;
    border: 1px solid #dee2e6;
}
</style>

<?php
$content = ob_get_clean();
$title = 'Categorias de Contratos';
require __DIR__ . '/../../layout/main.php';
?>

