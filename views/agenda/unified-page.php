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
    z-index: 40; /* abaixo da sidebar (50) para evitar submenu ficar por trás */
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
/* Botão primário Agenda: mesmo padrão visual de Lista/Quadro */
.btn-agenda-primary {
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 500;
    color: white;
    background: #023A8D;
    border: none;
    border-radius: 6px;
    height: 38px;
    box-sizing: border-box;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
}
.btn-agenda-primary:hover { background: #022a6b; color: white; }
.btn-agenda-primary:focus { outline: none; box-shadow: 0 0 0 2px rgba(2, 58, 141, 0.3); }
.agenda-nav { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.agenda-nav .btn-nav { padding: 6px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; color: #374151; text-decoration: none; background: white; }
.agenda-nav .btn-nav:hover { background: #f9fafb; }
.agenda-filters { display: flex; gap: 8px; flex-wrap: wrap; }
.agenda-filters select { padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; }
/* List view: tabela de blocos (estilo planilha ClickUp) */
.agenda-list-table { width: 100%; table-layout: fixed; border-collapse: collapse; font-size: 13px; background: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
.agenda-list-table thead { background: #f8fafc; }
.agenda-list-table th { padding: 10px 12px; text-align: left; font-weight: 600; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; border-bottom: 1px solid #e2e8f0; }
.agenda-list-table th.col-item { width: 220px; max-width: 220px; }
.agenda-list-table th.col-cliente { width: 140px; }
.agenda-list-table th.col-tipo { width: 120px; }
.agenda-list-table th.col-inicio { width: 130px; }
.agenda-list-table th.col-fim { width: 130px; }
.agenda-list-table td.col-inicio, .agenda-list-table td.col-fim { overflow: visible; }
.agenda-list-table th.col-acoes { width: 80px; }
.agenda-list-table tbody tr.block-row { height: 48px; cursor: pointer; transition: background 0.12s; border-bottom: 1px solid #f1f5f9; }
.agenda-list-table tbody tr.block-row:hover { background: #f8fafc; }
.agenda-list-table tbody tr.block-row.current { background: #eff6ff; }
.agenda-list-table tbody tr.block-row td { padding: 8px 12px; vertical-align: middle; }
.agenda-list-table td.col-item { max-width: 260px; overflow: hidden; text-overflow: ellipsis; }
.agenda-list-table .block-item-cell { display: flex; align-items: center; gap: 8px; }
.agenda-list-table .block-main { display: flex; flex-direction: column; gap: 1px; flex: 1; min-width: 0; }
.agenda-list-table .block-project-link { font-weight: 600; color: #111827; font-size: 13px; text-decoration: none; cursor: pointer; }
.agenda-list-table .block-project-link:hover { color: #023A8D; text-decoration: underline; }
.agenda-list-table .block-task-link { font-size: 12px; color: #6b7280; text-decoration: none; cursor: pointer; }
.agenda-list-table .block-task-link:hover { color: #023A8D; text-decoration: underline; }
.agenda-list-table .block-task { font-size: 12px; color: #6b7280; }
.agenda-list-table .block-expand-btn { flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer; color: #475569; font-size: 14px; font-weight: 600; background: #f1f5f9; transition: all 0.15s; }
.agenda-list-table .block-expand-btn:hover { background: #e2e8f0; color: #1e293b; border-color: #94a3b8; }
.agenda-list-table .block-expand-btn.expanded { background: #023A8D; color: white; border-color: #023A8D; }
.agenda-list-table .block-expand-btn.is-disabled { opacity: 0.4; cursor: default; pointer-events: none; }
.agenda-list-table .block-expand-btn.is-disabled:hover { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
.block-linked-tasks { list-style: none; padding: 0; margin: 0; }
.block-linked-tasks li { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px; display: flex; align-items: center; gap: 8px; }
.block-linked-tasks li:last-child { border-bottom: none; }
.block-linked-tasks li a { color: #023A8D; text-decoration: none; }
.block-linked-tasks .task-name-wrap { display: inline-flex; align-items: center; gap: 4px; flex-wrap: nowrap; }
.block-linked-tasks .block-task-unlink { margin-left: 2px; flex-shrink: 0; }
.block-linked-tasks li a:hover { text-decoration: underline; }
.block-add-task-btn { flex-shrink: 0; width: 24px; height: 24px; border: 1px solid #cbd5e1; border-radius: 4px; background: #f8fafc; color: #475569; font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.block-add-task-btn:hover { background: #e2e8f0; color: #1e293b; }
.block-add-task-btn:disabled, .block-add-task-btn.disabled { opacity: 0.5; cursor: not-allowed; }
.block-task-unlink { flex-shrink: 0; width: 24px; height: 24px; border: none; background: none; color: #6b7280; cursor: pointer; padding: 4px; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; }
.block-task-unlink:hover { color: #dc2626; background: #fef2f2; }
.block-tasks-time-table { width: 100%; font-size: 13px; border-collapse: collapse; margin-top: 8px; }
.block-tasks-time-table th { text-align: left; padding: 6px 8px; font-weight: 600; font-size: 11px; color: #64748b; text-transform: uppercase; }
.block-tasks-time-table th:nth-child(2), .block-tasks-time-table th:nth-child(3) { min-width: 130px; }
.block-tasks-time-table td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; overflow: visible; }
.block-tasks-time-table .task-time-input { width: 130px; min-width: 120px; padding: 6px 10px; font-size: 13px; border: 1px solid #e5e7eb; border-radius: 6px; box-sizing: border-box; height: 36px; }
.block-tasks-time-table .task-time-input:focus { border-color: #3b82f6; outline: none; }
.block-tasks-time-table tr.task-time-error .task-time-input { border-color: #dc2626; background: #fef2f2; }
.block-tasks-time-table .task-time-error-msg { font-size: 12px; color: #dc2626; margin-top: 6px; padding: 6px 8px; background: #fef2f2; border-radius: 4px; }
.block-tasks-time-table .task-name-wrap { display: inline-flex; align-items: center; gap: 4px; flex-wrap: nowrap; }
.block-tasks-time-table .block-task-unlink { margin-left: 2px; flex-shrink: 0; }
.block-add-task-section { max-width: 560px; width: 50%; min-width: 280px; }
.block-add-task-section .block-add-task-row { display: inline-flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.block-add-task-section .block-add-task-row select { flex: 1 1 200px; min-width: 180px; max-width: 320px; padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; }
.block-add-task-section .block-add-task-row .btn-add-task-to-block { flex-shrink: 0; padding: 6px 14px; background: #023A8D; color: white; border: none; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; }
.block-add-task-section .block-add-task-close { flex-shrink: 0; width: 28px; height: 28px; border: 1px solid #d1d5db; border-radius: 4px; background: #f8fafc; color: #6b7280; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; padding: 0; }
.block-add-task-section .block-add-task-close:hover { background: #e2e8f0; color: #374151; }
@media (max-width: 600px) { .block-add-task-section { width: 100%; max-width: 100%; } .block-add-task-section .block-add-task-row select { max-width: none; } }
.agenda-list-table .block-actions-cell { display: flex; align-items: center; gap: 4px; }
.agenda-list-table .btn-icon { background: none; border: none; cursor: pointer; padding: 4px; color: #6b7280; display: inline-flex; align-items: center; justify-content: center; }
.agenda-list-table .btn-icon:hover { color: #dc2626; }
.inline-edit-time { cursor: pointer; padding: 2px 4px; border-radius: 4px; }
.inline-edit-time:hover { background: #e2e8f0; }
/* Input de hora no modo edição: min-width para HH:MM + ícone relógio, sem corte */
.inline-edit-time-input { width: 140px; min-width: 130px; padding: 6px 10px; font-size: 13px; border: 1px solid #3b82f6; border-radius: 6px; box-sizing: border-box; height: 36px; }
.agenda-time-popover { position: fixed; z-index: 1000; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); padding: 8px; }
.block-expand { display: none; background: #f8fafc; padding: 16px; border: 1px solid #e5e7eb; border-top: none; }
.block-expand.show { display: table-row; }
.block-expand td { padding: 16px !important; vertical-align: top !important; border-bottom: 1px solid #e2e8f0; }
.planilha-registros { width: 100%; border-collapse: collapse; font-size: 13px; }
.planilha-registros th { background: #f5f5f5; padding: 8px 12px; text-align: left; font-weight: 600; font-size: 11px; color: #666; text-transform: uppercase; }
.planilha-registros td { padding: 8px 12px; border-bottom: 1px solid #eee; }
.btn-icon { background: none; border: none; cursor: pointer; padding: 4px; color: #6b7280; display: inline-flex; }
.btn-icon:hover { color: #374151; }
/* ===== Quadro semanal: layout fixo + scroll só no conteúdo ===== */
.agenda-quadro-page-layout {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    margin-top: 20px;
}
.agenda-quadro-headers {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 12px;
    flex-shrink: 0;
    padding: 0 0 8px 0;
}
@media (max-width: 1200px) { .agenda-quadro-headers { grid-template-columns: repeat(4, 1fr); } }
@media (max-width: 768px) { .agenda-quadro-headers { grid-template-columns: repeat(2, 1fr); } }
.quadro-dia-header-cell {
    font-weight: 600;
    font-size: 13px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e5e7eb;
}
.quadro-dia-header-cell a { text-decoration: none; color: #111827; }
.quadro-dia-header-cell a:hover { color: #023A8D; }
.quadro-dia-header-cell.hoje { color: #0ea5e9; }
.agenda-quadro-scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}
.agenda-quadro-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 12px; padding-top: 12px; }
@media (max-width: 1200px) { .agenda-quadro-grid { grid-template-columns: repeat(4, 1fr); } }
@media (max-width: 768px) {
    .agenda-quadro-grid { grid-template-columns: repeat(2, 1fr); }
    .agenda-quadro-outer { height: auto; max-height: none; min-height: 0; }
    .agenda-quadro-page-layout { flex: none; }
    .agenda-quadro-scroll { overflow-y: visible; }
}
.quadro-dia { border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; background: white; min-height: 120px; }
.quadro-dia.hoje { background: #f0f9ff; border-color: #0ea5e9; }
.quadro-card { border-left: 4px solid #94a3b8; padding: 8px; border-radius: 4px; background: #f8fafc; font-size: 12px; margin-bottom: 6px; cursor: pointer; transition: all 0.15s; }
.quadro-card:hover { transform: translateY(-1px); box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
.quadro-card.atual { background: #e0f2fe; border-left-color: #0ea5e9; }
.quadro-card-item { font-size: 11px; color: #374151; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
/* Quick-add form: grid com larguras fixas para evitar corte de horário */
/* PROJETO ~35% / TAREFA ~65% do espaço flexível; Tipo/Horários/Adicionar fixos */
.quick-add-form-grid { display: grid; grid-template-columns: minmax(120px, 1fr) minmax(180px, 2fr) 180px 140px 140px 120px; gap: 10px; align-items: center; }
.quick-add-form-grid .col-projeto { min-width: 0; }
.quick-add-form-grid .col-tarefa { min-width: 0; }
.quick-add-form-grid .col-tipo { min-width: 180px; }
.quick-add-form-grid .col-inicio, .quick-add-form-grid .col-fim { min-width: 120px; }
.quick-add-form-grid input[type="time"] { width: 100%; min-width: 110px; height: 38px; box-sizing: border-box; padding: 8px 10px; border-radius: 6px; }
.quick-add-form-grid .btn-add { min-width: 110px; display: flex; align-items: center; }
.quick-add-form-grid .btn-add .btn-agenda-primary { width: 100%; }
.agenda-cliente-autocomplete-results .ac-item { padding: 8px 12px; font-size: 13px; cursor: pointer; border-bottom: 1px solid #f1f5f9; }
.agenda-cliente-autocomplete-results .ac-item:last-child { border-bottom: none; }
.agenda-cliente-autocomplete-results .ac-item:hover, .agenda-cliente-autocomplete-results .ac-item.ac-selected { background: #f0f9ff; }
.agenda-cliente-autocomplete-results .ac-empty { padding: 12px; color: #94a3b8; font-size: 12px; text-align: center; }
.quick-add-avulsa-row { display: flex; flex-wrap: nowrap; }
.quick-add-avulsa-row .col-cliente-avulsa { flex: 0 0 30%; min-width: 160px; }
.quick-add-avulsa-row .col-observacao-avulsa { flex: 1 1 auto; min-width: 200px; }
@media (max-width: 600px) { .quick-add-avulsa-row { flex-wrap: wrap; } .quick-add-avulsa-row .col-cliente-avulsa, .quick-add-avulsa-row .col-observacao-avulsa { flex: 1 1 100%; min-width: 0; } }
@media (max-width: 900px) {
    .quick-add-form-grid { grid-template-columns: 1fr 1fr 1fr 1fr; gap: 8px; }
    .quick-add-form-grid .col-projeto { grid-column: 1 / -1; }
    .quick-add-form-grid .col-tarefa { grid-column: 1 / -1; }
}
/* ===== Layout fixo: topo + form + header fixos, scroll só na lista (desktop) ===== */
.agenda-lista-page-layout {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 60px - 60px);
    max-height: calc(100vh - 120px);
    min-height: 350px;
}
.agenda-lista-fixed { flex-shrink: 0; }
.agenda-lista-scroll-area {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    margin-top: 0;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    -webkit-overflow-scrolling: touch;
}
.agenda-lista-scroll-area .agenda-list-table thead th {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #f8fafc;
    box-shadow: 0 1px 0 0 #e2e8f0;
}
@media (max-width: 768px) {
    .agenda-lista-page-layout { height: auto; max-height: none; min-height: 0; }
    .agenda-lista-scroll-area { overflow-y: visible; border: none; }
}
</style>

<?php if ($viewMode === 'quadro'): ?>
<div class="agenda-quadro-outer">
<?php endif; ?>
<?php if ($viewMode === 'lista'): ?>
<div class="agenda-lista-page-layout">
<div class="agenda-lista-fixed">
<?php endif; ?>

<div class="agenda-unified-sticky">
    <div class="agenda-unified-sticky-inner">
        <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
            <span style="font-size: 12px; color: #64748b; font-weight: 500;">Visualização:</span>
            <div class="view-switcher">
                <a href="<?= $baseUrl ?>?view=lista&data=<?= $dataStr ?><?= $taskParam ?>" class="<?= $viewMode === 'lista' ? 'active' : '' ?>">Dia</a>
                <a href="<?= $baseUrl ?>?view=quadro&data=<?= $dataStr ?><?= $taskParam ?>" class="<?= $viewMode === 'quadro' ? 'active' : '' ?>">Semana</a>
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
            <button type="button" onclick="generateBlocks()" class="btn-agenda-primary">Gerar Blocos</button>
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
    <input type="hidden" name="activity_type_id" id="quick-add-activity-type-id" value="">
    <div class="quick-add-form-grid">
        <div class="col-projeto">
            <select name="project_id" id="quick-add-project" style="width:100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
                <option value="">Atividade avulsa</option>
                <?php foreach ($projetos as $p): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-tarefa">
            <select id="quick-add-task-select" style="width:100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;" disabled title="Selecione um projeto primeiro">
                <option value="">Selecione um projeto primeiro</option>
            </select>
        </div>
        <div class="col-tipo">
            <select name="tipo_id" required style="width:100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
                <option value="">Bloco</option>
                <?php foreach ($tipos as $t): ?><option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nome']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-inicio">
            <input type="time" name="hora_inicio" required placeholder="Início" style="padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
        </div>
        <div class="col-fim">
            <input type="time" name="hora_fim" required placeholder="Fim" style="padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
        </div>
        <div class="btn-add">
            <button type="submit" class="btn-add-submit btn-agenda-primary">Adicionar</button>
        </div>
    </div>
    <div id="quick-add-avulsa-fields" class="quick-add-avulsa-row" style="display:none; margin-top:10px; padding-top:10px; border-top:1px solid #e5e7eb; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <div class="col-cliente-avulsa" style="min-width:200px; position:relative;">
            <label style="font-size:11px; color:#64748b; display:block; margin-bottom:4px;">Cliente (opcional)</label>
            <input type="hidden" name="tenant_id" id="quick-add-tenant-id" value="">
            <input type="text" id="quick-add-tenant-input" placeholder="Digite 3 letras para buscar..." autocomplete="off" style="width:100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
            <div id="quick-add-tenant-results" class="agenda-cliente-autocomplete-results" style="display:none; position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #e5e7eb; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,0.1); max-height:200px; overflow-y:auto; z-index:100; margin-top:2px;"></div>
        </div>
        <div class="col-observacao-avulsa">
            <label style="font-size:11px; color:#64748b; display:block; margin-bottom:4px;">Observação (opcional)</label>
            <input type="text" name="resumo" id="quick-add-resumo" placeholder="Ex.: Reunião com Fulano — alinhamento proposta" maxlength="255" style="width:100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
        </div>
    </div>
</form>

</div><!-- .agenda-lista-fixed -->
<div class="agenda-lista-scroll-area">
<!-- Lista de blocos (tabela planilha) -->
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
    ?>
    <table class="agenda-list-table">
        <thead>
            <tr>
                <th class="col-item">Item</th>
                <th class="col-cliente">Cliente</th>
                <th class="col-tipo">Bloco</th>
                <th class="col-inicio">Início</th>
                <th class="col-fim">Fim</th>
                <th class="col-acoes">Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($blocos as $bloco):
            $isCurrent = ($bloco['id'] === $blocoAtualId);
            $corBorda = htmlspecialchars($bloco['tipo_cor'] ?? '#94a3b8');
            $projetoNome = !empty($bloco['projeto_foco_nome']) ? $bloco['projeto_foco_nome'] : (!empty($bloco['activity_type_name']) ? $bloco['activity_type_name'] : (!empty($bloco['resumo']) ? $bloco['resumo'] : 'Atividade avulsa'));
            $isExpanded = ($expandBlockId && $expandBlockId === (int)$bloco['id']);
        ?>
            <?php
                $horaInicioFmt = date('H:i', strtotime($bloco['hora_inicio']));
                $horaFimFmt = date('H:i', strtotime($bloco['hora_fim']));
            ?>
            <?php
                $projetoUrl = !empty($bloco['projeto_foco_id']) ? pixelhub_url('/projects/board?project_id=' . (int)$bloco['projeto_foco_id']) : pixelhub_url('/projects');
                $taskUrl = !empty($bloco['focus_task_id']) && !empty($bloco['focus_task_project_id']) ? pixelhub_url('/projects/board?project_id=' . (int)$bloco['focus_task_project_id'] . '&task_id=' . (int)$bloco['focus_task_id']) : (!empty($bloco['focus_task_id']) && !empty($bloco['projeto_foco_id']) ? pixelhub_url('/projects/board?project_id=' . (int)$bloco['projeto_foco_id'] . '&task_id=' . (int)$bloco['focus_task_id']) : null);
            ?>
            <?php $hasSubitems = (int)($bloco['tarefas_count'] ?? 0) > 0 || !empty($bloco['projeto_foco_id']); ?>
            <tr class="block-row <?= $isCurrent ? 'current' : '' ?>" data-block-id="<?= (int)$bloco['id'] ?>" data-projeto-foco-id="<?= (int)($bloco['projeto_foco_id'] ?? 0) ?>" data-tarefas-count="<?= (int)($bloco['tarefas_count'] ?? 0) ?>" data-hora-inicio="<?= htmlspecialchars($horaInicioFmt) ?>" data-hora-fim="<?= htmlspecialchars($horaFimFmt) ?>">
                <td class="col-item" style="border-left: 4px solid <?= $corBorda ?>;">
                    <div class="block-item-cell">
                        <button type="button" class="block-expand-btn <?= $isExpanded ? 'expanded' : '' ?> <?= !$hasSubitems ? 'is-disabled' : '' ?>" onclick="toggleBlockExpand(<?= (int)$bloco['id'] ?>)" title="<?= $hasSubitems ? ($isExpanded ? 'Recolher' : 'Expandir registros') : 'Sem itens' ?>" aria-label="<?= $hasSubitems ? ($isExpanded ? 'Recolher' : 'Expandir') : 'Sem itens' ?>" <?= !$hasSubitems ? 'aria-disabled="true"' : '' ?>>
                            <span class="expand-icon"><?= $isExpanded ? '▾' : '▸' ?></span>
                        </button>
                        <div class="block-main">
                            <a href="<?= $projetoUrl ?>" class="block-project-link" onclick="event.stopPropagation()"><?= htmlspecialchars($projetoNome) ?></a>
                            <?php if (!empty($bloco['focus_task_title'])): ?>
                                <?php if ($taskUrl): ?><a href="<?= $taskUrl ?>" class="block-task-link" onclick="event.stopPropagation()">↳ <?= htmlspecialchars($bloco['focus_task_title']) ?></a><?php else: ?><span class="block-task">↳ <?= htmlspecialchars($bloco['focus_task_title']) ?></span><?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($bloco['projeto_foco_id'])): ?><span class="block-add-task-btn-wrap" data-block-id="<?= (int)$bloco['id'] ?>"></span><?php endif; ?>
                    </div>
                </td>
                <td class="col-cliente"><?php
                    $clienteNome = '';
                    if (!empty($bloco['projeto_foco_id'])) {
                        $clienteNome = !empty($bloco['project_tenant_name']) ? $bloco['project_tenant_name'] : 'Interno';
                    } else {
                        $clienteNome = !empty($bloco['block_tenant_name']) ? $bloco['block_tenant_name'] : '—';
                    }
                    echo htmlspecialchars($clienteNome);
                ?></td>
                <td class="col-tipo"><span style="background: <?= $corBorda ?>; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; color: white;"><?= htmlspecialchars($bloco['tipo_nome']) ?></span></td>
                <td class="col-inicio" onclick="event.stopPropagation()">
                    <span class="inline-edit-time" data-field="hora_inicio" data-block-id="<?= (int)$bloco['id'] ?>" title="Clique para editar"><?= $horaInicioFmt ?></span><?= $isCurrent ? ' <span style="color:#1976d2;font-size:10px;">●</span>' : '' ?>
                </td>
                <td class="col-fim" onclick="event.stopPropagation()">
                    <span class="inline-edit-time" data-field="hora_fim" data-block-id="<?= (int)$bloco['id'] ?>" title="Clique para editar"><?= $horaFimFmt ?></span>
                </td>
                <td class="col-acoes">
                    <div class="block-actions-cell" onclick="event.stopPropagation()">
                        <form method="post" action="<?= pixelhub_url('/agenda/bloco/delete') ?>" style="display: inline;" onsubmit="return confirm('Excluir este bloco?');">
                            <input type="hidden" name="id" value="<?= (int)$bloco['id'] ?>">
                            <input type="hidden" name="date" value="<?= htmlspecialchars($bloco['data'] ?? $dataStr) ?>">
                            <button type="submit" class="btn-icon" title="Excluir bloco (remove o bloco inteiro)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <tr class="block-expand <?= $isExpanded ? 'show' : '' ?>" id="block-expand-<?= (int)$bloco['id'] ?>" data-block-id="<?= (int)$bloco['id'] ?>" onclick="event.stopPropagation()">
                <td colspan="6">
                    <div class="block-expand-content"></div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</div><!-- .agenda-lista-scroll-area -->
</div><!-- .agenda-lista-page-layout -->

<?php else: ?>
<!-- Quadro semanal: cabeçalho fixo + scroll só nos cards -->
<div class="agenda-quadro-page-layout">
    <div class="agenda-quadro-headers">
        <?php foreach ($diasSemana as $dia): ?>
        <div class="quadro-dia-header-cell <?= $dia['is_hoje'] ? 'hoje' : '' ?>">
            <a href="<?= $baseUrl ?>?view=lista&data=<?= $dia['data_iso'] ?><?= $taskParam ?>"><?= htmlspecialchars($dia['label_dia']) ?></a>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="agenda-quadro-scroll">
        <div class="agenda-quadro-grid">
            <?php foreach ($diasSemana as $dia): ?>
            <div class="quadro-dia <?= $dia['is_hoje'] ? 'hoje' : '' ?>">
                <?php if (empty($dia['blocos'])): ?>
                <div style="font-size: 12px; color: #9ca3af; text-align: center; padding: 20px 0;">Sem blocos</div>
                <?php else: ?>
                <?php foreach ($dia['blocos'] as $bloco):
                    $isAtual = !empty($bloco['is_atual']);
                    $corHex = $bloco['tipo_cor_hex'] ?? '#94a3b8';
                    $projetoNome = !empty($bloco['projeto_foco_nome']) ? $bloco['projeto_foco_nome'] : (!empty($bloco['activity_type_name']) ? $bloco['activity_type_name'] : (!empty($bloco['resumo']) ? $bloco['resumo'] : ''));
                    $itemLabel = $projetoNome;
                    if (!empty($bloco['focus_task_title'])) {
                        $itemLabel = $itemLabel ? ($itemLabel . ' — ' . $bloco['focus_task_title']) : $bloco['focus_task_title'];
                    }
                    if (!$itemLabel) $itemLabel = 'Atividade avulsa';
                ?>
                <div class="quadro-card <?= $isAtual ? 'atual' : '' ?>" style="border-left-color: <?= htmlspecialchars($corHex) ?>"
                     onclick="window.location.href='<?= $baseUrl ?>?view=lista&data=<?= $dia['data_iso'] ?>&block_id=<?= (int)$bloco['id'] ?><?= $taskParam ?>'">
                    <strong><?= date('H:i', strtotime($bloco['hora_inicio'])) ?>–<?= date('H:i', strtotime($bloco['hora_fim'])) ?></strong>
                    <div style="color: <?= htmlspecialchars($corHex) ?>; margin-top: 2px;"><?= htmlspecialchars($bloco['tipo_nome'] ?? '') ?></div>
                    <?php if ($itemLabel): ?><div class="quadro-card-item" title="<?= htmlspecialchars($itemLabel) ?>"><?= htmlspecialchars(mb_strimwidth($itemLabel, 0, 50, '…')) ?></div><?php endif; ?>
                    <?php if (!empty($bloco['segment_fatias'])): ?><div style="font-size: 11px; color: #6b7280; margin-top: 4px;"><?= htmlspecialchars(implode(' | ', $bloco['segment_fatias'])) ?></div><?php endif; ?>
                    <?php if ($isAtual): ?><div style="font-size: 11px; color: #0ea5e9; font-weight: 600; margin-top: 4px;">● Agora</div><?php endif; ?>
                </div>
                <?php endforeach; endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php if ($viewMode === 'quadro'): ?>
</div><!-- .agenda-quadro-outer -->
<?php endif; ?>
<?php endif; ?><!-- fecha if viewMode lista/quadro -->

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

function initInlineEditTime() {
    document.querySelectorAll('.inline-edit-time').forEach(span => {
        if (span.dataset.inited) return;
        span.dataset.inited = '1';
        span.addEventListener('click', function(e) {
            e.stopPropagation();
            const blockId = this.dataset.blockId;
            const field = this.dataset.field;
            const row = this.closest('.block-row');
            if (!row || !blockId) return;
            const currentVal = this.textContent.trim();
            const spanEl = this;
            /* Popover: input fora da célula para garantir HH:MM 100% visível (sem corte) */
            const popover = document.createElement('div');
            popover.className = 'agenda-time-popover';
            const rect = spanEl.getBoundingClientRect();
            popover.style.left = rect.left + 'px';
            popover.style.top = rect.top + 'px';
            const input = document.createElement('input');
            input.type = 'time';
            input.value = currentVal;
            input.className = 'inline-edit-time-input';
            popover.appendChild(input);
            document.body.appendChild(popover);
            spanEl.style.visibility = 'hidden';
            input.focus();
            input.select && input.select();
            const cleanup = (displayVal) => {
                if (popover.parentNode) popover.remove();
                spanEl.style.visibility = '';
                spanEl.textContent = displayVal !== undefined ? displayVal : (input.value || currentVal);
                spanEl.dataset.inited = '';
                initInlineEditTime();
            };
            const save = () => {
                const newVal = input.value;
                const horaInicio = field === 'hora_inicio' ? newVal : row.dataset.horaInicio;
                const horaFim = field === 'hora_fim' ? newVal : row.dataset.horaFim;
                if (newVal === currentVal) {
                    cleanup(currentVal);
                    return;
                }
                const fd = new FormData();
                fd.append('id', blockId);
                fd.append('hora_inicio', horaInicio);
                fd.append('hora_fim', horaFim);
                fetch('<?= pixelhub_url('/agenda/bloco/editar') ?>', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        const hi = (d.bloco.hora_inicio || '').substring(0, 5);
                        const hf = (d.bloco.hora_fim || '').substring(0, 5);
                        row.dataset.horaInicio = hi;
                        row.dataset.horaFim = hf;
                        cleanup(field === 'hora_inicio' ? hi : hf);
                    } else {
                        alert(d.error || 'Erro ao salvar');
                        cleanup(currentVal);
                    }
                })
                .catch(() => { alert('Erro ao salvar'); cleanup(currentVal); });
            };
            input.addEventListener('blur', save);
            input.addEventListener('keydown', function(ev) {
                if (ev.key === 'Enter') { ev.preventDefault(); save(); }
                if (ev.key === 'Escape') { ev.preventDefault(); cleanup(currentVal); }
            });
        });
    });
}

function toggleBlockExpand(blockId) {
    const el = document.getElementById('block-expand-' + blockId);
    if (!el) return;
    const blockRow = el.previousElementSibling;
    const tarefasCount = blockRow && blockRow.dataset.tarefasCount ? parseInt(blockRow.dataset.tarefasCount, 10) : 0;
    const projectId = blockRow && blockRow.dataset.projetoFocoId ? parseInt(blockRow.dataset.projetoFocoId, 10) : 0;
    const hasSubitems = tarefasCount > 0 || projectId > 0;
    if (!hasSubitems) return;
    const isOpen = el.classList.contains('show');
    document.querySelectorAll('.block-expand.show').forEach(e => {
        e.classList.remove('show');
        const row = e.previousElementSibling;
        if (row && row.classList.contains('block-row')) {
            const btn = row.querySelector('.block-expand-btn');
            const icon = btn && btn.querySelector('.expand-icon');
            if (btn) btn.classList.remove('expanded');
            if (icon) icon.textContent = '▸';
        }
    });
    if (!isOpen) {
        el.classList.add('show');
        const row = el.previousElementSibling;
        if (row && row.classList.contains('block-row')) {
            const btn = row.querySelector('.block-expand-btn');
            const icon = btn && btn.querySelector('.expand-icon');
            if (btn) btn.classList.add('expanded');
            if (icon) icon.textContent = '▾';
        }
        const content = el.querySelector('.block-expand-content');
        if (content && !content.dataset.loaded) {
            loadBlockContent(blockId, content);
            content.dataset.loaded = '1';
        }
    }
}

function loadBlockContent(blockId, container) {
    container.innerHTML = '<div style="color:#6b7280;font-size:13px;">Carregando…</div>';
    const expandRow = container.closest('tr');
    const blockRow = expandRow && expandRow.previousElementSibling;
    const projectId = blockRow && blockRow.dataset.projetoFocoId ? parseInt(blockRow.dataset.projetoFocoId, 10) : 0;
    const addBtnWrap = blockRow && blockRow.querySelector('.block-add-task-btn-wrap');

    fetch('<?= pixelhub_url('/agenda/bloco/linked-tasks') ?>?block_id=' + blockId)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                container.innerHTML = '<span style="color:#dc2626;">' + (data.error || 'Erro') + '</span>';
                return;
            }
            const linkedTasks = data.tasks || [];
            const linkedIds = new Set(linkedTasks.map(t => t.id));
            const boardBase = '<?= pixelhub_url('/projects/board') ?>';
            const blockInicio = (data.block_hora_inicio || '').toString().substring(0, 5) || '00:00';
            const blockFim = (data.block_hora_fim || '').toString().substring(0, 5) || '23:59';
            const hasMultipleTasks = linkedTasks.length >= 2;

            let html = '';
            if (linkedTasks.length > 0) {
                if (hasMultipleTasks) {
                    html += '<table class="block-tasks-time-table"><thead><tr><th>Tarefa</th><th>Início</th><th>Fim</th><th>Duração</th></tr></thead><tbody>';
                    linkedTasks.forEach(t => {
                        const tit = (t.title || '').replace(/</g,'&lt;');
                        const pid = t.project_id || '';
                        const url = pid ? boardBase + '?project_id=' + pid + '&task_id=' + t.id : boardBase + '?task_id=' + t.id;
                        const thIni = (t.task_hora_inicio || '').toString().substring(0, 5);
                        const thFim = (t.task_hora_fim || '').toString().substring(0, 5);
                        const durMins = (thIni && thFim) ? (parseInt(thFim.split(':')[0])*60 + parseInt(thFim.split(':')[1]) - parseInt(thIni.split(':')[0])*60 - parseInt(thIni.split(':')[1])) : 0;
                        const durStr = durMins > 0 ? durMins + ' min' : '—';
                        html += '<tr data-task-id="' + t.id + '"><td><span style="color:#64748b;">↳</span> <span class="task-name-wrap"><a href="' + url + '">' + tit + '</a> <button type="button" class="block-task-unlink" data-block-id="' + blockId + '" data-task-id="' + t.id + '" title="Desvincular"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button></span></td>';
                        html += '<td><input type="time" class="task-time-input task-time-inicio" data-block-id="' + blockId + '" data-task-id="' + t.id + '" value="' + (thIni || '') + '" min="' + blockInicio + '" max="' + blockFim + '" title="Início (janela do bloco: ' + blockInicio + '–' + blockFim + ')"></td>';
                        html += '<td><input type="time" class="task-time-input task-time-fim" data-block-id="' + blockId + '" data-task-id="' + t.id + '" value="' + (thFim || '') + '" min="' + blockInicio + '" max="' + blockFim + '" title="Fim (janela do bloco: ' + blockInicio + '–' + blockFim + ')"></td>';
                        html += '<td class="task-dur-display">' + durStr + '</td></tr>';
                    });
                    html += '</tbody></table>';
                    html += '<div class="task-time-error-msg" id="task-time-error-' + blockId + '" style="display:none;"></div>';
                } else {
                    html += '<ul class="block-linked-tasks">';
                    linkedTasks.forEach(t => {
                        const tit = (t.title || '').replace(/</g,'&lt;');
                        const pid = t.project_id || '';
                        const url = pid ? boardBase + '?project_id=' + pid + '&task_id=' + t.id : boardBase + '?task_id=' + t.id;
                        html += '<li><span style="color:#64748b;">↳</span> <span class="task-name-wrap"><a href="' + url + '">' + tit + '</a> <button type="button" class="block-task-unlink" data-block-id="' + blockId + '" data-task-id="' + t.id + '" title="Desvincular"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button></span></li>';
                    });
                    html += '</ul>';
                }
            } else {
                html += '<ul class="block-linked-tasks"><li style="color:#94a3b8;font-style:italic;">Nenhuma tarefa vinculada a este bloco.</li></ul>';
            }

            if (projectId > 0) {
                html += '<div class="block-add-task-section" id="block-add-task-form-' + blockId + '" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid #e2e8f0;">';
                html += '<label style="font-size:12px;color:#64748b;display:block;margin-bottom:6px;">Adicionar tarefa a este bloco</label>';
                html += '<div class="block-add-task-row">';
                html += '<select id="block-add-task-select-' + blockId + '"><option value="">Selecionar tarefa…</option></select>';
                html += '<button type="button" class="btn-add-task-to-block" data-block-id="' + blockId + '">Vincular</button>';
                html += '<button type="button" class="block-add-task-close" data-block-id="' + blockId + '" title="Fechar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>';
                html += '</div></div>';
            }
            container.innerHTML = html;

            container.querySelectorAll('.block-task-unlink').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!confirm('Desvincular esta tarefa?')) return;
                    const bid = parseInt(this.dataset.blockId, 10);
                    const tid = parseInt(this.dataset.taskId, 10);
                    this.disabled = true;
                    const fd = new FormData();
                    fd.append('block_id', bid);
                    fd.append('task_id', tid);
                    fetch('<?= pixelhub_url('/agenda/bloco/detach-task') ?>', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd
                    })
                    .then(r => r.json())
                    .then(d => { if (d.success) loadBlockContent(bid, container); else alert(d.error || 'Erro'); })
                    .catch(() => { alert('Erro ao desvincular'); this.disabled = false; });
                });
            });

            if (hasMultipleTasks) {
                const errDiv = document.getElementById('task-time-error-' + blockId);
                const showError = (msg) => {
                    if (errDiv) { errDiv.textContent = msg; errDiv.style.display = 'block'; }
                    container.querySelectorAll('tr[data-task-id]').forEach(r => r.classList.add('task-time-error'));
                };
                const clearError = () => {
                    if (errDiv) { errDiv.textContent = ''; errDiv.style.display = 'none'; }
                    container.querySelectorAll('tr[data-task-id].task-time-error').forEach(r => r.classList.remove('task-time-error'));
                };
                container.querySelectorAll('tr[data-task-id]').forEach(tr => {
                    const tid = parseInt(tr.dataset.taskId, 10);
                    const inpIni = tr.querySelector('.task-time-inicio');
                    const inpFim = tr.querySelector('.task-time-fim');
                    const durCell = tr.querySelector('.task-dur-display');
                    const saveTaskTime = () => {
                        const hi = inpIni && inpIni.value ? inpIni.value.substring(0, 5) : '';
                        const hf = inpFim && inpFim.value ? inpFim.value.substring(0, 5) : '';
                        if (!hi || !hf) return;
                        const fd = new FormData();
                        fd.append('block_id', blockId);
                        fd.append('task_id', tid);
                        fd.append('hora_inicio', hi);
                        fd.append('hora_fim', hf);
                        fetch('<?= pixelhub_url('/agenda/bloco/task-time') ?>', {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            body: fd
                        })
                        .then(r => r.json())
                        .then(d => {
                            if (d.success) {
                                clearError();
                                loadBlockContent(blockId, container);
                            } else {
                                showError(d.error || 'Erro ao salvar horário.');
                            }
                        })
                        .catch(() => showError('Erro ao salvar horário.'));
                    };
                    const updateDur = () => {
                        const hi = inpIni && inpIni.value ? inpIni.value : '';
                        const hf = inpFim && inpFim.value ? inpFim.value : '';
                        if (hi && hf && durCell) {
                            const p1 = hi.split(':'), p2 = hf.split(':');
                            const m = (parseInt(p2[0])*60 + parseInt(p2[1])) - (parseInt(p1[0])*60 + parseInt(p1[1]));
                            durCell.textContent = m > 0 ? m + ' min' : '—';
                        }
                    };
                    if (inpIni) { inpIni.addEventListener('blur', saveTaskTime); inpIni.addEventListener('input', updateDur); }
                    if (inpFim) { inpFim.addEventListener('blur', saveTaskTime); inpFim.addEventListener('input', updateDur); }
                });
            }

            if (projectId > 0) {
                fetch('<?= pixelhub_url('/agenda/tasks-by-project') ?>?project_id=' + projectId)
                    .then(r => r.json())
                    .then(projData => {
                        const allTasks = (projData.tasks || []).filter(t => !linkedIds.has(t.id));
                        const formSection = container.querySelector('.block-add-task-section');
                        const sel = document.getElementById('block-add-task-select-' + blockId);

                        if (addBtnWrap) {
                            addBtnWrap.innerHTML = '';
                            if (allTasks.length > 0) {
                                const plusBtn = document.createElement('button');
                                plusBtn.type = 'button';
                                plusBtn.className = 'block-add-task-btn';
                                plusBtn.title = 'Adicionar tarefa a este bloco';
                                plusBtn.textContent = '+';
                                plusBtn.addEventListener('click', function(e) {
                                    e.stopPropagation();
                                    if (formSection) {
                                        const isVisible = formSection.style.display === 'block';
                                        formSection.style.display = isVisible ? 'none' : 'block';
                                    }
                                });
                                addBtnWrap.appendChild(plusBtn);
                            } else {
                                const plusBtn = document.createElement('button');
                                plusBtn.type = 'button';
                                plusBtn.className = 'block-add-task-btn disabled';
                                plusBtn.title = 'Sem tarefas para vincular';
                                plusBtn.disabled = true;
                                plusBtn.textContent = '+';
                                addBtnWrap.appendChild(plusBtn);
                            }
                        }

                        const closeForm = () => {
                            if (formSection) formSection.style.display = 'none';
                        };
                        container.querySelector('.block-add-task-close')?.addEventListener('click', function(e) {
                            e.stopPropagation();
                            closeForm();
                        });

                        if (sel) {
                            allTasks.forEach(t => {
                                const opt = document.createElement('option');
                                opt.value = t.id;
                                opt.textContent = (t.title || '').substring(0, 60) + ((t.title || '').length > 60 ? '…' : '');
                                sel.appendChild(opt);
                            });
                            if (allTasks.length === 0 && sel.options.length === 1) {
                                const opt = document.createElement('option');
                                opt.value = '';
                                opt.textContent = 'Nenhuma tarefa disponível (todas já vinculadas)';
                                opt.disabled = true;
                                sel.appendChild(opt);
                            }
                        }

                        container.querySelector('.btn-add-task-to-block')?.addEventListener('click', function() {
                            const s = document.getElementById('block-add-task-select-' + blockId);
                            const taskId = s && s.value ? parseInt(s.value, 10) : 0;
                            if (!taskId) return;
                            const btn = this;
                            btn.disabled = true;
                            btn.textContent = 'Vinculando…';
                            const fd = new FormData();
                            fd.append('block_id', blockId);
                            fd.append('task_id', taskId);
                            fetch('<?= pixelhub_url('/agenda/bloco/attach-task') ?>', {
                                method: 'POST',
                                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                body: fd
                            })
                            .then(r => r.json())
                            .then(d => {
                                if (d.success) {
                                    loadBlockContent(blockId, container);
                                } else {
                                    alert(d.error || 'Erro ao vincular');
                                    btn.disabled = false;
                                    btn.textContent = 'Vincular';
                                }
                            })
                            .catch(() => { alert('Erro ao vincular'); btn.disabled = false; btn.textContent = 'Vincular'; });
                        });
                    })
                    .catch(() => {});
            }
        })
        .catch(() => { container.innerHTML = '<span style="color:#dc2626;">Erro ao carregar.</span>'; });
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
    initInlineEditTime();
    const qaProject = document.getElementById('quick-add-project');
    const qaTaskSelect = document.getElementById('quick-add-task-select');
    const qaTaskId = document.getElementById('quick-add-task-id');
    const qaActivityTypeId = document.getElementById('quick-add-activity-type-id');
    if (qaProject && qaTaskSelect && qaTaskId) {
        function syncToHidden() {
            const isAvulsa = !qaProject.value || qaProject.value === '';
            if (isAvulsa) {
                qaTaskId.value = '';
                qaActivityTypeId.value = qaTaskSelect.value || '';
            } else {
                qaActivityTypeId.value = '';
                qaTaskId.value = qaTaskSelect.value || '';
            }
        }
        qaTaskSelect.addEventListener('change', syncToHidden);
        document.getElementById('quick-add-form').addEventListener('submit', syncToHidden);
        function loadTasksForProject(pid) {
            qaTaskId.value = '';
            if (qaActivityTypeId) qaActivityTypeId.value = '';
            qaTaskSelect.innerHTML = '<option value="">Selecione um projeto primeiro</option>';
            qaTaskSelect.disabled = true;
            qaTaskSelect.title = 'Selecione um projeto primeiro';
            if (!pid) {
                loadActivityTypes();
                return;
            }
            qaTaskSelect.disabled = false;
            qaTaskSelect.title = '';
            qaTaskSelect.innerHTML = '<option value="">Carregando…</option>';
            fetch('<?= pixelhub_url('/agenda/tasks-by-project') ?>?project_id=' + encodeURIComponent(pid))
                .then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(d => {
                    qaTaskSelect.innerHTML = '<option value="">Tarefa (opcional)</option>';
                    if (d.success && d.tasks && d.tasks.length > 0) {
                        d.tasks.forEach(t => {
                            const opt = document.createElement('option');
                            opt.value = t.id;
                            const tit = (t.title || t.name || '').toString();
                            opt.textContent = tit.substring(0, 50) + (tit.length > 50 ? '…' : '');
                            qaTaskSelect.appendChild(opt);
                        });
                    } else if (d.success && (!d.tasks || d.tasks.length === 0)) {
                        qaTaskSelect.innerHTML = '<option value="">Nenhuma tarefa neste projeto</option>';
                    } else if (d.error) {
                        qaTaskSelect.innerHTML = '<option value="">Erro ao carregar</option>';
                        console.error('Erro ao carregar tarefas:', d.error);
                    }
                })
                .catch(err => {
                    console.error('Erro ao carregar tarefas:', err);
                    qaTaskSelect.innerHTML = '<option value="">Erro ao carregar</option>';
                });
        }
        function loadActivityTypes() {
            qaTaskSelect.disabled = false;
            qaTaskSelect.title = 'Selecione um tipo de atividade';
            qaTaskSelect.innerHTML = '<option value="">Carregando…</option>';
            fetch('<?= pixelhub_url('/agenda/activity-types') ?>')
                .then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(d => {
                    qaTaskSelect.innerHTML = '<option value="">Selecione um tipo de atividade</option>';
                    if (d.success && d.types && d.types.length > 0) {
                        d.types.forEach(t => {
                            const opt = document.createElement('option');
                            opt.value = t.id;
                            opt.textContent = (t.name || '').toString();
                            qaTaskSelect.appendChild(opt);
                        });
                    } else if (d.success && (!d.types || d.types.length === 0)) {
                        qaTaskSelect.innerHTML = '<option value="">Nenhum tipo cadastrado</option>';
                    } else if (d.error) {
                        qaTaskSelect.innerHTML = '<option value="">Erro ao carregar</option>';
                        console.error('Erro ao carregar tipos:', d.error);
                    }
                })
                .catch(err => {
                    console.error('Erro ao carregar tipos de atividade:', err);
                    qaTaskSelect.innerHTML = '<option value="">Erro ao carregar</option>';
                });
        }
        qaProject.addEventListener('change', function() {
            const isAvulsa = this.value === '';
            if (isAvulsa) {
                loadActivityTypes();
            } else {
                loadTasksForProject(this.value);
            }
            const avulsaRow = document.getElementById('quick-add-avulsa-fields');
            if (avulsaRow) avulsaRow.style.display = isAvulsa ? 'flex' : 'none';
            if (!isAvulsa) {
                const ti = document.getElementById('quick-add-tenant-input');
                const th = document.getElementById('quick-add-tenant-id');
                if (ti) ti.value = '';
                if (th) th.value = '';
            }
        });
        if (qaProject.value) {
            loadTasksForProject(qaProject.value);
        } else {
            loadActivityTypes();
        }
        const avulsaRow = document.getElementById('quick-add-avulsa-fields');
        if (avulsaRow && qaProject) avulsaRow.style.display = qaProject.value === '' ? 'flex' : 'none';
    }

    // Autocomplete Cliente (atividade avulsa)
    const tenantInput = document.getElementById('quick-add-tenant-input');
    const tenantIdHidden = document.getElementById('quick-add-tenant-id');
    const tenantResults = document.getElementById('quick-add-tenant-results');
    if (tenantInput && tenantIdHidden && tenantResults) {
        let debounceTimer = null;
        const searchUrl = '<?= pixelhub_url('/tenants/search-ajax') ?>';

        function hideResults() {
            tenantResults.style.display = 'none';
            tenantResults.innerHTML = '';
        }

        function selectClient(id, label) {
            tenantIdHidden.value = id;
            tenantInput.value = label;
            hideResults();
        }

        tenantInput.addEventListener('input', function() {
            const q = this.value.trim();
            tenantIdHidden.value = '';
            if (q.length < 3) {
                hideResults();
                if (debounceTimer) clearTimeout(debounceTimer);
                return;
            }
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                debounceTimer = null;
                fetch(searchUrl + '?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(d => {
                        if (d.success && d.clients && d.clients.length > 0) {
                            tenantResults.innerHTML = d.clients.map(c => {
                                const label = (c.label || c.name || '').replace(/</g,'&lt;').replace(/"/g,'&quot;');
                                return '<div class="ac-item" data-id="' + c.id + '" data-label="' + label + '">' + label + '</div>';
                            }).join('');
                            tenantResults.style.display = 'block';
                            tenantResults.querySelectorAll('.ac-item').forEach(el => {
                                el.addEventListener('click', function() {
                                    selectClient(this.dataset.id, this.dataset.label || this.textContent);
                                });
                            });
                        } else {
                            tenantResults.innerHTML = '<div class="ac-empty">Nenhum cliente encontrado</div>';
                            tenantResults.style.display = 'block';
                        }
                    })
                    .catch(() => { hideResults(); });
            }, 300);
        });

        tenantInput.addEventListener('blur', function() {
            setTimeout(hideResults, 150);
        });

        tenantInput.addEventListener('focus', function() {
            const q = this.value.trim();
            if (q.length >= 3 && tenantResults.children.length > 0) tenantResults.style.display = 'block';
        });

        tenantInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') { hideResults(); this.blur(); }
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
