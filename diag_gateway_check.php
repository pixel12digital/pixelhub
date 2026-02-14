<?php
// Diagnóstico rápido - testar conectividade com gateway em múltiplas portas/endpoints
$urls = [
    'https://wpp.pixel12digital.com.br/health',
    'https://wpp.pixel12digital.com.br:8443/health',
    'https://wpp.pixel12digital.com.br/api/health',
    'https://wpp.pixel12digital.com.br:8443/api/health',
    'https://wpp.pixel12digital.com.br/',
    'https://wpp.pixel12digital.com.br:8443/',
    'http://wpp.pixel12digital.com.br:21465/health',
    'http://wpp.pixel12digital.com.br:21465/api/health',
    'http://wpp.pixel12digital.com.br:21465/',
];

echo "=== TESTE DE CONECTIVIDADE COM GATEWAY ===\n\n";

foreach ($urls as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    $status = $httpCode >= 200 && $httpCode < 400 ? 'OK' : 'FAIL';
    echo sprintf("  [%s] %s -> HTTP %d", $status, $url, $httpCode);
    if ($curlErr) echo " | cURL({$curlErrno}): {$curlErr}";
    if ($resp && strlen($resp) < 300) echo " | Body: " . trim($resp);
    echo "\n";
}

// Teste DNS
echo "\n--- DNS Resolution ---\n";
$ips = gethostbynamel('wpp.pixel12digital.com.br');
if ($ips) {
    echo "  wpp.pixel12digital.com.br -> " . implode(', ', $ips) . "\n";
} else {
    echo "  FALHA na resolução DNS para wpp.pixel12digital.com.br\n";
}

// Teste de porta TCP
echo "\n--- TCP Port Check ---\n";
foreach ([443, 8443, 21465] as $port) {
    $fp = @fsockopen('wpp.pixel12digital.com.br', $port, $errno, $errstr, 5);
    if ($fp) {
        echo "  Porta {$port}: ABERTA\n";
        fclose($fp);
    } else {
        echo "  Porta {$port}: FECHADA ({$errno}: {$errstr})\n";
    }
}

echo "\n=== FIM ===\n";
