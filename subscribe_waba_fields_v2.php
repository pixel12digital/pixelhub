<?php
/**
 * Script para subscrever campos da WABA (versão 2 - via query params)
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

Env::load();

echo "=== SUBSCREVER CAMPOS DA WABA (V2) ===\n\n";

$db = DB::getConnection();
$stmt = $db->query("
    SELECT * FROM whatsapp_provider_configs 
    WHERE provider_type = 'meta_official' AND is_global = TRUE
    LIMIT 1
");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    echo "❌ Configuração Meta não encontrada\n";
    exit(1);
}

$accessToken = $config['meta_access_token'];

// Descriptografa token
if (strpos($accessToken, 'encrypted:') === 0) {
    $accessToken = CryptoHelper::decrypt(substr($accessToken, 10));
}

$wabaId = $config['meta_business_account_id'];

echo "1. Subscrevendo campos da WABA via query params...\n";
echo "   WABA ID: {$wabaId}\n";
echo "   Campos: messages, message_status\n\n";

// Subscreve aos campos via query params
$url = "https://graph.facebook.com/v18.0/{$wabaId}/subscribed_apps?" . http_build_query([
    'subscribed_fields' => 'messages,message_status',
    'access_token' => $accessToken
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Status: {$httpCode}\n\n";

$data = json_decode($response, true);

echo "2. Resposta da Meta:\n";
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if ($httpCode === 200 && isset($data['success']) && $data['success']) {
    echo "✅ SUCESSO! Campos subscritos\n\n";
    
    // Aguarda 2 segundos e verifica
    echo "Aguardando 2 segundos para propagação...\n";
    sleep(2);
    
    echo "\nVerificando subscrição...\n";
    $verifyUrl = "https://graph.facebook.com/v18.0/{$wabaId}/subscribed_apps?access_token=" . urlencode($accessToken);
    
    $ch = curl_init($verifyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $verifyResponse = curl_exec($ch);
    curl_close($ch);
    
    $verifyData = json_decode($verifyResponse, true);
    echo json_encode($verifyData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    echo "Agora envie uma mensagem de teste!\n";
} else {
    echo "❌ Erro ao subscrever campos\n";
}

echo "\n=== FIM ===\n";
