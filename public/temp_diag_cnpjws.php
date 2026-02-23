<?php
require_once __DIR__ . '/../vendor/autoload.php';
$apiKey = \PixelHub\Services\ProspectingService::getCnpjWsApiKey();

echo "<pre>";
echo "Token (mascarado): " . substr($apiKey, 0, 4) . str_repeat('*', max(0, strlen($apiKey) - 8)) . substr($apiKey, -4) . "\n";
echo "Tamanho do token: " . strlen($apiKey) . " chars\n\n";

$tests = [
    // Teste 1: consulta CNPJ individual (mais básico)
    ['url' => 'https://comercial.cnpj.ws/cnpj/11222333000181', 'desc' => 'Consulta CNPJ individual (comercial)'],
    // Teste 2: pesquisa v2
    ['url' => 'https://comercial.cnpj.ws/v2/pesquisa?atividade_principal_id=6203100&estado_id=26&limite=1', 'desc' => 'Pesquisa v2 (SP, TI)'],
    // Teste 3: sem token para comparar
    ['url' => 'https://publica.cnpj.ws/cnpj/11222333000181', 'desc' => 'Consulta pública sem token'],
    // Teste 4: consumo
    ['url' => 'https://comercial.cnpj.ws/consumo', 'desc' => 'Consumo mensal'],
];

foreach ($tests as $t) {
    $headers = [
        'Accept: application/json',
        'User-Agent: PixelHub/1.0',
    ];
    if (strpos($t['url'], 'comercial') !== false) {
        $headers[] = 'x_api_token: ' . $apiKey;
    }

    $ch = curl_init($t['url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_HEADERFUNCTION => function($ch, $header) use (&$responseHeaders) {
            $responseHeaders[] = trim($header);
            return strlen($header);
        },
    ]);
    $responseHeaders = [];
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    echo "=== {$t['desc']} ===\n";
    echo "URL: {$t['url']}\n";
    echo "HTTP: $code\n";
    if ($err) echo "ERRO: $err\n";
    echo "Resposta: " . substr($body, 0, 400) . "\n\n";
}
echo "</pre>";
