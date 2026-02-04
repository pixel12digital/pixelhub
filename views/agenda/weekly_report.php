<?php
ob_start();
$report = $report ?? [];
$tab = $tab ?? 'dashboard';
$period = $period ?? 'semana';
$dataStr = $dataStr ?? date('Y-m-d');
$dataInicio = $dataInicio ?? date('Y-m-d');
$dataFim = $dataFim ?? date('Y-m-d');
$filters = $filters ?? [];
$tipos = $tipos ?? [];
$projetos = $projetos ?? [];
$tenants = $tenants ?? [];
$activityTypes = $activityTypes ?? [];
$baseUrl = pixelhub_url('/agenda/weekly-report');
$printMode = isset($_GET['print']) && $_GET['print'] === '1';

function buildReportUrl($base, $params) {
    $q = array_merge($_GET, $params);
    return $base . '?' . http_build_query($q);
}
?>

<style>
.report-header { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.report-section { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.report-section h3 { margin: 0 0 15px 0; color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
.report-tabs { display: flex; gap: 0; border-bottom: 2px solid #e5e7eb; margin-bottom: 20px; }
.report-tabs a { padding: 12px 20px; font-size: 14px; font-weight: 500; text-decoration: none; color: #6b7280; background: #f9fafb; border: 1px solid #e5e7eb; border-bottom: none; margin-bottom: -2px; }
.report-tabs a:hover { background: #f3f4f6; color: #374151; }
.report-tabs a.active { background: white; color: #023A8D; border-bottom: 2px solid white; }
.report-topbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 20px; }
.report-filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.report-filters select { padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; }
.report-filters select.period-select { min-width: 180px; }
.report-period-nav { display: flex; gap: 8px; align-items: center; font-size: 13px; }
.report-period-nav a { color: #023A8D; text-decoration: none; }
.report-period-nav a:hover { text-decoration: underline; }
.report-export-btns { display: flex; gap: 8px; }
.report-export-btns a { padding: 8px 16px; font-size: 13px; background: #023A8D; color: white; text-decoration: none; border-radius: 6px; }
.report-export-btns a:hover { background: #022a6b; }
.report-export-btns a.btn-secondary { background: #6b7280; }
.report-export-btns a.btn-secondary:hover { background: #4b5563; }
table { width: 100%; border-collapse: collapse; }
table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
table th { background: #f5f5f5; font-weight: 600; color: #333; }
table tr:hover { background: #f9f9f9; }
.badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.badge-success { background: #e8f5e9; color: #388e3c; }
.badge-warning { background: #fff3e0; color: #f57c00; }
.badge-danger { background: #ffebee; color: #d32f2f; }
.badge-info { background: #e3f2fd; color: #1976d2; }
.dashboard-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
.dashboard-card { background: #f8fafc; border-radius: 8px; padding: 16px; border-left: 4px solid #023A8D; }
.dashboard-card h4 { margin: 0 0 8px 0; font-size: 12px; color: #64748b; text-transform: uppercase; }
.dashboard-card .value { font-size: 24px; font-weight: 700; color: #111827; }
.chart-placeholder { min-height: 200px; background: #f8fafc; border-radius: 8px; padding: 20px; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 14px; }
@media print { .report-tabs, .report-export-btns, .report-period-nav a, .no-print { display: none !important; } }
</style>

<div class="content-header">
    <h2>Relatório de Produtividade</h2>
    <p>Período: <?= date('d/m/Y', strtotime($dataInicio)) ?> a <?= date('d/m/Y', strtotime($dataFim)) ?></p>
</div>

<div class="report-header">
    <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center; justify-content: space-between;">
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="<?= pixelhub_url('/agenda') ?>" class="btn btn-secondary">← Voltar para Agenda</a>
            <a href="<?= pixelhub_url('/agenda/stats?week_start=' . $dataInicio) ?>" class="btn btn-secondary">Resumo Semanal</a>
        </div>
    </div>
</div>

<!-- Abas -->
<div class="report-tabs">
    <a href="<?= buildReportUrl($baseUrl, ['tab' => 'dashboard']) ?>" class="<?= $tab === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
    <a href="<?= buildReportUrl($baseUrl, ['tab' => 'agenda']) ?>" class="<?= $tab === 'agenda' ? 'active' : '' ?>">Agenda (Tempo)</a>
    <a href="<?= buildReportUrl($baseUrl, ['tab' => 'tarefas']) ?>" class="<?= $tab === 'tarefas' ? 'active' : '' ?>">Tarefas (Entrega)</a>
    <a href="<?= buildReportUrl($baseUrl, ['tab' => 'export']) ?>" class="<?= $tab === 'export' ? 'active' : '' ?>">Exportação</a>
</div>

<!-- Topbar: período + filtros + export -->
<div class="report-topbar no-print">
    <div class="report-filters">
        <select class="period-select" onchange="applyFilter('period', this.value)">
            <option value="hoje" <?= $period === 'hoje' ? 'selected' : '' ?>>Hoje</option>
            <option value="semana" <?= $period === 'semana' ? 'selected' : '' ?>>Semana</option>
            <option value="mes" <?= $period === 'mes' ? 'selected' : '' ?>>Mês</option>
            <option value="ano" <?= $period === 'ano' ? 'selected' : '' ?>>Ano</option>
            <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Personalizado</option>
        </select>
        <?php if ($period === 'custom'): ?>
        <form method="get" action="<?= $baseUrl ?>" style="display: inline-flex; gap: 8px; align-items: center;">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <input type="hidden" name="period" value="custom">
            <label style="font-size: 12px; color: #64748b;">De</label>
            <input type="date" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>" style="padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px;">
            <label style="font-size: 12px; color: #64748b;">Até</label>
            <input type="date" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>" style="padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px;">
            <button type="submit" style="padding: 6px 14px; font-size: 13px; background: #023A8D; color: white; border: none; border-radius: 6px; cursor: pointer;">Aplicar</button>
            <?php foreach (['tipo', 'project_id', 'tenant_id', 'status', 'vinculada', 'activity_type'] as $fk): ?>
            <?php if (!empty($_GET[$fk])): ?><input type="hidden" name="<?= htmlspecialchars($fk) ?>" value="<?= htmlspecialchars($_GET[$fk]) ?>"><?php endif; ?>
            <?php endforeach; ?>
        </form>
        <?php endif; ?>
        <select onchange="applyFilter('tipo', this.value)">
            <option value="">Todos os tipos</option>
            <?php foreach ($tipos as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= ($filters['tipo_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <select onchange="applyFilter('project_id', this.value)">
            <option value="">Todos os projetos</option>
            <?php foreach ($projetos as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= ($filters['project_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select onchange="applyFilter('tenant_id', this.value)">
            <option value="">Todos os clientes</option>
            <?php foreach ($tenants as $tn): ?>
            <option value="<?= (int)$tn['id'] ?>" <?= ($filters['tenant_id'] ?? '') == $tn['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tn['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select onchange="applyFilter('status', this.value)">
            <option value="">Todos os status</option>
            <option value="planned" <?= ($filters['status'] ?? '') === 'planned' ? 'selected' : '' ?>>Planejado</option>
            <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Concluído</option>
            <option value="partial" <?= ($filters['status'] ?? '') === 'partial' ? 'selected' : '' ?>>Parcial</option>
            <option value="canceled" <?= ($filters['status'] ?? '') === 'canceled' ? 'selected' : '' ?>>Cancelado</option>
        </select>
    </div>
    <div class="report-export-btns">
        <?php $csvTab = in_array($tab, ['agenda', 'tarefas']) ? $tab : 'agenda'; ?>
        <a href="<?= buildReportUrl(pixelhub_url('/agenda/report-export-csv'), ['tab' => $csvTab, 'data_inicio' => $dataInicio, 'data_fim' => $dataFim]) ?>">Export CSV</a>
        <a href="<?= buildReportUrl(pixelhub_url('/agenda/report-export-pdf'), ['data_inicio' => $dataInicio, 'data_fim' => $dataFim]) ?>" class="btn-secondary">Export PDF</a>
    </div>
</div>

<?php if ($period === 'semana'): ?>
<div class="report-period-nav no-print" style="margin-bottom: 16px;">
    <a href="<?= buildReportUrl($baseUrl, ['data' => date('Y-m-d', strtotime($dataInicio . ' -7 days'))]) ?>">← Semana anterior</a>
    &nbsp;|&nbsp;
    <a href="<?= buildReportUrl($baseUrl, ['data' => date('Y-m-d')]) ?>">Hoje</a>
    &nbsp;|&nbsp;
    <a href="<?= buildReportUrl($baseUrl, ['data' => date('Y-m-d', strtotime($dataFim . ' +1 day'))]) ?>">Próxima semana →</a>
</div>
<?php endif; ?>

<?php if ($tab === 'dashboard'): ?>
<!-- Dashboard -->
<?php $dash = $report['dashboard'] ?? []; ?>
<div class="dashboard-cards">
    <div class="dashboard-card">
        <h4>Total de Horas</h4>
        <div class="value"><?= number_format($dash['total_horas'] ?? 0, 1, ',', '.') ?>h</div>
    </div>
    <?php if (!empty($dash['show_media_dia'])): ?>
    <div class="dashboard-card">
        <h4>Média por Dia</h4>
        <div class="value"><?= round(($dash['media_por_dia_min'] ?? 0) / 60, 1) ?>h</div>
    </div>
    <?php endif; ?>
    <div class="dashboard-card">
        <h4>Produção</h4>
        <div class="value"><?= round(($dash['producao_min'] ?? 0) / 60, 1) ?>h</div>
        <div style="font-size: 12px; color: #64748b;"><?= (int)($dash['producao_pct'] ?? 0) ?>%</div>
    </div>
    <div class="dashboard-card">
        <h4>Comercial</h4>
        <div class="value"><?= round(($dash['comercial_min'] ?? 0) / 60, 1) ?>h</div>
        <div style="font-size: 12px; color: #64748b;"><?= (int)($dash['comercial_pct'] ?? 0) ?>%</div>
    </div>
    <div class="dashboard-card">
        <h4>Pausas</h4>
        <div class="value"><?= round(($dash['pausas_min'] ?? 0) / 60, 1) ?>h</div>
        <div style="font-size: 12px; color: #64748b;"><?= (int)($dash['pausas_pct'] ?? 0) ?>%</div>
    </div>
    <?php if (!empty($dash['show_outros'])): ?>
    <div class="dashboard-card">
        <h4>Outros</h4>
        <div class="value"><?= round(($dash['outros_min'] ?? 0) / 60, 1) ?>h</div>
        <div style="font-size: 12px; color: #64748b;"><?= (int)($dash['outros_pct'] ?? 0) ?>%</div>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($dash['por_tipo_detalle'])): ?>
<div class="report-section">
    <h3>Horas por Tipo de Bloco</h3>
    <table>
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Blocos Total</th>
                <th>Horas Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dash['por_tipo_detalle'] as $item): ?>
            <tr>
                <td><strong><?= htmlspecialchars($item['tipo_nome']) ?></strong></td>
                <td><?= (int)$item['blocos_total'] ?></td>
                <td><strong><?= number_format((int)($item['minutos_total'] ?? 0) / 60, 1, ',', '.') ?>h</strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="report-section">
    <h3>Top Projetos (Tempo)</h3>
    <?php if (empty($dash['top_projetos'])): ?>
        <p>Nenhum bloco com projeto vinculado neste período.</p>
    <?php else: ?>
        <table style="width: auto;">
            <?php foreach ($dash['top_projetos'] as $nome => $min): ?>
            <tr><td><?= htmlspecialchars($nome) ?></td><td><strong><?= round($min / 60, 1) ?>h</strong></td></tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<div class="report-section">
    <h3>Top Atividades (Tempo)</h3>
    <?php if (empty($dash['top_atividades'])): ?>
        <p>Nenhum dado disponível.</p>
    <?php else: ?>
        <table style="width: auto;">
            <?php foreach ($dash['top_atividades'] as $nome => $min): ?>
            <tr><td><?= htmlspecialchars($nome) ?></td><td><strong><?= round($min / 60, 1) ?>h</strong></td></tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'agenda'): ?>
<!-- Aba Agenda (Tempo) -->
<div class="report-section">
    <h3>Itens Agendados</h3>
    <?php $itens = $report['itens_agenda'] ?? []; ?>
    <?php if (empty($itens)): ?>
        <p>Nenhum item de agenda neste período.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Início</th>
                    <th>Fim</th>
                    <th>Duração</th>
                    <th>Tipo</th>
                    <th>Categoria</th>
                    <th>Projeto</th>
                    <th>Cliente</th>
                    <th>Tarefa</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $r): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($r['data'])) ?></td>
                    <td><?= substr($r['hora_inicio'] ?? '', 0, 5) ?></td>
                    <td><?= substr($r['hora_fim'] ?? '', 0, 5) ?></td>
                    <td><?= (int)($r['duracao_min'] ?? 0) ?> min</td>
                    <td><?= htmlspecialchars($r['tipo_nome'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['categoria_atividade'] ?? 'Sem categoria') ?></td>
                    <td><?= htmlspecialchars($r['projeto_nome'] ?? ($r['resumo'] ?: '—')) ?></td>
                    <td><?= htmlspecialchars($r['cliente_nome'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($r['tarefa_titulo'] ?? '—') ?></td>
                    <td>
                        <?php $st = $r['status'] ?? ''; ?>
                        <span class="badge <?= $st === 'completed' ? 'badge-success' : ($st === 'partial' ? 'badge-warning' : ($st === 'canceled' ? 'badge-danger' : 'badge-info')) ?>">
                            <?= $st === 'completed' ? 'Concluído' : ($st === 'partial' ? 'Parcial' : ($st === 'canceled' ? 'Cancelado' : 'Planejado')) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'tarefas'): ?>
<!-- Aba Tarefas (Entrega) -->
<div class="report-section">
    <h3>Tarefas Concluídas</h3>
    <p style="font-size: 13px; color: #666; margin: 0 0 15px 0;">Tarefas concluídas no período, com indicação de vínculo com bloco de agenda.</p>
    <div class="report-filters no-print" style="margin-bottom: 12px;">
        <select onchange="applyFilter('vinculada', this.value)">
            <option value="">Todas</option>
            <option value="sim" <?= ($filters['vinculada'] ?? '') === 'sim' ? 'selected' : '' ?>>Vinculada a bloco</option>
            <option value="nao" <?= ($filters['vinculada'] ?? '') === 'nao' ? 'selected' : '' ?>>Não vinculada</option>
        </select>
    </div>
    <?php $tarefas = $report['tarefas_com_vinculo'] ?? []; ?>
    <?php if (empty($tarefas)): ?>
        <p>Nenhuma tarefa concluída neste período.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Tarefa</th>
                    <th>Projeto</th>
                    <th>Cliente</th>
                    <th>Concluída em</th>
                    <th>Por</th>
                    <th>Vinculada a bloco</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tarefas as $t): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($t['title']) ?></strong></td>
                    <td><?= htmlspecialchars($t['project_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($t['tenant_name'] ?? 'Interno') ?></td>
                    <td><?= $t['completed_at'] ? date('d/m/Y H:i', strtotime($t['completed_at'])) : '-' ?></td>
                    <td><?= htmlspecialchars($t['completed_by_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($t['vinculada_bloco'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'export'): ?>
<!-- Aba Exportação -->
<div class="report-section">
    <h3>Exportar Dados</h3>
    <p>Exporte o dataset filtrado da aba atual.</p>
    <div class="report-export-btns" style="margin-top: 16px;">
        <a href="<?= buildReportUrl(pixelhub_url('/agenda/report-export-csv'), ['tab' => 'agenda', 'data_inicio' => $dataInicio, 'data_fim' => $dataFim]) ?>">Export CSV (Agenda)</a>
        <a href="<?= buildReportUrl(pixelhub_url('/agenda/report-export-csv'), ['tab' => 'tarefas', 'data_inicio' => $dataInicio, 'data_fim' => $dataFim]) ?>">Export CSV (Tarefas)</a>
        <a href="<?= buildReportUrl(pixelhub_url('/agenda/report-export-pdf'), ['data_inicio' => $dataInicio, 'data_fim' => $dataFim]) ?>" class="btn-secondary">Export PDF (Dashboard)</a>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'dashboard' && !empty($report['blocos_cancelados'])): ?>
<div class="report-section">
    <h3>Blocos Cancelados</h3>
    <table>
        <thead>
            <tr><th>Data</th><th>Tipo</th><th>Motivo</th></tr>
        </thead>
        <tbody>
            <?php foreach ($report['blocos_cancelados'] as $item): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($item['data'])) ?></td>
                <td><?= htmlspecialchars($item['tipo_nome']) ?></td>
                <td><?= htmlspecialchars($item['motivo_cancelamento'] ?? 'Não informado') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="report-section" style="background: #e3f2fd; border-left: 4px solid #023A8D; padding: 15px 20px;">
    <h4 style="margin: 0 0 8px 0; color: #023A8D; font-size: 14px;">Como incluir tarefas no relatório</h4>
    <p style="margin: 0; font-size: 13px; color: #333; line-height: 1.5;">
        Tarefas concluídas no <strong>Quadro Kanban</strong> aparecem na aba "Tarefas (Entrega)".
        Para vincular a um bloco de agenda, use o botão "Agendar na Agenda" no modal da tarefa em <a href="<?= pixelhub_url('/agenda') ?>" style="color: #023A8D; font-weight: 600;">Minha Agenda</a>.
    </p>
</div>

<script>
function applyFilter(name, value) {
    const params = new URLSearchParams(window.location.search);
    if (value) params.set(name, value);
    else params.delete(name);
    if (name === 'period') {
        if (value === 'hoje') {
            params.delete('data_inicio');
            params.delete('data_fim');
            params.set('data', new Date().toISOString().slice(0,10));
        } else if (value === 'custom') {
            // Preservar data_inicio/data_fim ao selecionar Personalizado (não apagar)
            if (!params.has('data_inicio')) params.set('data_inicio', '<?= $dataInicio ?>');
            if (!params.has('data_fim')) params.set('data_fim', '<?= $dataFim ?>');
        } else {
            params.delete('data_inicio');
            params.delete('data_fim');
        }
    }
    window.location.href = '<?= $baseUrl ?>?' + params.toString();
}
</script>

<?php
$content = ob_get_clean();
$title = 'Relatório de Produtividade';
require __DIR__ . '/../layout/main.php';
?>
