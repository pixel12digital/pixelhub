<?php
require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'], $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== Sessões Whapi configuradas ===" . PHP_EOL;
$rows = $pdo->query("
    SELECT id, tenant_id, provider_type, session_name, is_active, is_global,
           LEFT(whapi_api_token, 25) as token_prefix,
           whapi_channel_id, whapi_phone_number
    FROM whatsapp_provider_configs
    WHERE provider_type = 'whapi'
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    foreach ($r as $k => $v) echo "  {$k} = {$v}" . PHP_EOL;
    echo "---" . PHP_EOL;
}

echo PHP_EOL . "=== Canais WhatsApp ativos ===" . PHP_EOL;
$rows2 = $pdo->query("
    SELECT id, tenant_id, channel_name, provider, provider_type, is_active, is_enabled,
           whatsapp_number, session_id
    FROM tenant_message_channels
    WHERE provider = 'whapi' OR provider_type = 'whapi'
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows2 as $r) {
    foreach ($r as $k => $v) echo "  {$k} = {$v}" . PHP_EOL;
    echo "---" . PHP_EOL;
}

echo PHP_EOL . "=== Teste /contacts Whapi para números-problema ===" . PHP_EOL;
$testNumbers = [
    '5547997930191',  // (47) 99793-0191 = Bruna Souza Esmalteria
    '5547999945553',  // Studio Di Capelli (funcionou antes)
];

// Pega o token ativo
$tokenRow = $pdo->query("
    SELECT whapi_api_token, session_name FROM whatsapp_provider_configs
    WHERE provider_type = 'whapi' AND is_active = 1 LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$tokenRow) {
    echo "  ERRO: Nenhum token Whapi ativo (is_active=1) encontrado!" . PHP_EOL;
    exit(1);
}

$token = $tokenRow['whapi_api_token'];
if (!empty($token) && strpos($token, 'encrypted:') === 0) {
    require_once __DIR__ . '/../src/Core/CryptoHelper.php';
    $token = \PixelHub\Core\CryptoHelper::decrypt(substr($token, 10));
}
echo "  Usando sessão: " . $tokenRow['session_name'] . " | token_prefix: " . substr($token, 0, 20) . "..." . PHP_EOL . PHP_EOL;

foreach ($testNumbers as $num) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://gate.whapi.cloud/contacts',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['contacts' => [$num]]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    echo "  Número: {$num}" . PHP_EOL;
    echo "  HTTP: {$code}" . PHP_EOL;
    if ($err) echo "  cURL error: {$err}" . PHP_EOL;
    $data = json_decode($body, true);
    if (isset($data['contacts'][0])) {
        $c = $data['contacts'][0];
        echo "  status: " . ($c['status'] ?? 'N/A') . PHP_EOL;
        echo "  wa_id: " . ($c['wa_id'] ?? 'N/A') . PHP_EOL;
    } else {
        echo "  Response: " . substr($body, 0, 300) . PHP_EOL;
    }
    echo "---" . PHP_EOL;
}
