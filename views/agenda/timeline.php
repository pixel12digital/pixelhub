<?php
ob_start();
$projects = $projects ?? [];
$todayStr = $todayStr ?? date('Y-m-d');
$todayTs = strtotime($todayStr);
?>

<style>
    .timeline-header {
        margin-bottom: 24px;
    }
    .timeline-header h2 {
        margin: 0 0 8px 0;
        font-size: 24px;
        color: #333;
    }
    .timeline-header p {
        margin: 0;
        color: #666;
        font-size: 14px;
    }
    .timeline-container {
        background: white;
        border-radius: 8px;
        padding: 24px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    }
    .timeline-chart {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .timeline-row {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .timeline-row:last-child {
        border-bottom: none;
    }
    .timeline-row:hover {
        background: #f9f9f9;
        margin: 0 -24px;
        padding: 12px 24px;
    }
    .timeline-project-name {
        flex: 0 0 220px;
        font-weight: 600;
        color: #333;
    }
    .timeline-project-client {
        flex: 0 0 120px;
        font-size: 13px;
        color: #888;
    }
    .timeline-bar-container {
        flex: 1;
        height: 28px;
        background: #f0f0f0;
        border-radius: 6px;
        position: relative;
        overflow: hidden;
    }
    .timeline-bar {
        height: 100%;
        border-radius: 6px;
        min-width: 4px;
        transition: width 0.2s;
    }
    .timeline-bar.overdue {
        background: #ffcdd2;
        border: 1px solid #ef5350;
    }
    .timeline-bar.upcoming {
        background: #c8e6c9;
        border: 1px solid #66bb6a;
    }
    .timeline-date {
        flex: 0 0 90px;
        font-size: 13px;
        font-weight: 600;
        text-align: right;
    }
    .timeline-date.overdue {
        color: #c62828;
    }
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
    .timeline-back:hover {
        text-decoration: underline;
    }
</style>

<a href="<?= pixelhub_url('/agenda') ?>" class="timeline-back">← Voltar para Agenda</a>

<div class="timeline-header">
    <h2>Visão Macro — Projetos e Prazos</h2>
    <p>Projetos ativos com prazo definido (próximas 4 semanas)</p>
</div>

<div class="timeline-container">
    <?php if (empty($projects)): ?>
        <div class="timeline-empty">
            Nenhum projeto ativo com prazo nos próximos dias.
        </div>
    <?php else: ?>
        <?php
        $minDate = $todayTs;
        $maxDate = $todayTs;
        foreach ($projects as $p) {
            if (!empty($p['due_date'])) {
                $ts = strtotime($p['due_date']);
                if ($ts < $minDate) $minDate = $ts;
                if ($ts > $maxDate) $maxDate = $ts;
            }
        }
        $range = max(1, ($maxDate - $minDate) / 86400);
        ?>
        <div class="timeline-chart">
            <?php foreach ($projects as $p): ?>
                <?php
                $dueDate = $p['due_date'] ?? null;
                $dueTs = $dueDate ? strtotime($dueDate) : null;
                $isOverdue = $dueTs && $dueTs < $todayTs;
                $daysFromMin = $dueTs ? ($dueTs - $minDate) / 86400 : 0;
                $widthPercent = $range > 0 ? min(100, max(2, ($daysFromMin / $range) * 100)) : 50;
                ?>
                <a href="<?= pixelhub_url('/projects/show?id=' . (int)$p['id']) ?>" class="timeline-row" style="text-decoration: none; color: inherit;">
                    <span class="timeline-project-name"><?= htmlspecialchars($p['name']) ?></span>
                    <span class="timeline-project-client"><?= htmlspecialchars($p['tenant_name'] ?? 'Interno') ?></span>
                    <div class="timeline-bar-container">
                        <div class="timeline-bar <?= $isOverdue ? 'overdue' : 'upcoming' ?>" style="width: <?= $widthPercent ?>%;"></div>
                    </div>
                    <span class="timeline-date <?= $isOverdue ? 'overdue' : '' ?>">
                        <?= $dueDate ? date('d/m/Y', strtotime($dueDate)) : '-' ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
$title = 'Visão Macro — Projetos';
require __DIR__ . '/../layout/main.php';
?>
