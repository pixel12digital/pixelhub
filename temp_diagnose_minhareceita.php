<?php
// Diagnóstico Minha Receita
echo "PHP version: " . PHP_VERSION . "\n";
echo "str_contains available: " . (function_exists('str_contains') ? 'YES' : 'NO') . "\n";
echo "iconv available: " . (function_exists('iconv') ? 'YES' : 'NO') . "\n";
echo "curl available: " . (function_exists('curl_init') ? 'YES' : 'NO') . "\n";

// Testa chamada direta à API Minha Receita
echo "\n--- Teste API Minha Receita ---\n";
$url = 'https://minhareceita.org/?cnae=4781400&uf=SP&limit=5';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, 'PixelHub/1.0');
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) echo "cURL Error: $error\n";
if ($response) {
    $data = json_decode($response, true);
    echo "Response keys: " . implode(', ', array_keys($data ?? [])) . "\n";
    echo "Companies count: " . count($data['companies'] ?? $data['results'] ?? []) . "\n";
    echo "Raw (first 500 chars): " . substr($response, 0, 500) . "\n";
}

// Testa sem UF (nacional)
echo "\n--- Teste API Minha Receita (sem UF) ---\n";
$url2 = 'https://minhareceita.org/?cnae=4781400&limit=5';
$ch2 = curl_init($url2);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_TIMEOUT, 15);
curl_setopt($ch2, CURLOPT_USERAGENT, 'PixelHub/1.0');
$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$error2 = curl_error($ch2);
curl_close($ch2);

echo "HTTP Code: $httpCode2\n";
if ($error2) echo "cURL Error: $error2\n";
if ($response2) {
    $data2 = json_decode($response2, true);
    echo "Response keys: " . implode(', ', array_keys($data2 ?? [])) . "\n";
    echo "Raw (first 500 chars): " . substr($response2, 0, 500) . "\n";
}
