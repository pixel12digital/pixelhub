<?php
ob_start();
$viewMode = $viewMode ?? 'lista';
$blocos = $blocos ?? [];
$dataStr = $dataStr ?? date('Y-m-d');
$taskParam = !empty($agendaTaskContext) ? '&task_id=' . (int)$agendaTaskContext['id'] : '';
$baseUrl = pixelhub_url('/agenda');
?>

<style>
.agenda-unified-sticky {
    position: sticky;
    top: 0;
    z-index: 90;
    background: white;
    border-bottom: 1px solid #e5e7eb;
    padding: 16px 0;
    margin: 0 -30px 20px -30px;
    padding-left: 30px;
    padding-right: 30px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.agenda-unified-sticky-inner { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
.view-switcher { display: flex; gap: 0; border: 1px solid #d1d5db; border-radius: 6px; overflow: hidden; }
.view-switcher a {
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    color: #6b7280;
    background: white;
    border: none;
}
.view-switcher a:hover { background: #f9fafb; color: #374151; }
.view-switcher a.active { background: #023A8D; color: white; }
.agenda-nav { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.agenda-nav .btn-nav { padding: 6px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; color: #374151; text-decoration: none; background: white; }
.agenda-nav .btn-nav:hover { background: #f9fafb; }
.agenda-filters { display: flex; gap: 8px; flex-wrap: wrap; }
.agenda-filters select { padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; }
.block-row { display: grid; grid-template-columns: 1fr auto 100px 100px auto; gap: 12px; align-items: center; padding: 10px 14px; background: white; border-radius: 6px; border-left: 4px solid #ddd; margin-bottom: 4px; cursor: pointer; transition: background 0.15s; }
.block-row:hover { background: #f8fafc; }
.block-row.current { background: #eff6ff; border-left-color: #1d4ed8; }
.block-row .block-main { display: flex; flex-direction: column; gap: 2px; }
.block-row .block-project { font-weight: 600; color: #111827; font-size: 14px; }
.block-row .block-task { font-size: 12px; color: #6b7280; }
.block-expand { display: none; background: #f8fafc; border-radius: 0 0 6px 6px; margin: -4px 0 12px 0; padding: 16px; border: 1px solid #e5e7eb; border-top: none; }
.block-expand.show { display: block; }
.planilha-registros { width: 100%; border-collapse: collapse; font-size: 13px; }
.planilha-registros th { background: #f5f5f5; padding: 8px 12px; text-align: left; font-weight: 600; font-size: 11px; color: #666; text-transform: uppercase; }
.planilha-registros td { padding: 8px 12px; border-bottom: 1px solid #eee; }
.btn-icon { background: none; border: none; cursor: pointer; padding: 4px; color: #6b7280; display: inline-flex; }
.btn-icon:hover { color: #374151; }
.agenda-quadro-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 12px; margin-top: 20px; }
@media (max-width: 1200px) { .agenda-quadro-grid { grid-template-columns: repeat(4, 1fr); } }
@media (max-width: 768px) { .agenda-quadro-grid { grid-template-columns: repeat(2, 1fr); } }
.quadro-dia { border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; background: white; min-height: 180px; }
.quadro-dia.hoje { background: #f0f9ff; border-color: #0ea5e9; }
.quadro-dia-header { margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #eee; }
.quadro-dia-header a { font-weight: 600; font-size: 13px; text-decoration: none; color: #111827; }
.quadro-dia-header a:hover { color: #023A8D; }
.quadro-card { border-left: 4px solid #94a3b8; padding: 8px; border-radius: 4px; background: #f8fafc; font-size: 12px; margin-bottom: 6px; cursor: pointer; transition: all 0.15s; }
.quadro-card:hover { transform: translateY(-1px); box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
.quadro-card.atual { background: #e0f2fe; border-left-color: #0ea5e9; }
</style>

<div class="agenda-unified-sticky">
    <div class="agenda-unified-sticky-inner">
        <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
            <div class="view-switcher">
                <a href="<?= $baseUrl ?>?view=lista&data=<?= $dataStr ?><?= $taskParam ?>" class="<?= $viewMode === 'lista' ? 'active' : '' ?>">Lista</a>
                <a href="<?= $baseUrl ?>?view=quadro&data=<?= $dataStr ?><?= $taskParam ?>" class="<?= $viewMode === 'quadro' ? 'active' : '' ?>">Quadro</a>
            </div>
            <div class="agenda-nav">
                <a href="<?= $baseUrl ?>?view=<?= $viewMode ?>&data=<?= $viewMode === 'quadro' ? date('Y-m-d', strtotime($domingo->format('Y-m-d') . ' -7 days')) : date('Y-m-d', strtotime($dataStr . ' -1 day')) ?><?= $taskParam ?>" class="btn-nav">← Anterior</a>
                <strong style="font-size: 15px; color: #374151;"><?= $viewMode === 'lista' ? date('d/m/Y', strtotime($dataStr)) : (date('d/m', strtotime($domingo->format('Y-m-d'))) . ' a ' . date('d/m/Y', strtotime($sabado->format('Y-m-d')))) ?></strong>
                <a href="<?= $baseUrl ?>?view=<?= $viewMode ?>&data=<?= $viewMode === 'quadro' ? date('Y-m-d', strtotime($sabado->format('Y-m-d') . ' +1 day')) : date('Y-m-d', strtotime($dataStr . ' +1 day')) ?><?= $taskParam ?>" class="btn-nav">Próximo →</a>
                <a href="<?= $baseUrl ?>?view=<?= $viewMode ?>&data=<?= $todayStr ?><?= $taskParam ?>" class="btn-nav">Hoje</a>
                <form method="get" action="<?= $baseUrl ?>" style="display: inline-flex; align-items: center; gap: 6px; margin-left: 8px;">
                    <input type="hidden" name="view" value="<?= $viewMode ?>">
                    <input type="date" name="data" value="<?= htmlspecialchars($dataStr) ?>" style="padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px;">
                    <?php if (!empty($agendaTaskContext)): ?><input type="hidden" name="task_id" value="<?= (int)$agendaTaskContext['id'] ?>"><?php endif; ?>
                    <button type="submit" class="btn-nav">Ir</button>
                </form>
            </div>
            <?php if ($viewMode === 'lista'): ?>
            <div class="agenda-filters">
                <select onchange="applyFilters(this.value, 'tipo')">
                    <option value="">Todos os tipos</option>
                    <?php foreach ($tipos as $t): ?>
                    <option value="<?= htmlspecialchars($t['codigo'] ?? '') ?>" <?= ($tipoFiltro ?? '') === ($t['codigo'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select onchange="applyFilters(this.value, 'status')">
                    <option value="">Todos os status</option>
                    <option value="planned" <?= ($statusFiltro ?? '') === 'planned' ? 'selected' : '' ?>>Planejado</option>
                    <option value="ongoing" <?= ($statusFiltro ?? '') === 'ongoing' ? 'selected' : '' ?>>Em Andamento</option>
                    <option value="completed" <?= ($statusFiltro ?? '') === 'completed' ? 'selected' : '' ?>>Concluído</option>
                    <option value="canceled" <?= ($statusFiltro ?? '') === 'canceled' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div style="display: flex; gap: 8px;">
            <button onclick="generateBlocks()" class="btn-nav">Gerar Blocos</button>
            <a href="<?= pixelhub_url('/agenda/bloco/novo?data=' . $dataStr . $taskParam) ?>" class="btn-nav" style="background: #023A8D; color: white; border-color: #023A8D;">+ Bloco</a>
        </div>
    </div>
</div>

<?php if (isset($_GET['sucesso'])): ?>
<div style="background: #e8f5e9; border-left: 4px solid #4CAF50; padding: 12px 16px; margin-bottom: 16px; border-radius: 4px;"><?= htmlspecialchars($_GET['sucesso']) ?></div>
<?php endif; ?>
<?php if (isset($_GET['erro'])): ?>
<div style="background: #ffebee; border-left: 4px solid #f44336; padding: 12px 16px; margin-bottom: 16px; border-radius: 4px;"><strong>Erro:</strong> <?= htmlspecialchars($_GET['erro']) ?></div>
<?php endif; ?>
<?php if (!empty($agendaTaskContext)): ?>
<div style="background: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 12px 16px; margin-bottom: 16px; border-radius: 4px; font-size: 13px;">
    <strong>Vincular tarefa:</strong> <?= htmlspecialchars($agendaTaskContext['titulo']) ?> — escolha um bloco ou crie um extra.
</div>
<?php endif; ?>

<?php if ($viewMode === 'lista'): ?>
<!-- Quick-add -->
<form method="post" action="<?= pixelhub_url('/agenda/bloco/quick-add') ?>" id="quick-add-form" style="margin-bottom: 16px; padding: 12px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">
    <input type="hidden" name="data" value="<?= htmlspecialchars($dataStr) ?>">
    <input type="hidden" name="task_id" id="quick-add-task-id" value="">
    <div style="display: grid; grid-template-columns: 1fr 120px 80px 80px auto; gap: 8px; align-items: center;">
        <select name="project_id" id="quick-add-project" style="padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
            <option value="">Atividade avulsa</option>
            <?php foreach ($projetos as $p): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="tipo_id" required style="padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
            <option value="">Tipo</option>
            <?php foreach ($tipos as $t): ?><option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nome']) ?></option><?php endforeach; ?>
        </select>
        <input type="time" name="hora_inicio" required placeholder="Início" style="padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
        <input type="time" name="hora_fim" required placeholder="Fim" style="padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
        <button type="submit" class="btn-nav" style="background: #023A8D; color: white; border-color: #023A8D;">Adicionar</button>
    </div>
</form>
<div id="quick-add-tasks-area" style="display:none; margin-bottom:12px; padding:12px; background:#fff; border:1px solid #e5e7eb; border-radius:8px;">
    <div id="quick-add-tasks-list"></div>
</div>

<!-- Lista de blocos (expandíveis) -->
<div class="blocks-list">
    <?php if (empty($blocos)): ?>
    <div style="padding: 32px; text-align: center; color: #6b7280; background: white; border-radius: 8px; border: 1px solid #e5e7eb;">
        Nenhum bloco para <?= date('d/m/Y', strtotime($dataStr)) ?>. Use o formulário acima ou <strong>Gerar Blocos</strong>.
    </div>
    <?php else:
        $now = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        $horaAtual = $now->format('H:i:s');
        $dataAtual = $now->format('Y-m-d');
        $blocoAtualId = ($dataStr === $dataAtual) ? null : null;
        if ($dataStr === $dataAtual) {
            foreach ($blocos as $b) {
                if (($b['hora_inicio'] ?? '') <= $horaAtual && ($b['hora_fim'] ?? '') >= $horaAtual) {
                    $blocoAtualId = $b['id'];
                    break;
                }
            }
        }
        foreach ($blocos as $bloco):
            $isCurrent = ($bloco['id'] === $blocoAtualId);
            $corBorda = htmlspecialchars($bloco['tipo_cor'] ?? '#ddd');
            $projetoNome = !empty($bloco['projeto_foco_nome']) ? $bloco['projeto_foco_nome'] : (!empty($bloco['block_tenant_name']) ? $bloco['block_tenant_name'] : 'Atividade avulsa');
            $isExpanded = ($expandBlockId && $expandBlockId === (int)$bloco['id']);
    ?>
    <div class="block-row <?= $isCurrent ? 'current' : '' ?>" style="border-left-color: <?= $corBorda ?>"
         onclick="toggleBlockExpand(<?= (int)$bloco['id'] ?>)">
        <div class="block-main">
            <span class="block-project"><?= htmlspecialchars($projetoNome) ?></span>
            <?php if (!empty($bloco['focus_task_title'])): ?><span class="block-task">↳ <?= htmlspecialchars($bloco['focus_task_title']) ?></span><?php endif; ?>
        </div>
        <span style="background: <?= $corBorda ?>; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; color: white;"><?= htmlspecialchars($bloco['tipo_nome']) ?></span>
        <span><?= date('H:i', strtotime($bloco['hora_inicio'])) ?> – <?= date('H:i', strtotime($bloco['hora_fim'])) ?><?= $isCurrent ? ' <span style="color:#1976d2;font-size:11px;">● Agora</span>' : '' ?></span>
        <span style="font-size:12px;color:#9ca3af;">▶</span>
    </div>
    <div class="block-expand <?= $isExpanded ? 'show' : '' ?>" id="block-expand-<?= (int)$bloco['id'] ?>" data-block-id="<?= (int)$bloco['id'] ?>" onclick="event.stopPropagation()">
        <div class="block-expand-content"></div>
    </div>
    <?php endforeach; endif; ?>
</div>

<?php else: ?>
<!-- Quadro semanal -->
<div class="agenda-quadro-grid">
    <?php foreach ($diasSemana as $dia): ?>
    <div class="quadro-dia <?= $dia['is_hoje'] ? 'hoje' : '' ?>">
        <div class="quadro-dia-header">
            <a href="<?= $baseUrl ?>?view=lista&data=<?= $dia['data_iso'] ?><?= $taskParam ?>"><?= htmlspecialchars($dia['label_dia']) ?></a>
        </div>
        <?php if (empty($dia['blocos'])): ?>
        <div style="font-size: 12px; color: #9ca3af; text-align: center; padding: 20px 0;">Sem blocos</div>
        <?php else: ?>
        <?php foreach ($dia['blocos'] as $bloco):
            $isAtual = !empty($bloco['is_atual']);
            $corHex = $bloco['tipo_cor_hex'] ?? '#94a3b8';
        ?>
        <div class="quadro-card <?= $isAtual ? 'atual' : '' ?>" style="border-left-color: <?= htmlspecialchars($corHex) ?>"
             onclick="window.location.href='<?= $baseUrl ?>?view=lista&data=<?= $dia['data_iso'] ?>&block_id=<?= (int)$bloco['id'] ?><?= $taskParam ?>'">
            <strong><?= date('H:i', strtotime($bloco['hora_inicio'])) ?>–<?= date('H:i', strtotime($bloco['hora_fim'])) ?></strong>
            <div style="color: <?= htmlspecialchars($corHex) ?>; margin-top: 2px;"><?= htmlspecialchars($bloco['tipo_nome'] ?? '') ?></div>
            <?php if (!empty($bloco['segment_fatias'])): ?><div style="font-size: 11px; color: #6b7280; margin-top: 4px;"><?= htmlspecialchars(implode(' | ', $bloco['segment_fatias'])) ?></div><?php endif; ?>
            <?php if ($isAtual): ?><div style="font-size: 11px; color: #0ea5e9; font-weight: 600; margin-top: 4px;">● Agora</div><?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
const baseUrl = '<?= $baseUrl ?>';
const dataStr = '<?= $dataStr ?>';
const taskParam = '<?= $taskParam ?>';
window.AGENDA_TIPOS = <?= json_encode(array_map(fn($t) => ['id' => (int)$t['id'], 'nome' => $t['nome'] ?? ''], $tipos ?? [])) ?>;

function applyFilters(val, type) {
    const params = new URLSearchParams(window.location.search);
    params.set(type, val || '');
    window.location.href = baseUrl + '?' + params.toString();
}

function toggleBlockExpand(blockId) {
    const el = document.getElementById('block-expand-' + blockId);
    if (!el) return;
    const isOpen = el.classList.contains('show');
    document.querySelectorAll('.block-expand.show').forEach(e => e.classList.remove('show'));
    if (!isOpen) {
        el.classList.add('show');
        const content = el.querySelector('.block-expand-content');
        if (content && !content.dataset.loaded) {
            loadBlockContent(blockId, content);
            content.dataset.loaded = '1';
        }
    }
}

function loadBlockContent(blockId, container) {
    container.innerHTML = '<div style="color:#6b7280;font-size:13px;">Carregando…</div>';
    fetch('<?= pixelhub_url('/agenda/bloco/segments') ?>?block_id=' + blockId)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                container.innerHTML = '<span style="color:#dc2626;">' + (data.error || 'Erro') + '</span>';
                return;
            }
            const segments = data.segments || [];
            let html = '<form method="post" action="<?= pixelhub_url('/agenda/bloco/segment/create-manual') ?>" style="margin-bottom:16px;display:grid;grid-template-columns:1fr 1fr 100px 70px 70px auto;gap:8px;align-items:center;">';
            html += '<input type="hidden" name="block_id" value="' + blockId + '"><input type="hidden" name="return_to" value="agenda">';
            html += '<select name="project_id" class="seg-project" style="padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:13px;"><option value="">Atividade avulsa</option></select>';
            html += '<select name="task_id" class="seg-task" style="padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:13px;"><option value="">—</option></select>';
            html += '<select name="tipo_id" style="padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:13px;"><option value="">Tipo</option>';
            (window.AGENDA_TIPOS || []).forEach(t => { html += '<option value="' + t.id + '">' + (t.nome || '').replace(/</g,'&lt;') + '</option>'; });
            html += '</select>';
            html += '<input type="time" name="hora_inicio" required style="padding:6px 8px;border:1px solid #ddd;border-radius:4px;">';
            html += '<input type="time" name="hora_fim" required style="padding:6px 8px;border:1px solid #ddd;border-radius:4px;">';
            html += '<button type="submit" style="padding:6px 12px;background:#023A8D;color:white;border:none;border-radius:4px;cursor:pointer;">Adicionar</button>';
            html += '</form>';
            html += '<table class="planilha-registros"><thead><tr><th>Projeto</th><th>Tarefa</th><th>Tipo</th><th>Início</th><th>Fim</th><th style="width:50px;"></th></tr></thead><tbody>';
            segments.forEach(s => {
                const proj = (s.project_name || 'Atividade avulsa').replace(/</g,'&lt;');
                const task = (s.task_title || '—').replace(/</g,'&lt;');
                const tipo = (s.tipo_nome || '—').replace(/</g,'&lt;');
                const ini = (s.started_at || '').substring(11, 16);
                const fim = s.ended_at ? s.ended_at.substring(11, 16) : '—';
                html += '<tr><td>' + proj + '</td><td>' + task + '</td><td>' + tipo + '</td><td>' + ini + '</td><td>' + fim + '</td><td>';
                html += '<form method="post" action="<?= pixelhub_url('/agenda/bloco/segment/delete') ?>" style="display:inline;" onsubmit="return confirm(\'Excluir?\');">';
                html += '<input type="hidden" name="segment_id" value="' + s.id + '"><input type="hidden" name="block_id" value="' + blockId + '"><input type="hidden" name="return_to" value="agenda">';
                html += '<button type="submit" class="btn-icon" title="Excluir"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button></form></td></tr>';
            });
            html += '</tbody></table>';
            if (segments.length === 0) html += '<p style="color:#9ca3af;font-size:13px;margin-top:8px;">Nenhum registro. Use o formulário acima.</p>';
            container.innerHTML = html;
            loadProjectsForSegment(blockId, container);
        })
        .catch(() => { container.innerHTML = '<span style="color:#dc2626;">Erro ao carregar.</span>'; });
}

function loadProjectsForSegment(blockId, container) {
    const projSelect = container.querySelector('.seg-project');
    const taskSelect = container.querySelector('.seg-task');
    if (!projSelect) return;
    <?php foreach ($projetos as $p): ?>
    const o = document.createElement('option');
    o.value = '<?= (int)$p['id'] ?>';
    o.textContent = <?= json_encode($p['name']) ?>;
    projSelect.appendChild(o);
    <?php endforeach; ?>
    projSelect.addEventListener('change', function() {
        const pid = this.value;
        taskSelect.innerHTML = '<option value="">—</option>';
        if (!pid) return;
        fetch('<?= pixelhub_url('/agenda/tasks-by-project') ?>?project_id=' + pid)
            .then(r => r.json())
            .then(d => {
                if (d.success && d.tasks) d.tasks.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t.id;
                    opt.textContent = (t.title || '').substring(0, 50) + ((t.title || '').length > 50 ? '…' : '');
                    taskSelect.appendChild(opt);
                });
            });
    });
}

function generateBlocks() {
    if (!confirm('Gerar blocos para <?= date('d/m/Y', strtotime($dataStr)) ?>?')) return;
    fetch('<?= pixelhub_url('/agenda/generate-blocks') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'data=<?= $dataStr ?>'
    })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else alert(d.error || 'Erro'); })
    .catch(() => alert('Erro ao gerar blocos'));
}

document.addEventListener('DOMContentLoaded', function() {
    const qaProject = document.getElementById('quick-add-project');
    const qaTaskId = document.getElementById('quick-add-task-id');
    if (qaProject && qaTaskId) {
        qaProject.addEventListener('change', function() {
            const pid = this.value;
            qaTaskId.value = '';
            if (!pid) return;
            fetch('<?= pixelhub_url('/agenda/tasks-by-project') ?>?project_id=' + pid)
                .then(r => r.json())
                .then(d => {
                    if (d.success && d.tasks && d.tasks.length > 0) {
                        const area = document.getElementById('quick-add-tasks-area');
                        if (area) {
                            area.style.display = 'block';
                            const list = document.getElementById('quick-add-tasks-list');
                            if (list) {
                                list.style.display = 'block';
                                list.innerHTML = '<div style="font-size:12px;color:#6b7280;margin-bottom:8px;">Vincular tarefa (opcional):</div><div style="display:flex;flex-wrap:wrap;gap:6px;">' +
                                    d.tasks.map(t => '<button type="button" class="task-chip" data-id="' + t.id + '" style="padding:6px 12px;border-radius:6px;border:1px solid #d1d5db;background:#fff;font-size:12px;cursor:pointer;">' + ((t.title||'').substring(0,40) + ((t.title||'').length>40?'…':'')) + '</button>').join('') +
                                    '<button type="button" class="task-chip" data-id="" style="padding:6px 12px;border-radius:6px;border:1px solid #e5e7eb;background:#f9fafb;font-size:12px;color:#6b7280;cursor:pointer;">Nenhuma</button></div>';
                                list.querySelectorAll('.task-chip').forEach(btn => {
                                    btn.addEventListener('click', function() {
                                        list.querySelectorAll('.task-chip').forEach(b => { b.style.background = b.dataset.id ? '#fff' : '#f9fafb'; b.style.borderColor = '#d1d5db'; });
                                        this.style.background = this.dataset.id ? '#eff6ff' : '#f3f4f6'; this.style.borderColor = '#1d4ed8';
                                        qaTaskId.value = this.dataset.id || '';
                                    });
                                });
                            }
                        }
                    } else {
                        const area = document.getElementById('quick-add-tasks-area');
                        if (area) area.style.display = 'none';
                    }
                });
        });
    }
});

<?php if ($expandBlockId): ?>
document.addEventListener('DOMContentLoaded', function() {
    const el = document.getElementById('block-expand-<?= $expandBlockId ?>');
    if (el) {
        el.classList.add('show');
        const content = el.querySelector('.block-expand-content');
        if (content && !content.dataset.loaded) {
            loadBlockContent(<?= $expandBlockId ?>, content);
            content.dataset.loaded = '1';
        }
    }
});
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
$title = 'Agenda';
require __DIR__ . '/../layout/main.php';
?>
