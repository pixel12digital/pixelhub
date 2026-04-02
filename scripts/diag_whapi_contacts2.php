<?php
require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'], $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Descobre colunas reais da tabela
$cols = array_column($pdo->query("SHOW COLUMNS FROM whatsapp_provider_configs")->fetchAll(PDO::FETCH_ASSOC), 'Field');
echo "=== Colunas de whatsapp_provider_configs ===" . PHP_EOL;
echo "  " . implode(', ', $cols) . PHP_EOL . PHP_EOL;

echo "=== Sessões Whapi configuradas ===" . PHP_EOL;
foreach ($pdo->query("SELECT * FROM whatsapp_provider_configs WHERE provider_type = 'whapi' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    foreach ($r as $k => $v) {
        if ($k === 'whapi_api_token' && strlen($v) > 25) $v = substr($v, 0, 25) . '...';
        echo "  {$k} = {$v}" . PHP_EOL;
    }
    echo "---" . PHP_EOL;
}

echo PHP_EOL . "=== Canais WhatsApp ===" . PHP_EOL;
foreach ($pdo->query("SELECT * FROM tenant_message_channels ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    foreach ($r as $k => $v) echo "  {$k} = {$v}" . PHP_EOL;
    echo "---" . PHP_EOL;
}

// Pega o token ativo
$tokenRow = $pdo->query("
    SELECT whapi_api_token, session_name FROM whatsapp_provider_configs
    WHERE provider_type = 'whapi' AND is_active = 1 LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$tokenRow) {
    echo PHP_EOL . "ERRO: Nenhum token Whapi ativo (is_active=1) encontrado!" . PHP_EOL;
    // Tenta buscar qualquer token whapi
    $tokenRow = $pdo->query("SELECT whapi_api_token, session_name FROM whatsapp_provider_configs WHERE provider_type = 'whapi' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$tokenRow) { echo "Nenhum registro Whapi encontrado." . PHP_EOL; exit(1); }
    echo "Usando token inativo como fallback..." . PHP_EOL;
}

$token = $tokenRow['whapi_api_token'];
if (!empty($token) && strpos($token, 'encrypted:') === 0) {
    require_once __DIR__ . '/../src/Core/CryptoHelper.php';
    $token = \PixelHub\Core\CryptoHelper::decrypt(substr($token, 10));
}

echo PHP_EOL . "=== Teste /contacts Whapi ===" . PHP_EOL;
echo "  Sessão: " . ($tokenRow['session_name'] ?? 'N/A') . " | token_prefix: " . substr($token, 0, 20) . "..." . PHP_EOL . PHP_EOL;

foreach (['5547997930191', '5547999945553'] as $num) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://gate.whapi.cloud/contacts',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['contacts' => [$num]]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    echo "  Número: {$num} → HTTP {$code}" . PHP_EOL;
    if ($err) echo "  cURL error: {$err}" . PHP_EOL;
    $data = json_decode($body, true);
    if (isset($data['contacts'][0])) {
        $c = $data['contacts'][0];
        echo "  status=" . ($c['status'] ?? 'N/A') . " | wa_id=" . ($c['wa_id'] ?? 'N/A') . PHP_EOL;
    } else {
        echo "  Response: " . substr($body, 0, 300) . PHP_EOL;
    }
    echo "---" . PHP_EOL;
}

// Testa também /health
echo PHP_EOL . "=== GET /health ===" . PHP_EOL;
$ch2 = curl_init();
curl_setopt_array($ch2, [CURLOPT_URL => 'https://gate.whapi.cloud/health', CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'], CURLOPT_TIMEOUT => 10]);
$hBody = curl_exec($ch2);
$hCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);
echo "  HTTP {$hCode}: " . substr($hBody, 0, 400) . PHP_EOL;
