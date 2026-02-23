<?php
// Teste Casa dos Dados do servidor de produção
// DELETE após testar!

$payload = json_encode([
    'query' => [
        'termo'              => [],
        'cnae_fiscal'        => ['4755501'],
        'municipio'          => ['FLORIANOPOLIS'],
        'uf'                 => ['SC'],
        'situacao_cadastral' => ['ATIVA'],
    ],
    'extras' => [
        'somente_mei' => false, 'excluir_mei' => false,
        'com_email' => false, 'incluir_ativo_baixado' => false,
        'ip_migracao_regime_tributario' => false,
        'opcao_simples' => false, 'opcao_mei' => false,
    ],
    'range_query' => [
        'data_abertura'  => ['lte' => null, 'gte' => null],
        'capital_social' => ['lte' => null, 'gte' => null],
    ],
    'page' => 1,
], JSON_UNESCAPED_UNICODE);

echo "<pre>";
echo "IP do servidor: " . file_get_contents('https://api.ipify.org') . "\n\n";

$ch = curl_init('https://api.casadosdados.com.br/v2/public/cnpj/search');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Origin: https://casadosdados.com.br',
        'Referer: https://casadosdados.com.br/',
        'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ],
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

echo "HTTP Status: $code\n";
if ($err) echo "cURL Error: $err\n";
else {
    $data = json_decode($body, true);
    if ($code === 200 && !empty($data['data']['cnpj'])) {
        echo "✅ SUCESSO! Retornou " . count($data['data']['cnpj']) . " empresas\n\n";
        foreach (array_slice($data['data']['cnpj'], 0, 3) as $emp) {
            echo "  - " . ($emp['razao_social'] ?? '') . " | CNPJ: " . ($emp['cnpj'] ?? '') . "\n";
        }
    } else {
        echo "❌ FALHOU\n";
        echo substr($body, 0, 500) . "\n";
    }
}
echo "</pre>";
