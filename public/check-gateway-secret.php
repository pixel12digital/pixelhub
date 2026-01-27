<?php
/**
 * Diagnóstico do WPP_GATEWAY_SECRET
 * 
 * Compara o secret do Hub com o fingerprint esperado do gateway.
 * Acesso: GET /check-gateway-secret.php?token=SEU_TOKEN
 * 
 * NÃO expõe o valor do secret, apenas fingerprint e tamanho.
 */

// Token de segurança (definir no .env como CHECK_SECRET_TOKEN ou usar valor fixo)
$expectedToken = getenv('CHECK_SECRET_TOKEN') ?: 'pixel12_check_secret_2026';
$providedToken = $_GET['token'] ?? '';

header('Content-Type: application/json; charset=utf-8');

if ($providedToken !== $expectedToken) {
    http_response_code(403);
    echo json_encode(['error' => 'Token inválido'], JSON_PRETTY_PRINT);
    exit;
}

// Carregar .env manualmente (sem dependências)
$envPath = __DIR__ . '/../.env';
$envVars = [];

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $envVars[trim($key)] = trim($value);
    }
}

// Dados do gateway (VPS) - fingerprint conhecido
$gatewayFingerprint = 'a03817fe';
$gatewaySecretLen = 64;

// Ler WPP_GATEWAY_SECRET do .env
$wppSecretRaw = $envVars['WPP_GATEWAY_SECRET'] ?? '';

$result = [
    'timestamp' => date('Y-m-d H:i:s'),
    'hostname' => gethostname(),
    'env_file' => $envPath,
    'env_file_exists' => file_exists($envPath),
    'gateway_expected' => [
        'variable' => 'GATEWAY_SECRET',
        'len' => $gatewaySecretLen,
        'fingerprint' => $gatewayFingerprint
    ],
    'hub_secret' => [
        'variable' => 'WPP_GATEWAY_SECRET',
        'found' => !empty($wppSecretRaw),
        'len' => strlen($wppSecretRaw),
        'fingerprint' => !empty($wppSecretRaw) ? substr(hash('sha256', $wppSecretRaw), 0, 8) : null,
        'looks_encrypted' => (
            strlen($wppSecretRaw) > 100 || 
            preg_match('/^[A-Za-z0-9+\/=]+$/', $wppSecretRaw) && strlen($wppSecretRaw) > 80
        )
    ],
    'match' => false,
    'diagnosis' => '',
    'action' => ''
];

// Verificar se está criptografado (tentar descriptografar)
$infraKey = $envVars['INFRA_SECRET_KEY'] ?? '';
$decryptedSecret = null;

if (!empty($wppSecretRaw) && !empty($infraKey) && $result['hub_secret']['looks_encrypted']) {
    // Tentar descriptografar (base64 + openssl)
    $decoded = base64_decode($wppSecretRaw, true);
    if ($decoded !== false && strlen($decoded) > 16) {
        $iv = substr($decoded, 0, 16);
        $ciphertext = substr($decoded, 16);
        $decrypted = @openssl_decrypt($ciphertext, 'AES-256-CBC', $infraKey, OPENSSL_RAW_DATA, $iv);
        if ($decrypted !== false) {
            $decryptedSecret = $decrypted;
            $result['hub_secret']['decrypted_len'] = strlen($decrypted);
            $result['hub_secret']['decrypted_fingerprint'] = substr(hash('sha256', $decrypted), 0, 8);
        }
    }
}

// Comparar fingerprints
$hubFingerprint = $decryptedSecret !== null 
    ? $result['hub_secret']['decrypted_fingerprint'] 
    : $result['hub_secret']['fingerprint'];

if (empty($wppSecretRaw)) {
    $result['diagnosis'] = 'WPP_GATEWAY_SECRET não encontrado no .env';
    $result['action'] = 'Adicionar WPP_GATEWAY_SECRET=VALOR_DO_GATEWAY ao .env do Hub';
} elseif ($hubFingerprint === $gatewayFingerprint) {
    $result['match'] = true;
    $result['diagnosis'] = 'Secrets ALINHADOS! O Hub e o gateway usam o mesmo secret.';
    $result['action'] = 'Nenhuma ação necessária. Testar envio de texto/áudio/mídia.';
} else {
    $result['diagnosis'] = 'Secrets DIFERENTES! Fingerprints não coincidem.';
    $result['action'] = 'Atualizar WPP_GATEWAY_SECRET no .env do Hub para o valor do GATEWAY_SECRET da VPS.';
    $result['hub_fingerprint'] = $hubFingerprint;
    $result['gateway_fingerprint'] = $gatewayFingerprint;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
