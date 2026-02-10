<?php
/**
 * Auditoria de envios de cobrança (billing_notifications)
 */
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: start;">
    <div>
        <h2>Auditoria de Cobranças</h2>
        <p>Histórico de todos os envios automáticos e manuais de cobrança</p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <?php if ($recentFailures > 0): ?>
            <span style="background: #dc3545; color: white; padding: 6px 14px; border-radius: 20px; font-weight: 600; font-size: 13px;">
                <?= $recentFailures ?> falha(s) nas últimas 24h
            </span>
        <?php else: ?>
            <span style="background: #28a745; color: white; padding: 6px 14px; border-radius: 20px; font-weight: 600; font-size: 13px;">
                Sem falhas recentes
            </span>
        <?php endif; ?>
        <a href="<?= pixelhub_url('/billing/overview') ?>" style="color: #6c757d; text-decoration: none; font-size: 14px;">← Central de Cobranças</a>
    </div>
</div>

<!-- Fila do Dia -->
<?php
$queueTotal = array_sum($queueSummary ?? []);
$hasQueue = $queueTotal > 0;
?>
<?php if ($hasQueue): ?>
<div class="card" style="margin-bottom: 20px; border-left: 4px solid #6f42c1;">
    <h4 style="margin: 0 0 12px 0; color: #6f42c1; font-size: 15px;">Fila de Envios — Hoje (<?= date('d/m/Y') ?>)</h4>
    
    <div style="display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap;">
        <div style="text-align: center; padding: 10px 20px; background: #f8f9fa; border-radius: 6px; min-width: 80px;">
            <div style="font-size: 22px; font-weight: 700; color: #ffc107;"><?= $queueSummary['queued'] ?? 0 ?></div>
            <div style="font-size: 11px; color: #666; font-weight: 500;">Na fila</div>
        </div>
        <div style="text-align: center; padding: 10px 20px; background: #f8f9fa; border-radius: 6px; min-width: 80px;">
            <div style="font-size: 22px; font-weight: 700; color: #17a2b8;"><?= $queueSummary['processing'] ?? 0 ?></div>
            <div style="font-size: 11px; color: #666; font-weight: 500;">Processando</div>
        </div>
        <div style="text-align: center; padding: 10px 20px; background: #f8f9fa; border-radius: 6px; min-width: 80px;">
            <div style="font-size: 22px; font-weight: 700; color: #28a745;"><?= $queueSummary['sent'] ?? 0 ?></div>
            <div style="font-size: 11px; color: #666; font-weight: 500;">Enviados</div>
        </div>
        <div style="text-align: center; padding: 10px 20px; background: <?= ($queueSummary['failed'] ?? 0) > 0 ? '#fff5f5' : '#f8f9fa' ?>; border-radius: 6px; min-width: 80px;">
            <div style="font-size: 22px; font-weight: 700; color: #dc3545;"><?= $queueSummary['failed'] ?? 0 ?></div>
            <div style="font-size: 11px; color: #666; font-weight: 500;">Falhas</div>
        </div>
    </div>

    <?php if (!empty($queueJobs)): ?>
    <details>
        <summary style="cursor: pointer; font-size: 13px; color: #6f42c1; font-weight: 500;">Ver detalhes da fila (<?= count($queueJobs) ?> job<?= count($queueJobs) > 1 ? 's' : '' ?>)</summary>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 6px 8px; text-align: left;">Job</th>
                    <th style="padding: 6px 8px; text-align: left;">Cliente</th>
                    <th style="padding: 6px 8px; text-align: center;">Faturas</th>
                    <th style="padding: 6px 8px; text-align: center;">Agendado</th>
                    <th style="padding: 6px 8px; text-align: center;">Status</th>
                    <th style="padding: 6px 8px; text-align: center;">Tentativas</th>
                    <th style="padding: 6px 8px; text-align: left;">Erro</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($queueJobs as $qj):
                    $qjStatus = $qj['status'];
                    $qjColors = [
                        'queued' => ['bg' => '#fff3cd', 'color' => '#856404'],
                        'processing' => ['bg' => '#cce5ff', 'color' => '#004085'],
                        'sent' => ['bg' => '#d4edda', 'color' => '#155724'],
                        'failed' => ['bg' => '#f8d7da', 'color' => '#721c24'],
                    ];
                    $qjC = $qjColors[$qjStatus] ?? ['bg' => '#e2e3e5', 'color' => '#383d41'];
                    $scheduledTime = !empty($qj['scheduled_at']) ? (new DateTime($qj['scheduled_at']))->format('H:i') : '-';
                    $invoiceCount = count(json_decode($qj['invoice_ids'] ?? '[]', true));
                ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 6px 8px; font-family: monospace;">#<?= $qj['id'] ?></td>
                    <td style="padding: 6px 8px;"><?= htmlspecialchars($qj['tenant_name'] ?? "#{$qj['tenant_id']}") ?></td>
                    <td style="padding: 6px 8px; text-align: center;"><?= $invoiceCount ?></td>
                    <td style="padding: 6px 8px; text-align: center; font-family: monospace;"><?= $scheduledTime ?></td>
                    <td style="padding: 6px 8px; text-align: center;">
                        <span style="background: <?= $qjC['bg'] ?>; color: <?= $qjC['color'] ?>; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">
                            <?= $qjStatus ?>
                        </span>
                    </td>
                    <td style="padding: 6px 8px; text-align: center;"><?= $qj['attempts'] ?>/<?= $qj['max_attempts'] ?></td>
                    <td style="padding: 6px 8px; color: #dc3545; font-size: 11px;"><?= htmlspecialchars(mb_substr($qj['error_message'] ?? '', 0, 50)) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </details>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Filtros -->
<div class="card" style="margin-bottom: 20px;">
    <form method="GET" action="<?= pixelhub_url('/billing/notifications-log') ?>" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555; font-size: 13px;">Status:</label>
            <select name="status" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Todos</option>
                <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Enviado</option>
                <option value="sent_manual" <?= $status === 'sent_manual' ? 'selected' : '' ?>>Enviado (manual)</option>
                <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Falha</option>
                <option value="prepared" <?= $status === 'prepared' ? 'selected' : '' ?>>Preparado</option>
            </select>
        </div>
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555; font-size: 13px;">Canal:</label>
            <select name="channel" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                <option value="all" <?= $channel === 'all' ? 'selected' : '' ?>>Todos</option>
                <option value="whatsapp_inbox" <?= $channel === 'whatsapp_inbox' ? 'selected' : '' ?>>WhatsApp Inbox</option>
                <option value="whatsapp_web" <?= $channel === 'whatsapp_web' ? 'selected' : '' ?>>WhatsApp Web</option>
                <option value="email" <?= $channel === 'email' ? 'selected' : '' ?>>E-mail</option>
            </select>
        </div>
        <?php if ($tenantId): ?>
            <input type="hidden" name="tenant_id" value="<?= (int) $tenantId ?>">
        <?php endif; ?>
        <button type="submit" style="background: #023A8D; color: white; padding: 8px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
            Filtrar
        </button>
        <?php if ($status !== 'all' || $channel !== 'all' || $tenantId): ?>
            <a href="<?= pixelhub_url('/billing/notifications-log') ?>" style="color: #6c757d; font-size: 13px; text-decoration: none;">Limpar filtros</a>
        <?php endif; ?>
    </form>
</div>

<!-- Tabela -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin: 0;">Registros (<?= $total ?>)</h3>
    </div>

    <?php if (empty($notifications)): ?>
        <p style="color: #666; text-align: center; padding: 30px;">Nenhum registro encontrado.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Data/Hora</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Cliente</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Fatura</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Canal</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Status</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Disparo</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Telefone</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $n): ?>
                        <?php
                        $statusColors = [
                            'sent' => ['bg' => '#d4edda', 'color' => '#155724', 'label' => 'Enviado'],
                            'sent_manual' => ['bg' => '#cce5ff', 'color' => '#004085', 'label' => 'Manual'],
                            'failed' => ['bg' => '#f8d7da', 'color' => '#721c24', 'label' => 'Falha'],
                            'prepared' => ['bg' => '#fff3cd', 'color' => '#856404', 'label' => 'Preparado'],
                        ];
                        $st = $statusColors[$n['status']] ?? ['bg' => '#e2e3e5', 'color' => '#383d41', 'label' => $n['status']];

                        $channelLabels = [
                            'whatsapp_inbox' => 'WA Inbox',
                            'whatsapp_web' => 'WA Web',
                            'email' => 'E-mail',
                        ];
                        $channelLabel = $channelLabels[$n['channel']] ?? $n['channel'];

                        $triggeredLabels = [
                            'manual' => 'Manual',
                            'scheduler' => 'Automático',
                        ];
                        $triggeredLabel = $triggeredLabels[$n['triggered_by'] ?? 'manual'] ?? ($n['triggered_by'] ?? 'manual');

                        $createdAt = 'N/A';
                        if (!empty($n['created_at'])) {
                            try {
                                $createdAt = (new DateTime($n['created_at']))->format('d/m/Y H:i');
                            } catch (Exception $e) {}
                        }

                        $invoiceInfo = '';
                        if (!empty($n['due_date'])) {
                            try {
                                $invoiceInfo = (new DateTime($n['due_date']))->format('d/m/Y');
                            } catch (Exception $e) {}
                        }
                        if (!empty($n['amount'])) {
                            $invoiceInfo .= ' R$ ' . number_format((float) $n['amount'], 2, ',', '.');
                        }
                        ?>
                        <tr style="border-bottom: 1px solid #eee; <?= $n['status'] === 'failed' ? 'background: #fff5f5;' : '' ?>">
                            <td style="padding: 10px; white-space: nowrap;"><?= $createdAt ?></td>
                            <td style="padding: 10px;">
                                <?php if (!empty($n['tenant_name'])): ?>
                                    <a href="<?= pixelhub_url('/tenants/view?id=' . $n['tenant_id'] . '&tab=financial') ?>" style="color: #023A8D; text-decoration: none;">
                                        <?= htmlspecialchars($n['tenant_name']) ?>
                                    </a>
                                <?php else: ?>
                                    #<?= $n['tenant_id'] ?>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px; font-size: 12px;"><?= htmlspecialchars($invoiceInfo ?: '-') ?></td>
                            <td style="padding: 10px;">
                                <span style="font-size: 11px; font-weight: 600;"><?= htmlspecialchars($channelLabel) ?></span>
                            </td>
                            <td style="padding: 10px;">
                                <span style="background: <?= $st['bg'] ?>; color: <?= $st['color'] ?>; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                    <?= htmlspecialchars($st['label']) ?>
                                </span>
                            </td>
                            <td style="padding: 10px;">
                                <span style="font-size: 11px; color: <?= ($n['triggered_by'] ?? 'manual') === 'scheduler' ? '#6f42c1' : '#666' ?>; font-weight: 500;">
                                    <?= htmlspecialchars($triggeredLabel) ?>
                                </span>
                            </td>
                            <td style="padding: 10px; font-size: 12px; font-family: monospace;"><?= htmlspecialchars($n['phone_normalized'] ?? $n['phone_raw'] ?? '-') ?></td>
                            <td style="padding: 10px;">
                                <?php if (!empty($n['last_error'])): ?>
                                    <span title="<?= htmlspecialchars($n['last_error']) ?>" style="color: #dc3545; cursor: help; font-size: 12px;">
                                        <?= htmlspecialchars(mb_substr($n['last_error'], 0, 60)) ?><?= mb_strlen($n['last_error']) > 60 ? '...' : '' ?>
                                    </span>
                                <?php elseif (!empty($n['gateway_message_id'])): ?>
                                    <span style="color: #666; font-size: 11px; font-family: monospace;" title="Gateway Message ID">
                                        <?= htmlspecialchars(mb_substr($n['gateway_message_id'], 0, 20)) ?>...
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php
        $totalPages = ceil($total / $perPage);
        if ($totalPages > 1):
            $queryParams = $_GET;
        ?>
            <div style="margin-top: 20px; display: flex; justify-content: center; gap: 5px;">
                <?php for ($p = 1; $p <= $totalPages; $p++):
                    $queryParams['page'] = $p;
                    $isActive = $p === $page;
                ?>
                    <a href="<?= pixelhub_url('/billing/notifications-log?' . http_build_query($queryParams)) ?>"
                       style="padding: 6px 12px; border: 1px solid <?= $isActive ? '#023A8D' : '#ddd' ?>; border-radius: 4px; text-decoration: none; color: <?= $isActive ? 'white' : '#333' ?>; background: <?= $isActive ? '#023A8D' : 'white' ?>; font-size: 13px;">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
$title = 'Auditoria de Cobranças';
include __DIR__ . '/../layout/main.php';
?>
