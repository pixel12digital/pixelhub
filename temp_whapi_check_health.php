<?php
require_once __DIR__ . '/vendor/autoload.php';
use PixelHub\Core\Env;
Env::load();

$cfg = require __DIR__ . '/config/database.php';
$pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8", $cfg['username'], $cfg['password']);

$row = $pdo->query("SELECT * FROM whatsapp_provider_configs LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "Colunas: " . implode(', ', array_keys($row)) . "\n";
if (!$row) { die("Token não encontrado no banco\n"); }

// Decrypt token
$encryptedToken = $row['whapi_api_token'];
$appKey = Env::get('APP_KEY');
$decoded = base64_decode($encryptedToken, true);
$iv = substr($decoded, 0, 16);
$cipher = substr($decoded, 16);
$token = openssl_decrypt($cipher, 'AES-256-CBC', substr(hash('sha256', $appKey, true), 0, 32), OPENSSL_RAW_DATA, $iv);

if (!$token) {
    // Try without encryption
    $token = $encryptedToken;
}

echo "Token usado: " . substr($token, 0, 10) . "...\n\n";

// Call checkHealth
$ch = curl_init('https://gate.whapi.cloud/health');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT => 15,
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $httpCode\n";
$data = json_decode($resp, true);
echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";

if (!empty($data['channel_info']['ip'])) {
    echo "\n=== IP DO CANAL WHAPI: " . $data['channel_info']['ip'] . " ===\n";
} elseif (!empty($data['ip'])) {
    echo "\n=== IP DO CANAL WHAPI: " . $data['ip'] . " ===\n";
} else {
    echo "\nRaw response: $resp\n";
}
