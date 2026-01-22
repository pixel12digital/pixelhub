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
    <h2>Relatório Mensal de Produtividade</h2>
    <p>Mês: <?= str_pad($mes, 2, '0', STR_PAD_LEFT) ?>/<?= $ano ?></p>
</div>

<div class="report-header">
    <a href="<?= pixelhub_url('/agenda') ?>" class="btn btn-secondary">← Voltar para Agenda</a>
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
    <h3>Tarefas Concluídas por Tipo de Bloco</h3>
    <?php if (empty($report['tarefas_por_tipo'])): ?>
        <p>Nenhuma tarefa concluída neste período.</p>
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
$title = 'Relatório Mensal';
require __DIR__ . '/../layout/main.php';
?>











