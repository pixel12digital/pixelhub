<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/CryptoHelper.php';
require_once __DIR__ . '/../src/Services/GatewaySecret.php';

\PixelHub\Core\Env::load();

echo "=== TESTE: Resolver pnLid via API (COM AUTH) ===\n\n";

$sessionId = 'ImobSites';
$pnLid = '10523374551225';
$baseUrl = \PixelHub\Core\Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br');

// Obtém secret para autenticação
try {
    $secret = \PixelHub\Services\GatewaySecret::getDecrypted();
} catch (\Exception $e) {
    echo "❌ Erro ao obter secret: " . $e->getMessage() . "\n";
    exit(1);
}

if (empty($secret)) {
    echo "❌ Secret vazio!\n";
    exit(1);
}

echo "Parâmetros:\n";
echo "  Session ID: {$sessionId}\n";
echo "  pnLid: {$pnLid}\n";
echo "  Base URL: {$baseUrl}\n";
echo "  Secret: " . substr($secret, 0, 4) . "..." . substr($secret, -4) . " (len=" . strlen($secret) . ")\n\n";

$url = rtrim($baseUrl, '/') . "/api/" . rawurlencode($sessionId) . "/contact/pn-lid/" . rawurlencode($pnLid);

echo "URL: {$url}\n\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => [
        "Accept: application/json",
        "X-Gateway-Secret: {$secret}"
    ],
    CURLOPT_VERBOSE => false,
]);

$raw = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "Resposta:\n";
echo "  HTTP Code: {$code}\n";
echo "  CURL Error: " . ($curlError ?: 'NONE') . "\n";
echo "  Raw Response (primeiros 500 chars):\n";
echo str_repeat("-", 80) . "\n";
echo substr($raw ?: 'NULL', 0, 500) . "\n";
echo str_repeat("-", 80) . "\n\n";

if ($code >= 200 && $code < 300 && $raw) {
    $json = json_decode($raw, true);
    if (is_array($json)) {
        echo "JSON Parseado:\n";
        echo "  Keys: " . implode(', ', array_keys($json)) . "\n\n";
        
        // Tenta extrair telefone
        $candidates = [
            'phone' => $json['phone'] ?? null,
            'number' => $json['number'] ?? null,
            'wid' => $json['wid'] ?? null,
            'id.user' => $json['id']['user'] ?? null,
            'user' => $json['user'] ?? null,
            'contact.number' => $json['contact']['number'] ?? null,
            'contact.phone' => $json['contact']['phone'] ?? null,
            'data.phone' => $json['data']['phone'] ?? null,
            'data.number' => $json['data']['number'] ?? null,
            'jid' => $json['jid'] ?? null,
        ];
        
        echo "Candidatos para telefone:\n";
        foreach ($candidates as $key => $value) {
            echo "  {$key}: " . ($value ?: 'NULL') . "\n";
        }
        
        // JSON completo (mascarado)
        echo "\nJSON Completo (mascarado):\n";
        $masked = $json;
        if (isset($masked['phone'])) $masked['phone'] = preg_replace('/\d{5}/', '*****', $masked['phone']);
        if (isset($masked['number'])) $masked['number'] = preg_replace('/\d{5}/', '*****', $masked['number']);
        echo json_encode($masked, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "❌ JSON inválido ou não é array\n";
    }
} else {
    echo "❌ Falha na requisição HTTP\n";
}

echo "\n";

