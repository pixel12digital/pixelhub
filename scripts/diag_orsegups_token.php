<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Core/CryptoHelper.php';
$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'], $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function decryptToken(string $raw): string {
    if (strpos($raw, 'encrypted:') === 0) {
        return \PixelHub\Core\CryptoHelper::decrypt(substr($raw, 10));
    }
    return $raw;
}

function testContacts(string $label, string $token, array $numbers): void {
    foreach ($numbers as $num) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://gate.whapi.cloud/contacts',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['contacts' => [$num]]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($body, true);
        $status = $data['contacts'][0]['status'] ?? 'N/A';
        $wa_id  = $data['contacts'][0]['wa_id']  ?? 'N/A';
        echo "  [{$label}] {$num} → HTTP {$code} | status={$status} | wa_id={$wa_id}" . PHP_EOL;
        if ($code !== 200) echo "    resp=" . substr($body, 0, 200) . PHP_EOL;
    }
}

function testHealth(string $label, string $token): void {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://gate.whapi.cloud/health',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_TIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $d = json_decode($body, true);
    $status = $d['status']['text'] ?? 'N/A';
    $user   = $d['user']['id'] ?? 'N/A';
    $push   = $d['user']['pushname'] ?? 'N/A';
    $ch_id  = $d['channel_id'] ?? 'N/A';
    echo "  [{$label}] HTTP {$code} | status={$status} | user={$user} ({$push}) | channel_id={$ch_id}" . PHP_EOL;
}

$sessions = $pdo->query("
    SELECT session_name, whapi_api_token
    FROM whatsapp_provider_configs
    WHERE provider_type = 'whapi' AND is_active = 1
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

$testNums = [
    '554797930191',   // (47) 9793-0191 formato antigo sem 9
    '5547997930191',  // (47) 9793-0191 com 9 inserido
    '5547999945553',  // Studio Di Capelli (sabemos que é válido)
];

echo "=== HEALTH por sessão ===" . PHP_EOL;
foreach ($sessions as $s) {
    $token = decryptToken($s['whapi_api_token']);
    testHealth($s['session_name'], $token);
}

echo PHP_EOL . "=== /contacts por sessão ===" . PHP_EOL;
foreach ($sessions as $s) {
    $token = decryptToken($s['whapi_api_token']);
    testContacts($s['session_name'], $token, $testNums);
}
