<?php
ob_start();
$segments = $segments ?? [];
$blocoId = (int)$bloco['id'];
$blocoData = $bloco['data'];
?>

<style>
    .bloco-sticky-header {
        position: sticky;
        top: 0;
        z-index: 40; /* abaixo da sidebar (50) para evitar submenu ficar por trás */
        background: white;
        border-bottom: 1px solid #e5e7eb;
        padding: 12px 0 16px 0;
        margin: 0 -30px 20px -30px;
        padding-left: 30px;
        padding-right: 30px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .bloco-sticky-header-inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }
    .bloco-sticky-header-info {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }
    .bloco-sticky-header-info .data { font-size: 18px; font-weight: 600; color: #111827; }
    .bloco-sticky-header-info .intervalo { font-size: 14px; color: #6b7280; }
    .bloco-sticky-header-info .categoria {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        color: white;
        background: <?= htmlspecialchars($bloco['tipo_cor'] ?? '#6b7280') ?>;
    }
    .bloco-sticky-header-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .btn-icon {
        background: none;
        border: none;
        cursor: pointer;
        padding: 6px;
        color: #6b7280;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
    }
    .btn-icon:hover { color: #374151; background: #f3f4f6; }
    .btn-icon[title]:hover::after { content: attr(title); }
    .btn-primary { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; background: #023A8D; color: white; text-decoration: none; display: inline-flex; align-items: center; }
    .btn-primary:hover { background: #022a6d; }
    .btn-secondary { padding: 8px 16px; border: 1px solid #d1d5db; border-radius: 4px; cursor: pointer; font-size: 14px; background: white; color: #374151; text-decoration: none; display: inline-flex; align-items: center; }
    .btn-secondary:hover { background: #f9fafb; }
    .planilha-wrap { overflow-x: auto; }
    .planilha-registros { width: 100%; border-collapse: collapse; }
    .planilha-registros th { background: #f5f5f5; font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 12px; text-align: left; font-weight: 600; }
    .planilha-registros td { padding: 10px 12px; font-size: 13px; border-bottom: 1px solid #eee; }
    .planilha-registros tr:hover { background: #fafafa; }
    .cell-project, .cell-task { cursor: pointer; color: #023A8D; }
    .cell-project:hover, .cell-task:hover { text-decoration: underline; }
    .cell-project.expanded, .cell-task.expanded { font-weight: 600; }
    .expand-icon { display: inline-block; width: 16px; margin-right: 4px; transition: transform 0.2s; }
    .expand-icon.expanded { transform: rotate(90deg); }
    .segment-expand { display: none; background: #f8fafc; border-left: 3px solid #e5e7eb; }
    .segment-expand.show { display: table-row; }
    .segment-expand td { padding: 12px 16px; vertical-align: top; }
    .checklist-panel { max-height: 200px; overflow-y: auto; }
    .checklist-item { display: flex; align-items: center; gap: 8px; padding: 6px 0; font-size: 13px; }
    .checklist-item input[type="checkbox"] { cursor: pointer; }
    .checklist-item.done { color: #9ca3af; text-decoration: line-through; }
</style>

<!-- Header fixo sticky -->
<div class="bloco-sticky-header">
    <div class="bloco-sticky-header-inner">
        <div class="bloco-sticky-header-info">
            <span class="data"><?= date('d/m/Y', strtotime($bloco['data'])) ?></span>
            <span class="intervalo"><?= date('H:i', strtotime($bloco['hora_inicio'])) ?> – <?= date('H:i', strtotime($bloco['hora_fim'])) ?></span>
            <span class="categoria"><?= htmlspecialchars($bloco['tipo_nome']) ?></span>
        </div>
        <div class="bloco-sticky-header-actions">
            <a href="<?= pixelhub_url('/agenda/blocos?data=' . $bloco['data']) ?>" class="btn-secondary">← Voltar</a>
            <?php if (in_array($bloco['status'] ?? '', ['planned', 'ongoing'])): ?>
            <button type="button" class="btn-icon" title="Cancelar bloco" onclick="cancelBloco()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
            </button>
            <form method="post" action="<?= pixelhub_url('/agenda/bloco/delete') ?>" style="display: inline;" onsubmit="return confirm('Excluir este bloco?');">
                <input type="hidden" name="id" value="<?= $blocoId ?>">
                <input type="hidden" name="date" value="<?= htmlspecialchars($bloco['data']) ?>">
                <button type="submit" class="btn-icon" title="Excluir bloco">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (isset($_GET['erro'])): ?>
<div style="background: #ffebee; border-left: 4px solid #f44336; padding: 12px 16px; margin-bottom: 16px; border-radius: 4px;">
    <strong>Erro:</strong> <?= htmlspecialchars($_GET['erro']) ?>
</div>
<?php endif; ?>
<?php if (isset($_GET['sucesso'])): ?>
<div style="background: #e8f5e9; border-left: 4px solid #4CAF50; padding: 12px 16px; margin-bottom: 16px; border-radius: 4px;">
    <?= htmlspecialchars($_GET['sucesso']) ?>
</div>
<?php endif; ?>

<?php if (!empty($agendaTaskContext)): ?>
<div id="agenda-task-context-banner" style="background: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 12px 16px; margin-bottom: 16px; border-radius: 4px; font-size: 13px;">
    <strong>Vincular tarefa:</strong> <?= htmlspecialchars($agendaTaskContext['titulo']) ?> — selecione projeto e tarefa no formulário abaixo e clique Adicionar.
</div>
<?php endif; ?>

<!-- Formulário de adição -->
<div style="background: #f8f9fa; border-radius: 8px; padding: 16px; margin-bottom: 20px; border: 1px solid #e9ecef;">
    <form method="post" action="<?= pixelhub_url('/agenda/bloco/segment/create-manual') ?>" id="segment-add-form" style="display: grid; grid-template-columns: 1fr 1fr 100px 70px 70px auto; gap: 8px; align-items: center;">
        <input type="hidden" name="block_id" value="<?= $blocoId ?>">
        <select name="project_id" id="segment-project" style="padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
            <option value="">Atividade avulsa</option>
            <?php foreach ($projetos as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="task_id" id="segment-task" style="padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
            <option value="">— Nenhuma</option>
            <?php if (!empty($agendaTaskContext)): ?>
            <option value="<?= (int)$agendaTaskContext['id'] ?>" selected><?= htmlspecialchars(mb_substr($agendaTaskContext['titulo'], 0, 50)) ?><?= mb_strlen($agendaTaskContext['titulo']) > 50 ? '…' : '' ?></option>
            <?php endif; ?>
        </select>
        <select name="tipo_id" style="padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
            <option value=""><?= htmlspecialchars($bloco['tipo_nome'] ?? 'Padrão') ?></option>
            <?php foreach ($blockTypes ?? [] as $t): ?>
            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="time" name="hora_inicio" required placeholder="Início" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
        <input type="time" name="hora_fim" required placeholder="Fim" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
        <button type="submit" class="btn-primary">Adicionar</button>
    </form>
</div>

<!-- Planilha de registros -->
<div class="planilha-wrap" style="background: white; border-radius: 8px; border: 1px solid #e9ecef; overflow: hidden;">
    <table class="planilha-registros">
        <thead>
            <tr>
                <th>Projeto</th>
                <th>Tarefa</th>
                <th>Tipo</th>
                <th>Início</th>
                <th>Fim</th>
                <th style="width: 60px; text-align: right;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($segments as $seg):
                $inicio = date('H:i', strtotime($seg['started_at']));
                $fim = !empty($seg['ended_at']) ? date('H:i', strtotime($seg['ended_at'])) : '—';
                $projId = (int)($seg['project_id'] ?? 0);
                $projNome = !empty($seg['project_name']) ? $seg['project_name'] : 'Atividade avulsa';
                $taskId = (int)($seg['task_id'] ?? 0);
                $taskNome = !empty($seg['task_title']) ? $seg['task_title'] : ($taskId ? 'Tarefa #' . $taskId : '—');
                $tipoNome = !empty($seg['tipo_nome']) ? $seg['tipo_nome'] : ($bloco['tipo_nome'] ?? '—');
                $segId = (int)$seg['id'];
            ?>
            <tr class="segment-row" data-segment-id="<?= $segId ?>" data-project-id="<?= $projId ?>" data-task-id="<?= $taskId ?>">
                <td class="cell-project" data-expand="project">
                    <?php if ($projId): ?><span class="expand-icon">▶</span><?php endif; ?>
                    <?= htmlspecialchars($projNome) ?>
                </td>
                <td class="cell-task" data-expand="task">
                    <?php if ($taskId): ?><span class="expand-icon">▶</span><?php endif; ?>
                    <?= htmlspecialchars($taskNome) ?>
                </td>
                <td><?= htmlspecialchars($tipoNome) ?></td>
                <td><?= htmlspecialchars($inicio) ?></td>
                <td><?= htmlspecialchars($fim) ?></td>
                <td style="text-align: right;">
                    <form method="post" action="<?= pixelhub_url('/agenda/bloco/segment/delete') ?>" style="display: inline;" onsubmit="return confirm('Excluir este registro?');">
                        <input type="hidden" name="segment_id" value="<?= $segId ?>">
                        <input type="hidden" name="block_id" value="<?= $blocoId ?>">
                        <button type="submit" class="btn-icon" title="Excluir registro">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                        </button>
                    </form>
                </td>
            </tr>
            <tr class="segment-expand" id="expand-project-<?= $segId ?>" data-for="project" data-segment-id="<?= $segId ?>" data-project-id="<?= $projId ?>">
                <td colspan="6"><div class="expand-content"></div></td>
            </tr>
            <tr class="segment-expand" id="expand-task-<?= $segId ?>" data-for="task" data-segment-id="<?= $segId ?>" data-task-id="<?= $taskId ?>">
                <td colspan="6"><div class="expand-content checklist-panel"></div></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($segments)): ?>
            <tr>
                <td colspan="6" style="padding: 32px; text-align: center; color: #999; font-size: 13px;">Nenhum registro. Use o formulário acima para adicionar.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const projectSelect = document.getElementById('segment-project');
    const taskSelect = document.getElementById('segment-task');
    if (!projectSelect || !taskSelect) return;

    function loadTasksForProject(projectId, selectTaskId) {
        const ctxTaskId = <?= !empty($agendaTaskContext) ? (int)$agendaTaskContext['id'] : 0 ?>;
        const ctxTitle = <?= !empty($agendaTaskContext) ? json_encode(mb_substr($agendaTaskContext['titulo'], 0, 50) . (mb_strlen($agendaTaskContext['titulo']) > 50 ? '…' : '')) : '""' ?>;
        taskSelect.innerHTML = '<option value="">— Nenhuma</option>';
        if (ctxTaskId && projectId == '<?= !empty($agendaTaskContext) && !empty($agendaTaskContext['project_id']) ? (int)$agendaTaskContext['project_id'] : 0 ?>') {
            const opt = document.createElement('option');
            opt.value = ctxTaskId;
            opt.textContent = ctxTitle || 'Tarefa';
            taskSelect.appendChild(opt);
        }
        if (!projectId) return;
        fetch('<?= pixelhub_url('/agenda/tasks-by-project') ?>?project_id=' + encodeURIComponent(projectId))
            .then(r => r.json())
            .then(data => {
                if (data.success && data.tasks && data.tasks.length > 0) {
                    const seen = new Set([ctxTaskId]);
                    data.tasks.forEach(t => {
                        if (seen.has(t.id)) return;
                        seen.add(t.id);
                        const opt = document.createElement('option');
                        opt.value = t.id;
                        opt.textContent = (t.title || '').length > 50 ? (t.title || '').substring(0, 50) + '…' : (t.title || 'Tarefa');
                        taskSelect.appendChild(opt);
                    });
                    if (selectTaskId) taskSelect.value = selectTaskId;
                }
            })
            .catch(err => console.error('Erro ao carregar tarefas:', err));
    }

    projectSelect.addEventListener('change', function() {
        loadTasksForProject(this.value, null);
    });

    <?php if (!empty($agendaTaskContext) && !empty($agendaTaskContext['project_id'])): ?>
    projectSelect.value = '<?= (int)$agendaTaskContext['project_id'] ?>';
    taskSelect.value = '<?= (int)$agendaTaskContext['id'] ?>';
    <?php endif; ?>

    // Drill-down: clique em projeto ou tarefa
    document.querySelectorAll('.cell-project[data-expand="project"], .cell-task[data-expand="task"]').forEach(cell => {
        cell.addEventListener('click', function(e) {
            if (e.target.tagName === 'BUTTON' || e.target.closest('form')) return;
            const row = this.closest('tr.segment-row');
            if (!row) return;
            const segId = row.dataset.segmentId;
            const projectId = row.dataset.projectId;
            const taskId = row.dataset.taskId;
            const isProject = this.classList.contains('cell-project');

            if (isProject && projectId && projectId !== '0') {
                toggleExpand('project', segId, projectId, this);
            } else if (!isProject && taskId && taskId !== '0') {
                toggleExpand('task', segId, taskId, this);
            }
        });
    });
});

function toggleExpand(type, segId, id, cell) {
    const expandRow = document.getElementById('expand-' + type + '-' + segId);
    if (!expandRow) return;
    const isOpen = expandRow.classList.contains('show');
    document.querySelectorAll('.segment-expand.show').forEach(r => {
        r.classList.remove('show');
        const c = r.querySelector('.expand-content');
        if (c && c.dataset) delete c.dataset.loaded;
    });
    document.querySelectorAll('.cell-project.expanded, .cell-task.expanded').forEach(c => c.classList.remove('expanded'));
    document.querySelectorAll('.expand-icon.expanded').forEach(i => i.classList.remove('expanded'));

    if (!isOpen) {
        expandRow.classList.add('show');
        cell.classList.add('expanded');
        const icon = cell.querySelector('.expand-icon');
        if (icon) icon.classList.add('expanded');
        const content = expandRow.querySelector('.expand-content');
        if (content && content.dataset.loaded !== '1') {
            if (type === 'task') loadTaskChecklist(id, content);
            else loadProjectSegments(id, segId, content);
            content.dataset.loaded = '1';
        }
    }
}

function loadTaskChecklist(taskId, container) {
    container.innerHTML = '<span style="color:#6b7280;font-size:13px;">Carregando…</span>';
    fetch('<?= pixelhub_url('/tasks/modal') ?>?id=' + encodeURIComponent(taskId))
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                container.innerHTML = '<span style="color:#dc2626;">' + (data.error || 'Erro') + '</span>';
                return;
            }
            let html = '<div style="margin-bottom:8px;"><strong>' + escapeHtml(data.title || 'Tarefa') + '</strong></div>';
            if (data.checklist && data.checklist.length > 0) {
                html += '<div class="checklist-panel">';
                data.checklist.forEach(item => {
                    const done = item.is_done ? ' done' : '';
                    html += '<div class="checklist-item' + done + '"><input type="checkbox" class="chk-toggle" data-id="' + item.id + '" ' + (item.is_done ? 'checked' : '') + '> ' + escapeHtml(item.label || '') + '</div>';
                });
                html += '</div>';
            } else {
                html += '<p style="color:#9ca3af;font-size:13px;">Nenhum item no checklist.</p>';
            }
            container.innerHTML = html;
            container.querySelectorAll('.chk-toggle').forEach(cb => {
                cb.addEventListener('change', function() {
                    const formData = new FormData();
                    formData.append('id', this.dataset.id);
                    formData.append('is_done', this.checked ? 1 : 0);
                    fetch('<?= pixelhub_url('/tasks/checklist/toggle') ?>', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(res => { if (!res.success) this.checked = !this.checked; })
                        .catch(() => this.checked = !this.checked);
                });
            });
        })
        .catch(() => { container.innerHTML = '<span style="color:#dc2626;">Erro ao carregar.</span>'; });
}

function loadProjectSegments(projectId, currentSegId, container) {
    const rows = document.querySelectorAll('tr.segment-row[data-project-id="' + projectId + '"]');
    let html = '<div style="font-size:12px;color:#6b7280;">Registros deste projeto neste bloco:</div><ul style="margin:8px 0 0 16px;padding:0;">';
    rows.forEach(r => {
        const taskCell = r.querySelector('.cell-task');
        let taskTxt = '—';
        if (taskCell) {
            taskTxt = taskCell.cloneNode(true);
            taskTxt.querySelectorAll('.expand-icon').forEach(i => i.remove());
            taskTxt = taskTxt.textContent.trim() || '—';
        }
        const inicio = r.cells[3] ? r.cells[3].textContent.trim() : '';
        const fim = r.cells[4] ? r.cells[4].textContent.trim() : '';
        html += '<li style="margin:4px 0;">' + escapeHtml(taskTxt) + ' · ' + escapeHtml(inicio) + '–' + escapeHtml(fim) + '</li>';
    });
    html += '</ul>';
    container.innerHTML = html;
}

function escapeHtml(t) {
    if (!t) return '';
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}

function cancelBloco() {
    const motivo = prompt('Motivo do cancelamento:');
    if (!motivo) return;
    const formData = new URLSearchParams();
    formData.set('id', '<?= $blocoId ?>');
    formData.set('motivo', motivo);
    fetch('<?= pixelhub_url('/agenda/cancel') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(r => r.json())
    .then(data => { if (data.success) location.reload(); else alert(data.error || 'Erro'); })
    .catch(() => alert('Erro ao cancelar'));
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('<?= pixelhub_url('/agenda/ongoing-block') ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.has_ongoing && data.block && data.block.id !== <?= $blocoId ?>) {
                const w = data.block;
                const el = document.createElement('div');
                el.id = 'ongoing-block-warning';
                el.style.cssText = 'background:#fff3cd;border:2px solid #ffc107;border-radius:6px;padding:15px;margin-bottom:20px;';
                el.innerHTML = '<strong style="color:#856404;">⚠️ Bloco em andamento:</strong> ' + (w.data_formatada||'') + ' ' + (w.hora_inicio||'') + '–' + (w.hora_fim||'') + ' <a href="<?= pixelhub_url('/agenda/bloco?id=') ?>' + w.id + '" class="btn-primary" style="margin-left:12px;">Ir</a>';
                const h = document.querySelector('.bloco-sticky-header');
                if (h) h.insertAdjacentElement('afterend', el);
            }
        })
        .catch(() => {});
});
</script>

<?php
$content = ob_get_clean();
$title = 'Bloco de Agenda';
require __DIR__ . '/../layout/main.php';
?>
