<?php
ob_start();
$types = $types ?? [];
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2>Tipos de Blocos</h2>
        <p style="color: #666; font-size: 14px; margin-top: 5px;">
            Gerencie os tipos exibidos no select "Tipo de bloco" (FUTURE, CLIENTES, ADMIN, etc.). Adicione, edite ou desative tipos.
        </p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="<?= pixelhub_url('/settings/agenda-block-templates') ?>" 
           style="padding: 8px 16px; border: 1px solid #e5e7eb; border-radius: 6px; color: #374151; text-decoration: none; font-size: 14px;">
            Modelos de Blocos
        </a>
        <a href="<?= pixelhub_url('/settings/agenda-block-types/create') ?>" 
           style="background: #1d4ed8; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-block; font-size: 14px;">
            + Novo Tipo
        </a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #f0fdf4; border-left: 4px solid #22c55e; margin-bottom: 20px;">
        <p style="color: #15803d; margin: 0;">
            <?php
            $s = $_GET['success'];
            if ($s === 'created') echo 'Tipo criado com sucesso.';
            elseif ($s === 'updated') echo 'Tipo atualizado com sucesso.';
            elseif ($s === 'deleted') echo 'Tipo desativado. Não aparecerá mais em novos modelos.';
            elseif ($s === 'restored') echo 'Tipo reativado com sucesso.';
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fef2f2; border-left: 4px solid #dc2626; margin-bottom: 20px;">
        <p style="color: #b91c1c; margin: 0;"><?= htmlspecialchars(urldecode($_GET['error'])) ?></p>
    </div>
<?php endif; ?>

<div class="card">
    <?php if (empty($types)): ?>
        <p style="color: #666; text-align: center; padding: 40px 20px;">
            Nenhum tipo cadastrado. Crie o primeiro tipo para usar nos modelos de blocos.
        </p>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f9fafb;">
                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #6b7280; font-size: 13px;">Tipo</th>
                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #6b7280; font-size: 13px;">Código</th>
                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #6b7280; font-size: 13px;">Cor</th>
                    <th style="padding: 10px 12px; text-align: center; font-weight: 600; color: #6b7280; font-size: 13px;">Status</th>
                    <th style="padding: 10px 12px; text-align: center; font-weight: 600; color: #6b7280; font-size: 13px;">Uso</th>
                    <th style="padding: 10px 12px; text-align: right; font-weight: 600; color: #6b7280; font-size: 13px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($types as $t): ?>
                    <tr style="border-bottom: 1px solid #f3f4f6; <?= !$t['ativo'] ? 'opacity: 0.7;' : '' ?>">
                        <td style="padding: 10px 12px; font-size: 13px; font-weight: 500;">
                            <?= htmlspecialchars($t['nome']) ?>
                        </td>
                        <td style="padding: 10px 12px; font-size: 13px; font-family: monospace; color: #6b7280;">
                            <?= htmlspecialchars($t['codigo']) ?>
                        </td>
                        <td style="padding: 10px 12px;">
                            <?php if (!empty($t['cor_hex'])): ?>
                                <span style="display: inline-block; width: 20px; height: 20px; border-radius: 4px; background: <?= htmlspecialchars($t['cor_hex']) ?>; border: 1px solid #e5e7eb;" title="<?= htmlspecialchars($t['cor_hex']) ?>"></span>
                                <span style="font-size: 12px; color: #6b7280; margin-left: 6px;"><?= htmlspecialchars($t['cor_hex']) ?></span>
                            <?php else: ?>
                                <span style="color: #9ca3af; font-size: 12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px 12px; text-align: center;">
                            <?php if ($t['ativo']): ?>
                                <span style="background: #dcfce7; color: #15803d; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 500;">Ativo</span>
                            <?php else: ?>
                                <span style="background: #f3f4f6; color: #6b7280; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 500;">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px 12px; text-align: center; font-size: 12px; color: #6b7280;">
                            <?= (int)($t['templates_count'] ?? 0) ?> modelos · <?= (int)($t['blocks_count'] ?? 0) ?> blocos
                        </td>
                        <td style="padding: 10px 12px; text-align: right;">
                            <a href="<?= pixelhub_url('/settings/agenda-block-types/edit?id=' . $t['id']) ?>" 
                               style="color: #1d4ed8; text-decoration: none; font-size: 13px; margin-right: 12px;">Editar</a>
                            <?php if ($t['ativo']): ?>
                                <form method="POST" action="<?= pixelhub_url('/settings/agenda-block-types/delete') ?>" 
                                      style="display: inline;" onsubmit="return confirm('Desativar este tipo? Ele não aparecerá mais em novos modelos.');">
                                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                    <button type="submit" style="background: none; border: none; color: #dc2626; cursor: pointer; font-size: 13px; padding: 0;">Desativar</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="<?= pixelhub_url('/settings/agenda-block-types/restore') ?>" style="display: inline;">
                                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                    <button type="submit" style="background: none; border: none; color: #15803d; cursor: pointer; font-size: 13px; padding: 0;">Reativar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card" style="margin-top: 20px; background: #f8fafc; border-left: 4px solid #64748b;">
    <h3 style="margin: 0 0 8px 0; font-size: 14px; color: #475569;">Sobre os tipos</h3>
    <p style="margin: 0; color: #64748b; font-size: 13px; line-height: 1.5;">
        Os tipos definem as categorias dos blocos (ex: FUTURE, CLIENTES, ADMIN). Ao desativar um tipo, ele deixa de aparecer no select ao criar/editar modelos, mas blocos já existentes continuam funcionando.
    </p>
</div>

<?php
$content = ob_get_clean();
$title = 'Tipos de Blocos';
require __DIR__ . '/../layout/main.php';
?>
