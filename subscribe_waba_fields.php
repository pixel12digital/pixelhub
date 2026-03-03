<?php
/**
 * Script para subscrever campos específicos da WABA
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

Env::load();

echo "=== SUBSCREVER CAMPOS DA WABA ===\n\n";

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

echo "1. Subscrevendo campos da WABA...\n";
echo "   WABA ID: {$wabaId}\n";
echo "   Campos: messages, message_status\n\n";

// Subscreve aos campos
$url = "https://graph.facebook.com/v18.0/{$wabaId}/subscribed_apps";

$payload = [
    'subscribed_fields' => ['messages', 'message_status']
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$accessToken}",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Status: {$httpCode}\n\n";

$data = json_decode($response, true);

echo "2. Resposta da Meta:\n";
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if ($httpCode === 200 && isset($data['success']) && $data['success']) {
    echo "✅ SUCESSO! Campos subscritos\n\n";
    echo "Agora execute novamente para verificar:\n";
    echo "php check_waba_subscriptions.php\n";
} else {
    echo "❌ Erro ao subscrever campos\n";
}

echo "\n=== FIM ===\n";
