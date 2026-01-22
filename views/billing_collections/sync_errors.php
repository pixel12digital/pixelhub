<?php
/**
 * Visualização de erros de sincronização com Asaas
 */
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: start;">
    <div>
        <h2>Erros de Sincronização - Asaas</h2>
        <p>Últimos erros registrados durante a sincronização com o Asaas</p>
    </div>
    <div>
        <a href="<?= pixelhub_url('/billing/overview') ?>" class="btn btn-secondary btn-sm" style="background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 14px; text-decoration: none; display: inline-block;">
            ← Voltar para Central de Cobranças
        </a>
    </div>
</div>

<div class="card">
    <?php if (empty($errors)): ?>
        <div style="padding: 40px; text-align: center; color: #6c757d;">
            <p style="font-size: 18px; margin-bottom: 10px;">✓ Nenhum erro encontrado</p>
            <p style="font-size: 14px;">Não há erros registrados nas últimas sincronizações.</p>
        </div>
    <?php else: ?>
        <div style="margin-bottom: 20px;">
            <p style="color: #666; font-size: 14px;">
                Mostrando os últimos <strong><?= count($errors) ?></strong> erros registrados.
            </p>
        </div>
        
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057; width: 180px;">Data/Hora</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Mensagem de Erro</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($errors as $error): ?>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 12px; color: #666; font-size: 13px; font-family: monospace;">
                            <?= htmlspecialchars($error['timestamp']) ?>
                        </td>
                        <td style="padding: 12px; color: #721c24;">
                            <div style="background: #f8d7da; padding: 10px; border-radius: 4px; border-left: 4px solid #dc3545;">
                                <?= htmlspecialchars($error['message']) ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px; font-size: 13px; color: #666;">
            <strong>Localização do arquivo de log:</strong><br>
            <code style="background: white; padding: 5px 10px; border-radius: 3px; display: inline-block; margin-top: 5px;">
                <?= htmlspecialchars($logFile) ?>
            </code>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>

