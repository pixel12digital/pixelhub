<?php
/**
 * Script para debugar processamento de webhook Meta
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== DEBUG PROCESSAMENTO WEBHOOK META ===\n\n";

$db = DB::getConnection();

// Pega o último webhook Meta não processado
$stmt = $db->query("
    SELECT * FROM webhook_raw_logs
    WHERE event_type = 'meta_message'
    AND processed = 0
    ORDER BY created_at DESC
    LIMIT 1
");

$webhook = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$webhook) {
    echo "❌ Nenhum webhook Meta não processado encontrado\n";
    exit;
}

echo "1. Webhook encontrado:\n";
echo "   ID: {$webhook['id']}\n";
echo "   Data: {$webhook['created_at']}\n\n";

$payload = json_decode($webhook['payload_json'], true);

echo "2. Payload:\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Extrai phone_number_id
$phoneNumberId = $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] ?? null;

echo "3. Phone Number ID extraído: {$phoneNumberId}\n\n";

// Tenta resolver tenant
echo "4. Tentando resolver tenant...\n";

$stmt = $db->prepare("
    SELECT tenant_id, is_global
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'meta_official' 
    AND meta_phone_number_id = ? 
    AND is_active = 1
    LIMIT 1
");
$stmt->execute([$phoneNumberId]);
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if ($config) {
    echo "   ✅ Config encontrada:\n";
    echo "   Tenant ID: " . ($config['tenant_id'] ?? 'NULL') . "\n";
    echo "   Is Global: " . ($config['is_global'] ? 'SIM' : 'NÃO') . "\n\n";
    
    if ($config['is_global'] && !$config['tenant_id']) {
        echo "   ⚠️  PROBLEMA: Config é global mas não tem tenant_id!\n";
        echo "   Isso faz com que EventIngestionService falhe.\n\n";
        echo "   SOLUÇÃO: Configuração global Meta precisa ter um tenant_id padrão.\n";
    }
} else {
    echo "   ❌ Config não encontrada para phone_number_id: {$phoneNumberId}\n";
}

echo "\n=== FIM ===\n";
