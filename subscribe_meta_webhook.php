<?php
/**
 * Script para subscrever app ao webhook Meta via API
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

Env::load();

echo "=== SUBSCREVER APP AO WEBHOOK META ===\n\n";

$db = DB::getConnection();
$stmt = $db->query("
    SELECT * FROM whatsapp_provider_configs 
    WHERE provider_type = 'meta_official' AND is_global = TRUE
    LIMIT 1
");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    echo "❌ Configuração Meta não encontrada\n";
    exit(1);
}

$accessToken = $config['meta_access_token'];

// Descriptografa token
if (strpos($accessToken, 'encrypted:') === 0) {
    $accessToken = CryptoHelper::decrypt(substr($accessToken, 10));
}

// WhatsApp Business Account ID (WABA ID)
$wabaId = $config['meta_business_account_id'];

echo "1. Subscrevendo app ao webhook...\n";
echo "   WABA ID: {$wabaId}\n\n";

// Subscreve app ao webhook
$url = "https://graph.facebook.com/v18.0/{$wabaId}/subscribed_apps";

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
    echo "✅ SUCESSO! App subscrita ao webhook\n\n";
    echo "Resposta da Meta:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    echo "🎉 Agora o webhook deve receber mensagens!\n\n";
    echo "Teste:\n";
    echo "1. Envie mensagem para +55 47 9647-4223\n";
    echo "2. Execute: php check_meta_messages.php\n";
} else {
    echo "❌ ERRO ao subscrever app\n\n";
    echo "Resposta da Meta:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    if (isset($data['error'])) {
        echo "Erro: {$data['error']['message']}\n";
        echo "Código: {$data['error']['code']}\n";
    }
}

echo "\n=== FIM ===\n";
