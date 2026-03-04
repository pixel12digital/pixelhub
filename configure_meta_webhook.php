<?php
/**
 * Script para configurar webhook da Meta WhatsApp Business API
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

Env::load();

echo "=== CONFIGURAR WEBHOOK META WHATSAPP ===\n\n";

// Busca configuração Meta
$db = DB::getConnection();
$stmt = $db->query("
    SELECT * FROM whatsapp_provider_configs 
    WHERE provider_type = 'meta_official' AND is_global = TRUE
    LIMIT 1
");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    echo "❌ Erro: Configuração Meta não encontrada\n";
    exit(1);
}

$accessToken = $config['meta_access_token'];
$verifyToken = $config['meta_webhook_verify_token'] ?: 'pixelhub_meta_webhook_2026';

// Descriptografa token se necessário
if (strpos($accessToken, 'encrypted:') === 0) {
    $accessToken = CryptoHelper::decrypt(substr($accessToken, 10));
}

// Dados do webhook
$appId = '920144551191818'; // Phone Number ID
$callbackUrl = 'https://hub.pixel12digital.com.br/api/whatsapp/meta/webhook';

echo "1. Configuração do Webhook:\n";
echo "   Callback URL: {$callbackUrl}\n";
echo "   Verify Token: {$verifyToken}\n\n";

echo "2. Configurando webhook via API...\n";

// Configura webhook
$url = "https://graph.facebook.com/v18.0/{$appId}/subscribed_apps";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$accessToken}",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Status: {$httpCode}\n\n";

$data = json_decode($response, true);

if ($httpCode === 200) {
    echo "✅ SUCESSO! Webhook configurado\n\n";
    echo "Resposta da Meta:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    echo "📋 Próximos passos:\n";
    echo "1. Acesse Meta Business Suite → WhatsApp → Configuration → Webhooks\n";
    echo "2. Verifique se o webhook está ativo\n";
    echo "3. Campos inscritos devem incluir: messages, message_status\n\n";
    
    echo "🧪 Para testar:\n";
    echo "   - Envie uma mensagem do seu celular para o número WhatsApp Business\n";
    echo "   - A mensagem deve aparecer no Inbox do PixelHub\n";
} else {
    echo "❌ ERRO ao configurar webhook\n\n";
    echo "Resposta da Meta:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    if (isset($data['error'])) {
        echo "Erro: {$data['error']['message']}\n";
        echo "Código: {$data['error']['code']}\n";
    }
    
    echo "\n⚠️  Configure manualmente no Meta Business Suite:\n";
    echo "   1. Acesse: https://business.facebook.com/\n";
    echo "   2. WhatsApp → Configuration → Webhooks\n";
    echo "   3. Callback URL: {$callbackUrl}\n";
    echo "   4. Verify Token: {$verifyToken}\n";
    echo "   5. Subscribe to: messages, message_status\n";
}

echo "\n=== FIM ===\n";
