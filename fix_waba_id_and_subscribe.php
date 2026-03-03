<?php
/**
 * Script para corrigir WABA ID e subscrever aos campos
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

Env::load();

echo "=== CORRIGIR WABA ID E SUBSCREVER ===\n\n";

$correctWabaId = '538989602621328';

// 1. Atualizar WABA ID no banco
echo "1. Atualizando WABA ID no banco de dados...\n";
echo "   WABA ID correto: {$correctWabaId}\n\n";

$db = DB::getConnection();
$stmt = $db->prepare("
    UPDATE whatsapp_provider_configs 
    SET meta_business_account_id = ?
    WHERE provider_type = 'meta_official' AND is_global = TRUE
");
$stmt->execute([$correctWabaId]);

echo "   ✅ WABA ID atualizado no banco\n\n";

// 2. Buscar configuração atualizada
$stmt = $db->query("
    SELECT * FROM whatsapp_provider_configs 
    WHERE provider_type = 'meta_official' AND is_global = TRUE
    LIMIT 1
");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

$accessToken = $config['meta_access_token'];

// Descriptografa token
if (strpos($accessToken, 'encrypted:') === 0) {
    $accessToken = CryptoHelper::decrypt(substr($accessToken, 10));
}

// 3. Subscrever app à WABA
echo "2. Subscrevendo app à WABA {$correctWabaId}...\n";

$url = "https://graph.facebook.com/v18.0/{$correctWabaId}/subscribed_apps";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$accessToken}",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Status: {$httpCode}\n";
echo "   Resposta: " . $response . "\n\n";

// 4. Subscrever aos campos
echo "3. Subscrevendo aos campos messages e message_status...\n";

$url = "https://graph.facebook.com/v18.0/{$correctWabaId}/subscribed_apps?" . http_build_query([
    'subscribed_fields' => 'messages,message_status',
    'access_token' => $accessToken
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Status: {$httpCode}\n";
echo "   Resposta: " . $response . "\n\n";

// 5. Verificar subscrição
echo "4. Verificando subscrição (GET)...\n";

$verifyUrl = "https://graph.facebook.com/v18.0/{$correctWabaId}/subscribed_apps?access_token=" . urlencode($accessToken);

$ch = curl_init($verifyUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$verifyResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Status: {$httpCode}\n\n";

echo "5. RESPOSTA COMPLETA DA META:\n";
echo str_repeat('=', 80) . "\n";
echo json_encode(json_decode($verifyResponse, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
echo str_repeat('=', 80) . "\n\n";

$verifyData = json_decode($verifyResponse, true);

if (isset($verifyData['data']) && !empty($verifyData['data'])) {
    foreach ($verifyData['data'] as $app) {
        if (isset($app['subscribed_fields'])) {
            echo "✅ Campos subscritos encontrados:\n";
            foreach ($app['subscribed_fields'] as $field) {
                echo "   - {$field}\n";
            }
            
            if (in_array('messages', $app['subscribed_fields'])) {
                echo "\n🎉 SUCESSO! Campo 'messages' está subscrito!\n";
                echo "Agora envie uma mensagem de teste para +55 47 9647-4223\n";
            }
        }
    }
}

echo "\n=== FIM ===\n";
