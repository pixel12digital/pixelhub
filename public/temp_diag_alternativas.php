<?php
echo "<pre>";

$clientId     = 'H7uhkra2Vi79OCocOGuu';
$clientSecret = 'ckBoATAYerpAmAosrk3GUwp9TIngq18AXbAg8KD7';

// Passo 1: obter token OAuth2
echo "=== Passo 1: OAuth2 Token ===\n";
$ch = curl_init('https://auth.nuvemfiscal.com.br/oauth/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'grant_type'    => 'client_credentials',
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'scope'         => 'cnpj',
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

echo "HTTP $code\n";
if ($err) { echo "ERR: $err\n"; exit; }
echo substr($body, 0, 300) . "\n\n";

$tokenData = json_decode($body, true);
$token = $tokenData['access_token'] ?? null;
if (!$token) { echo "Falhou ao obter token!\n"; exit; }
echo "Token obtido: " . substr($token, 0, 20) . "...\n\n";

// Passo 2: busca por CNAE + municipio
echo "=== Passo 2: Busca por CNAE + municipio_id ===\n";
$url = 'https://api.nuvemfiscal.com.br/cnpj?' . http_build_query([
    'cnae_fiscal'  => '4755501',
    'municipio_id' => '4205407', // Florianópolis IBGE
    'situacao'     => 'ATIVA',
    '$top'         => 5,
]);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    ],
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

echo "HTTP $code\n";
if ($err) echo "ERR: $err\n";
else echo substr($body, 0, 600) . "\n";

echo "</pre>";
