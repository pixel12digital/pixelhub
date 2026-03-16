<?php
require_once __DIR__ . '/vendor/autoload.php';
use PixelHub\Core\Env;
use PixelHub\Core\CryptoHelper;
Env::load();

$cfg = require __DIR__ . '/config/database.php';
$pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8", $cfg['username'], $cfg['password']);
$row = $pdo->query("SELECT whapi_api_token FROM whatsapp_provider_configs WHERE provider_type='whapi' AND is_global=1 AND is_active=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$token = CryptoHelper::decrypt(substr($row['whapi_api_token'], 10));

// Testa envio de texto para o número do Marcos (sem enviar de verdade — usa número de teste)
$testPhone = '5548993580049'; // Marcos Vinicius

$payload = json_encode([
    'to' => $testPhone,
    'body' => 'teste de diagnóstico - pode ignorar'
]);

echo "Testando POST /messages/text para $testPhone...\n";
$ch = curl_init('https://gate.whapi.cloud/messages/text');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
    ],
]);
$body = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP: $httpCode\n";
if ($err) echo "cURL error: $err\n";
echo "Response: " . $body . "\n";
$data = json_decode($body, true);
if (isset($data['error'])) {
    $errObj = $data['error'];
    echo "\nERRO WHAPI:\n";
    echo "  code: " . ($errObj['code'] ?? '?') . "\n";
    echo "  message: " . ($errObj['message'] ?? '?') . "\n";
    echo "  details: " . ($errObj['details'] ?? '?') . "\n";
}
