<?php
ob_start();
$types = $types ?? [];
?>
<style>
.btn-icon { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; padding: 0; border: none; background: none; cursor: pointer; color: #6b7280; border-radius: 4px; transition: color 0.15s, background 0.15s; }
.btn-icon:hover { color: #374151; background: #f3f4f6; }
.btn-icon-danger:hover { color: #dc2626; background: #fef2f2; }
</style>

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
            elseif ($s === 'hard_deleted') echo 'Tipo excluído permanentemente.';
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
                            <span style="display: inline-flex; align-items: center; gap: 4px;">
                                <a href="<?= pixelhub_url('/settings/agenda-block-types/edit?id=' . $t['id']) ?>" 
                                   class="btn-icon" title="Editar">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                </a>
                                <?php if ($t['ativo']): ?>
                                    <form method="POST" action="<?= pixelhub_url('/settings/agenda-block-types/delete') ?>" style="display: inline;" onsubmit="return confirm('Desativar este tipo? Ele não aparecerá mais em novos modelos.');">
                                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                        <button type="submit" class="btn-icon" title="Desativar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="<?= pixelhub_url('/settings/agenda-block-types/restore') ?>" style="display: inline;">
                                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                        <button type="submit" class="btn-icon" title="Reativar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="<?= pixelhub_url('/settings/agenda-block-types/hard-delete') ?>" style="display: inline;" onsubmit="return confirm('Excluir permanentemente? Só é possível se não houver modelos nem blocos usando este tipo.');">
                                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                    <button type="submit" class="btn-icon btn-icon-danger" title="Excluir permanentemente">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                    </button>
                                </form>
                            </span>
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
