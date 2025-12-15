<?php
/**
 * Diagnóstico Financeiro - Visualização de erros do módulo financeiro
 */
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header">
    <div>
        <h2>Diagnóstico Financeiro</h2>
        <p>Visualização de erros e problemas relacionados ao módulo financeiro</p>
    </div>
</div>

<!-- Estatísticas Gerais -->
<div class="stats">
    <div class="stat-card">
        <h3>Erros de Sincronização</h3>
        <div class="value" style="color: <?= $stats['total_sync_errors'] > 0 ? '#dc3545' : '#28a745' ?>;">
            <?= $stats['total_sync_errors'] ?>
        </div>
        <?php if ($stats['last_sync_error']): ?>
            <small style="color: #666; font-size: 12px;">Último: <?= date('d/m/Y H:i', strtotime($stats['last_sync_error'])) ?></small>
        <?php endif; ?>
    </div>
    <div class="stat-card">
        <h3>Erros de Webhook</h3>
        <div class="value" style="color: <?= $stats['total_webhook_errors'] > 0 ? '#dc3545' : '#28a745' ?>;">
            <?= $stats['total_webhook_errors'] ?>
        </div>
        <?php if ($stats['last_webhook_error']): ?>
            <small style="color: #666; font-size: 12px;">Último: <?= date('d/m/Y H:i', strtotime($stats['last_webhook_error'])) ?></small>
        <?php endif; ?>
    </div>
    <div class="stat-card">
        <h3>Problemas em Faturas</h3>
        <div class="value" style="color: <?= $stats['total_billing_errors'] > 0 ? '#dc3545' : '#28a745' ?>;">
            <?= $stats['total_billing_errors'] ?>
        </div>
    </div>
</div>

<!-- Abas para diferentes tipos de erros -->
<div class="card">
    <div style="border-bottom: 2px solid #dee2e6; margin-bottom: 20px;">
        <div style="display: flex; gap: 10px;">
            <button onclick="showTab('sync')" id="tab-sync-btn" class="tab-button active" style="padding: 10px 20px; background: #023A8D; color: white; border: none; border-radius: 4px 4px 0 0; cursor: pointer; font-weight: 500;">
                Erros de Sincronização (<?= count($syncErrors) ?>)
            </button>
            <button onclick="showTab('webhook')" id="tab-webhook-btn" class="tab-button" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px 4px 0 0; cursor: pointer; font-weight: 500;">
                Erros de Webhook (<?= count($webhookErrors) ?>)
            </button>
            <button onclick="showTab('billing')" id="tab-billing-btn" class="tab-button" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px 4px 0 0; cursor: pointer; font-weight: 500;">
                Problemas em Faturas (<?= count($billingErrors) ?>)
            </button>
        </div>
    </div>

    <!-- Aba: Erros de Sincronização -->
    <div id="tab-sync" class="tab-content">
        <?php if (empty($syncErrors)): ?>
            <div style="padding: 40px; text-align: center; color: #6c757d;">
                <p style="font-size: 18px; margin-bottom: 10px;">✓ Nenhum erro de sincronização encontrado</p>
                <p style="font-size: 14px;">Não há erros registrados nas últimas sincronizações com o Asaas.</p>
            </div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057; width: 180px;">Data/Hora</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Mensagem de Erro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($syncErrors as $error): ?>
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
        <?php endif; ?>
    </div>

    <!-- Aba: Erros de Webhook -->
    <div id="tab-webhook" class="tab-content" style="display: none;">
        <?php if (empty($webhookErrors)): ?>
            <div style="padding: 40px; text-align: center; color: #6c757d;">
                <p style="font-size: 18px; margin-bottom: 10px;">✓ Nenhum erro de webhook encontrado</p>
                <p style="font-size: 14px;">Não há erros registrados nos webhooks do Asaas.</p>
            </div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057; width: 180px;">Data/Hora</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057; width: 150px;">Evento</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Mensagem de Erro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($webhookErrors as $error): ?>
                        <tr style="border-bottom: 1px solid #dee2e6;">
                            <td style="padding: 12px; color: #666; font-size: 13px; font-family: monospace;">
                                <?= htmlspecialchars($error['created_at']) ?>
                            </td>
                            <td style="padding: 12px; color: #666; font-size: 13px;">
                                <span style="background: #e9ecef; padding: 4px 8px; border-radius: 3px; font-weight: 500;">
                                    <?= htmlspecialchars($error['event'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td style="padding: 12px; color: #721c24;">
                                <div style="background: #f8d7da; padding: 10px; border-radius: 4px; border-left: 4px solid #dc3545;">
                                    <?= htmlspecialchars($error['message']) ?>
                                </div>
                                <?php if (!empty($error['payload'])): ?>
                                    <details style="margin-top: 8px;">
                                        <summary style="cursor: pointer; color: #666; font-size: 12px;">Ver payload completo</summary>
                                        <pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-top: 8px; font-size: 11px; overflow-x: auto;"><?= htmlspecialchars(json_encode(json_decode($error['payload']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                    </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Aba: Problemas em Faturas -->
    <div id="tab-billing" class="tab-content" style="display: none;">
        <?php if (empty($billingErrors)): ?>
            <div style="padding: 40px; text-align: center; color: #6c757d;">
                <p style="font-size: 18px; margin-bottom: 10px;">✓ Nenhum problema encontrado</p>
                <p style="font-size: 14px;">Todas as faturas estão com dados consistentes.</p>
            </div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057; width: 100px;">ID Fatura</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057; width: 100px;">ID Cliente</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057; width: 100px;">Status</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057; width: 180px;">Data</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Problema</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($billingErrors as $error): ?>
                        <tr style="border-bottom: 1px solid #dee2e6;">
                            <td style="padding: 12px; color: #666; font-size: 13px; font-family: monospace;">
                                <a href="<?= pixelhub_url('/billing/collections') ?>" style="color: #023A8D; text-decoration: none;">
                                    #<?= htmlspecialchars($error['invoice_id']) ?>
                                </a>
                            </td>
                            <td style="padding: 12px; color: #666; font-size: 13px; font-family: monospace;">
                                <a href="<?= pixelhub_url('/tenants/view?id=' . $error['tenant_id']) ?>" style="color: #023A8D; text-decoration: none;">
                                    #<?= htmlspecialchars($error['tenant_id']) ?>
                                </a>
                            </td>
                            <td style="padding: 12px;">
                                <span style="background: #e9ecef; padding: 4px 8px; border-radius: 3px; font-weight: 500; font-size: 12px;">
                                    <?= htmlspecialchars($error['status'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td style="padding: 12px; color: #666; font-size: 13px; font-family: monospace;">
                                <?= htmlspecialchars($error['created_at']) ?>
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
        <?php endif; ?>
    </div>
</div>

<script>
function showTab(tabName) {
    // Esconde todas as abas
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
    });
    
    // Remove classe active de todos os botões
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.style.background = '#6c757d';
    });
    
    // Mostra a aba selecionada
    document.getElementById('tab-' + tabName).style.display = 'block';
    
    // Ativa o botão correspondente
    const btn = document.getElementById('tab-' + tabName + '-btn');
    if (btn) {
        btn.style.background = '#023A8D';
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>

