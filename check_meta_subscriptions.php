<?php
/**
 * Script para verificar subscrições do webhook na Meta
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

Env::load();

echo "=== VERIFICAR SUBSCRIÇÕES DO WEBHOOK META ===\n\n";

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

$phoneNumberId = $config['meta_phone_number_id'];
$accessToken = $config['meta_access_token'];

// Descriptografa token
if (strpos($accessToken, 'encrypted:') === 0) {
    $accessToken = CryptoHelper::decrypt(substr($accessToken, 10));
}

echo "1. Verificando subscrições do Phone Number ID: {$phoneNumberId}\n\n";

// Verifica apps subscritas
$url = "https://graph.facebook.com/v18.0/{$phoneNumberId}/subscribed_apps?access_token=" . urlencode($accessToken);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Status: {$httpCode}\n\n";

$data = json_decode($response, true);

if ($httpCode === 200) {
    echo "2. Apps subscritas:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    if (isset($data['data']) && !empty($data['data'])) {
        echo "✅ Webhook está subscrito!\n";
        foreach ($data['data'] as $app) {
            echo "\nApp ID: " . ($app['id'] ?? 'N/A') . "\n";
            echo "Subscribed fields: " . json_encode($app['subscribed_fields'] ?? []) . "\n";
        }
    } else {
        echo "❌ PROBLEMA: Nenhuma app subscrita!\n\n";
        echo "Isso significa que o webhook não está ativo para este número.\n";
        echo "Você precisa subscrever a app no Meta Business Suite.\n";
    }
} else {
    echo "❌ Erro ao consultar subscrições\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    if (isset($data['error']['code']) && $data['error']['code'] == 100) {
        echo "⚠️  Este endpoint pode não estar disponível para este tipo de número.\n";
        echo "Verifique manualmente no Meta Business Suite se o webhook está ativo.\n";
    }
}

echo "\n=== FIM ===\n";
