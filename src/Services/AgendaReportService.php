<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Serviço isolado para relatórios de produtividade (Agenda + Tarefas).
 * Fonte principal: itens da agenda (agenda_blocks).
 * Suporta filtros e períodos flexíveis (diário, semanal, mensal, anual, custom).
 */
class AgendaReportService
{
    /**
     * Retorna itens da agenda no período com filtros aplicados.
     *
     * @param string $dataInicio Y-m-d (inclusive)
     * @param string $dataFim Y-m-d (inclusive)
     * @param array $filters ['tipo_id' => int, 'activity_type_id' => int, 'project_id' => int, 'tenant_id' => int, 'status' => string]
     * @return array Lista de itens com: data, hora_inicio, hora_fim, duracao_min, tipo_nome, atividade, projeto, cliente, tarefa, status
     */
    public static function getAgendaItemsForPeriod(string $dataInicio, string $dataFim, array $filters = []): array
    {
        $db = DB::getConnection();

        $params = [$dataInicio, $dataFim];
        $where = ["b.data BETWEEN ? AND ?"];

        if (!empty($filters['tipo_id'])) {
            $where[] = "b.tipo_id = ?";
            $params[] = (int)$filters['tipo_id'];
        }
        if (!empty($filters['activity_type_id'])) {
            $where[] = "b.activity_type_id = ?";
            $params[] = (int)$filters['activity_type_id'];
        }
        if (!empty($filters['project_id'])) {
            $where[] = "b.projeto_foco_id = ?";
            $params[] = (int)$filters['project_id'];
        }
        $hasTenantId = self::hasTenantIdColumn($db);
        if (!empty($filters['tenant_id']) && $hasTenantId) {
            $where[] = "(p.tenant_id = ? OR (b.projeto_foco_id IS NULL AND b.tenant_id = ?))";
            $params[] = (int)$filters['tenant_id'];
            $params[] = (int)$filters['tenant_id'];
        }
        if (!empty($filters['status'])) {
            $statusMap = ['completed' => 'completed', 'partial' => 'partial', 'canceled' => 'canceled', 'planned' => 'planned'];
            if (isset($statusMap[$filters['status']])) {
                $where[] = "b.status = ?";
                $params[] = $statusMap[$filters['status']];
            }
        }

        $whereClause = implode(' AND ', $where);

        $hasActivityTypes = self::hasActivityTypesColumn($db);
        $hasTenantId = self::hasTenantIdColumn($db);
        $activitySelect = $hasActivityTypes
            ? "COALESCE(at.name, '—') as atividade,"
            : "'—' as atividade,";

        $sql = "
            SELECT 
                b.id,
                b.data,
                b.hora_inicio,
                b.hora_fim,
                COALESCE(b.duracao_real, b.duracao_planejada, 
                    TIMESTAMPDIFF(MINUTE, CONCAT(b.data,' ',b.hora_inicio), CONCAT(b.data,' ',b.hora_fim))) as duracao_min,
                bt.nome as tipo_nome,
                bt.codigo as tipo_codigo,
                $activitySelect
                p.name as projeto_nome,
                COALESCE(tn_projeto.name, tn_block.name, 'Interno') as cliente_nome,
                t_focus.title as tarefa_titulo,
                b.status,
                b.resumo
            FROM agenda_blocks b
            INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
            LEFT JOIN projects p ON b.projeto_foco_id = p.id
            LEFT JOIN tenants tn_projeto ON p.tenant_id = tn_projeto.id
            " . ($hasTenantId ? "LEFT JOIN tenants tn_block ON b.tenant_id = tn_block.id" : "LEFT JOIN tenants tn_block ON 1=0") . "
            LEFT JOIN tasks t_focus ON b.focus_task_id = t_focus.id
        ";
        if ($hasActivityTypes) {
            $sql .= " LEFT JOIN activity_types at ON b.activity_type_id = at.id ";
        }
        $sql .= " WHERE $whereClause ORDER BY b.data ASC, b.hora_inicio ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * Dados para o Dashboard: cards + agregados para gráficos.
     */
    public static function getDashboardData(string $dataInicio, string $dataFim, array $filters = []): array
    {
        $items = self::getAgendaItemsForPeriod($dataInicio, $dataFim, $filters);

        $totalMinutos = 0;
        $porTipo = [];
        $porDia = [];
        $porProjeto = [];
        $planejado = 0;
        $executado = 0;

        foreach ($items as $r) {
            $min = (int)($r['duracao_min'] ?? 0);
            $totalMinutos += $min;

            $tipo = $r['tipo_nome'] ?? 'Outros';
            $porTipo[$tipo] = ($porTipo[$tipo] ?? 0) + $min;

            $dia = $r['data'] ?? '';
            if ($dia) {
                $porDia[$dia] = ($porDia[$dia] ?? 0) + $min;
            }

            $proj = $r['projeto_nome'] ?? ($r['resumo'] ? 'Atividade avulsa' : '—');
            $porProjeto[$proj] = ($porProjeto[$proj] ?? 0) + $min;

            $st = $r['status'] ?? '';
            if (in_array($st, ['planned'])) {
                $planejado += $min;
            } else {
                $executado += $min;
            }
        }

        $diasCount = count(array_unique(array_column($items, 'data'))) ?: 1;
        $mediaPorDia = $totalMinutos / $diasCount;

        // Top 10 projetos
        arsort($porProjeto);
        $topProjetos = array_slice(array_keys($porProjeto), 0, 10);

        return [
            'total_horas' => round($totalMinutos / 60, 1),
            'total_minutos' => $totalMinutos,
            'media_por_dia_min' => round($mediaPorDia),
            'por_tipo' => $porTipo,
            'por_dia' => $porDia,
            'por_projeto' => $porProjeto,
            'top_projetos' => $topProjetos,
            'planejado_min' => $planejado,
            'executado_min' => $executado,
        ];
    }

    /**
     * Tarefas concluídas no período com flag "vinculada a bloco".
     */
    public static function getTasksWithAgendaLink(string $dataInicio, string $dataFim, array $filters = []): array
    {
        $tasks = TaskService::getTasksCompletedInPeriod($dataInicio, $dataFim);
        $db = DB::getConnection();

        $result = [];
        foreach ($tasks as $t) {
            $taskId = (int)$t['id'];
            $stmt = $db->prepare("SELECT 1 FROM agenda_block_tasks abt INNER JOIN agenda_blocks b ON abt.bloco_id = b.id WHERE abt.task_id = ? AND b.data BETWEEN ? AND ? LIMIT 1");
            $stmt->execute([$taskId, $dataInicio, $dataFim]);
            $vinculada = $stmt->fetch() ? 'Sim' : 'Não';

            $result[] = array_merge($t, ['vinculada_bloco' => $vinculada]);
        }

        if (!empty($filters['vinculada'])) {
            if ($filters['vinculada'] === 'sim') {
                $result = array_filter($result, fn($r) => $r['vinculada_bloco'] === 'Sim');
            } elseif ($filters['vinculada'] === 'nao') {
                $result = array_filter($result, fn($r) => $r['vinculada_bloco'] === 'Não');
            }
        }
        return array_values($result);
    }

    /**
     * Retorna o relatório completo (compatível com getWeeklyReport) para um período arbitrário.
     */
    public static function getReportForPeriod(string $dataInicio, string $dataFim, array $filters = []): array
    {
        $report = AgendaService::getReportForDateRange($dataInicio, $dataFim);

        $items = self::getAgendaItemsForPeriod($dataInicio, $dataFim, $filters);
        $report['itens_agenda'] = $items;
        $report['dashboard'] = self::getDashboardData($dataInicio, $dataFim, $filters);
        $report['tarefas_com_vinculo'] = self::getTasksWithAgendaLink($dataInicio, $dataFim, $filters);

        return $report;
    }

    private static function hasActivityTypesColumn(\PDO $db): bool
    {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM agenda_blocks LIKE 'activity_type_id'");
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function hasTenantIdColumn(\PDO $db): bool
    {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM agenda_blocks LIKE 'tenant_id'");
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
