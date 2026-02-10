<?php
spl_autoload_register(function($c){
    if(strncmp('PixelHub\\', $c, 9) === 0){
        $f = __DIR__ . '/src/' . str_replace('\\', '/', substr($c, 9)) . '.php';
        if(file_exists($f)) require $f;
    }
});
PixelHub\Core\Env::load();

use PixelHub\Services\SmtpService;
use PixelHub\Core\DB;

echo "Enviando email de teste para Charles Dietrich...\n";

try {
    // Busca configurações SMTP
    $db = DB::getConnection();
    $stmt = $db->query("SELECT * FROM smtp_settings WHERE smtp_enabled = 1 LIMIT 1");
    $settings = $stmt->fetch();
    
    if (!$settings) {
        echo "ERRO: SMTP não está configurado ou está desativado.\n";
        exit(1);
    }
    
    echo "Configurações SMTP encontradas:\n";
    echo "- Host: {$settings['smtp_host']}\n";
    echo "- Porta: {$settings['smtp_port']}\n";
    echo "- Usuário: {$settings['smtp_username']}\n";
    echo "- Criptografia: {$settings['smtp_encryption']}\n";
    echo "- Remetente: {$settings['smtp_from_name']} <{$settings['smtp_from_email']}>\n\n";
    
    // Cria serviço SMTP
    $smtpService = new SmtpService($settings);
    
    // Envia email para Charles Dietrich
    $toEmail = 'charles.dietrich@example.com'; // Substituir pelo email real
    $subject = 'Teste SMTP - PixelHub';
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Teste SMTP</title>
    </head>
    <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: #023A8D; color: white; padding: 20px; text-align: center;">
            <h1 style="margin: 0;">📧 PixelHub</h1>
            <p style="margin: 5px 0 0 0;">Teste de Configuração SMTP via SSH</p>
        </div>
        
        <div style="padding: 30px; background: #f8f9fa;">
            <h2 style="color: #333;">✅ Configuração SMTP Funcionando!</h2>
            <p style="color: #666; line-height: 1.6;">
                Este é um email de teste enviado via linha de comando (SSH) para confirmar 
                que as configurações SMTP estão funcionando corretamente no sistema PixelHub.
            </p>
            
            <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: #023A8D; margin-top: 0;">Detalhes do Teste:</h3>
                <ul style="color: #666;">
                    <li><strong>Servidor:</strong> ' . htmlspecialchars($settings['smtp_host']) . '</li>
                    <li><strong>Porta:</strong> ' . $settings['smtp_port'] . '</li>
                    <li><strong>Criptografia:</strong> ' . strtoupper($settings['smtp_encryption']) . '</li>
                    <li><strong>Data/Hora:</strong> ' . date('d/m/Y H:i:s') . '</li>
                    <li><strong>Enviado por:</strong> Linha de comando (SSH)</li>
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
    
    echo "Enviando email para: $toEmail\n";
    $result = $smtpService->send($toEmail, $subject, $body, true);
    
    if ($result) {
        echo "✅ SUCESSO: Email enviado com sucesso para $toEmail\n";
    } else {
        echo "❌ FALHA: Não foi possível enviar o email\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nProcesso concluído.\n";
?>
