<?php
/**
 * Script para verificar configuração do webhook no Meta
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

Env::load();

echo "=== VERIFICAR CONFIGURAÇÃO WEBHOOK META ===\n\n";

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

echo "1. Consultando configuração de webhooks no Meta...\n";
echo "   Phone Number ID: {$phoneNumberId}\n\n";

// Verifica webhooks subscritos
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
    
    if (empty($data['data'])) {
        echo "❌ PROBLEMA ENCONTRADO: Nenhuma app está subscrita ao webhook!\n\n";
        echo "📋 SOLUÇÃO:\n";
        echo "1. Acesse: https://business.facebook.com/latest/whatsapp_manager/phone_numbers/\n";
        echo "2. Selecione o número +55 47 9647-4223\n";
        echo "3. Vá em: Configuration → Webhooks\n";
        echo "4. Configure:\n";
        echo "   - Callback URL: https://hub.pixel12digital.com.br/api/whatsapp/meta/webhook\n";
        echo "   - Verify Token: pixelhub_meta_webhook_2026\n";
        echo "5. Subscribe to fields:\n";
        echo "   ✅ messages\n";
        echo "   ✅ message_status\n";
        echo "6. Clique em 'Subscribe'\n";
    } else {
        echo "✅ Webhook está subscrito!\n";
        echo "   Apps: " . count($data['data']) . "\n";
    }
} else {
    echo "❌ Erro ao consultar webhooks\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== FIM ===\n";
