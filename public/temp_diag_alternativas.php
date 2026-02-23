<?php
echo "<pre>";
echo "IP: " . file_get_contents('https://api.ipify.org') . "\n\n";

$tests = [
    [
        'desc' => 'Minha Receita — busca por CNAE + municipio',
        'url'  => 'https://minhareceita.org/cnpj?cnae=4755501&municipio=FLORIANOPOLIS&uf=SC',
    ],
    [
        'desc' => 'Minha Receita — busca só por CNAE',
        'url'  => 'https://minhareceita.org/cnpj?cnae=4755501',
    ],
    [
        'desc' => 'ReceitaWS — busca por CNAE',
        'url'  => 'https://receitaws.com.br/v1/cnpj/search?cnae=4755501&municipio=FLORIANOPOLIS',
    ],
    [
        'desc' => 'Nuvem Fiscal — busca por CNAE (sem token)',
        'url'  => 'https://api.nuvemfiscal.com.br/cnpj?cnae=4755501&municipio_id=4205407&situacao=ATIVA',
    ],
    [
        'desc' => 'Brasil.io — busca por CNAE (sem token)',
        'url'  => 'https://brasil.io/api/dataset/socios-brasil/empresas/data/?cnae_fiscal=4755501&municipio=FLORIANOPOLIS&format=json',
    ],
];

foreach ($tests as $t) {
    $ch = curl_init($t['url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: Mozilla/5.0'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    echo "=== {$t['desc']} ===\n";
    echo "HTTP $code\n";
    if ($err) echo "ERR: $err\n";
    else echo substr($body, 0, 300) . "\n";
    echo "\n";
}
echo "</pre>";
