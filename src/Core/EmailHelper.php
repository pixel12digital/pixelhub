<?php

namespace PixelHub\Core;

/**
 * Helper simples para envio de e-mails
 */
class EmailHelper
{
    /**
     * Envia um e-mail simples usando mail() nativo do PHP
     * 
     * @param string $to E-mail do destinatário
     * @param string $subject Assunto do e-mail
     * @param string $message Corpo do e-mail (texto ou HTML)
     * @param string $from E-mail do remetente (opcional)
     * @return bool True se enviado com sucesso, false caso contrário
     */
    public static function send(string $to, string $subject, string $message, ?string $from = null): bool
    {
        if (empty($to)) {
            error_log("EmailHelper: E-mail do destinatário vazio");
            return false;
        }

        // Define remetente padrão se não fornecido
        if (empty($from)) {
            $from = Env::get('MAIL_FROM') ?: 'noreply@pixel12digital.com.br';
        }

        // Headers do e-mail
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from,
            'Reply-To: ' . $from,
        ];

        $headersString = implode("\r\n", $headers);

        // Tenta enviar o e-mail
        $result = @mail($to, $subject, $message, $headersString);

        if (!$result) {
            error_log("EmailHelper: Falha ao enviar e-mail para {$to}");
            return false;
        }

        error_log("EmailHelper: E-mail enviado com sucesso para {$to}");
        return true;
    }

    /**
     * Envia e-mail de aviso de vencimento de domínio
     * 
     * @param array $tenant Dados do tenant (nome, email)
     * @param array $hostingAccount Dados da conta de hospedagem (domain, domain_expiration_date)
     * @param int $daysLeft Dias restantes até o vencimento
     * @return bool True se enviado com sucesso
     */
    public static function sendDomainExpirationWarning(array $tenant, array $hostingAccount, int $daysLeft): bool
    {
        $tenantName = $tenant['name'] ?? 'Cliente';
        $tenantEmail = $tenant['email'] ?? null;
        $domain = $hostingAccount['domain'] ?? 'domínio não informado';
        $expirationDate = $hostingAccount['domain_expiration_date'] ?? null;

        if (empty($tenantEmail)) {
            error_log("EmailHelper: Tenant {$tenantName} não possui e-mail cadastrado");
            return false;
        }

        // Formata data de vencimento
        $expirationFormatted = 'Data não informada';
        if ($expirationDate) {
            try {
                $date = new \DateTime($expirationDate);
                $expirationFormatted = $date->format('d/m/Y');
            } catch (\Exception $e) {
                $expirationFormatted = $expirationDate;
            }
        }

        $subject = "⚠️ Aviso: Domínio {$domain} vence em {$daysLeft} dias";

        // Corpo do e-mail em HTML
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #023A8D; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-top: none; }
                .warning { background: #fff3cd; border-left: 4px solid #F7931E; padding: 15px; margin: 20px 0; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0;'>Pixel Hub - Aviso de Vencimento</h1>
                </div>
                <div class='content'>
                    <p>Olá, <strong>{$tenantName}</strong>!</p>
                    
                    <div class='warning'>
                        <h2 style='margin-top: 0; color: #856404;'>⚠️ Atenção: Vencimento de Domínio</h2>
                        <p style='margin-bottom: 0;'><strong>Domínio:</strong> {$domain}</p>
                        <p style='margin-bottom: 0;'><strong>Data de Vencimento:</strong> {$expirationFormatted}</p>
                        <p style='margin-bottom: 0;'><strong>Dias Restantes:</strong> {$daysLeft} dia(s)</p>
                    </div>
                    
                    <p>Este é um aviso automático para informar que o domínio <strong>{$domain}</strong> está próximo do vencimento.</p>
                    
                    <p><strong>Por favor, providencie a renovação do domínio o quanto antes para evitar que o site fique fora do ar.</strong></p>
                    
                    <p>Se você já renovou o domínio, pode ignorar este e-mail.</p>
                    
                    <p>Atenciosamente,<br><strong>Equipe Pixel12 Digital</strong></p>
                </div>
                <div class='footer'>
                    <p>Este é um e-mail automático. Por favor, não responda.</p>
                    <p>Pixel Hub - Painel Central da Pixel12 Digital</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return self::send($tenantEmail, $subject, $message);
    }
}

