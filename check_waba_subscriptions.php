<?php
/**
 * Script para verificar subscrições da WABA
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

Env::load();

echo "=== VERIFICAR SUBSCRIÇÕES DA WABA ===\n\n";

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

// WhatsApp Business Account ID
$wabaId = $config['meta_business_account_id'];

echo "1. Consultando subscrições da WABA...\n";
echo "   WABA ID: {$wabaId}\n\n";

// Consulta subscrições
$url = "https://graph.facebook.com/v18.0/{$wabaId}/subscribed_apps?access_token=" . urlencode($accessToken);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Status: {$httpCode}\n\n";

$data = json_decode($response, true);

echo "2. Resposta completa da Meta:\n";
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if ($httpCode === 200) {
    if (isset($data['data']) && !empty($data['data'])) {
        echo "3. Análise das subscrições:\n";
        echo str_repeat('-', 80) . "\n";
        
        foreach ($data['data'] as $app) {
            echo "App ID: " . ($app['id'] ?? 'N/A') . "\n";
            echo "Whitelisted: " . (isset($app['whitelisted']) ? ($app['whitelisted'] ? 'SIM' : 'NÃO') : 'N/A') . "\n";
            
            if (isset($app['subscribed_fields'])) {
                echo "Campos subscritos:\n";
                foreach ($app['subscribed_fields'] as $field) {
                    echo "  - {$field}\n";
                }
                
                // Verifica se 'messages' está subscrito
                if (in_array('messages', $app['subscribed_fields'])) {
                    echo "\n✅ Campo 'messages' ESTÁ SUBSCRITO!\n";
                } else {
                    echo "\n❌ Campo 'messages' NÃO está subscrito!\n";
                    echo "   Isso explica por que não recebe webhooks de mensagens.\n";
                }
            } else {
                echo "Nenhum campo subscrito\n";
            }
            
            echo str_repeat('-', 80) . "\n";
        }
    } else {
        echo "❌ Nenhuma app subscrita à WABA\n";
        echo "   Isso explica por que não recebe webhooks.\n";
    }
} else {
    echo "❌ Erro ao consultar subscrições\n";
}

echo "\n=== FIM ===\n";
