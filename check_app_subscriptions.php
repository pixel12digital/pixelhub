<?php
/**
 * Script para verificar subscrições diretamente no App ID
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

Env::load();

echo "=== VERIFICAR SUBSCRIÇÕES DO APP ===\n\n";

$db = DB::getConnection();
$stmt = $db->query("
    SELECT * FROM whatsapp_provider_configs 
    WHERE provider_type = 'meta_official' AND is_global = TRUE
    LIMIT 1
");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

$accessToken = $config['meta_access_token'];

if (strpos($accessToken, 'encrypted:') === 0) {
    $accessToken = CryptoHelper::decrypt(substr($accessToken, 10));
}

$appId = '1441482297504750'; // PixelHub Messaging
$wabaId = '538989602621328';

echo "1. Verificando subscrições do App ID: {$appId}\n";
echo "   WABA ID: {$wabaId}\n\n";

// Tenta verificar via App ID
$url = "https://graph.facebook.com/v18.0/{$appId}/subscriptions?access_token=" . urlencode($accessToken);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Status: {$httpCode}\n\n";

echo "2. RESPOSTA COMPLETA:\n";
echo str_repeat('=', 80) . "\n";
echo json_encode(json_decode($response, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
echo str_repeat('=', 80) . "\n\n";

$data = json_decode($response, true);

if (isset($data['data'])) {
    echo "3. Análise:\n";
    foreach ($data['data'] as $subscription) {
        echo "   Object: " . ($subscription['object'] ?? 'N/A') . "\n";
        echo "   Callback URL: " . ($subscription['callback_url'] ?? 'N/A') . "\n";
        
        if (isset($subscription['fields'])) {
            echo "   Campos subscritos:\n";
            foreach ($subscription['fields'] as $field) {
                echo "      - " . ($field['name'] ?? $field) . "\n";
            }
        }
        echo "\n";
    }
}

echo "\n=== FIM ===\n";
