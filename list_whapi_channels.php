<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

$db = DB::getConnection();

// Pegar o token da sessão orsegups
$stmt = $db->prepare("
    SELECT whapi_api_token 
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'whapi' AND session_name = 'orsegups'
");
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config || !$config['whapi_api_token']) {
    echo "❌ Token da sessão orsegups não encontrado!\n";
    exit;
}

$apiToken = $config['whapi_api_token'];
if (!empty($apiToken) && strpos($apiToken, 'encrypted:') === 0) {
    $token = CryptoHelper::decrypt(substr($apiToken, 10));
} else {
    $token = $apiToken;
}

echo "=== LISTANDO CANAIS WHAPI ===\n";
echo "Token original: " . substr($config['whapi_api_token'], 0, 30) . "...\n";
echo "Token decrypt: " . substr($token, 0, 30) . "...\n\n";

// Tentar pegar status do canal via API do Whapi
$url = "https://gate.whapi.cloud/status";
$headers = [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    echo "❌ Erro na requisição: " . curl_error($ch) . "\n";
} else {
    echo "HTTP Status: {$httpCode}\n\n";
    
    $data = json_decode($response, true);
    if ($data && isset($data['account'])) {
        $account = $data['account'];
        echo "Informações da conta/canal:\n";
        echo sprintf(
            "- ID: %s | Telefone: %s | Nome: %s\n",
            $account['id'] ?? 'N/A',
            $account['phone_number'] ?? $account['phone'] ?? 'N/A',
            $account['name'] ?? 'N/A'
        );
        
        if (isset($account['id'])) {
            echo "\n🔧 Para configurar o canal orsegups:\n";
            echo "UPDATE whatsapp_provider_configs SET whapi_channel_id = '" . $account['id'] . "' WHERE id = 4;\n";
        }
    } else {
        echo "Resposta da API: " . substr($response, 0, 500) . "...\n";
    }
}

echo "\n=== FIM ===\n";
