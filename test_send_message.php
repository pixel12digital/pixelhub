<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

$db = DB::getConnection();

echo "=== TESTANDO ENVIO DE MENSAGEM ===\n";

// Pegar configuração da sessão orsegups
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

echo "Canal: {$channelId}\n";
echo "Token: " . substr($token, 0, 20) . "...\n\n";

// Enviar mensagem de teste
$url = "https://gate.whapi.cloud/messages/text";
$message = [
    'to' => '5547991953981',  // O mesmo número da mensagem original
    'body' => '🧪 Mensagem de teste - Canal orsegups configurado!'
];

$headers = [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Enviando mensagem de teste...\n";
echo "HTTP Status: {$httpCode}\n";
echo "Resposta: " . substr($response, 0, 500) . "...\n\n";

$data = json_decode($response, true);
if ($httpCode === 200 && isset($data['sent']) && $data['sent'] === true) {
    echo "✅ Mensagem enviada com sucesso!\n";
    echo "Message ID: " . ($data['message']['id'] ?? 'N/A') . "\n";
    echo "Status: " . ($data['message']['status'] ?? 'N/A') . "\n";
    
    // Aguardar 2 segundos e consultar status
    sleep(2);
    
    $statusUrl = "https://gate.whapi.cloud/messages/" . ($data['message']['id'] ?? '');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $statusUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $statusResponse = curl_exec($ch);
    curl_close($ch);
    
    $statusData = json_decode($statusResponse, true);
    if ($statusData && isset($statusData['status'])) {
        echo "Status da mensagem: " . $statusData['status'] . "\n";
    }
} else {
    echo "❌ Erro ao enviar mensagem\n";
}

echo "\n=== FIM ===\n";
