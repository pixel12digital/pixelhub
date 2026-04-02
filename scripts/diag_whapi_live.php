<?php
require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'], $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== whatsapp_provider_configs ===" . PHP_EOL;
foreach ($pdo->query("SELECT id, tenant_id, provider_type, is_active, created_at,
    LEFT(whapi_api_token, 30) as token_prefix,
    whapi_channel_id, whapi_phone_number
    FROM whatsapp_provider_configs ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    foreach ($r as $k => $v) echo "  {$k} = {$v}" . PHP_EOL;
    echo "---" . PHP_EOL;
}

echo PHP_EOL . "=== tenant_message_channels ===" . PHP_EOL;
foreach ($pdo->query("SELECT id, tenant_id, channel_name, provider, provider_type, is_active, whatsapp_number
    FROM tenant_message_channels ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    foreach ($r as $k => $v) echo "  {$k} = {$v}" . PHP_EOL;
    echo "---" . PHP_EOL;
}

echo PHP_EOL . "=== Últimos 5 eventos de falha ou webhook de status Whapi ===" . PHP_EOL;
$rows = $pdo->query("
    SELECT id, created_at, source, LEFT(payload,300) as payload_preview
    FROM webhook_raw_logs
    WHERE source LIKE '%whapi%' OR source LIKE '%status%'
    ORDER BY created_at DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
if ($rows) {
    foreach ($rows as $r) {
        echo "  [{$r['created_at']}] source={$r['source']}" . PHP_EOL;
        echo "  payload=" . $r['payload_preview'] . PHP_EOL . "---" . PHP_EOL;
    }
} else {
    echo "  (nenhum)" . PHP_EOL;
}

echo PHP_EOL . "=== Últimos eventos com erro no inbox (hoje) ===" . PHP_EOL;
$rows2 = $pdo->query("
    SELECT id, created_at, event_type, source_system,
           LEFT(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.error')), 200) as error,
           JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.delivery_uncertain')) as uncertain
    FROM communication_events
    WHERE DATE(created_at) = CURDATE()
      AND (
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.error')) IS NOT NULL
        OR JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.delivery_uncertain')) = 'true'
        OR status IN ('failed', 'error')
      )
    ORDER BY created_at DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
if ($rows2) {
    foreach ($rows2 as $r) {
        echo "  [{$r['created_at']}] {$r['event_type']} | uncertain={$r['uncertain']} | error={$r['error']}" . PHP_EOL;
    }
} else {
    echo "  (nenhum erro registrado hoje)" . PHP_EOL;
}

// Agora testa a API do Whapi diretamente
echo PHP_EOL . "=== Teste live Whapi API ===" . PHP_EOL;
$tokenRow = $pdo->query("SELECT whapi_api_token FROM whatsapp_provider_configs WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$tokenRow || empty($tokenRow['whapi_api_token'])) {
    echo "  ERRO: Nenhum token Whapi ativo encontrado no banco!" . PHP_EOL;
} else {
    $token = $tokenRow['whapi_api_token'];
    // Descriptografa se necessário
    if (strpos($token, 'encrypted:') === 0) {
        require_once __DIR__ . '/../src/Core/CryptoHelper.php';
        $token = \PixelHub\Core\CryptoHelper::decrypt(substr($token, 10));
    }
    echo "  Token prefix: " . substr($token, 0, 20) . "..." . PHP_EOL;

    // Chama GET /health na API do Whapi para checar status da sessão
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://gate.whapi.cloud/health',
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    echo "  GET /health → HTTP {$code}" . PHP_EOL;
    if ($err) echo "  cURL error: {$err}" . PHP_EOL;
    echo "  Response: " . substr($body, 0, 400) . PHP_EOL;

    // Testa também GET /settings para ver status do canal/sessão
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL => 'https://gate.whapi.cloud/settings',
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body2 = curl_exec($ch2);
    $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    echo PHP_EOL . "  GET /settings → HTTP {$code2}" . PHP_EOL;
    echo "  Response: " . substr($body2, 0, 600) . PHP_EOL;
}
