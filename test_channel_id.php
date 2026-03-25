<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

$db = DB::getConnection();

echo "=== TESTANDO CHANNEL ID ===\n";

// Pegar token da sessão orsegups
$stmt = $db->prepare("
    SELECT whapi_api_token, whapi_channel_id 
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'whapi' AND session_name = 'orsegups'
");
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    echo "❌ Configuração não encontrada!\n";
    exit;
}

// Descriptografar token
$apiToken = $config['whapi_api_token'];
if (!empty($apiToken) && strpos($apiToken, 'encrypted:') === 0) {
    $token = CryptoHelper::decrypt(substr($apiToken, 10));
} else {
    $token = $apiToken;
}

$channelId = $config['whapi_channel_id'];

echo "Channel ID: {$channelId}\n";
echo "Token: " . substr($token, 0, 20) . "...\n\n";

// Testar endpoint com o Channel ID
$url = "https://gate.whapi.cloud/messages/{$channelId}";
$headers = [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Testando endpoint: {$url}\n";
echo "HTTP Status: {$httpCode}\n";
echo "Resposta: " . substr($response, 0, 300) . "...\n\n";

// Se der 404, tentar descobrir o formato correto
if ($httpCode === 404) {
    echo "❌ Channel ID não encontrado. Possíveis causas:\n";
    echo "1. O Channel ID pode estar em formato diferente\n";
    echo "2. Pode precisar de prefixo como 'channel_'\n";
    echo "3. O token pode ser de outro canal\n\n";
    
    echo "Sugestões para testar:\n";
    echo "- channel_{$channelId}\n";
    echo "- GRNARN_TK5RD (com underline)\n";
    echo "- Verificar no painel se o ID está exatamente como mostrado\n";
}

echo "\n=== FIM ===\n";
