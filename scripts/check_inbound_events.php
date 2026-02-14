<?php
/**
 * Script para verificar eventos de inbound WhatsApp recentes
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PixelHub\Core\DB;

echo "=== Verificando eventos de inbound WhatsApp ===\n\n";

$db = DB::getConnection();

// Verifica eventos recentes (últimos 15 minutos)
$sql = "
    SELECT id, event_type, event_id, conversation_id, tenant_id, status, created_at 
    FROM communication_events 
    WHERE event_type = 'whatsapp.inbound.message' 
    AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ORDER BY created_at DESC
    LIMIT 10
";

$stmt = $db->query($sql);
$events = $stmt->fetchAll();

if (empty($events)) {
    echo "❌ Nenhum evento whatsapp.inbound.message encontrado nos últimos 15 minutos.\n\n";
    
    // Verifica se há algum evento recente de qualquer tipo
    $sql2 = "
        SELECT id, event_type, event_id, status, created_at 
        FROM communication_events 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ORDER BY created_at DESC
        LIMIT 5
    ";
    $stmt2 = $db->query($sql2);
    $anyEvents = $stmt2->fetchAll();
    
    if (!empty($anyEvents)) {
        echo "📋 Eventos recentes encontrados (últimos 30 min):\n";
        foreach ($anyEvents as $e) {
            echo "   - {$e['event_type']} | {$e['status']} | {$e['created_at']}\n";
        }
    } else {
        echo "❌ Nenhum evento recente de qualquer tipo.\n";
    }
} else {
    echo "✅ Eventos whatsapp.inbound.message encontrados:\n";
    foreach ($events as $event) {
        echo "\n";
        echo "ID: {$event['id']}\n";
        echo "Event Type: {$event['event_type']}\n";
        echo "Event ID: {$event['event_id']}\n";
        echo "Conversation ID: " . ($event['conversation_id'] ?? 'NULL') . "\n";
        echo "Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n";
        echo "Status: {$event['status']}\n";
        echo "Created At: {$event['created_at']}\n";
    }
}

echo "\n=== Fim da verificação ===\n";
