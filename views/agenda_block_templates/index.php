<?php
ob_start();
$diasSemana = $diasSemana ?? [];
$templates = $templates ?? [];
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2>Modelos de Blocos de Agenda</h2>
        <p style="color: #666; font-size: 14px; margin-top: 5px;">
            Defina quais blocos são criados ao clicar em "Gerar Blocos do Dia". Edite ou exclua templates para alterar a estrutura da sua semana.
        </p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="<?= pixelhub_url('/settings/agenda-block-types') ?>" 
           style="padding: 8px 16px; border: 1px solid #e5e7eb; border-radius: 6px; color: #374151; text-decoration: none; font-size: 14px;">
            Tipos de Blocos
        </a>
        <a href="<?= pixelhub_url('/settings/agenda-block-templates/create') ?>" 
           style="background: #1d4ed8; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-block; font-size: 14px;">
            + Novo Modelo
        </a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #f0fdf4; border-left: 4px solid #22c55e; margin-bottom: 20px;">
        <p style="color: #15803d; margin: 0;">
            <?php
            $s = $_GET['success'];
            if ($s === 'created') echo 'Modelo criado com sucesso.';
            elseif ($s === 'updated') echo 'Modelo atualizado com sucesso.';
            elseif ($s === 'deleted') echo 'Modelo excluído com sucesso.';
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
    <?php if (empty($templates)): ?>
        <p style="color: #666; text-align: center; padding: 40px 20px;">
            Nenhum modelo cadastrado. Crie o primeiro modelo para definir a estrutura dos blocos gerados.
        </p>
    <?php else: ?>
        <?php
        $porDia = [];
        foreach ($templates as $t) {
            $dia = (int)$t['dia_semana'];
            if (!isset($porDia[$dia])) $porDia[$dia] = [];
            $porDia[$dia][] = $t;
        }
        ksort($porDia);
        ?>
        <?php foreach ($porDia as $diaNum => $itens): ?>
            <div style="margin-bottom: 24px;">
                <h3 style="margin: 0 0 12px 0; font-size: 15px; color: #374151; border-bottom: 1px solid #e5e7eb; padding-bottom: 8px;">
                    <?= htmlspecialchars($diasSemana[$diaNum] ?? "Dia $diaNum") ?>
                </h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f9fafb;">
                            <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #6b7280; font-size: 13px;">Horário</th>
                            <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #6b7280; font-size: 13px;">Tipo</th>
                            <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #6b7280; font-size: 13px;">Descrição</th>
                            <th style="padding: 10px 12px; text-align: center; font-weight: 600; color: #6b7280; font-size: 13px;">Status</th>
                            <th style="padding: 10px 12px; text-align: right; font-weight: 600; color: #6b7280; font-size: 13px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens as $t): ?>
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 10px 12px; font-size: 13px;">
                                    <?= htmlspecialchars(substr($t['hora_inicio'], 0, 5)) ?> – <?= htmlspecialchars(substr($t['hora_fim'], 0, 5)) ?>
                                </td>
                                <td style="padding: 10px 12px;">
                                    <span style="display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; background: <?= htmlspecialchars($t['tipo_cor'] ?? '#e5e7eb') ?>20; color: <?= htmlspecialchars($t['tipo_cor'] ?? '#374151') ?>;">
                                        <?= htmlspecialchars($t['tipo_nome'] ?? $t['tipo_codigo']) ?>
                                    </span>
                                </td>
                                <td style="padding: 10px 12px; font-size: 13px; color: #6b7280;">
                                    <?= htmlspecialchars($t['descricao_padrao'] ?? '-') ?>
                                </td>
                                <td style="padding: 10px 12px; text-align: center;">
                                    <?php if ($t['ativo']): ?>
                                        <span style="background: #dcfce7; color: #15803d; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 500;">Ativo</span>
                                    <?php else: ?>
                                        <span style="background: #f3f4f6; color: #6b7280; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 500;">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 10px 12px; text-align: right;">
                                    <a href="<?= pixelhub_url('/settings/agenda-block-templates/edit?id=' . $t['id']) ?>" 
                                       style="color: #1d4ed8; text-decoration: none; font-size: 13px; margin-right: 12px;">Editar</a>
                                    <form method="POST" action="<?= pixelhub_url('/settings/agenda-block-templates/delete') ?>" 
                                          style="display: inline;" onsubmit="return confirm('Excluir este modelo? Blocos já gerados não serão afetados.');">
                                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                        <button type="submit" style="background: none; border: none; color: #dc2626; cursor: pointer; font-size: 13px; padding: 0;">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="card" style="margin-top: 20px; background: #f8fafc; border-left: 4px solid #64748b;">
    <h3 style="margin: 0 0 8px 0; font-size: 14px; color: #475569;">Como funciona</h3>
    <p style="margin: 0; color: #64748b; font-size: 13px; line-height: 1.5;">
        Ao clicar em <strong>"Gerar Blocos do Dia"</strong> em Blocos de tempo, o sistema cria blocos para aquele dia com base nestes modelos.
        Exclua um modelo para que ele não seja mais criado. Edite para alterar horário ou tipo.
    </p>
</div>

<?php
$content = ob_get_clean();
$title = 'Modelos de Blocos';
require __DIR__ . '/../layout/main.php';
?>
