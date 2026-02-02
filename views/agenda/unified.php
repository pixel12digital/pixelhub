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
        gap: 15px;
        margin-bottom: 24px;
    }
    .agenda-unified-nav {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .agenda-unified-nav a {
        padding: 8px 16px;
        background: #f0f0f0;
        color: #333;
        text-decoration: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        transition: background 0.2s;
    }
    .agenda-unified-nav a:hover {
        background: #e0e0e0;
    }
    .agenda-unified-nav a.btn-active {
        background: #023A8D;
        color: white;
    }
    .agenda-unified-period {
        font-size: 18px;
        font-weight: 600;
        color: #333;
    }
    .agenda-section {
        background: white;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    }
    .agenda-section h3 {
        margin: 0 0 16px 0;
        font-size: 16px;
        color: #555;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 10px;
    }
    .agenda-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 0;
        border-bottom: 1px solid #f5f5f5;
        text-decoration: none;
        color: inherit;
        transition: background 0.2s;
    }
    .agenda-item:last-child {
        border-bottom: none;
    }
    .agenda-item:hover {
        background: #f9f9f9;
        margin: 0 -12px;
        padding: 12px;
    }
    .agenda-item-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }
    .agenda-item-icon.task { background: #e3f2fd; color: #1976d2; }
    .agenda-item-icon.project { background: #e8f5e9; color: #388e3c; }
    .agenda-item-icon.manual { background: #fff3e0; color: #f57c00; }
    .agenda-item-content {
        flex: 1;
        min-width: 0;
    }
    .agenda-item-title {
        font-weight: 600;
        color: #333;
        margin-bottom: 2px;
    }
    .agenda-item-meta {
        font-size: 12px;
        color: #888;
    }
    .agenda-item-badge {
        font-size: 11px;
        padding: 2px 8px;
        border-radius: 10px;
        font-weight: 600;
    }
    .agenda-day-column {
        margin-bottom: 24px;
    }
    .agenda-day-header {
        font-weight: 600;
        color: #023A8D;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid #e0e0e0;
    }
    .agenda-blocos-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background: #f5f5f5;
        color: #555;
        text-decoration: none;
        border-radius: 6px;
        font-size: 14px;
        margin-top: 20px;
        transition: background 0.2s;
    }
    .agenda-blocos-link:hover {
        background: #e8e8e8;
        color: #333;
    }
    .agenda-empty {
        text-align: center;
        padding: 40px 20px;
        color: #888;
        font-size: 15px;
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
        <a href="<?= pixelhub_url('/agenda?view=hoje&data=' . $todayStr) ?>" class="<?= $isHoje ? 'btn-active' : '' ?>">Hoje</a>
        <a href="<?= pixelhub_url('/agenda?view=semana&data=' . $dataStr) ?>" class="<?= !$isHoje ? 'btn-active' : '' ?>">Esta semana</a>
        <span style="color: #999; padding: 0 8px;">|</span>
        <a href="<?= $prevUrl ?>">← Anterior</a>
        <span class="agenda-unified-period"><?= htmlspecialchars($periodLabel) ?></span>
        <a href="<?= $nextUrl ?>">Próximo →</a>
        <a href="<?= pixelhub_url('/agenda?view=' . $viewMode . '&data=' . $todayStr) ?>" style="margin-left: 8px;">Ir para hoje</a>
    </div>
    <div>
        <a href="<?= pixelhub_url('/agenda/manual-item/novo?data=' . $dataStr) ?>" class="agenda-blocos-link" style="background: #023A8D; color: white;">+ Compromisso</a>
        <a href="<?= pixelhub_url('/agenda/timeline') ?>" class="agenda-blocos-link" style="margin-left: 8px;">Visão macro (projetos)</a>
        <a href="<?= pixelhub_url('/agenda/blocos?data=' . $dataStr) ?>" class="agenda-blocos-link" style="margin-left: 8px;">Blocos de tempo</a>
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
            <?php foreach ($tasks as $t): ?>
                <a href="<?= pixelhub_url('/projects/board?project_id=' . (int)$t['project_id']) ?>#task-<?= (int)$t['id'] ?>" class="agenda-item">
                    <span class="agenda-item-icon task">✓</span>
                    <div class="agenda-item-content">
                        <div class="agenda-item-title"><?= htmlspecialchars($t['title']) ?></div>
                        <div class="agenda-item-meta"><?= htmlspecialchars($t['project_name'] ?? '') ?> · <?= htmlspecialchars($t['tenant_name'] ?? 'Interno') ?></div>
                    </div>
                    <?php
                    $statusLabels = ['backlog' => 'Backlog', 'em_andamento' => 'Em andamento', 'aguardando_cliente' => 'Aguardando cliente', 'concluida' => 'Concluída'];
                    $statusLabel = $statusLabels[$t['status'] ?? ''] ?? ($t['status'] ?? '');
                    ?>
                    <span class="agenda-item-badge" style="background: #e3f2fd; color: #1976d2;"><?= htmlspecialchars($statusLabel) ?></span>
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
                <?php foreach ($day['tasks'] as $t): ?>
                    <a href="<?= pixelhub_url('/projects/board?project_id=' . (int)$t['project_id']) ?>#task-<?= (int)$t['id'] ?>" class="agenda-item">
                        <span class="agenda-item-icon task">✓</span>
                        <div class="agenda-item-content">
                            <div class="agenda-item-title"><?= htmlspecialchars($t['title']) ?></div>
                            <div class="agenda-item-meta"><?= htmlspecialchars($t['project_name'] ?? '') ?></div>
                        </div>
                        <span class="agenda-item-badge" style="background: #e3f2fd; color: #1976d2;">Tarefa</span>
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

<div style="margin-top: 24px;">
    <a href="<?= pixelhub_url('/agenda/weekly-report') ?>" class="agenda-blocos-link">Relatório de Produtividade</a>
</div>

<?php
$content = ob_get_clean();
$title = 'Minha Agenda';
require __DIR__ . '/../layout/main.php';
?>
