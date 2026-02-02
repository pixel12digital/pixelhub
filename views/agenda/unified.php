<?php
ob_start();
$viewMode = $viewMode ?? 'hoje';
$items = $items ?? [];
$isHoje = ($viewMode === 'hoje');
?>

<style>
    .agenda-unified-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 28px;
        padding-bottom: 20px;
        border-bottom: 1px solid #e8e8e8;
    }
    .agenda-unified-nav {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .agenda-unified-nav .btn-nav {
        padding: 8px 14px;
        background: #f5f5f5;
        color: #555;
        text-decoration: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        border: 1px solid #e8e8e8;
        transition: background 0.2s, border-color 0.2s;
    }
    .agenda-unified-nav .btn-nav:hover {
        background: #ebebeb;
        border-color: #ddd;
        color: #333;
    }
    .agenda-unified-nav .btn-nav.btn-active {
        background: #023A8D;
        color: white;
        border-color: #023A8D;
    }
    .agenda-unified-nav .btn-nav.btn-active:hover {
        background: #022a6d;
        border-color: #022a6d;
    }
    .agenda-unified-period {
        font-size: 15px;
        font-weight: 600;
        color: #444;
        padding: 0 4px;
    }
    .agenda-unified-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .agenda-unified-actions .btn-primary {
        padding: 8px 16px;
        background: #023A8D;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        border: none;
        transition: background 0.2s;
    }
    .agenda-unified-actions .btn-primary:hover {
        background: #022a6d;
    }
    .agenda-unified-actions .btn-secondary {
        padding: 8px 14px;
        background: #fff;
        color: #555;
        text-decoration: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        border: 1px solid #ddd;
        transition: background 0.2s, border-color 0.2s;
    }
    .agenda-unified-actions .btn-secondary:hover {
        background: #f8f8f8;
        border-color: #ccc;
        color: #333;
    }
    .agenda-section {
        background: white;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        border: 1px solid #eee;
    }
    .agenda-section h3 {
        margin: 0 0 16px 0;
        font-size: 15px;
        font-weight: 600;
        color: #444;
        border-bottom: 1px solid #eee;
        padding-bottom: 12px;
    }
    .agenda-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 20px;
        margin: 0 -20px;
        border-radius: 6px;
        text-decoration: none;
        color: inherit;
        transition: background 0.2s;
        border: 1px solid transparent;
    }
    .agenda-item + .agenda-item {
        margin-top: 4px;
    }
    .agenda-item:hover {
        background: #f8f9fa;
        border-color: #eee;
    }
    .agenda-item-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }
    .agenda-item-icon.task { background: #f0f4f8; color: #1976d2; }
    .agenda-item-icon.task.status-backlog { background: #f5f5f5; color: #757575; }
    .agenda-item-icon.task.status-andamento { background: #e3f2fd; color: #1976d2; }
    .agenda-item-icon.task.status-aguardando { background: #fff3e0; color: #f57c00; }
    .agenda-item-icon.task.status-concluida { background: #e8f5e9; color: #388e3c; }
    .agenda-item-icon.project { background: #e8f5e9; color: #388e3c; }
    .agenda-item-icon.manual { background: #fff3e0; color: #f57c00; }
    .agenda-item-content {
        flex: 1;
        min-width: 0;
    }
    .agenda-item-title {
        font-weight: 600;
        color: #333;
        margin-bottom: 4px;
        font-size: 15px;
    }
    .agenda-item-meta {
        font-size: 13px;
        color: #777;
    }
    .agenda-item-badge {
        font-size: 11px;
        padding: 4px 10px;
        border-radius: 12px;
        font-weight: 600;
        flex-shrink: 0;
    }
    .agenda-item-badge.badge-backlog { background: #f5f5f5; color: #616161; }
    .agenda-item-badge.badge-andamento { background: #e3f2fd; color: #1565c0; }
    .agenda-item-badge.badge-aguardando { background: #fff3e0; color: #e65100; }
    .agenda-item-badge.badge-concluida { background: #e8f5e9; color: #2e7d32; }
    .agenda-item-badge.badge-projeto { background: #e8f5e9; color: #2e7d32; }
    .agenda-day-column {
        margin-bottom: 28px;
    }
    .agenda-day-header {
        font-weight: 600;
        font-size: 15px;
        color: #023A8D;
        margin-bottom: 14px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    .agenda-footer-link {
        display: inline-flex;
        align-items: center;
        padding: 10px 16px;
        background: #fff;
        color: #555;
        text-decoration: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        border: 1px solid #ddd;
        transition: background 0.2s, border-color 0.2s;
    }
    .agenda-footer-link:hover {
        background: #f8f8f8;
        border-color: #ccc;
        color: #333;
    }
    .agenda-empty {
        text-align: center;
        padding: 48px 24px;
        color: #777;
        font-size: 15px;
        line-height: 1.6;
    }
</style>

<div class="content-header">
    <h2>Minha Agenda</h2>
    <p>O que fazer — tarefas, projetos e compromissos</p>
</div>

<?php if (isset($_GET['sucesso'])): ?>
    <div style="background: #e8f5e9; border-left: 4px solid #4CAF50; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
        <strong style="color: #2e7d32;"><?= htmlspecialchars($_GET['sucesso']) ?></strong>
    </div>
<?php endif; ?>

<div class="agenda-unified-header">
    <div class="agenda-unified-nav">
        <a href="<?= pixelhub_url('/agenda?view=hoje&data=' . $todayStr) ?>" class="btn-nav <?= $isHoje ? 'btn-active' : '' ?>">Hoje</a>
        <a href="<?= pixelhub_url('/agenda?view=semana&data=' . $dataStr) ?>" class="btn-nav <?= !$isHoje ? 'btn-active' : '' ?>">Esta semana</a>
        <span style="color: #ccc; font-weight: 300;">|</span>
        <a href="<?= $prevUrl ?>" class="btn-nav">← Anterior</a>
        <span class="agenda-unified-period"><?= htmlspecialchars($periodLabel) ?></span>
        <a href="<?= $nextUrl ?>" class="btn-nav">Próximo →</a>
        <a href="<?= pixelhub_url('/agenda?view=' . $viewMode . '&data=' . $todayStr) ?>" class="btn-nav">Ir para hoje</a>
    </div>
    <div class="agenda-unified-actions">
        <a href="<?= pixelhub_url('/agenda/manual-item/novo?data=' . $dataStr) ?>" class="btn-primary">+ Compromisso</a>
        <a href="<?= pixelhub_url('/agenda/timeline') ?>" class="btn-secondary">Visão macro</a>
        <a href="<?= pixelhub_url('/agenda/blocos?data=' . $dataStr) ?>" class="btn-secondary">Blocos de tempo</a>
    </div>
</div>

<?php if ($isHoje): ?>
    <?php
    $tasks = $items['tasks'] ?? [];
    $projects = $items['projects'] ?? [];
    $manualItems = $items['manual_items'] ?? [];
    $total = count($tasks) + count($projects) + count($manualItems);
    ?>
    <?php if ($total === 0): ?>
        <div class="agenda-section">
            <div class="agenda-empty">
                Nenhum item para <?= htmlspecialchars($periodLabel) ?>.<br>
                <small style="margin-top: 8px; display: block;">Tarefas e projetos com prazo nesta data aparecem aqui automaticamente.</small>
            </div>
        </div>
    <?php else: ?>
        <?php if (!empty($tasks)): ?>
        <div class="agenda-section">
            <h3>Tarefas (<?= count($tasks) ?>)</h3>
            <?php foreach ($tasks as $t):
                $status = $t['status'] ?? 'backlog';
                $statusLabels = ['backlog' => 'Backlog', 'em_andamento' => 'Em andamento', 'aguardando_cliente' => 'Aguardando cliente', 'concluida' => 'Concluída'];
                $statusLabel = $statusLabels[$status] ?? $status;
                $statusMap = ['backlog' => 'backlog', 'em_andamento' => 'andamento', 'aguardando_cliente' => 'aguardando', 'concluida' => 'concluida'];
                $statusClass = $statusMap[$status] ?? 'backlog';
            ?>
                <a href="<?= pixelhub_url('/projects/board?project_id=' . (int)$t['project_id']) ?>#task-<?= (int)$t['id'] ?>" class="agenda-item">
                    <span class="agenda-item-icon task status-<?= $statusClass ?>">✓</span>
                    <div class="agenda-item-content">
                        <div class="agenda-item-title"><?= htmlspecialchars($t['title']) ?></div>
                        <div class="agenda-item-meta"><?= htmlspecialchars($t['project_name'] ?? '') ?> · <?= htmlspecialchars($t['tenant_name'] ?? 'Interno') ?></div>
                    </div>
                    <span class="agenda-item-badge badge-<?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($projects)): ?>
        <div class="agenda-section">
            <h3>Projetos com prazo (<?= count($projects) ?>)</h3>
            <?php foreach ($projects as $p): ?>
                <a href="<?= pixelhub_url('/projects/show?id=' . (int)$p['id']) ?>" class="agenda-item">
                    <span class="agenda-item-icon project">📁</span>
                    <div class="agenda-item-content">
                        <div class="agenda-item-title"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="agenda-item-meta"><?= htmlspecialchars($p['tenant_name'] ?? 'Interno') ?> · Prazo: <?= $p['due_date'] ? date('d/m/Y', strtotime($p['due_date'])) : '-' ?></div>
                    </div>
                    <span class="agenda-item-badge badge-projeto">Projeto</span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($manualItems)): ?>
        <div class="agenda-section">
            <h3>Compromissos (<?= count($manualItems) ?>)</h3>
            <?php foreach ($manualItems as $m): ?>
                <div class="agenda-item" style="cursor: default;">
                    <span class="agenda-item-icon manual">📌</span>
                    <div class="agenda-item-content">
                        <div class="agenda-item-title"><?= htmlspecialchars($m['title']) ?></div>
                        <div class="agenda-item-meta">
                            <?php if (!empty($m['time_start'])): ?>
                                <?= substr($m['time_start'], 0, 5) ?>
                                <?php if (!empty($m['time_end'])): ?> – <?= substr($m['time_end'], 0, 5) ?><?php endif; ?>
                            <?php endif; ?>
                            <?php if (!empty($m['notes'])): ?> · <?= htmlspecialchars(substr($m['notes'], 0, 80)) ?><?= strlen($m['notes']) > 80 ? '...' : '' ?><?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

<?php else: ?>
    <?php
    $byDay = $items['by_day'] ?? [];
    $hasAny = false;
    foreach ($byDay as $day) {
        if (count($day['tasks']) + count($day['projects']) + count($day['manual_items']) > 0) {
            $hasAny = true;
            break;
        }
    }
    ?>
    <?php if (!$hasAny): ?>
        <div class="agenda-section">
            <div class="agenda-empty">
                Nenhum item para esta semana.<br>
                <small style="margin-top: 8px; display: block;">Tarefas e projetos com prazo no período aparecem aqui automaticamente.</small>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($byDay as $day): ?>
            <?php if (count($day['tasks']) + count($day['projects']) + count($day['manual_items']) === 0) continue; ?>
            <div class="agenda-section agenda-day-column">
                <h3 class="agenda-day-header"><?= htmlspecialchars($day['date_formatted']) ?></h3>
                <?php foreach ($day['tasks'] as $t):
                    $status = $t['status'] ?? 'backlog';
                    $statusMap = ['backlog' => 'backlog', 'em_andamento' => 'andamento', 'aguardando_cliente' => 'aguardando'];
                    $statusClass = $statusMap[$status] ?? 'backlog';
                ?>
                    <a href="<?= pixelhub_url('/projects/board?project_id=' . (int)$t['project_id']) ?>#task-<?= (int)$t['id'] ?>" class="agenda-item">
                        <span class="agenda-item-icon task status-<?= $statusClass ?>">✓</span>
                        <div class="agenda-item-content">
                            <div class="agenda-item-title"><?= htmlspecialchars($t['title']) ?></div>
                            <div class="agenda-item-meta"><?= htmlspecialchars($t['project_name'] ?? '') ?></div>
                        </div>
                        <span class="agenda-item-badge badge-<?= $statusClass ?>">Tarefa</span>
                    </a>
                <?php endforeach; ?>
                <?php foreach ($day['projects'] as $p): ?>
                    <a href="<?= pixelhub_url('/projects/show?id=' . (int)$p['id']) ?>" class="agenda-item">
                        <span class="agenda-item-icon project">📁</span>
                        <div class="agenda-item-content">
                            <div class="agenda-item-title"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="agenda-item-meta"><?= htmlspecialchars($p['tenant_name'] ?? 'Interno') ?></div>
                        </div>
                        <span class="agenda-item-badge" style="background: #e8f5e9; color: #388e3c;">Projeto</span>
                    </a>
                <?php endforeach; ?>
                <?php foreach ($day['manual_items'] as $m): ?>
                    <div class="agenda-item" style="cursor: default;">
                        <span class="agenda-item-icon manual">📌</span>
                        <div class="agenda-item-content">
                            <div class="agenda-item-title"><?= htmlspecialchars($m['title']) ?></div>
                            <div class="agenda-item-meta"><?php if (!empty($m['time_start'])) echo substr($m['time_start'], 0, 5); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<div style="margin-top: 28px; padding-top: 20px; border-top: 1px solid #eee;">
    <a href="<?= pixelhub_url('/agenda/weekly-report') ?>" class="agenda-footer-link">Relatório de Produtividade</a>
</div>

<?php
$content = ob_get_clean();
$title = 'Minha Agenda';
require __DIR__ . '/../layout/main.php';
?>
