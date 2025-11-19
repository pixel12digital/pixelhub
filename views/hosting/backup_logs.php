<?php
ob_start();
?>
<div class="content-header">
    <h2>Logs de Upload - <?= htmlspecialchars($hostingAccount['domain'] ?? 'N/A') ?></h2>
    <p>Cliente: <a href="<?= pixelhub_url('/tenants/view?id=' . (int)$hostingAccount['tenant_id']) ?>" style="color: #023A8D; text-decoration: none; font-weight: 600;"><?= htmlspecialchars($hostingAccount['tenant_name'] ?? 'N/A') ?></a></p>
    <p style="margin-top: 10px;">
        <a href="<?= pixelhub_url('/hosting/backups?hosting_id=' . (int)$hostingAccount['id']) ?>" style="color: #023A8D; text-decoration: none; font-size: 14px;">
            ← Voltar para Backups
        </a>
    </p>
</div>

<div class="card">
    <h3 style="margin-bottom: 20px;">Informações do Log</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: 600;">Arquivo de log:</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee; font-family: monospace; font-size: 12px;">
                <?= htmlspecialchars($logFile ?? 'Não encontrado') ?>
            </td>
        </tr>
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: 600;">Última atualização:</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">
                <?php
                if ($logFile && file_exists($logFile) && filesize($logFile) > 0) {
                    echo date('d/m/Y H:i:s', filemtime($logFile));
                } else {
                    echo 'N/A';
                }
                ?>
            </td>
        </tr>
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: 600;">Tamanho:</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">
                <?php
                if ($logFile && file_exists($logFile) && filesize($logFile) > 0) {
                    echo number_format(filesize($logFile) / 1024, 2) . ' KB';
                } else {
                    echo '0 KB (vazio)';
                }
                ?>
            </td>
        </tr>
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: 600;">Logs encontrados:</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">
                <?= count($logs ?? []) ?> <?= count($logs ?? []) === 1 ? 'entrada' : 'entradas' ?>
            </td>
        </tr>
    </table>
</div>

<div class="card">
    <h3 style="margin-bottom: 20px;">Logs de Upload</h3>
    
    <?php if (empty($logs)): ?>
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 4px;">
            <p style="margin: 0; color: #856404;">
                <strong>Nenhum log de upload encontrado para este backup/conta.</strong>
            </p>
            <p style="margin: 10px 0 0 0; color: #856404; font-size: 14px;">
                Faça upload de um arquivo .wpress para gerar logs de diagnóstico.
            </p>
        </div>
    <?php else: ?>
        <div style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; overflow-x: auto; max-height: 600px; overflow-y: auto; font-family: monospace; font-size: 12px; line-height: 1.5;">
            <?php foreach ($logs as $logLine): ?>
                <?php
                $isError = stripos($logLine, 'ERRO') !== false || stripos($logLine, 'error') !== false;
                $isSuccess = stripos($logLine, 'successfully') !== false;
                $color = '';
                if ($isError) $color = '#f44336';
                elseif ($isSuccess) $color = '#4caf50';
                else $color = '#d4d4d4';
                ?>
                <div style="margin-bottom: 5px; padding: 5px; border-left: 3px solid <?= $isError ? '#f44336' : ($isSuccess ? '#4caf50' : '#F7931E') ?>; background: #2d2d2d;">
                    <span style="color: <?= $color ?>;"><?= htmlspecialchars($logLine) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div style="margin-top: 20px;">
    <a href="<?= pixelhub_url('/hosting/backups?hosting_id=' . (int)$hostingAccount['id']) ?>" 
       style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; display: inline-block;">
        Voltar para Backups
    </a>
    <a href="javascript:location.reload()" 
       style="background: #6c757d; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; display: inline-block; margin-left: 10px;">
        🔄 Atualizar
    </a>
</div>

<?php
$content = ob_get_clean();
$title = 'Logs de Upload - ' . htmlspecialchars($hostingAccount['domain'] ?? 'N/A');
require __DIR__ . '/../layout/main.php';
?>

