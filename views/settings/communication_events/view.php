<?php
/**
 * Detalhes de um Evento de Comunicação
 */
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header">
    <div>
        <h2>Detalhes do Evento</h2>
        <p><a href="<?= pixelhub_url('/settings/communication-events') ?>" style="color: #007bff; text-decoration: none;">← Voltar para lista</a></p>
    </div>
</div>

<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-top: 0; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #dee2e6;">Informações Básicas</h3>
    
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 10px; font-weight: 600; width: 200px; color: #666;">Event ID:</td>
            <td style="padding: 10px;">
                <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 3px; font-size: 12px;">
                    <?= htmlspecialchars($event['event_id']) ?>
                </code>
            </td>
        </tr>
        <tr>
            <td style="padding: 10px; font-weight: 600; color: #666;">Tipo:</td>
            <td style="padding: 10px;">
                <code style="background: #e7f3ff; padding: 4px 8px; border-radius: 3px; font-size: 12px;">
                    <?= htmlspecialchars($event['event_type']) ?>
                </code>
            </td>
        </tr>
        <tr>
            <td style="padding: 10px; font-weight: 600; color: #666;">Sistema de Origem:</td>
            <td style="padding: 10px;">
                <span style="background: #e7f3ff; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 600;">
                    <?= htmlspecialchars($event['source_system']) ?>
                </span>
            </td>
        </tr>
        <tr>
            <td style="padding: 10px; font-weight: 600; color: #666;">Status:</td>
            <td style="padding: 10px;">
                <?php
                $statusColors = [
                    'queued' => '#ffc107',
                    'processing' => '#17a2b8',
                    'processed' => '#28a745',
                    'failed' => '#dc3545'
                ];
                $color = $statusColors[$event['status']] ?? '#6c757d';
                ?>
                <span style="background: <?= $color ?>; color: white; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                    <?= strtoupper($event['status']) ?>
                </span>
            </td>
        </tr>
        <tr>
            <td style="padding: 10px; font-weight: 600; color: #666;">Cliente:</td>
            <td style="padding: 10px;">
                <?php if ($event['tenant_name']): ?>
                    <a href="<?= pixelhub_url('/tenants/view?id=' . $event['tenant_id']) ?>" style="color: #007bff; text-decoration: none;">
                        <?= htmlspecialchars($event['tenant_name']) ?>
                    </a>
                    <?php if ($event['tenant_email']): ?>
                        <br><small style="color: #666;"><?= htmlspecialchars($event['tenant_email']) ?></small>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="color: #999;">Não identificado</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td style="padding: 10px; font-weight: 600; color: #666;">Trace ID:</td>
            <td style="padding: 10px;">
                <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 3px; font-size: 11px; font-family: monospace;">
                    <?= htmlspecialchars($event['trace_id']) ?>
                </code>
                <a href="<?= pixelhub_url('/settings/communication-events?trace_id=' . urlencode($event['trace_id'])) ?>" 
                   style="margin-left: 10px; color: #007bff; text-decoration: none; font-size: 12px;">
                    Ver todos os eventos deste trace
                </a>
            </td>
        </tr>
        <?php if ($event['correlation_id']): ?>
        <tr>
            <td style="padding: 10px; font-weight: 600; color: #666;">Correlation ID:</td>
            <td style="padding: 10px;">
                <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 3px; font-size: 11px; font-family: monospace;">
                    <?= htmlspecialchars($event['correlation_id']) ?>
                </code>
            </td>
        </tr>
        <?php endif; ?>
        <tr>
            <td style="padding: 10px; font-weight: 600; color: #666;">Criado em:</td>
            <td style="padding: 10px;">
                <?= date('d/m/Y H:i:s', strtotime($event['created_at'])) ?>
            </td>
        </tr>
        <?php if ($event['processed_at']): ?>
        <tr>
            <td style="padding: 10px; font-weight: 600; color: #666;">Processado em:</td>
            <td style="padding: 10px;">
                <?= date('d/m/Y H:i:s', strtotime($event['processed_at'])) ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php if ($event['error_message']): ?>
        <tr>
            <td style="padding: 10px; font-weight: 600; color: #dc3545;">Erro:</td>
            <td style="padding: 10px; color: #dc3545;">
                <?= htmlspecialchars($event['error_message']) ?>
            </td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<!-- Payload -->
<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-top: 0; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #dee2e6;">Payload</h3>
    <pre style="background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; max-height: 400px; overflow-y: auto;"><?= htmlspecialchars(json_encode($event['payload_decoded'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
</div>

<!-- Metadata -->
<?php if ($event['metadata_decoded']): ?>
<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-top: 0; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #dee2e6;">Metadados</h3>
    <pre style="background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; max-height: 300px; overflow-y: auto;"><?= htmlspecialchars(json_encode($event['metadata_decoded'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
</div>
<?php endif; ?>

<!-- Eventos Relacionados -->
<?php if (!empty($relatedEvents)): ?>
<div class="card">
    <h3 style="margin-top: 0; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #dee2e6;">Eventos Relacionados (mesmo trace)</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                <th style="padding: 10px; text-align: left; font-weight: 600; font-size: 12px;">Data/Hora</th>
                <th style="padding: 10px; text-align: left; font-weight: 600; font-size: 12px;">Tipo</th>
                <th style="padding: 10px; text-align: left; font-weight: 600; font-size: 12px;">Origem</th>
                <th style="padding: 10px; text-align: left; font-weight: 600; font-size: 12px;">Status</th>
                <th style="padding: 10px; text-align: left; font-weight: 600; font-size: 12px;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($relatedEvents as $related): ?>
                <tr style="border-bottom: 1px solid #dee2e6;">
                    <td style="padding: 10px; font-size: 12px;">
                        <?= date('d/m/Y H:i:s', strtotime($related['created_at'])) ?>
                    </td>
                    <td style="padding: 10px; font-size: 12px;">
                        <code style="background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                            <?= htmlspecialchars($related['event_type']) ?>
                        </code>
                    </td>
                    <td style="padding: 10px; font-size: 12px;">
                        <?= htmlspecialchars($related['source_system']) ?>
                    </td>
                    <td style="padding: 10px; font-size: 12px;">
                        <?php
                        $color = $statusColors[$related['status']] ?? '#6c757d';
                        ?>
                        <span style="background: <?= $color ?>; color: white; padding: 4px 8px; border-radius: 3px; font-size: 11px;">
                            <?= strtoupper($related['status']) ?>
                        </span>
                    </td>
                    <td style="padding: 10px;">
                        <a href="<?= pixelhub_url('/settings/communication-events/view?event_id=' . urlencode($related['event_id'])) ?>" 
                           style="color: #007bff; text-decoration: none; font-size: 12px;">
                            Ver
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layout/main.php';
?>

