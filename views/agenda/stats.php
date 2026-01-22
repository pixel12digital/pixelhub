<?php
ob_start();
?>

<style>
    .stats-header {
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #e0e0e0;
    }
    .stats-title {
        font-size: 24px;
        font-weight: 600;
        color: #023A8D;
        margin-bottom: 8px;
    }
    .stats-subtitle {
        font-size: 16px;
        color: #666;
        margin-bottom: 20px;
    }
    .stats-navigation {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 20px;
    }
    .stats-navigation a {
        padding: 8px 16px;
        background: #023A8D;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        font-weight: 600;
        transition: background 0.3s;
    }
    .stats-navigation a:hover {
        background: #0354b8;
    }
    .stats-navigation .current-week {
        padding: 8px 16px;
        background: #f5f5f5;
        color: #666;
        text-decoration: none;
        border-radius: 4px;
        font-weight: 600;
    }
    .stats-summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .summary-card {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .summary-card-title {
        font-size: 14px;
        color: #666;
        margin-bottom: 8px;
        font-weight: 600;
    }
    .summary-card-value {
        font-size: 28px;
        font-weight: 700;
        color: #023A8D;
    }
    .summary-card-subtitle {
        font-size: 12px;
        color: #999;
        margin-top: 4px;
    }
    .stats-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .stats-table thead {
        background: #023A8D;
        color: white;
    }
    .stats-table th {
        padding: 15px;
        text-align: left;
        font-weight: 600;
        font-size: 14px;
    }
    .stats-table td {
        padding: 15px;
        border-bottom: 1px solid #e0e0e0;
    }
    .stats-table tbody tr:hover {
        background: #f9f9f9;
    }
    .stats-table tbody tr:last-child td {
        border-bottom: none;
    }
    .type-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .type-color-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
    }
    .type-name {
        font-weight: 600;
        color: #333;
    }
    .type-code {
        font-size: 12px;
        color: #666;
        font-weight: normal;
    }
    .hours-cell {
        font-weight: 600;
        color: #333;
    }
    .percent-cell {
        font-weight: 600;
    }
    .percent-high {
        color: #4CAF50;
    }
    .percent-medium {
        color: #FF9800;
    }
    .percent-low {
        color: #999;
    }
    .no-data-message {
        text-align: center;
        padding: 40px;
        color: #666;
        font-size: 16px;
    }
</style>

<div class="stats-header">
    <h1 class="stats-title">Resumo Semanal da Agenda</h1>
    <p class="stats-subtitle">Semana de <?= htmlspecialchars($week_start_formatted) ?> a <?= htmlspecialchars($week_end_formatted) ?></p>
    
    <div class="stats-navigation">
        <a href="<?= htmlspecialchars($prev_week_url) ?>">← Semana Anterior</a>
        <a href="<?= htmlspecialchars($current_week_url) ?>">Esta Semana</a>
        <a href="<?= htmlspecialchars($next_week_url) ?>">Próxima Semana →</a>
    </div>
</div>

<?php if (empty($stats_by_type)): ?>
    <div class="no-data-message">
        <p>Nenhum bloco cadastrado nesta semana.</p>
        <p style="font-size: 14px; margin-top: 10px; color: #999;">Gere blocos na <a href="<?= pixelhub_url('/agenda') ?>">Agenda diária</a> para ver as estatísticas.</p>
    </div>
<?php else: ?>
    <!-- Resumo Geral -->
    <div class="stats-summary-cards">
        <div class="summary-card">
            <div class="summary-card-title">Total de Horas em Blocos</div>
            <div class="summary-card-value"><?= number_format($summary_totals['total_hours'], 1, ',', '.') ?>h</div>
            <div class="summary-card-subtitle"><?= $summary_totals['total_blocks'] ?> blocos cadastrados</div>
        </div>
        
        <div class="summary-card">
            <div class="summary-card-title">Horas Ocupadas</div>
            <div class="summary-card-value" style="color: #4CAF50;"><?= number_format($summary_totals['occupied_hours'], 1, ',', '.') ?>h</div>
            <div class="summary-card-subtitle"><?= $summary_totals['occupied_blocks'] ?> blocos com tarefas</div>
        </div>
        
        <div class="summary-card">
            <div class="summary-card-title">Horas Livres</div>
            <div class="summary-card-value" style="color: #999;"><?= number_format($summary_totals['free_hours'], 1, ',', '.') ?>h</div>
            <div class="summary-card-subtitle"><?= $summary_totals['free_blocks'] ?> blocos disponíveis</div>
        </div>
        
        <div class="summary-card">
            <div class="summary-card-title">Ocupação Geral</div>
            <div class="summary-card-value" style="color: <?= $summary_totals['occupancy_percent'] >= 70 ? '#4CAF50' : ($summary_totals['occupancy_percent'] >= 40 ? '#FF9800' : '#999') ?>;">
                <?= number_format($summary_totals['occupancy_percent'], 1, ',', '.') ?>%
            </div>
            <div class="summary-card-subtitle">Taxa de utilização</div>
        </div>
    </div>
    
    <!-- Tabela por Tipo de Bloco -->
    <table class="stats-table">
        <thead>
            <tr>
                <th>Tipo de Bloco</th>
                <th style="text-align: right;">Horas Totais</th>
                <th style="text-align: right;">Horas Ocupadas</th>
                <th style="text-align: right;">Horas Livres</th>
                <th style="text-align: right;">% Ocupação</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stats_by_type as $stat): ?>
                <?php
                $percentClass = 'percent-low';
                if ($stat['occupancy_percent'] >= 70) {
                    $percentClass = 'percent-high';
                } elseif ($stat['occupancy_percent'] >= 40) {
                    $percentClass = 'percent-medium';
                }
                ?>
                <tr>
                    <td>
                        <div class="type-badge">
                            <span class="type-color-dot" style="background: <?= htmlspecialchars($stat['type_color']) ?>;"></span>
                            <span>
                                <span class="type-name"><?= htmlspecialchars($stat['type_name']) ?></span>
                                <span class="type-code">(<?= htmlspecialchars($stat['type_code']) ?>)</span>
                            </span>
                        </div>
                    </td>
                    <td style="text-align: right;" class="hours-cell">
                        <?= number_format($stat['total_hours'], 1, ',', '.') ?>h
                        <span style="font-size: 12px; color: #999; font-weight: normal;">(<?= $stat['total_blocks'] ?> blocos)</span>
                    </td>
                    <td style="text-align: right;" class="hours-cell" style="color: #4CAF50;">
                        <?= number_format($stat['occupied_hours'], 1, ',', '.') ?>h
                        <span style="font-size: 12px; color: #999; font-weight: normal;">(<?= $stat['occupied_blocks'] ?> blocos)</span>
                    </td>
                    <td style="text-align: right;" class="hours-cell" style="color: #999;">
                        <?= number_format($stat['free_hours'], 1, ',', '.') ?>h
                        <span style="font-size: 12px; color: #999; font-weight: normal;">(<?= $stat['free_blocks'] ?> blocos)</span>
                    </td>
                    <td style="text-align: right;" class="percent-cell <?= $percentClass ?>">
                        <?= number_format($stat['occupancy_percent'], 1, ',', '.') ?>%
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
/**
 * AUDITORIA DE PERFORMANCE - Resumo Semanal (/agenda/stats)
 * 
 * Alterações realizadas em 2025-01-25:
 * 
 * 1. Método getWeeklyStats() reescrito para usar queries vetorizadas:
 *    - Query única para buscar todos os blocos da semana (com filtro WHERE data BETWEEN)
 *    - Query vetorizada para buscar blocos com tarefas (usando IN com lista de IDs)
 *    - Processamento em PHP em vez de subqueries EXISTS dentro de agregações
 * 
 * 2. Índices verificados e confirmados:
 *    - agenda_blocks: idx_data, idx_tipo_id, idx_status (já existiam)
 *    - agenda_block_tasks: idx_bloco_id (já existia)
 * 
 * 3. Medição de tempo adicionada temporariamente para debug:
 *    - Logs de tempo de cada query e processamento total
 *    - Verificar logs do servidor para tempos de execução
 * 
 * Tempo médio esperado: < 50ms para semanas com até 100 blocos
 */
$content = ob_get_clean();
$title = 'Resumo Semanal da Agenda';
require __DIR__ . '/../layout/main.php';
?>


