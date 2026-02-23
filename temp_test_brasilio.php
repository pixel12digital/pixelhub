<?php
// Testa Brasil.io - dados abertos RF com busca por CNAE + município
// Documentação: https://brasil.io/api/datasets/

$token = ''; // sem token primeiro

$tests = [
    // Brasil.io - empresas por CNAE + estado
    'https://brasil.io/api/dataset/socios-brasil/empresas/data/?cnae_fiscal=4755501&estado=SC&format=json',
    'https://brasil.io/api/dataset/socios-brasil/empresas/data/?cnae_fiscal=4755501&municipio=FLORIANOPOLIS&format=json',
    // Endpoint de datasets disponíveis
    'https://brasil.io/api/datasets/',
    // Endpoint alternativo
    'https://brasil.io/api/dataset/cnpj/empresas/data/?cnae_fiscal_principal=4755501&municipio_id=4205407&format=json',
    'https://brasil.io/api/dataset/cnpj/empresas/data/?cnae_fiscal_principal=4755501&uf=SC&format=json',
];

foreach ($tests as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (compatible; PixelHub)',
            $token ? "Authorization: Token $token" : '',
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    echo "[$code] $url\n";
    if ($err) echo "  ERR: $err\n";
    else echo "  → " . substr($body, 0, 300) . "\n\n";
}
