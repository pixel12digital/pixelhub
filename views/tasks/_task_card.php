<?php
$checklistTotal = (int) ($task['checklist_total'] ?? 0);
$checklistDone = (int) ($task['checklist_done'] ?? 0);
$taskStatus = $task['status'] ?? '';
$isConcluida = ($taskStatus === 'concluida' || $taskStatus === 'completed' || $taskStatus === 'Concluída');
$isOverdue = false;
if (!$isConcluida && !empty($task['due_date'])) {
    $dueDateStr = $task['due_date'];
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dueDateStr, $m)) {
        $isOverdue = strtotime($dueDateStr) < strtotime('today');
    }
}
?>
<div class="kanban-task task-card<?= $isOverdue ? ' task-overdue' : '' ?>" 
     draggable="true"
     data-task-id="<?= (int)$task['id'] ?>"
     <?= !empty($task['agenda_block_id']) ? ' data-agenda-block-id="' . (int)$task['agenda_block_id'] . '"' : '' ?>
     <?= !empty($task['agenda_block_date']) ? ' data-agenda-block-date="' . htmlspecialchars($task['agenda_block_date']) . '"' : '' ?>
     <?= $isOverdue ? ' title="Prazo vencido"' : '' ?>>
    <?php if (!isset($selectedProjectId) || !$selectedProjectId && isset($task['project_name'])): ?>
        <span class="task-project-tag"><?= htmlspecialchars($task['project_name']) ?></span>
    <?php endif; ?>
    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
        <?php 
        $taskType = $task['task_type'] ?? 'internal';
        if ($taskType === 'client_ticket'): 
        ?>
            <span style="background: #fff3e0; color: #e65100; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600;">[TCK]</span>
        <?php else: ?>
            <span style="background: #e8f5e9; color: #2e7d32; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600;">[INT]</span>
        <?php endif; ?>
        <div class="task-title" style="flex: 1;"><?= htmlspecialchars($task['title']) ?></div>
    </div>
    <?php 
    // Badge "Na Agenda" apenas quando houver vínculo com bloco (link direto para Planejamento do Dia)
    $hasAgendaBlocks = isset($task['has_agenda_blocks']) && (int)$task['has_agenda_blocks'] > 0;
    if (!$isConcluida && $hasAgendaBlocks): 
        $agendaDate = !empty($task['agenda_block_date']) ? $task['agenda_block_date'] : ($task['due_date'] ?? date('Y-m-d'));
        $agendaBlockId = !empty($task['agenda_block_id']) ? (int)$task['agenda_block_id'] : 0;
        $agendaUrl = pixelhub_url('/agenda?view=lista&data=' . urlencode($agendaDate) . '&task_id=' . (int)$task['id'] . ($agendaBlockId ? '&block_id=' . $agendaBlockId : ''));
    ?>
        <div class="task-agenda-badge-container" style="margin-bottom: 5px;">
            <a href="<?= htmlspecialchars($agendaUrl) ?>" class="badge-agenda badge-na-agenda" style="background: #4CAF50; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; text-decoration: none; display: inline-block; cursor: pointer;" aria-label="Abrir na agenda" title="Abrir Planejamento do Dia" onclick="event.stopPropagation()">Na Agenda</a>
        </div>
    <?php endif; ?>
    <?php if ($task['description']): ?>
        <div style="font-size: 12px; color: #666; margin-top: 5px;">
            <?= htmlspecialchars(substr($task['description'], 0, 100)) ?><?= strlen($task['description']) > 100 ? '...' : '' ?>
        </div>
    <?php endif; ?>
    <div class="task-meta">
        <?php if ($checklistTotal > 0): ?>
            <span class="task-checklist-progress">
                Checklist: <?= $checklistDone ?>/<?= $checklistTotal ?>
            </span>
        <?php endif; ?>
        <?php if ($task['due_date']): ?>
            <?php
            // IMPORTANTE: Para campos DATE, formatamos diretamente a string Y-m-d para d/m/Y
            // sem usar strtotime para evitar problemas de timezone
            $dueDateStr = $task['due_date'];
            $dueDate = null;
            $isOverdue = false;
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dueDateStr, $matches)) {
                // Data no formato Y-m-d, converte diretamente
                $dueDateDisplay = $matches[3] . '/' . $matches[2] . '/' . $matches[1];
                $dueDate = strtotime($dueDateStr);
                $today = strtotime('today');
                $isOverdue = $dueDate < $today;
            } else {
                // Fallback para strtotime apenas se necessário
                $dueDate = strtotime($dueDateStr);
                $today = strtotime('today');
                $isOverdue = $dueDate < $today;
                $dueDateDisplay = date('d/m/Y', $dueDate);
            }
            ?>
            <div class="task-due-date" style="<?= $isOverdue ? 'color: #c33; font-weight: 600;' : '' ?>">
                Prazo: <?= $dueDateDisplay ?>
            </div>
        <?php endif; ?>
        <?php if ($task['assignee']): ?>
            <div style="font-size: 11px; color: #666; margin-top: 5px;">
                👤 <?= htmlspecialchars($task['assignee']) ?>
            </div>
        <?php endif; ?>
    </div>
    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee;">
        <select class="task-status-select" 
                data-task-id="<?= (int)$task['id'] ?>"
                style="width: 100%; padding: 5px; font-size: 12px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="backlog" <?= $task['status'] === 'backlog' ? 'selected' : '' ?>>Backlog</option>
            <option value="em_andamento" <?= $task['status'] === 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
            <option value="aguardando_cliente" <?= $task['status'] === 'aguardando_cliente' ? 'selected' : '' ?>>Aguardando Cliente</option>
            <option value="concluida" <?= $task['status'] === 'concluida' ? 'selected' : '' ?>>Concluída</option>
        </select>
    </div>
</div>

