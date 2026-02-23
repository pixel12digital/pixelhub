<?php
echo "<pre>";

// ============================================================
// DIAGNÓSTICO: Minha Receita — busca em LOTE por CNAE + região
// Endpoint: GET https://minhareceita.org/?cnae=XXXX&uf=SC&limit=5
// Sem token, sem cadastro — dados abertos da Receita Federal
// ============================================================

function testarMR(string $label, string $url): void {
    echo "=== $label ===\n";
    echo "URL: $url\n";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_USERAGENT      => 'PixelHub/1.0',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    echo "HTTP $code\n";
    if ($err) { echo "CURL ERR: $err\n\n"; return; }

    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Resposta não-JSON: " . substr($body, 0, 300) . "\n\n";
        return;
    }

    // Verifica se retornou lista (busca em lote)
    if (isset($data['data']) && is_array($data['data'])) {
        $count = count($data['data']);
        echo "RESULTADO: $count empresa(s) retornada(s) [BUSCA EM LOTE OK]\n";
        if ($count > 0) {
            $first = $data['data'][0];
            echo "  Primeira empresa:\n";
            echo "    CNPJ        : " . ($first['cnpj'] ?? 'n/a') . "\n";
            echo "    Razão Social: " . ($first['razao_social'] ?? 'n/a') . "\n";
            echo "    Município   : " . ($first['municipio'] ?? 'n/a') . "\n";
            echo "    UF          : " . ($first['uf'] ?? 'n/a') . "\n";
            echo "    CNAE        : " . ($first['cnae_fiscal'] ?? 'n/a') . "\n";
            echo "    Telefone    : " . ($first['ddd_telefone_1'] ?? 'n/a') . "\n";
            echo "    Email       : " . ($first['email'] ?? 'n/a') . "\n";
        }
        if (isset($data['cursor'])) {
            echo "  Cursor próxima página: " . $data['cursor'] . "\n";
        }
    } elseif (isset($data['message'])) {
        echo "ERRO API: " . $data['message'] . "\n";
    } else {
        echo "Resposta inesperada: " . substr($body, 0, 400) . "\n";
    }
    echo "\n";
}

// Teste 1: CNAE + UF (sem município) — mais amplo
$url1 = 'https://minhareceita.org/?' . http_build_query([
    'cnae'  => '4755501',  // Comércio varejista de tecidos
    'uf'    => 'SC',
    'limit' => 5,
]);
testarMR('Teste 1: CNAE 4755501 + UF=SC (sem município)', $url1);

// Teste 2: CNAE + UF + município IBGE (Florianópolis = 4205407)
$url2 = 'https://minhareceita.org/?' . http_build_query([
    'cnae'     => '4755501',
    'uf'       => 'SC',
    'municipio' => '4205407',
    'limit'    => 5,
]);
testarMR('Teste 2: CNAE 4755501 + UF=SC + municipio=4205407 (Florianópolis IBGE)', $url2);

// Teste 3: CNAE diferente (TI) + SP para confirmar que funciona em geral
$url3 = 'https://minhareceita.org/?' . http_build_query([
    'cnae'  => '6202300',  // Desenvolvimento de software
    'uf'    => 'SP',
    'limit' => 3,
]);
testarMR('Teste 3: CNAE 6202300 (TI) + UF=SP', $url3);

echo "</pre>";
