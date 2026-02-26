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
     * Envia novo email
     * POST /inbox/emails/send
     */
    public function sendEmail(): void
    {
        Auth::requireInternal();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $tenantId = $input['tenant_id'] ?? null;
        $subject = trim($input['subject'] ?? '');
        $message = trim($input['message'] ?? '');
        
        if (!$tenantId || !$subject || !$message) {
            $this->json(['success' => false, 'error' => 'Campos obrigatórios: tenant_id, subject, message']);
            return;
        }
        
        $db = DB::getConnection();
        
        try {
            // Busca dados do tenant
            $stmt = $db->prepare("SELECT name, email FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$tenant) {
                $this->json(['success' => false, 'error' => 'Cliente não encontrado']);
                return;
            }
            
            if (!$tenant['email']) {
                $this->json(['success' => false, 'error' => 'Cliente não possui email cadastrado']);
                return;
            }
            
            // Busca configuração SMTP da tabela smtp_settings
            $stmt = $db->query("SELECT * FROM smtp_settings WHERE smtp_enabled = 1 LIMIT 1");
            $smtp = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
            
            if (!$smtp || !$smtp['smtp_host'] || !$smtp['smtp_username'] || !$smtp['smtp_password']) {
                $this->json(['success' => false, 'error' => 'Configuração SMTP não encontrada ou incompleta. Configure em /settings/smtp']);
                return;
            }
            
            $smtpHost = $smtp['smtp_host'];
            $smtpPort = $smtp['smtp_port'] ?: 587;
            $smtpUser = $smtp['smtp_username'];
            $smtpPassword = $smtp['smtp_password'];
            $smtpFromName = $smtp['smtp_from_name'] ?: 'Pixel12 Digital';
            $smtpFromEmail = $smtp['smtp_from_email'] ?: $smtpUser;
            $smtpEncryption = $smtp['smtp_encryption'] ?: 'tls';
            
            // Converte mensagem para HTML
            $messageHtml = nl2br(htmlspecialchars($message));
            
            // Assinatura profissional em HTML (otimizada para deliverability)
            $signatureHtml = '
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <p style="margin: 0; line-height: 1.6; color: #333;">
                        Atenciosamente,<br><br>
                        <strong>Charles Dietrich</strong><br>
                        <span style="color: #666;">Consultor em Transformação Digital</span><br>
                        <strong>Pixel12 Digital</strong>
                    </p>
                    <p style="margin: 15px 0 0 0; line-height: 1.6; color: #666; font-size: 14px;">
                        WhatsApp: (47) 99730-9525<br>
                        pixel12digital.com.br<br>
                        contato@pixel12digital.com.br
                    </p>
                </div>
            ';
            
            // Monta email HTML completo
            $emailBody = '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                </head>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
                    ' . $messageHtml . '
                    ' . $signatureHtml . '
                </body>
                </html>
            ';
            
            // Envia email via PHPMailer
            require_once __DIR__ . '/../../vendor/autoload.php';
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPassword;
            $mail->SMTPSecure = strtolower($smtpEncryption) === 'ssl' 
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS 
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtpPort;
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            
            $mail->setFrom($smtpFromEmail, $smtpFromName);
            $mail->addAddress($tenant['email'], $tenant['name']);
            $mail->Subject = $subject;
            $mail->Body = $emailBody;
            $mail->AltBody = $message . "\n\n---\n\nAtenciosamente,\n\nCharles Dietrich\nConsultor em Transformação Digital\nPixel12 Digital\n\nWhatsApp: (47) 99730-9525\nSite: https://pixel12digital.com.br\nEmail: contato@pixel12digital.com.br";
            
            $mail->send();
            
            // Registra em billing_notifications
            $stmt = $db->prepare("
                INSERT INTO billing_notifications 
                (tenant_id, channel, status, message, sent_at, created_at) 
                VALUES (?, 'email_smtp', 'sent', ?, NOW(), NOW())
            ");
            $stmt->execute([$tenantId, $message]);
            
            $this->json([
                'success' => true,
                'message' => 'Email enviado com sucesso'
            ]);
            
        } catch (\Exception $e) {
            error_log('[InboxEmail] Erro ao enviar email: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'error' => 'Erro ao enviar email',
                'debug' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Busca clientes e leads por nome ou email (autocomplete)
     * GET /inbox/emails/search-recipients?q={query}
     */
    public function searchRecipients(): void
    {
        Auth::requireInternal();
        
        $query = trim($_GET['q'] ?? '');
        
        if (strlen($query) < 3) {
            $this->json(['success' => true, 'recipients' => []]);
            return;
        }
        
        $db = DB::getConnection();
        $recipients = [];
        
        try {
            // Busca clientes (tenants) com email
            $stmt = $db->prepare("
                SELECT 
                    id,
                    name,
                    email,
                    'tenant' as type
                FROM tenants 
                WHERE (name LIKE ? OR email LIKE ?) 
                  AND email IS NOT NULL 
                  AND email != ''
                ORDER BY name
                LIMIT 20
            ");
            $searchTerm = "%{$query}%";
            $stmt->execute([$searchTerm, $searchTerm]);
            $tenants = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($tenants as $t) {
                $recipients[] = [
                    'id' => $t['id'],
                    'name' => $t['name'],
                    'email' => $t['email'],
                    'type' => 'tenant',
                    'label' => $t['name'] . ' (' . $t['email'] . ')'
                ];
            }
            
            // Busca leads com email
            $stmt = $db->prepare("
                SELECT 
                    id,
                    name,
                    email,
                    'lead' as type
                FROM leads 
                WHERE (name LIKE ? OR email LIKE ?) 
                  AND email IS NOT NULL 
                  AND email != ''
                ORDER BY name
                LIMIT 20
            ");
            $stmt->execute([$searchTerm, $searchTerm]);
            $leads = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($leads as $l) {
                $recipients[] = [
                    'id' => $l['id'],
                    'name' => $l['name'],
                    'email' => $l['email'],
                    'type' => 'lead',
                    'label' => $l['name'] . ' (' . $l['email'] . ') - Lead'
                ];
            }
            
            $this->json([
                'success' => true,
                'recipients' => $recipients,
                'count' => count($recipients)
            ]);
            
        } catch (\Exception $e) {
            error_log('[InboxEmail] Erro ao buscar destinatários: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'error' => 'Erro ao buscar destinatários',
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
