<?php
/**
 * Central de Eventos de Comunicação
 */
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header">
    <div>
        <h2>Central de Eventos de Comunicação</h2>
        <p>Visualize e monitore todos os eventos do sistema de comunicação centralizado</p>
    </div>
</div>

<!-- Estatísticas -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
    <div class="card" style="text-align: center; padding: 20px;">
        <div style="font-size: 32px; font-weight: bold; color: #007bff;">
            <?= array_sum($stats) ?>
        </div>
        <div style="color: #666; font-size: 14px; margin-top: 5px;">Total de Eventos</div>
    </div>
    <div class="card" style="text-align: center; padding: 20px;">
        <div style="font-size: 32px; font-weight: bold; color: #28a745;">
            <?= $stats['processed'] ?? 0 ?>
        </div>
        <div style="color: #666; font-size: 14px; margin-top: 5px;">Processados</div>
    </div>
    <div class="card" style="text-align: center; padding: 20px;">
        <div style="font-size: 32px; font-weight: bold; color: #ffc107;">
            <?= $stats['queued'] ?? 0 ?>
        </div>
        <div style="color: #666; font-size: 14px; margin-top: 5px;">Na Fila</div>
    </div>
    <div class="card" style="text-align: center; padding: 20px;">
        <div style="font-size: 32px; font-weight: bold; color: #dc3545;">
            <?= $stats['failed'] ?? 0 ?>
        </div>
        <div style="color: #666; font-size: 14px; margin-top: 5px;">Falhados</div>
    </div>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom: 20px;">
    <form method="GET" action="<?= pixelhub_url('/settings/communication-events') ?>" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Tipo de Evento</label>
            <select name="event_type" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">Todos</option>
                <?php foreach ($eventTypes as $type): ?>
                    <option value="<?= htmlspecialchars($type) ?>" <?= ($filters['event_type'] === $type) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($type) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Sistema de Origem</label>
            <select name="source_system" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">Todos</option>
                <?php foreach ($sourceSystems as $source): ?>
                    <option value="<?= htmlspecialchars($source) ?>" <?= ($filters['source_system'] === $source) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($source) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Status</label>
            <select name="status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">Todos</option>
                <option value="queued" <?= ($filters['status'] === 'queued') ? 'selected' : '' ?>>Na Fila</option>
                <option value="processing" <?= ($filters['status'] === 'processing') ? 'selected' : '' ?>>Processando</option>
                <option value="processed" <?= ($filters['status'] === 'processed') ? 'selected' : '' ?>>Processado</option>
                <option value="failed" <?= ($filters['status'] === 'failed') ? 'selected' : '' ?>>Falhado</option>
            </select>
        </div>
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Trace ID</label>
            <input type="text" name="trace_id" value="<?= htmlspecialchars($filters['trace_id'] ?? '') ?>" 
                   placeholder="UUID do trace" 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px;">
        </div>
        <div>
            <button type="submit" style="width: 100%; padding: 10px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Filtrar
            </button>
        </div>
    </form>
</div>

<!-- Tabela de Eventos -->
<div class="card">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px;">Data/Hora</th>
                <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px;">Tipo</th>
                <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px;">Origem</th>
                <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px;">Cliente</th>
                <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px;">Status</th>
                <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($events)): ?>
                <tr>
                    <td colspan="6" style="padding: 40px; text-align: center; color: #666;">
                        Nenhum evento encontrado
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 12px; font-size: 13px;">
                            <?= date('d/m/Y H:i:s', strtotime($event['created_at'])) ?>
                        </td>
                        <td style="padding: 12px; font-size: 13px;">
                            <code style="background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                <?= htmlspecialchars($event['event_type']) ?>
                            </code>
                        </td>
                        <td style="padding: 12px; font-size: 13px;">
                            <span style="background: #e7f3ff; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600;">
                                <?= htmlspecialchars($event['source_system']) ?>
                            </span>
                        </td>
                        <td style="padding: 12px; font-size: 13px;">
                            <?php if ($event['tenant_name']): ?>
                                <a href="<?= pixelhub_url('/tenants/view?id=' . $event['tenant_id']) ?>" style="color: #007bff; text-decoration: none;">
                                    <?= htmlspecialchars($event['tenant_name']) ?>
                                </a>
                            <?php else: ?>
                                <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; font-size: 13px;">
                            <?php
                            $statusColors = [
                                'queued' => '#ffc107',
                                'processing' => '#17a2b8',
                                'processed' => '#28a745',
                                'failed' => '#dc3545'
                            ];
                            $color = $statusColors[$event['status']] ?? '#6c757d';
                            ?>
                            <span style="background: <?= $color ?>; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                <?= strtoupper($event['status']) ?>
                            </span>
                            <?php if ($event['error_message']): ?>
                                <br><small style="color: #dc3545; font-size: 11px;"><?= htmlspecialchars(substr($event['error_message'], 0, 50)) ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px;">
                            <a href="<?= pixelhub_url('/settings/communication-events/view?event_id=' . urlencode($event['event_id'])) ?>" 
                               style="color: #007bff; text-decoration: none; font-size: 12px; font-weight: 600;">
                                Ver Detalhes
                            </a>
                            <?php if ($event['trace_id']): ?>
                                <br>
                                <a href="<?= pixelhub_url('/settings/communication-events?trace_id=' . urlencode($event['trace_id'])) ?>" 
                                   style="color: #6c757d; text-decoration: none; font-size: 11px;">
                                    Ver Trace
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Paginação -->
    <?php if ($pagination['total_pages'] > 1): ?>
        <div style="padding: 20px; text-align: center; border-top: 1px solid #dee2e6;">
            <?php if ($pagination['page'] > 1): ?>
                <a href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['page'] - 1])) ?>" 
                   style="display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;">
                    ← Anterior
                </a>
            <?php endif; ?>
            
            <span style="color: #666; font-size: 14px;">
                Página <?= $pagination['page'] ?> de <?= $pagination['total_pages'] ?> 
                (<?= $pagination['total'] ?> eventos)
            </span>
            
            <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                <a href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['page'] + 1])) ?>" 
                   style="display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-left: 10px;">
                    Próxima →
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layout/main.php';
?>

