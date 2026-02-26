<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Auth;
use PixelHub\Core\DB;

/**
 * Controller para Inbox de Emails
 * Gerencia visualização de emails enviados via sistema de cobrança
 */
class InboxEmailController
{
    /**
     * Lista emails agrupados por tenant (conversas de email)
     * GET /inbox/emails
     */
    public function listEmails(): void
    {
        Auth::requireInternal();
        
        $tenantId = $_GET['tenant_id'] ?? null;
        $status = $_GET['status'] ?? 'all';
        
        $db = DB::getConnection();
        
        try {
            // Busca emails de billing_notifications agrupados por tenant
            $sql = "
                SELECT 
                    bn.tenant_id,
                    t.name as tenant_name,
                    COUNT(*) as email_count,
                    MAX(bn.created_at) as last_email_at,
                    SUM(CASE WHEN bn.status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                    SUM(CASE WHEN bn.status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                    MAX(bn.id) as latest_email_id
                FROM billing_notifications bn
                INNER JOIN tenants t ON bn.tenant_id = t.id
                WHERE bn.channel LIKE 'email%'
            ";
            
            $params = [];
            
            if ($tenantId) {
                $sql .= " AND bn.tenant_id = ?";
                $params[] = $tenantId;
            }
            
            $sql .= " GROUP BY bn.tenant_id, t.name ORDER BY last_email_at DESC LIMIT 100";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $conversations = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->json([
                'success' => true,
                'conversations' => $conversations,
                'count' => count($conversations)
            ]);
            
        } catch (\Exception $e) {
            error_log('[InboxEmail] Erro ao listar emails: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'error' => 'Erro ao carregar emails'
            ]);
        }
    }
    
    /**
     * Lista emails de um tenant específico
     * GET /inbox/emails/thread
     */
    public function getEmailThread(): void
    {
        Auth::requireInternal();
        
        $tenantId = $_GET['tenant_id'] ?? null;
        
        if (!$tenantId) {
            $this->json(['success' => false, 'error' => 'tenant_id obrigatório']);
            return;
        }
        
        $db = DB::getConnection();
        
        try {
            // Busca todos os emails do tenant
            $stmt = $db->prepare("
                SELECT 
                    bn.id,
                    bn.tenant_id,
                    bn.invoice_id,
                    bn.channel,
                    bn.status,
                    bn.message as message_text,
                    bn.created_at,
                    bn.sent_at,
                    bn.last_error,
                    bn.gateway_message_id,
                    bi.amount as invoice_amount,
                    bi.due_date as invoice_due_date,
                    bi.status as invoice_status,
                    t.name as tenant_name,
                    t.email as tenant_email
                FROM billing_notifications bn
                LEFT JOIN billing_invoices bi ON bn.invoice_id = bi.id
                INNER JOIN tenants t ON bn.tenant_id = t.id
                WHERE bn.tenant_id = ? AND bn.channel LIKE 'email%'
                ORDER BY bn.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$tenantId]);
            $emails = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->json([
                'success' => true,
                'emails' => $emails,
                'count' => count($emails)
            ]);
            
        } catch (\Exception $e) {
            error_log('[InboxEmail] Erro ao buscar thread: ' . $e->getMessage());
            error_log('[InboxEmail] Stack trace: ' . $e->getTraceAsString());
            $this->json([
                'success' => false,
                'error' => 'Erro ao carregar emails',
                'debug' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Retorna JSON response
     */
    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
