<?php
ob_start();
?>

<style>
    .report-header {
        background: white;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .report-section {
        background: white;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .report-section h3 {
        margin: 0 0 15px 0;
        color: #333;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 10px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    table th,
    table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e0e0e0;
    }
    table th {
        background: #f5f5f5;
        font-weight: 600;
        color: #333;
    }
    table tr:hover {
        background: #f9f9f9;
    }
    .badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .badge-success { background: #e8f5e9; color: #388e3c; }
    .badge-warning { background: #fff3e0; color: #f57c00; }
    .badge-danger { background: #ffebee; color: #d32f2f; }
</style>

<div class="content-header">
    <h2>Relatório Semanal de Produtividade</h2>
    <p>Período: <?= date('d/m/Y', strtotime($report['periodo']['inicio'])) ?> a <?= date('d/m/Y', strtotime($report['periodo']['fim'])) ?></p>
</div>

<div class="report-header" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center; justify-content: space-between;">
    <div style="display: flex; gap: 10px; align-items: center;">
        <a href="<?= pixelhub_url('/agenda?data=' . $report['periodo']['inicio']) ?>" class="btn btn-secondary">← Voltar para Agenda</a>
        <a href="<?= pixelhub_url('/agenda/stats?week_start=' . $report['periodo']['inicio']) ?>" class="btn btn-secondary">Resumo Semanal</a>
    </div>
    <div style="font-size: 13px; color: #666;">
        <a href="<?= pixelhub_url('/agenda/weekly-report?data=' . date('Y-m-d', strtotime($report['periodo']['inicio'] . ' -7 days'))) ?>">← Semana anterior</a>
        &nbsp;|&nbsp;
        <a href="<?= pixelhub_url('/agenda/weekly-report') ?>">Hoje</a>
        &nbsp;|&nbsp;
        <a href="<?= pixelhub_url('/agenda/weekly-report?data=' . date('Y-m-d', strtotime($report['periodo']['fim'] . ' +1 day'))) ?>">Próxima semana →</a>
    </div>
</div>

<div class="report-section" style="background: #e3f2fd; border-left: 4px solid #023A8D; padding: 15px 20px;">
    <h4 style="margin: 0 0 8px 0; color: #023A8D; font-size: 14px;">Como incluir tarefas no relatório</h4>
    <p style="margin: 0; font-size: 13px; color: #333; line-height: 1.5;">
        Tarefas concluídas no <strong>Quadro Kanban</strong> aparecem na seção "Tarefas concluídas por data de conclusão" abaixo.
        Para incluir na seção "Tarefas por tipo de bloco", vincule a tarefa a um bloco na <a href="<?= pixelhub_url('/agenda?data=' . $report['periodo']['inicio']) ?>" style="color: #023A8D; font-weight: 600;">Agenda</a> usando o botão "Agendar na Agenda" no modal da tarefa.
    </p>
</div>

<div class="report-section">
    <h3>Horas por Tipo de Bloco</h3>
    <?php if (empty($report['horas_por_tipo'])): ?>
        <p>Nenhum dado disponível para este período.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Blocos Total</th>
                    <th>Concluídos</th>
                    <th>Parciais</th>
                    <th>Cancelados</th>
                    <th>Minutos Total</th>
                    <th>Horas Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['horas_por_tipo'] as $item): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['nome']) ?></strong></td>
                        <td><?= (int)$item['blocos_total'] ?></td>
                        <td>
                            <span class="badge badge-success"><?= (int)$item['blocos_concluidos'] ?></span>
                        </td>
                        <td>
                            <span class="badge badge-warning"><?= (int)$item['blocos_parciais'] ?></span>
                        </td>
                        <td>
                            <span class="badge badge-danger"><?= (int)$item['blocos_cancelados'] ?></span>
                        </td>
                        <td><?= (int)$item['minutos_total'] ?> min</td>
                        <td><strong><?= number_format((int)$item['minutos_total'] / 60, 1, ',', '.') ?>h</strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="report-section">
    <h3>Tarefas Concluídas por Data de Conclusão</h3>
    <p style="font-size: 13px; color: #666; margin: 0 0 15px 0;">Todas as tarefas concluídas no período, independente de vínculo com blocos de agenda.</p>
    <?php 
    $tarefasPorData = $report['tarefas_concluidas_por_data'] ?? [];
    if (empty($tarefasPorData)): ?>
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
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tarefasPorData as $t): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($t['title']) ?></strong></td>
                        <td><?= htmlspecialchars($t['project_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($t['tenant_name'] ?? 'Interno') ?></td>
                        <td><?= $t['completed_at'] ? date('d/m/Y H:i', strtotime($t['completed_at'])) : '-' ?></td>
                        <td><?= htmlspecialchars($t['completed_by_name'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="report-section">
    <h3>Tarefas Concluídas por Tipo de Bloco</h3>
    <p style="font-size: 13px; color: #666; margin: 0 0 15px 0;">Tarefas vinculadas a blocos de agenda (concluídas nesta semana).</p>
    <?php if (empty($report['tarefas_por_tipo'])): ?>
        <p>Nenhuma tarefa vinculada a blocos concluída neste período.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Tarefas Concluídas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['tarefas_por_tipo'] as $item): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['nome']) ?></strong></td>
                        <td><?= (int)$item['tarefas_concluidas'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if (!empty($report['horas_por_projeto'])): ?>
    <div class="report-section">
        <h3>Horas por Projeto</h3>
        <table>
            <thead>
                <tr>
                    <th>Projeto</th>
                    <th>Minutos Total</th>
                    <th>Horas Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['horas_por_projeto'] as $item): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['projeto_nome']) ?></strong></td>
                        <td><?= (int)$item['minutos_total'] ?> min</td>
                        <td><strong><?= number_format((int)$item['minutos_total'] / 60, 1, ',', '.') ?>h</strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if (!empty($report['blocos_cancelados'])): ?>
    <div class="report-section">
        <h3>Blocos Cancelados</h3>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Motivo</th>
                </tr>
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

<?php
$content = ob_get_clean();
$title = 'Relatório Semanal';
require __DIR__ . '/../layout/main.php';
?>











