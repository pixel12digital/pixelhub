<?php
// Testa endpoints reais da API CNPJ.ws comercial
// DELETE após testar!

require_once __DIR__ . '/../vendor/autoload.php';
$apiKey = \PixelHub\Services\ProspectingService::getCnpjWsApiKey();

echo "<pre>";
echo "IP do servidor: " . file_get_contents('https://api.ipify.org') . "\n\n";
echo "Chave (mascarada): " . substr($apiKey, 0, 4) . str_repeat('*', strlen($apiKey) - 8) . substr($apiKey, -4) . "\n\n";

$endpoints = [
    'https://api.cnpj.ws/v1/estabelecimentos?cnae_fiscal_principal=4755501&municipio_id=4205407&situacao_cadastral=ATIVA&quantidade=1',
    'https://www.cnpj.ws/api/v1/estabelecimentos?cnae_fiscal_principal=4755501&municipio_id=4205407',
    'https://cnpj.ws/api/v1/estabelecimentos?cnae_fiscal_principal=4755501&municipio_id=4205407',
    'https://api.cnpj.ws/estabelecimentos?cnae_fiscal_principal=4755501&municipio_id=4205407',
    // Testa resolução DNS
    'https://publica.cnpj.ws/cnpj/11222333000181',
];

foreach ($endpoints as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: Bearer ' . $apiKey,
            'User-Agent: PixelHub/1.0',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    echo "[$code] $url\n";
    if ($err) echo "  ERR: $err\n";
    else echo "  → " . substr($body, 0, 200) . "\n\n";
}
echo "</pre>";
