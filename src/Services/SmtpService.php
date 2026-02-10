<?php

namespace PixelHub\Services;

use PixelHub\Core\Security;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Serviço para envio de e-mails via SMTP
 */
class SmtpService
{
    private array $settings;

    public function __construct(array $settings = null)
    {
        if ($settings) {
            $this->settings = $settings;
        } else {
            // Carrega do banco se não fornecido
            $db = \PixelHub\Core\DB::getConnection();
            $stmt = $db->query("SELECT * FROM smtp_settings WHERE smtp_enabled = 1 LIMIT 1");
            $this->settings = $stmt->fetch() ?: [];
        }
    }

    /**
     * Envia email usando SMTP ou fallback para mail()
     */
    public function send(string $to, string $subject, string $body, bool $isHtml = true): bool
    {
        // Se SMTP não estiver configurado, usa fallback
        if (empty($this->settings) || !$this->settings['smtp_enabled']) {
            return $this->sendFallback($to, $subject, $body, $isHtml);
        }

        try {
            $mail = $this->createMailer();
            
            $mail->setFrom(
                $this->settings['smtp_from_email'],
                $this->settings['smtp_from_name']
            );
            $mail->addAddress($to);
            $mail->Subject = $subject;
            
            if ($isHtml) {
                $mail->isHTML(true);
                $mail->Body = $body;
                $mail->AltBody = strip_tags($body);
            } else {
                $mail->Body = $body;
            }

            return $mail->send();
        } catch (Exception $e) {
            error_log("SMTPService: Falha ao enviar email para {$to}: " . $e->getMessage());
            
            // Fallback para mail() nativo
            return $this->sendFallback($to, $subject, $body, $isHtml);
        }
    }

    /**
     * Envia email de teste
     */
    public function sendTest(string $to): bool
    {
        $subject = '📧 Teste de Configuração SMTP - PixelHub';
        $body = $this->getTestEmailTemplate();
        
        return $this->send($to, $subject, $body, true);
    }

    /**
     * Cria instância do PHPMailer com configurações SMTP
     */
    private function createMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        
        // Configurações SMTP
        $mail->isSMTP();
        $mail->Host = $this->settings['smtp_host'];
        $mail->Port = (int) $this->settings['smtp_port'];
        $mail->Username = $this->settings['smtp_username'];
        $mail->Password = $this->settings['smtp_password'];
        
        // Criptografia
        switch ($this->settings['smtp_encryption']) {
            case 'tls':
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                break;
            case 'ssl':
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                break;
            case 'none':
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
                break;
        }
        
        $mail->SMTPAuth = true;
        $mail->CharSet = 'UTF-8';
        
        // Timeout
        $mail->Timeout = 30;
        
        return $mail;
    }

    /**
     * Fallback usando mail() nativo do PHP
     */
    private function sendFallback(string $to, string $subject, string $body, bool $isHtml): bool
    {
        $fromEmail = $this->settings['smtp_from_email'] ?? 'noreply@pixel12digital.com.br';
        $fromName = $this->settings['smtp_from_name'] ?? 'Pixel12 Digital';
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: ' . ($isHtml ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8'),
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
        ];
        
        $headersString = implode("\r\n", $headers);
        
        return @mail($to, $subject, $isHtml ? $body : strip_tags($body), $headersString);
    }

    /**
     * Template HTML para email de teste
     */
    private function getTestEmailTemplate(): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Teste SMTP</title>
        </head>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: #023A8D; color: white; padding: 20px; text-align: center;">
                <h1 style="margin: 0;">📧 PixelHub</h1>
                <p style="margin: 5px 0 0 0;">Teste de Configuração SMTP</p>
            </div>
            
            <div style="padding: 30px; background: #f8f9fa;">
                <h2 style="color: #333;">✅ Configuração SMTP Funcionando!</h2>
                <p style="color: #666; line-height: 1.6;">
                    Este é um email de teste para confirmar que as configurações SMTP estão 
                    funcionando corretamente no sistema PixelHub.
                </p>
                
                <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h3 style="color: #023A8D; margin-top: 0;">Detalhes do Teste:</h3>
                    <ul style="color: #666;">
                        <li><strong>Servidor:</strong> ' . htmlspecialchars($this->settings['smtp_host']) . '</li>
                        <li><strong>Porta:</strong> ' . $this->settings['smtp_port'] . '</li>
                        <li><strong>Criptografia:</strong> ' . strtoupper($this->settings['smtp_encryption']) . '</li>
                        <li><strong>Data/Hora:</strong> ' . date('d/m/Y H:i:s') . '</li>
                    </ul>
                </div>
                
                <p style="color: #666;">
                    Se você recebeu este email, sua configuração SMTP está pronta para uso!
                </p>
            </div>
            
            <div style="background: #333; color: white; padding: 20px; text-align: center; font-size: 12px;">
                <p style="margin: 0;">Este é um email automático do PixelHub - Painel Central</p>
                <p style="margin: 5px 0 0 0;">© ' . date('Y') . ' Pixel12 Digital</p>
            </div>
        </body>
        </html>';
    }
}
