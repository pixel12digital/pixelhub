<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para gerar tarefas automáticas relacionadas a financeiro/inadimplência
 */
class FinancialTaskService
{
    /**
     * Gera tarefas para inadimplentes
     * 
     * @param int $diasAtrasoMinimo Mínimo de dias em atraso para gerar tarefa (padrão: 7)
     * @return array Estatísticas ['created' => int, 'skipped' => int]
     */
    public static function generateTasksForOverdue(int $diasAtrasoMinimo = 7): array
    {
        $db = DB::getConnection();
        
        // Busca tenants com faturas vencidas há mais de X dias
        $stmt = $db->prepare("
            SELECT DISTINCT
                t.id as tenant_id,
                t.name as tenant_name,
                COUNT(DISTINCT bi.id) as qtd_faturas_vencidas,
                SUM(bi.amount) as valor_total_vencido,
                MAX(bi.due_date) as ultima_fatura_vencida
            FROM tenants t
            INNER JOIN billing_invoices bi ON t.id = bi.tenant_id
            WHERE bi.status = 'overdue'
            AND (bi.is_deleted IS NULL OR bi.is_deleted = 0)
            AND DATEDIFF(CURDATE(), bi.due_date) >= ?
            AND t.status = 'active'
            GROUP BY t.id, t.name
            HAVING qtd_faturas_vencidas > 0
        ");
        $stmt->execute([$diasAtrasoMinimo]);
        $inadimplentes = $stmt->fetchAll();
        
        $created = 0;
        $skipped = 0;
        
        foreach ($inadimplentes as $inadimplente) {
            $tenantId = (int)$inadimplente['tenant_id'];
            
            // Verifica se já existe tarefa financeira recente para este tenant
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM tasks t
                INNER JOIN projects p ON t.project_id = p.id
                WHERE p.tenant_id = ?
                AND t.task_type = 'finance_overdue'
                AND t.status != 'concluida'
                AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$tenantId]);
            $existe = $stmt->fetch();
            
            if ($existe && (int)$existe['count'] > 0) {
                $skipped++;
                continue;
            }
            
            // Busca ou cria projeto financeiro para o tenant
            $projectId = self::getOrCreateFinancialProject($tenantId);
            
            if (!$projectId) {
                $skipped++;
                continue;
            }
            
            // Cria tarefa
            $taskData = [
                'project_id' => $projectId,
                'title' => "Inadimplência: {$inadimplente['tenant_name']} - R$ " . number_format($inadimplente['valor_total_vencido'], 2, ',', '.'),
                'description' => "Cliente com {$inadimplente['qtd_faturas_vencidas']} fatura(s) vencida(s) há mais de {$diasAtrasoMinimo} dias.\n\n" .
                               "Valor total vencido: R$ " . number_format($inadimplente['valor_total_vencido'], 2, ',', '.') . "\n" .
                               "Última fatura vencida: " . date('d/m/Y', strtotime($inadimplente['ultima_fatura_vencida'])),
                'task_type' => 'finance_overdue',
                'status' => 'backlog',
                'created_by' => null, // Sistema
            ];
            
            try {
                $taskId = TaskService::createTask($taskData);
                
                // REMOVIDO: Vínculo automático com Agenda
                // Agora as tarefas financeiras só são vinculadas manualmente via:
                // - Botão "Agendar na Agenda" no modal da tarefa
                // - Botão "Vincular tarefa existente" dentro do bloco
                
                $created++;
            } catch (\Exception $e) {
                error_log("Erro ao criar tarefa de inadimplência para tenant {$tenantId}: " . $e->getMessage());
                $skipped++;
            }
        }
        
        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }
    
    /**
     * Busca ou cria projeto financeiro para um tenant
     * 
     * @param int $tenantId ID do tenant
     * @return int|null ID do projeto ou null se erro
     */
    private static function getOrCreateFinancialProject(int $tenantId): ?int
    {
        $db = DB::getConnection();
        
        // Busca projeto financeiro existente
        $stmt = $db->prepare("
            SELECT id FROM projects 
            WHERE tenant_id = ? 
            AND (name LIKE '%Financeiro%' OR name LIKE '%Cobrança%' OR name LIKE '%Inadimplência%')
            AND status = 'ativo'
            LIMIT 1
        ");
        $stmt->execute([$tenantId]);
        $project = $stmt->fetch();
        
        if ($project) {
            return (int)$project['id'];
        }
        
        // Busca tenant para pegar o nome
        $stmt = $db->prepare("SELECT name FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();
        
        if (!$tenant) {
            return null;
        }
        
        // Cria projeto financeiro
        $projectData = [
            'tenant_id' => $tenantId,
            'name' => 'Financeiro - ' . $tenant['name'],
            'description' => 'Projeto para gerenciar questões financeiras e inadimplência',
            'type' => 'cliente',
            'status' => 'ativo',
            'priority' => 'alta',
            'created_by' => null,
        ];
        
        try {
            return ProjectService::createProject($projectData);
        } catch (\Exception $e) {
            error_log("Erro ao criar projeto financeiro para tenant {$tenantId}: " . $e->getMessage());
            return null;
        }
    }
}

