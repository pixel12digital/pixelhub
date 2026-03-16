<?php
require_once __DIR__ . '/vendor/autoload.php';
use PixelHub\Core\Env;
use PixelHub\Core\CryptoHelper;
Env::load();

$cfg = require __DIR__ . '/config/database.php';
$pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8", $cfg['username'], $cfg['password']);
$row = $pdo->query("SELECT whapi_api_token FROM whatsapp_provider_configs WHERE provider_type='whapi' AND is_global=1 AND is_active=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$token = CryptoHelper::decrypt(substr($row['whapi_api_token'], 10));
$headers = ['Authorization: Bearer ' . $token, 'Accept: application/json'];

function whapiGet($url, $headers) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_HTTPHEADER=>$headers]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "  HTTP $code: " . substr($body, 0, 200) . "\n";
    return json_decode($body, true);
}

$chatId = '5511940863773@s.whatsapp.net';

echo "Teste A: GET /chats/{chatId} (info do chat)\n";
whapiGet('https://gate.whapi.cloud/chats/' . urlencode($chatId), $headers);

echo "\nTeste B: GET /messages?count=3&chat_id={chatId}\n";
whapiGet('https://gate.whapi.cloud/messages?count=3&chat_id=' . urlencode($chatId), $headers);

echo "\nTeste C: GET /messages?count=3&chatId={chatId}\n";
whapiGet('https://gate.whapi.cloud/messages?count=3&chatId=' . urlencode($chatId), $headers);

echo "\nTeste D: GET /chats com detalhes do primeiro chat\n";
$data = whapiGet('https://gate.whapi.cloud/chats?count=1', $headers);
echo "Full first chat: " . json_encode($data['chats'][0] ?? [], JSON_PRETTY_PRINT) . "\n";
