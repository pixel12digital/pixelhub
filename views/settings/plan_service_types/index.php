<?php
ob_start();

$serviceTypes = $serviceTypes ?? [];
$editing = $editing ?? null;
?>

<div class="content-header">
    <h2>Tipos de Serviço</h2>
    <p>Gerencie os tipos de serviço disponíveis para planos recorrentes</p>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #efe; border-left: 4px solid #3c3; margin-bottom: 20px;">
        <p style="color: #3c3; margin: 0;">
            <?php
            $s = $_GET['success'];
            if ($s === 'created') echo 'Tipo de serviço criado com sucesso!';
            elseif ($s === 'updated') echo 'Tipo de serviço atualizado com sucesso!';
            elseif ($s === 'toggled') echo 'Status alterado com sucesso!';
            elseif ($s === 'deleted') echo 'Tipo de serviço excluído com sucesso!';
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?php
            $e = $_GET['error'];
            if ($e === 'missing_name') echo 'Nome é obrigatório.';
            elseif ($e === 'invalid_slug') echo 'Slug inválido. Use apenas letras minúsculas, números e underscore.';
            elseif ($e === 'slug_exists') echo 'Já existe um tipo de serviço com este slug.';
            elseif ($e === 'in_use') echo 'Não é possível excluir: existem planos usando este tipo de serviço.';
            elseif ($e === 'database_error') echo 'Erro ao salvar no banco de dados.';
            ?>
        </p>
    </div>
<?php endif; ?>

<!-- Form inline para criar/editar -->
<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #023A8D;">
        <?= $editing ? 'Editar Tipo de Serviço' : 'Novo Tipo de Serviço' ?>
    </h3>
    <form method="POST" action="<?= pixelhub_url($editing ? '/settings/plan-service-types/update' : '/settings/plan-service-types/store') ?>"
          style="display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap;">
        <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= $editing['id'] ?>">
        <?php endif; ?>
        
        <div style="flex: 1; min-width: 200px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Nome *</label>
            <input type="text" name="name" required
                   value="<?= htmlspecialchars($editing['name'] ?? '') ?>"
                   placeholder="ex: Hospedagem, E-commerce, Manutenção"
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <div style="min-width: 180px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Slug (auto)</label>
            <input type="text" name="slug"
                   value="<?= htmlspecialchars($editing['slug'] ?? '') ?>"
                   placeholder="gerado automaticamente"
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 13px;">
        </div>
        
        <div style="display: flex; gap: 5px;">
            <button type="submit"
                    style="background: #023A8D; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; white-space: nowrap;">
                <?= $editing ? 'Salvar' : 'Adicionar' ?>
            </button>
            <?php if ($editing): ?>
                <a href="<?= pixelhub_url('/settings/plan-service-types') ?>"
                   style="background: #666; color: white; padding: 8px 16px; border: none; border-radius: 4px; text-decoration: none; display: inline-block; font-weight: 600; white-space: nowrap;">
                    Cancelar
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Listagem -->
<div class="card">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f5f5f5;">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Nome</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Slug</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Status</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($serviceTypes)): ?>
                <tr>
                    <td colspan="4" style="padding: 20px; text-align: center; color: #666;">
                        Nenhum tipo de serviço cadastrado.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($serviceTypes as $st): ?>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eee; font-weight: 600;">
                        <?= htmlspecialchars($st['name']) ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-size: 13px;">
                            <?= htmlspecialchars($st['slug']) ?>
                        </code>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php
                        $statusColor = $st['is_active'] ? '#3c3' : '#c33';
                        $statusText = $st['is_active'] ? 'Ativo' : 'Inativo';
                        echo '<span style="color: ' . $statusColor . '; font-weight: 600;">' . $statusText . '</span>';
                        ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <a href="<?= pixelhub_url('/settings/plan-service-types?edit=' . $st['id']) ?>"
                               style="background: #023A8D; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 600;">
                                Editar
                            </a>
                            <form method="POST" action="<?= pixelhub_url('/settings/plan-service-types/toggle-status') ?>" style="display: inline; margin: 0;">
                                <input type="hidden" name="id" value="<?= $st['id'] ?>">
                                <button type="submit"
                                        style="background: <?= $st['is_active'] ? '#f93' : '#3c3' ?>; color: white; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600;">
                                    <?= $st['is_active'] ? 'Desativar' : 'Ativar' ?>
                                </button>
                            </form>
                            <form method="POST" action="<?= pixelhub_url('/settings/plan-service-types/delete') ?>" style="display: inline; margin: 0;"
                                  onsubmit="return confirm('Excluir este tipo de serviço?');">
                                <input type="hidden" name="id" value="<?= $st['id'] ?>">
                                <button type="submit"
                                        style="background: #c33; color: white; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600;">
                                    Excluir
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
$title = 'Tipos de Serviço';
require __DIR__ . '/../../layout/main.php';
?>
