<?php
ob_start();
$projects = $projects ?? [];
$todayStr = $todayStr ?? date('Y-m-d');
$todayTs = strtotime($todayStr);
$tomorrowStr = date('Y-m-d', strtotime($todayStr . ' +1 day'));
$boardBase = pixelhub_url('/projects/board');
?>

<?php
function timelineBadgeLabel(string $dueDate, string $todayStr, string $tomorrowStr): string {
    if ($dueDate < $todayStr) return 'Atrasada';
    if ($dueDate === $todayStr) return 'Hoje';
    if ($dueDate === $tomorrowStr) return 'Amanhã';
    $days = (strtotime($dueDate) - strtotime($todayStr)) / 86400;
    return 'D-' . (int)$days;
}
function timelinePrazoLabel(?string $dueDate, string $todayStr): string {
    if (!$dueDate) return '—';
    $d = date('d/m/Y', strtotime($dueDate));
    $days = (int)((strtotime($dueDate) - strtotime($todayStr)) / 86400);
    if ($days < 0) return $d . ' • Atrasado';
    if ($days === 0) return $d . ' • Hoje';
    return $d . ' • D-' . $days;
}
/** Posição percentual na régua 4 semanas (0=hoje, 100=hoje+28). Overdue=0. */
function timelinePosition(?string $dueDate, string $todayStr): ?float {
    if (!$dueDate) return null;
    $days = (strtotime($dueDate) - strtotime($todayStr)) / 86400;
    if ($days < 0) return 0;
    if ($days > 28) return 100;
    return ($days / 28) * 100;
}
?>

<style>
    .timeline-header { margin-bottom: 24px; }
    .timeline-header h2 { margin: 0 0 8px 0; font-size: 24px; color: #333; }
    .timeline-header p { margin: 0; color: #666; font-size: 14px; }
    .timeline-container {
        background: white;
        border-radius: 8px;
        padding: 24px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    }
    .timeline-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    .timeline-table th {
        text-align: left;
        padding: 10px 12px;
        font-weight: 600;
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
    }
    .timeline-table td {
        padding: 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .timeline-table tbody tr:hover { background: #f8fafc; }
    .timeline-table .col-projeto { width: 18%; font-weight: 600; color: #111827; }
    .timeline-table .col-cliente { width: 12%; color: #64748b; font-size: 13px; }
    .timeline-table .col-proxima { width: 24%; }
    .timeline-table .col-timeline { width: 200px; min-width: 200px; }
    .timeline-table .col-abertas { width: 8%; text-align: center; }
    .timeline-table .col-atrasadas { width: 8%; text-align: center; }
    .timeline-table .col-prazo { width: 14%; font-size: 13px; color: #475569; }
    .timeline-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 13px;
        text-decoration: none;
        color: inherit;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        transition: background 0.15s, border-color 0.15s;
        max-width: 100%;
    }
    .timeline-chip:hover {
        background: #e2e8f0;
        border-color: #cbd5e1;
    }
    .timeline-chip .chip-title {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 180px;
    }
    .timeline-chip .chip-badge {
        flex-shrink: 0;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 600;
    }
    .timeline-chip .chip-badge.atrasada { background: #fecaca; color: #b91c1c; }
    .timeline-chip .chip-badge.hoje { background: #fef3c7; color: #b45309; }
    .timeline-chip .chip-badge.amanha { background: #dbeafe; color: #1d4ed8; }
    .timeline-chip .chip-badge.d-x { background: #e2e8f0; color: #475569; }
    .timeline-chip-empty {
        color: #94a3b8;
        font-size: 13px;
        font-style: italic;
    }
    .timeline-project-link {
        color: #023A8D;
        text-decoration: none;
        font-weight: 600;
    }
    .timeline-project-link:hover { text-decoration: underline; }
    .timeline-empty {
        text-align: center;
        padding: 60px 20px;
        color: #888;
        font-size: 16px;
    }
    .timeline-back {
        display: inline-block;
        margin-bottom: 16px;
        color: #023A8D;
        text-decoration: none;
        font-weight: 500;
    }
    .timeline-back:hover { text-decoration: underline; }
    /* Coluna Timeline (4 semanas) */
    .timeline-ruler {
        position: relative;
        width: 200px;
        height: 32px;
        background: #f1f5f9;
        border-radius: 6px;
        overflow: visible;
    }
    .timeline-ruler-inner {
        position: relative;
        width: 100%;
        height: 100%;
        border-radius: 6px;
    }
    .timeline-ruler-hoje {
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #0ea5e9;
        z-index: 2;
    }
    .timeline-ruler-hoje::after {
        content: 'Hoje';
        position: absolute;
        left: 2px;
        top: 2px;
        font-size: 9px;
        font-weight: 600;
        color: #0ea5e9;
        white-space: nowrap;
    }
    .timeline-ruler-week {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 1px;
        background: #cbd5e1;
        z-index: 1;
    }
    .timeline-ruler-marker {
        position: absolute;
        top: 50%;
        transform: translate(-50%, -50%);
        z-index: 4;
    }
    .timeline-ruler-marker.prazo {
        width: 0;
        height: 0;
        border-left: 6px solid transparent;
        border-right: 6px solid transparent;
        border-bottom: 10px solid #1e293b;
        margin-top: -5px;
    }
    .timeline-ruler-marker.next {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #f59e0b;
        box-shadow: 0 0 0 2px white;
    }
    .timeline-ruler-marker.future {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #64748b;
    }
    .timeline-ruler-marker.overdue {
        background: #dc2626;
    }
</style>

<a href="<?= pixelhub_url('/agenda') ?>" class="timeline-back">← Voltar para Agenda</a>

<div class="timeline-header">
    <h2>Visão Macro — Projetos e Prazos</h2>
    <p>Próxima entrega por projeto, contadores e prazo final</p>
</div>

<div class="timeline-container">
    <?php if (empty($projects)): ?>
        <div class="timeline-empty">
            Nenhum projeto ativo.
        </div>
    <?php else: ?>
        <table class="timeline-table">
            <thead>
                <tr>
                    <th class="col-projeto">Projeto</th>
                    <th class="col-cliente">Cliente</th>
                    <th class="col-proxima">Próxima entrega</th>
                    <th class="col-timeline">Timeline (4 semanas)</th>
                    <th class="col-abertas">Abertas</th>
                    <th class="col-atrasadas">Atrasadas</th>
                    <th class="col-prazo">Prazo</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($projects as $p): ?>
                <?php
                $nextTask = $p['next_task'] ?? null;
                $projectUrl = pixelhub_url('/projects/board?project_id=' . (int)$p['id']);
                $prazoLabel = timelinePrazoLabel($p['due_date'] ?? null, $todayStr);
                ?>
                <tr>
                    <td class="col-projeto">
                        <a href="<?= $projectUrl ?>" class="timeline-project-link"><?= htmlspecialchars($p['name']) ?></a>
                    </td>
                    <td class="col-cliente"><?= htmlspecialchars($p['tenant_name'] ?? 'Interno') ?></td>
                    <td class="col-proxima">
                        <?php if ($nextTask): ?>
                            <?php
                            $taskUrl = $boardBase . '?project_id=' . (int)$p['id'] . '&task_id=' . (int)$nextTask['id'];
                            $badgeLabel = timelineBadgeLabel($nextTask['due_date'], $todayStr, $tomorrowStr);
                            $badgeClass = ($badgeLabel === 'Atrasada') ? 'atrasada' : (($badgeLabel === 'Hoje') ? 'hoje' : (($badgeLabel === 'Amanhã') ? 'amanha' : 'd-x'));
                            $titleShort = mb_strimwidth($nextTask['title'] ?? '', 0, 35, '…');
                            ?>
                            <a href="<?= htmlspecialchars($taskUrl) ?>" class="timeline-chip" title="<?= htmlspecialchars($nextTask['title'] ?? '') ?>">
                                <span class="chip-title"><?= htmlspecialchars($titleShort) ?></span>
                                <span class="chip-badge <?= $badgeClass ?>"><?= htmlspecialchars($badgeLabel) ?></span>
                            </a>
                        <?php else: ?>
                            <span class="timeline-chip-empty">Sem tarefas com prazo</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-timeline">
                        <?php
                        $prazoPos = timelinePosition($p['due_date'] ?? null, $todayStr);
                        $nextPos = $nextTask ? timelinePosition($nextTask['due_date'], $todayStr) : null;
                        $nextOverdue = $nextTask && ($nextTask['due_date'] ?? '') < $todayStr;
                        $futureTasks = $p['future_tasks'] ?? [];
                        ?>
                        <div class="timeline-ruler">
                            <div class="timeline-ruler-inner">
                                <div class="timeline-ruler-hoje" title="Hoje"></div>
                                <?php for ($w = 1; $w <= 4; $w++): ?>
                                <div class="timeline-ruler-week" style="left: <?= ($w / 4) * 100 ?>%;"></div>
                                <?php endfor; ?>
                                <?php if ($prazoPos !== null): ?>
                                <div class="timeline-ruler-marker prazo" style="left: <?= $prazoPos ?>%;" title="Prazo: <?= htmlspecialchars($p['due_date'] ? date('d/m/Y', strtotime($p['due_date'])) : '') ?>"></div>
                                <?php endif; ?>
                                <?php if ($nextPos !== null): ?>
                                <a href="<?= htmlspecialchars($boardBase . '?project_id=' . (int)$p['id'] . '&task_id=' . (int)$nextTask['id']) ?>" class="timeline-ruler-marker next <?= $nextOverdue ? 'overdue' : '' ?>" style="left: <?= $nextPos ?>%;" title="<?= htmlspecialchars(($nextTask['title'] ?? '') . ' — ' . ($nextTask['due_date'] ? date('d/m', strtotime($nextTask['due_date'])) : '')) ?>"></a>
                                <?php endif; ?>
                                <?php foreach ($futureTasks as $ft): ?>
                                    <?php $ftPos = timelinePosition($ft['due_date'] ?? null, $todayStr); if ($ftPos !== null): ?>
                                <a href="<?= htmlspecialchars($boardBase . '?project_id=' . (int)$p['id'] . '&task_id=' . (int)$ft['id']) ?>" class="timeline-ruler-marker future" style="left: <?= $ftPos ?>%;" title="<?= htmlspecialchars(($ft['title'] ?? '') . ' — ' . ($ft['due_date'] ? date('d/m', strtotime($ft['due_date'])) : '')) ?>"></a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </td>
                    <td class="col-abertas"><?= (int)($p['open_tasks_count'] ?? 0) ?></td>
                    <td class="col-atrasadas"><?= (int)($p['overdue_tasks_count'] ?? 0) ?></td>
                    <td class="col-prazo"><?= htmlspecialchars($prazoLabel) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
$title = 'Visão Macro — Projetos';
require __DIR__ . '/../layout/main.php';
?>
