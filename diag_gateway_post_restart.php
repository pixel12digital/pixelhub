<?php
// Diagnóstico pós-restart - verificar se gateway voltou
echo "=== TESTE PÓS-RESTART DO GATEWAY ===\n\n";

$tests = [
    'https://wpp.pixel12digital.com.br/api/health' => 'Porta 443 (nginx → 127.0.0.1:3000)',
    'https://wpp.pixel12digital.com.br:8443/api/health' => 'Porta 8443 (nginx → 172.19.0.1:3000)',
];

foreach ($tests as $url => $desc) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    
    $ok = ($httpCode >= 200 && $httpCode < 500 && $httpCode != 502);
    echo sprintf("  [%s] %s\n", $ok ? 'OK' : 'FAIL', $desc);
    echo sprintf("       URL: %s\n", $url);
    echo sprintf("       HTTP: %d\n", $httpCode);
    if ($curlErr) echo sprintf("       cURL: %s\n", $curlErr);
    if ($resp) echo sprintf("       Body: %s\n", substr(trim($resp), 0, 200));
    echo "\n";
}

// Teste webhook endpoint do PixelHub (deve estar acessível)
echo "--- Teste webhook endpoint PixelHub ---\n";
$ch = curl_init('https://hub.pixel12digital.com.br/api/whatsapp/webhook');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => '{"test": true}',
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);
echo sprintf("  Webhook endpoint: HTTP %d\n", $httpCode);
if ($resp) echo sprintf("  Body: %s\n", substr(trim($resp), 0, 200));
if ($curlErr) echo sprintf("  cURL: %s\n", $curlErr);

echo "\n=== FIM ===\n";
