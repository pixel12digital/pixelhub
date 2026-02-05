<?php
ob_start();
$projects = $projects ?? [];
$todayStr = $todayStr ?? date('Y-m-d');
$todayTs = strtotime($todayStr);
$tomorrowStr = date('Y-m-d', strtotime($todayStr . ' +1 day'));
$boardBase = pixelhub_url('/projects/board');
$activeTab = $_GET['tab'] ?? 'tabela';
if (!in_array($activeTab, ['tabela', 'grafico'])) $activeTab = 'tabela';
$baseTimelineUrl = pixelhub_url('/agenda/timeline');
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
/**
 * Parse seguro: APENAS ISO (Y-m-d ou Y-m-d H:i:s). NUNCA dd/mm/yyyy.
 * Retorna timestamp (meio-dia local para evitar timezone) ou null.
 */
function parseDateToTs(?string $dateStr): ?int {
    if (!$dateStr || trim($dateStr) === '') return null;
    $s = trim($dateStr);
    $datePart = substr($s, 0, 10);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $datePart, $m)) {
        return mktime(12, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
    }
    return null;
}
/** Posição percentual no Gantt (rangeStart a rangeEnd em timestamps). Retorna 0-100 ou null. */
function ganttPosition(?string $dateStr, int $rangeStart, int $rangeEnd): ?float {
    $ts = parseDateToTs($dateStr);
    if ($ts === null || $rangeEnd <= $rangeStart) return null;
    return (($ts - $rangeStart) / ($rangeEnd - $rangeStart)) * 100;
}
/** Retorna posição para exibição (clamp 0-100 se fora do range). */
function ganttPositionClamped(?string $dateStr, int $rangeStart, int $rangeEnd): ?float {
    $p = ganttPosition($dateStr, $rangeStart, $rangeEnd);
    if ($p === null) return null;
    return max(0, min(100, $p));
}
/**
 * Atribui faixas (lanes) a tarefas sobrepostas para empilhar verticalmente.
 * Retorna array de tarefas com 'lane' (0,1,2...) e 'left','right' em %.
 */
function assignTaskLanes(array $tasks, int $rangeStart, int $rangeEnd): array {
    $result = [];
    foreach ($tasks as $tk) {
        $left = ganttPosition($tk['start_date'] ?? null, $rangeStart, $rangeEnd);
        $right = ganttPosition($tk['due_date'] ?? null, $rangeStart, $rangeEnd);
        if ($left === null || $right === null) continue;
        if ($right <= $left) $right = $left + 4;
        $result[] = array_merge($tk, ['left' => $left, 'right' => $right, 'lane' => -1]);
    }
    usort($result, fn($a, $b) => $a['left'] <=> $b['left']);
    $lanes = [];
    foreach ($result as &$t) {
        $lane = 0;
        while (true) {
            $ok = true;
            foreach ($lanes[$lane] ?? [] as $other) {
                if ($t['left'] < $other['right'] && $other['left'] < $t['right']) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) break;
            $lane++;
        }
        if (!isset($lanes[$lane])) $lanes[$lane] = [];
        $lanes[$lane][] = ['left' => $t['left'], 'right' => $t['right']];
        $t['lane'] = $lane;
    }
    return $result;
}
$MESES_PT = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
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
    /* Tabs */
    .timeline-tabs {
        display: flex;
        gap: 0;
        margin-bottom: 20px;
        border-bottom: 1px solid #e2e8f0;
    }
    .timeline-tabs a {
        padding: 10px 20px;
        font-size: 14px;
        font-weight: 500;
        color: #64748b;
        text-decoration: none;
        border-bottom: 2px solid transparent;
        margin-bottom: -1px;
    }
    .timeline-tabs a:hover { color: #334155; }
    .timeline-tabs a.active {
        color: #023A8D;
        border-bottom-color: #023A8D;
    }
    .timeline-tab-panel { display: none; }
    .timeline-tab-panel.active { display: block; }
    /* Gantt */
    .gantt-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 16px 24px;
        margin-bottom: 16px;
        padding: 10px 14px;
        background: #f8fafc;
        border-radius: 6px;
        font-size: 12px;
        color: #475569;
    }
    .gantt-legend-item { display: flex; align-items: center; gap: 6px; }
    .gantt-legend-dot { width: 10px; height: 10px; border-radius: 50%; }
    .gantt-legend-bar { width: 24px; height: 8px; border-radius: 4px; }
    .gantt-legend-prazo { font-size: 10px; color: #1e293b; line-height: 1; }
    .gantt-outer {
        width: 100%;
        overflow: visible;
    }
    .gantt-wrapper {
        width: 100%;
        padding: 16px 0;
        box-sizing: border-box;
    }
    .gantt-periodo {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 8px;
        font-weight: 500;
    }
    .gantt-axis {
        display: flex;
        align-items: stretch;
        gap: 12px;
        height: 36px;
        margin-bottom: 8px;
        font-size: 11px;
        color: #64748b;
    }
    .gantt-axis-spacer {
        flex: 0 0 200px;
        min-width: 180px;
    }
    .gantt-axis-chart {
        flex: 1;
        position: relative;
        background: #f1f5f9;
        border-radius: 6px;
        min-width: 0;
    }
    .gantt-axis-label {
        position: absolute;
        transform: translateX(-50%);
        top: 8px;
        white-space: nowrap;
    }
    .gantt-axis-hoje {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #0ea5e9;
        z-index: 3;
    }
    .gantt-axis-hoje-label {
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        bottom: 100%;
        margin-bottom: 2px;
        font-size: 10px;
        font-weight: 600;
        color: #0ea5e9;
        white-space: nowrap;
    }
    .gantt-axis-hoje.outside-left .gantt-axis-hoje-label { left: 0; transform: none; }
    .gantt-axis-hoje.outside-right .gantt-axis-hoje-label { left: 100%; transform: translateX(-100%); }
    .gantt-row {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        min-height: 44px;
        padding: 6px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .gantt-row-label {
        flex: 0 0 200px;
        min-width: 180px;
        font-size: 13px;
    }
    .gantt-row-label a { color: #023A8D; text-decoration: none; font-weight: 500; }
    .gantt-row-label a:hover { text-decoration: underline; }
    .gantt-row-chart {
        flex: 1;
        position: relative;
        min-height: 28px;
        background: #f1f5f9;
        background-image: repeating-linear-gradient(
            to bottom,
            transparent 0,
            transparent 21px,
            rgba(148, 163, 184, 0.2) 21px,
            rgba(148, 163, 184, 0.2) 22px
        );
        background-position: 0 6px;
        border-radius: 8px;
        min-width: 0;
        overflow: hidden;
        padding: 6px 8px;
        box-sizing: border-box;
    }
    .gantt-bar {
        position: absolute;
        top: 6px;
        bottom: 6px;
        border-radius: 6px;
        background: #cbd5e1;
        text-decoration: none;
        cursor: pointer;
        display: block;
        transition: background 0.15s;
    }
    .gantt-bar:hover { background: #94a3b8; }
    .gantt-bar.overdue { background: #fecaca; }
    .gantt-bar.tenant-tickets { background: #ddd6fe; }
    .gantt-bar.tenant-tickets:hover { background: #c4b5fd; }
    .gantt-task-bar {
        position: absolute;
        height: 10px;
        border-radius: 4px;
        background: #475569;
        z-index: 2;
        text-decoration: none;
        cursor: pointer;
        border: 1px solid rgba(51, 65, 85, 0.2);
        box-shadow: none;
        opacity: 0.75;
        transition: opacity 0.15s, box-shadow 0.15s, border-color 0.15s;
    }
    .gantt-row-chart:hover .gantt-task-bar { opacity: 0.9; }
    .gantt-task-bar:hover { opacity: 1; box-shadow: 0 1px 3px rgba(0,0,0,0.12); border-color: #334155; }
    .gantt-task-bar.ticket { background: #7c3aed; border-color: rgba(124, 58, 237, 0.3); }
    .gantt-task-bar.overdue { background: #b91c1c; border-color: rgba(153, 27, 27, 0.4); }
    .gantt-task-bar.next-delivery {
        border: 2px solid #f59e0b;
        box-shadow: 0 0 0 1px #fef3c7;
    }
    .gantt-task-bar.next-delivery:hover { box-shadow: 0 0 0 2px #f59e0b, 0 1px 3px rgba(0,0,0,0.12); }
    .gantt-task-bar.closed { background: #94a3b8; border-color: #64748b; opacity: 0.5; }
    .gantt-task-bar.closed:hover { opacity: 0.85; }
    /* Tarefa de um dia = ponto circular */
    .gantt-task-bar.gantt-task-dot {
        width: 8px !important;
        height: 8px;
        min-width: 8px;
        border-radius: 50%;
        transform: translateX(-50%);
    }
    .gantt-task-bar.gantt-task-dot.next-delivery {
        width: 10px !important;
        height: 10px;
        min-width: 10px;
        transform: translateX(-50%);
    }
    .gantt-legend-task { height: 10px; background: #475569; border-radius: 4px; }
    /* Limite visual: max 6 lanes visíveis (0-5) */
    .gantt-row-chart.gantt-collapsed { max-height: 154px; }
    .gantt-row-chart.gantt-collapsed .gantt-task-bar[data-lane="6"],
    .gantt-row-chart.gantt-collapsed .gantt-task-bar[data-lane="7"],
    .gantt-row-chart.gantt-collapsed .gantt-task-bar[data-lane="8"],
    .gantt-row-chart.gantt-collapsed .gantt-task-bar[data-lane="9"],
    .gantt-row-chart.gantt-collapsed .gantt-task-bar[data-lane="10"],
    .gantt-row-chart.gantt-collapsed .gantt-task-bar[data-lane="11"],
    .gantt-row-chart.gantt-collapsed .gantt-task-bar[data-lane="12"],
    .gantt-row-chart.gantt-collapsed .gantt-task-bar[data-lane="13"],
    .gantt-row-chart.gantt-collapsed .gantt-task-bar[data-lane="14"],
    .gantt-row-chart.gantt-collapsed .gantt-task-bar[data-lane="15"] { display: none; }
    .gantt-expand-trigger {
        position: absolute;
        bottom: 6px;
        right: 12px;
        font-size: 10px;
        font-weight: 600;
        color: #64748b;
        background: rgba(255,255,255,0.95);
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        z-index: 5;
        transition: background 0.15s, color 0.15s;
    }
    .gantt-expand-trigger:hover { background: #f1f5f9; color: #334155; }
    .gantt-row-chart.gantt-expanded .gantt-expand-trigger { display: none; }
    .gantt-row-chart.gantt-expanded { max-height: none !important; }
</style>

<a href="<?= pixelhub_url('/agenda') ?>" class="timeline-back">← Voltar para Agenda</a>

<div class="timeline-header">
    <h2>Visão Macro — Projetos e Prazos</h2>
    <p>Próxima entrega por projeto, contadores e prazo final</p>
</div>

<div class="timeline-tabs">
    <a href="<?= $baseTimelineUrl ?>" class="<?= $activeTab === 'tabela' ? 'active' : '' ?>">Tabela</a>
    <a href="<?= $baseTimelineUrl ?>?tab=grafico" class="<?= $activeTab === 'grafico' ? 'active' : '' ?>">Gráfico</a>
</div>

<div class="timeline-container">
    <?php if (empty($projects)): ?>
        <div class="timeline-empty">
            Nenhum projeto ativo.
        </div>
    <?php else: ?>
        <!-- Tab Tabela (sem coluna Timeline) -->
        <div class="timeline-tab-panel <?= $activeTab === 'tabela' ? 'active' : '' ?>">
            <table class="timeline-table">
                <thead>
                    <tr>
                        <th class="col-projeto">Projeto</th>
                        <th class="col-cliente">Cliente</th>
                        <th class="col-proxima">Próxima entrega</th>
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
                        <td class="col-abertas"><?= (int)($p['open_tasks_count'] ?? 0) ?></td>
                        <td class="col-atrasadas"><?= (int)($p['overdue_tasks_count'] ?? 0) ?></td>
                        <td class="col-prazo"><?= htmlspecialchars($prazoLabel) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Tab Gráfico (Gantt macro) -->
        <div class="timeline-tab-panel <?= $activeTab === 'grafico' ? 'active' : '' ?>">
            <?php
            $startGlobal = PHP_INT_MAX;
            $endGlobal = 0;
            foreach ($projects as $p) {
                $startDate = $p['start_date'] ?? $p['created_at'] ?? null;
                if ($startDate) {
                    $ts = parseDateToTs($startDate);
                    if ($ts !== null && $ts < $startGlobal) $startGlobal = $ts;
                }
                $due = $p['due_date'] ?? null;
                if ($due) {
                    $ts = parseDateToTs($due);
                    if ($ts !== null && $ts > $endGlobal) $endGlobal = $ts;
                }
            }
            if ($endGlobal < $todayTs) $endGlobal = $todayTs;
            if ($startGlobal === PHP_INT_MAX) $startGlobal = $todayTs - (90 * 86400);
            $paddingDays = 5;
            $rangeStart = $startGlobal - ($paddingDays * 86400);
            $rangeEnd = $endGlobal + ($paddingDays * 86400);
            $rangeSpan = max(1, $rangeEnd - $rangeStart);
            $hojePos = ganttPosition($todayStr, $rangeStart, $rangeEnd);
            $hojeInside = $hojePos !== null && $hojePos >= 0 && $hojePos <= 100;
            $hojeOutsideLeft = $hojePos !== null && $hojePos < 0;
            $hojeOutsideRight = $hojePos !== null && $hojePos > 100;
            $rangeDays = $rangeSpan / 86400;
            $ticks = [];
            $firstMonth = strtotime(date('Y-m-01', $rangeStart));
            $lastMonth = strtotime(date('Y-m-01', $rangeEnd));
            if ($rangeDays <= 120) {
                $d = $firstMonth;
                while ($d <= $rangeEnd) {
                    $ticks[] = $d;
                    $d = strtotime('+1 month', $d);
                }
            } else {
                $d = $firstMonth;
                while ($d <= $rangeEnd) {
                    $m = (int)date('n', $d);
                    if (in_array($m, [1, 4, 7, 10]) || $d == $firstMonth || $d == $lastMonth) {
                        $ticks[] = $d;
                    }
                    $d = strtotime('+1 month', $d);
                }
            }
            $ticks = array_unique($ticks);
            sort($ticks);
            if (empty($ticks)) {
                $ticks = [$rangeStart, $rangeEnd];
            }
            $periodoIni = date('d/m/Y', $rangeStart);
            $periodoFim = date('d/m/Y', $rangeEnd);
            ?>
            <div class="gantt-periodo">Período: <?= $periodoIni ?> → <?= $periodoFim ?></div>
            <div class="gantt-legend">
                <span class="gantt-legend-item"><span class="gantt-legend-bar" style="background:#cbd5e1;"></span> Barra do projeto</span>
                <span class="gantt-legend-item"><span class="gantt-legend-bar" style="background:#fecaca;"></span> Projeto atrasado</span>
                <span class="gantt-legend-item"><span class="gantt-legend-bar" style="background:#ddd6fe;"></span> Linha de tickets sem projeto</span>
                <span class="gantt-legend-item"><span class="gantt-legend-bar gantt-legend-task"></span> Tarefas (Kanban)</span>
                <span class="gantt-legend-item"><span class="gantt-legend-bar" style="background:#7c3aed;"></span> Ticket aberto</span>
                <span class="gantt-legend-item"><span class="gantt-legend-bar" style="background:#b91c1c;"></span> Ticket/Tarefa atrasado</span>
                <span class="gantt-legend-item"><span class="gantt-legend-bar" style="background:#475569;border:2px solid #f59e0b;"></span> Próxima entrega</span>
                <span class="gantt-legend-item"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#475569;"></span> Tarefa de 1 dia</span>
            </div>
            <p class="gantt-legend-hint" style="font-size:11px;color:#64748b;margin:-8px 0 12px 0;">Roxo = linha de tickets sem projeto. Roxo/vermelho = ticket (vermelho = atrasado). Tickets e tarefas são distintos: tickets em Tickets de Suporte; tarefas no Quadro Kanban.</p>
            <div class="gantt-outer">
                <div class="gantt-wrapper">
                    <div class="gantt-axis">
                        <div class="gantt-axis-spacer"></div>
                        <div class="gantt-axis-chart">
                        <?php foreach ($ticks as $tickTs): ?>
                            <?php $tickPct = (($tickTs - $rangeStart) / $rangeSpan) * 100; if ($tickPct >= -1 && $tickPct <= 101): ?>
                        <div class="gantt-axis-label" style="left: <?= $tickPct ?>%;"><?= $MESES_PT[(int)date('n', $tickTs)-1] ?>/<?= date('y', $tickTs) ?></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($hojePos !== null): ?>
                            <?php
                            $hojeDisplayPos = $hojeInside ? $hojePos : ($hojeOutsideLeft ? 0 : 100);
                            $hojeClass = $hojeOutsideLeft ? 'outside-left' : ($hojeOutsideRight ? 'outside-right' : '');
                            ?>
                        <div class="gantt-axis-hoje <?= $hojeClass ?>" style="left: <?= $hojeDisplayPos ?>%;">
                            <span class="gantt-axis-hoje-label"><?php
                                if ($hojeOutsideLeft) echo '← Hoje (' . date('d/m/Y', $todayTs) . ')';
                                elseif ($hojeOutsideRight) echo 'Hoje (' . date('d/m/Y', $todayTs) . ') →';
                                else echo 'Hoje (' . date('d/m/Y', $todayTs) . ')';
                            ?></span>
                        </div>
                        <?php endif; ?>
                        </div>
                    </div>
                    <?php foreach ($projects as $p): ?>
                        <?php
                        $isTenantTickets = !empty($p['is_tenant_tickets']);
                        $projectId = is_numeric($p['id']) ? (int)$p['id'] : null;
                        $tenantId = $p['tenant_id'] ?? null;
                        $labelUrl = $isTenantTickets ? pixelhub_url('/tickets?tenant_id=' . (int)$tenantId) : pixelhub_url('/projects/board?project_id=' . $projectId);
                        $created = $p['created_at'] ?? date('Y-m-d', $rangeStart);
                        $prazo = $p['due_date'] ?? null;
                        $barEnd = $prazo ?: $todayStr;
                        $barStartPos = ganttPosition($created, $rangeStart, $rangeEnd);
                        $barEndPos = ganttPosition($barEnd, $rangeStart, $rangeEnd);
                        $visibleLeft = max(0, min(100, min($barStartPos ?? 0, $barEndPos ?? 100)));
                        $visibleRight = max(0, min(100, max($barStartPos ?? 0, $barEndPos ?? 100)));
                        $barLeft = $visibleLeft;
                        $barWidth = max(2, $visibleRight - $visibleLeft);
                        $isOverdue = $p['due_date'] && strtotime($p['due_date']) < $todayTs;
                        $taskLanes = assignTaskLanes($p['timeline_tasks'] ?? [], $rangeStart, $rangeEnd);
                        $numLanes = empty($taskLanes) ? 1 : (max(array_column($taskLanes, 'lane')) + 1);
                        $chartHeight = 12 + $numLanes * 22;
                        $nextTaskId = ($p['next_task']['id'] ?? null);
                        $needsCollapse = $numLanes > 6;
                        $taskCount = count($taskLanes);
                        $taskTypes = array_count_values(array_map(fn($t) => $t['type'] ?? 'task', $taskLanes));
                        $tasksCount = $taskTypes['task'] ?? 0;
                        $ticketsCount = $taskTypes['ticket'] ?? 0;
                        if ($isTenantTickets) {
                            $barTooltip = "Exibido por: " . $ticketsCount . " ticket(s) aberto(s) sem projeto vinculado\nCliente: " . htmlspecialchars($p['tenant_name'] ?? '');
                            $barTooltip .= "\nInício: " . date('d/m/Y', strtotime($created)) . " — Fim: " . date('d/m/Y', strtotime($barEnd));
                        } else {
                            $barTooltip = htmlspecialchars($p['name']) . "\nInício: " . date('d/m/Y', strtotime($created)) . " — Fim: " . date('d/m/Y', strtotime($barEnd));
                            if ($taskCount > 0) {
                                $parts = [];
                                if ($tasksCount > 0) $parts[] = $tasksCount . ' tarefa' . ($tasksCount > 1 ? 's' : '');
                                if ($ticketsCount > 0) $parts[] = $ticketsCount . ' ticket' . ($ticketsCount > 1 ? 's' : '');
                                $barTooltip .= "\nExibido por: " . implode(', ', $parts) . " abertos";
                            } else {
                                $barTooltip .= "\nExibido por: prazo do projeto em " . date('d/m/Y', strtotime($barEnd));
                            }
                        }
                        ?>
                        <div class="gantt-row">
                            <div class="gantt-row-label">
                                <a href="<?= $labelUrl ?>"><?= htmlspecialchars($p['name']) ?></a>
                                <span style="color:#94a3b8;font-size:11px;"><?= $isTenantTickets ? 'Tickets sem projeto' : htmlspecialchars($p['tenant_name'] ?? 'Interno') ?></span>
                            </div>
                            <div class="gantt-row-chart <?= $needsCollapse ? 'gantt-collapsed' : '' ?>" style="height: <?= $chartHeight ?>px;" title="<?= $barTooltip ?>" data-row-id="<?= $isTenantTickets ? 't' . (int)$tenantId : 'p' . (int)$projectId ?>">
                                <?php if ($barWidth > 0): ?>
                                <a href="<?= $labelUrl ?>" class="gantt-bar <?= $isOverdue ? 'overdue' : '' ?> <?= $isTenantTickets ? 'tenant-tickets' : '' ?>" style="left: <?= $barLeft ?>%; width: <?= $barWidth ?>%;" title="<?= $barTooltip ?>"></a>
                                <?php endif; ?>
                                <?php foreach ($taskLanes as $tk): ?>
                                    <?php
                                    $tkStartRaw = $tk['start_date'] ?? $tk['due_date'] ?? null;
                                    $tkEndRaw = $tk['due_date'] ?? null;
                                    $tkStartYmd = $tkStartRaw ? date('Y-m-d', strtotime($tkStartRaw)) : null;
                                    $tkEndYmd = $tkEndRaw ? date('Y-m-d', strtotime($tkEndRaw)) : null;
                                    $isSingleDay = $tkStartYmd && $tkEndYmd && $tkStartYmd === $tkEndYmd;
                                    $tkLeftClamp = max(0, min(100, $tk['left']));
                                    $tkRightClamp = max(0, min(100, $tk['right']));
                                    $tkWidth = $isSingleDay ? 0 : max(2, $tkRightClamp - $tkLeftClamp);
                                    $tkCenter = ($tkLeftClamp + $tkRightClamp) / 2;
                                    $tkOverdue = ($tk['due_date'] ?? '') < $todayStr;
                                    $tkTop = $isSingleDay ? (6 + $tk['lane'] * 22 + 7) : (6 + $tk['lane'] * 22);
                                    $tkStart = $tkStartRaw;
                                    $tkEnd = $tkEndRaw;
                                    $tkTitle = $tk['title'] ?? 'Tarefa';
                                    $tkPrefix = ($tk['type'] ?? 'task') === 'ticket' ? '[Ticket] ' : '';
                                    $statusLabel = ['aberto' => 'Aberto', 'em_atendimento' => 'Em atendimento', 'aguardando_cliente' => 'Aguardando cliente'][$tk['status'] ?? ''] ?? '';
                                    $tkTooltip = $tkPrefix . $tkTitle
                                        . "\nPrazo: " . date('d/m/Y', strtotime($tkEnd))
                                        . ($statusLabel ? "\nStatus: " . $statusLabel : '')
                                        . ($tkOverdue ? "\n⚠ Atrasada" : '')
                                        . "\nClique para abrir";
                                    $tkUrl = ($tk['type'] ?? 'task') === 'ticket' ? pixelhub_url('/tickets/show?id=' . (int)$tk['id']) : ($projectId ? $boardBase . '?project_id=' . $projectId . '&task_id=' . (int)$tk['id'] : pixelhub_url('/tickets/show?id=' . (int)$tk['id']));
                                    $tkClass = $tkOverdue ? 'overdue' : '';
                                    if (($tk['type'] ?? 'task') === 'ticket') $tkClass .= ' ticket';
                                    if ($nextTaskId && (int)$tk['id'] === (int)$nextTaskId) $tkClass .= ' next-delivery';
                                    if ($isSingleDay) $tkClass .= ' gantt-task-dot';
                                    $tkStyle = $isSingleDay
                                        ? "left: {$tkCenter}%; top: {$tkTop}px;"
                                        : "left: {$tkLeftClamp}%; width: {$tkWidth}%; top: {$tkTop}px;";
                                    ?>
                                <a href="<?= htmlspecialchars($tkUrl) ?>" class="gantt-task-bar <?= $tkClass ?>" data-lane="<?= $tk['lane'] ?>" style="<?= $tkStyle ?>" title="<?= htmlspecialchars($tkTooltip) ?>"></a>
                                <?php endforeach; ?>
                                <?php if ($needsCollapse): ?>
                                <button type="button" class="gantt-expand-trigger" aria-label="Expandir">+<?= $numLanes - 6 ?> tarefas</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
(function(){
    document.querySelectorAll('.gantt-expand-trigger').forEach(function(btn){
        btn.addEventListener('click', function(){
            var chart = this.closest('.gantt-row-chart');
            if (chart) {
                chart.classList.remove('gantt-collapsed');
                chart.classList.add('gantt-expanded');
            }
        });
    });
})();
</script>
<?php
$content = ob_get_clean();
$title = 'Visão Macro — Projetos';
require __DIR__ . '/../layout/main.php';
?>
