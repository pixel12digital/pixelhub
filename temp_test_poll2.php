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

// Simula o poll com sinceTs=0 (busca tudo)
$ch = curl_init('https://gate.whapi.cloud/chats?count=10');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_HTTPHEADER=>$headers]);
$body = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$chatsData = json_decode($body, true);
$chats = $chatsData['chats'] ?? [];
echo "Chats encontrados: " . count($chats) . "\n\n";

$messages = [];
foreach ($chats as $chat) {
    $chatId = $chat['id'] ?? null;
    if (!$chatId || strpos($chatId, '@g.us') !== false) continue;
    $lastMsg = $chat['last_message'] ?? null;
    if (!$lastMsg) continue;
    $messages[] = $lastMsg;
}

echo "Mensagens para processar: " . count($messages) . "\n\n";
foreach ($messages as $m) {
    echo "  id=" . ($m['id'] ?? '?') . "\n";
    echo "  from_me=" . ($m['from_me'] ? 'true' : 'false') . "\n";
    echo "  type=" . ($m['type'] ?? '?') . "\n";
    echo "  from=" . ($m['from'] ?? '?') . "\n";
    echo "  chat_id=" . ($m['chat_id'] ?? '?') . "\n";
    echo "  ts=" . ($m['timestamp'] ?? '?') . "\n";
    echo "  body=" . substr($m['text']['body'] ?? '', 0, 50) . "\n";
    echo "  ---\n";
}
