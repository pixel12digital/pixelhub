<?php
/**
 * Script para verificar eventos outbound recentes
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

echo "=== EVENTOS OUTBOUND RECENTES ===\n\n";

try {
    $db = DB::getConnection();
    
    // Busca últimos 10 eventos outbound
    $stmt = $db->query("
        SELECT 
            event_id,
            event_type,
            created_at,
            tenant_id,
            payload,
            metadata
        FROM communication_events
        WHERE event_type = 'whatsapp.outbound.message'
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $events = $stmt->fetchAll();
    
    echo "Encontrados " . count($events) . " evento(s) outbound:\n\n";
    
    foreach ($events as $i => $event) {
        echo str_repeat("=", 80) . "\n";
        echo "EVENTO " . ($i + 1) . ":\n";
        echo str_repeat("-", 80) . "\n";
        echo "Event ID:   {$event['event_id']}\n";
        echo "Created:    {$event['created_at']}\n";
        echo "Tenant ID:  {$event['tenant_id']}\n";
        
        $payload = json_decode($event['payload'], true);
        $metadata = json_decode($event['metadata'], true);
        
        echo "Type (payload): " . ($payload['type'] ?? 'NÃO DEFINIDO') . "\n";
        echo "Type (message): " . ($payload['message']['type'] ?? 'NÃO DEFINIDO') . "\n";
        echo "Sent by:    " . ($metadata['sent_by_name'] ?? 'N/A') . "\n";
        
        // Verifica mídia
        $mediaStmt = $db->prepare("SELECT * FROM communication_media WHERE event_id = ?");
        $mediaStmt->execute([$event['event_id']]);
        $media = $mediaStmt->fetch();
        
        echo "Mídia:      " . ($media ? "✅ SIM (path: {$media['stored_path']})" : "❌ NÃO") . "\n";
        
        // Mostra payload resumido
        echo "\nPayload (resumido):\n";
        $payloadForDisplay = $payload;
        // Remove campos muito longos
        if (isset($payloadForDisplay['base64Ptt'])) {
            $payloadForDisplay['base64Ptt'] = '[BASE64... ' . strlen($payload['base64Ptt']) . ' chars]';
        }
        echo json_encode($payloadForDisplay, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
