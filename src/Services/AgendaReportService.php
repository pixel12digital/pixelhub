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
            ? "COALESCE(NULLIF(TRIM(at.name), ''), 'Sem categoria') as categoria_atividade,"
            : "'Sem categoria' as categoria_atividade,";

        $sql = "
            SELECT 
                b.id,
                b.data,
                b.hora_inicio,
                b.hora_fim,
                COALESCE(b.duracao_real, b.duracao_planejada, 
                    TIMESTAMPDIFF(MINUTE, CONCAT(b.data,' ',b.hora_inicio), CONCAT(b.data,' ',b.hora_fim))) as duracao_min,
                bt.nome as tipo_nome,
                COALESCE(bt.codigo, bt.nome, '') as tipo_codigo,
                $activitySelect
                b.projeto_foco_id,
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

        foreach ($rows as &$r) {
            $cat = trim($r['categoria_atividade'] ?? '');
            if (!$cat || $cat === 'Sem categoria') {
                $codigo = self::normalizeTipoBloco($r['tipo_codigo'] ?? '');
                // Blocos de Produção sem categoria recebem "Desenvolvimento" (nunca "Sem categoria" dominante)
                if (in_array($codigo, ['CLIENTES', 'FUTURE', 'SUPORTE', 'PRODUCAO'])) {
                    $r['categoria_atividade'] = 'Desenvolvimento';
                } else {
                    $r['categoria_atividade'] = 'Sem categoria';
                }
            }
        }
        return $rows;
    }

    /**
     * Normaliza tipo_bloco bruto para comparação consistente.
     * Usado por cards e tabela "Horas por Tipo de Bloco".
     * Regras: trim, upper, remover acentos, mapear variações.
     */
    private static function normalizeTipoBloco(?string $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }
        $s = trim($raw);
        $s = mb_strtoupper($s, 'UTF-8');
        // Remover acentos (PRODUÇÃO → PRODUCAO, etc.)
        $s = str_replace(
            ['Á', 'À', 'Ã', 'Â', 'É', 'Ê', 'Í', 'Ó', 'Ô', 'Õ', 'Ú', 'Ç'],
            ['A', 'A', 'A', 'A', 'E', 'E', 'I', 'O', 'O', 'O', 'U', 'C'],
            $s
        );
        // Mapear variações conhecidas para forma canônica (evitar cair em Outros)
        $variacoes = [
            'PRODUÇÃO' => 'PRODUCAO',
            'PROD' => 'PRODUCAO',
            'PROJETO' => 'PRODUCAO',
            'PROJETOS' => 'PRODUCAO',
            'CLIENTE' => 'CLIENTES',
            'PAUSA' => 'PAUSAS',
            'PESSOAL' => 'PESSOAL',
            'ADMIN' => 'ADMIN',
            'ADMINISTRATIVO' => 'ADMIN',
        ];
        return $variacoes[$s] ?? $s;
    }

    /**
     * Mapeia codigo do tipo de bloco (normalizado) para bucket analítico.
     * Cards e tabela usam APENAS tipo_bloco, nunca categoria_atividade.
     */
    private static function mapTipoToBucket(string $codigo): string
    {
        $codigo = self::normalizeTipoBloco($codigo);
        if (in_array($codigo, ['CLIENTES', 'FUTURE', 'SUPORTE', 'PRODUCAO'])) {
            return 'Produção';
        }
        if (in_array($codigo, ['COMERCIAL', 'FLEX'])) {
            return 'Comercial';
        }
        if (in_array($codigo, ['PESSOAL', 'ADMIN'])) {
            return 'Pausas';
        }
        return 'Outros';
    }

    /**
     * Dados para o Dashboard: cards + agregados.
     * Total = soma durações (end-start). Média/dia = total / dias_com_blocos.
     * Sem Planejado/Executado. Rankings separados: Top Projetos vs Top Atividades.
     */
    public static function getDashboardData(string $dataInicio, string $dataFim, array $filters = []): array
    {
        $items = self::getAgendaItemsForPeriod($dataInicio, $dataFim, $filters);

        // Log de sanidade (temporário): período 03/02/2026 — confirmar tipo_bloco no dataset
        if ($dataInicio === '2026-02-03' || $dataFim === '2026-02-03') {
            $sample = array_slice($items, 0, 5);
            foreach ($sample as $i => $r) {
                error_log(sprintf(
                    '[AgendaReport] sanidade 03/02: item[%d] id=%s start=%s end=%s duracao=%d tipo_codigo_raw=%s tipo_nome=%s categoria=%s projeto_id=%s',
                    $i,
                    $r['id'] ?? '?',
                    ($r['data'] ?? '') . ' ' . ($r['hora_inicio'] ?? ''),
                    ($r['data'] ?? '') . ' ' . ($r['hora_fim'] ?? ''),
                    (int)($r['duracao_min'] ?? 0),
                    var_export($r['tipo_codigo'] ?? null, true),
                    $r['tipo_nome'] ?? '?',
                    $r['categoria_atividade'] ?? '?',
                    $r['projeto_foco_id'] ?? '?'
                ));
            }
        }

        $totalMinutos = 0;
        $porTipo = [];
        $porDia = [];
        $porProjeto = [];   // só projeto_id != null
        $porAtividade = []; // por categoria_atividade (nunca — nem Atividade avulsa)
        $producaoMin = 0;
        $comercialMin = 0;
        $pausasMin = 0;
        $outrosMin = 0;

        $porBucketBlocos = ['Produção' => 0, 'Comercial' => 0, 'Pausas' => 0, 'Outros' => 0];

        foreach ($items as $r) {
            $min = (int)($r['duracao_min'] ?? 0);
            $totalMinutos += $min;

            $codigo = $r['tipo_codigo'] ?? '';
            $bucket = self::mapTipoToBucket($codigo);
            $porTipo[$bucket] = ($porTipo[$bucket] ?? 0) + $min;
            $porBucketBlocos[$bucket] = ($porBucketBlocos[$bucket] ?? 0) + 1;

            $dia = $r['data'] ?? '';
            if ($dia) {
                $porDia[$dia] = ($porDia[$dia] ?? 0) + $min;
            }

            if ($bucket === 'Produção') $producaoMin += $min;
            elseif ($bucket === 'Comercial') $comercialMin += $min;
            elseif ($bucket === 'Pausas') $pausasMin += $min;
            else $outrosMin += $min;

            // Top Projetos: apenas itens com projeto_id e que NÃO sejam Pausas
            if ($bucket !== 'Pausas' && !empty($r['projeto_foco_id']) && !empty($r['projeto_nome'])) {
                $porProjeto[$r['projeto_nome']] = ($porProjeto[$r['projeto_nome']] ?? 0) + $min;
            }

            // Top Atividades: por categoria_atividade; nunca —, Atividade avulsa, Sem categoria
            $cat = trim($r['categoria_atividade'] ?? '');
            $cat = $cat ?: 'Sem categoria';
            if (!in_array($cat, ['—', 'Atividade avulsa', 'Sem categoria'], true)) {
                $porAtividade[$cat] = ($porAtividade[$cat] ?? 0) + $min;
            }
        }

        $diasComBlocos = count($porDia) ?: 1;
        $mediaPorDia = $totalMinutos / $diasComBlocos;
        $isSingleDay = ($dataInicio === $dataFim);

        arsort($porProjeto);
        arsort($porAtividade);
        $topProjetos = array_slice($porProjeto, 0, 10, true);
        $topAtividades = array_slice($porAtividade, 0, 10, true);

        $pct = $totalMinutos > 0 ? fn($m) => round($m * 100 / $totalMinutos, 1) : 0;

        // Outros: ocultar se residual (<=0.3h ou <=5%) — indicador de dado mal classificado
        $outrosHoras = $outrosMin / 60;
        $showOutros = $outrosMin > 0 && ($outrosHoras > 0.3 || $pct($outrosMin) > 5);

        // por_tipo_detalle: buckets (Produção/Comercial/Pausas/Outros) para bater com os cards
        $porTipoDetalle = [];
        foreach (['Produção', 'Comercial', 'Pausas', 'Outros'] as $b) {
            $min = $porTipo[$b] ?? 0;
            if ($min > 0 && ($b !== 'Outros' || $showOutros)) {
                $porTipoDetalle[] = [
                    'tipo_nome' => $b,
                    'blocos_total' => $porBucketBlocos[$b] ?? 0,
                    'minutos_total' => $min,
                ];
            }
        }

        return [
            'total_horas' => round($totalMinutos / 60, 1),
            'total_minutos' => $totalMinutos,
            'media_por_dia_min' => round($mediaPorDia),
            'show_media_dia' => !$isSingleDay,
            'por_tipo' => $porTipo,
            'por_tipo_detalle' => $porTipoDetalle,
            'por_dia' => $porDia,
            'top_projetos' => $topProjetos,
            'top_atividades' => $topAtividades,
            'producao_min' => $producaoMin,
            'comercial_min' => $comercialMin,
            'pausas_min' => $pausasMin,
            'outros_min' => $outrosMin,
            'show_outros' => $showOutros,
            'producao_pct' => $pct($producaoMin),
            'comercial_pct' => $pct($comercialMin),
            'pausas_pct' => $pct($pausasMin),
            'outros_pct' => $pct($outrosMin),
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
