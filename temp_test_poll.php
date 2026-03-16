<?php
require_once __DIR__ . '/vendor/autoload.php';
use PixelHub\Core\Env;
use PixelHub\Core\CryptoHelper;
Env::load();

$cfg = require __DIR__ . '/config/database.php';
$pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8", $cfg['username'], $cfg['password']);

// Verifica CRON_SECRET
$cronSecret = Env::get('CRON_SECRET', '');
echo "CRON_SECRET vazio? " . (empty($cronSecret) ? "SIM (sem proteção)" : "NÃO (precisa de token)") . "\n\n";

// Busca token Whapi
$row = $pdo->query("SELECT whapi_api_token FROM whatsapp_provider_configs WHERE provider_type='whapi' AND is_global=1 AND is_active=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$row) { die("❌ Config Whapi não encontrada\n"); }

$token = $row['whapi_api_token'];
echo "Token raw (início): " . substr($token, 0, 20) . "...\n";

if (strpos($token, 'encrypted:') === 0) {
    $decrypted = CryptoHelper::decrypt(substr($token, 10));
    echo "Token descriptografado: " . ($decrypted ? substr($decrypted, 0, 10) . "..." : "❌ FALHOU") . "\n\n";
    $token = $decrypted;
} else {
    echo "Token sem criptografia\n\n";
}

if (!$token) { die("❌ Token vazio após descriptografia\n"); }

// Testa GET /chats
echo "Chamando GET https://gate.whapi.cloud/chats?count=5...\n";
$ch = curl_init('https://gate.whapi.cloud/chats?count=5');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
]);
$body = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP: $httpCode\n";
if ($err) echo "cURL error: $err\n";
$data = json_decode($body, true);
$chats = $data['chats'] ?? [];
echo "Total chats: " . count($chats) . "\n";
foreach (array_slice($chats, 0, 3) as $chat) {
    echo "  Chat: " . ($chat['id'] ?? '?') . " last_msg_ts: " . ($chat['last_message']['timestamp'] ?? '?') . "\n";
}

// Testa GET /messages/{chatId} para o primeiro chat
if (!empty($chats[0]['id'])) {
    $chatId = $chats[0]['id'];
    echo "\nChamando GET /messages/{$chatId}?count=3...\n";
    $ch2 = curl_init('https://gate.whapi.cloud/messages/' . urlencode($chatId) . '?count=3');
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
    ]);
    $body2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    echo "HTTP: $httpCode2\n";
    $msgs = json_decode($body2, true);
    echo "Messages count: " . count($msgs['messages'] ?? []) . "\n";
    foreach (($msgs['messages'] ?? []) as $m) {
        echo "  msg id=" . ($m['id'] ?? '?') . " from_me=" . ($m['from_me'] ? 'true' : 'false') . " ts=" . ($m['timestamp'] ?? '?') . "\n";
    }
}
