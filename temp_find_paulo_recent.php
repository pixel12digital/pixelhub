<?php
/**
 * Script de diagnóstico: Buscar conversas recentes e identificar o Paulo
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

use PixelHub\Core\DB;

try {
    $db = DB::getConnection();
    
    echo "=== BUSCAR CONVERSAS RECENTES (últimas 24h) ===\n\n";
    
    // Buscar todas as conversas criadas nas últimas 24h
    $stmt = $db->query("
        SELECT 
            c.id,
            c.conversation_key,
            c.contact_external_id,
            c.contact_name,
            c.channel_id,
            c.tenant_id,
            t.name as tenant_name,
            c.is_incoming_lead,
            c.created_at,
            c.last_message_at
        FROM conversations c
        LEFT JOIN tenants t ON c.tenant_id = t.id
        WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY c.created_at DESC
        LIMIT 30
    ");
    
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Conversas criadas nas últimas 24h:\n";
    echo str_repeat("-", 100) . "\n";
    
    foreach ($conversations as $conv) {
        echo sprintf(
            "ID: %d | Criada: %s\n" .
            "  Nome: %s | Telefone: %s\n" .
            "  Tenant: %s (%s) | Lead: %s | Canal: %s\n",
            $conv['id'],
            $conv['created_at'],
            $conv['contact_name'] ?: 'NULL',
            $conv['contact_external_id'],
            $conv['tenant_id'] ? "#{$conv['tenant_id']}" : 'NULL',
            $conv['tenant_name'] ?: 'não vinculado',
            $conv['is_incoming_lead'] ? 'SIM' : 'NÃO',
            $conv['channel_id'] ?: 'NULL'
        );
        echo str_repeat("-", 100) . "\n";
    }
    
    // Buscar eventos outbound recentes
    echo "\n\n=== EVENTOS OUTBOUND RECENTES (últimas 2h) ===\n\n";
    
    $stmt = $db->query("
        SELECT 
            ce.id,
            ce.event_id,
            ce.event_type,
            ce.tenant_id,
            t.name as tenant_name,
            JSON_EXTRACT(ce.payload, '$.to') as to_number,
            JSON_EXTRACT(ce.payload, '$.message.to') as message_to,
            JSON_EXTRACT(ce.payload, '$.text') as message_text,
            JSON_EXTRACT(ce.metadata, '$.sent_by_name') as sent_by,
            JSON_EXTRACT(ce.metadata, '$.channel_id') as channel_id,
            ce.created_at
        FROM communication_events ce
        LEFT JOIN tenants t ON ce.tenant_id = t.id
        WHERE ce.event_type = 'whatsapp.outbound.message'
          AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ORDER BY ce.created_at DESC
        LIMIT 20
    ");
    
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($events as $event) {
        $to = trim($event['to_number'] ?: $event['message_to'] ?: 'NULL', '"');
        $text = trim($event['message_text'] ?: '', '"');
        $text = strlen($text) > 50 ? substr($text, 0, 50) . '...' : $text;
        
        echo sprintf(
            "Event #%d | %s\n" .
            "  Para: %s | Canal: %s\n" .
            "  Tenant: %s (%s) | Enviado por: %s\n" .
            "  Texto: %s\n",
            $event['id'],
            $event['created_at'],
            $to,
            trim($event['channel_id'] ?: 'NULL', '"'),
            $event['tenant_id'] ? "#{$event['tenant_id']}" : 'NULL',
            $event['tenant_name'] ?: 'não vinculado',
            trim($event['sent_by'] ?: 'NULL', '"'),
            $text
        );
        echo str_repeat("-", 100) . "\n";
    }
    
    // Buscar conversas não vinculadas recentes
    echo "\n\n=== CONVERSAS NÃO VINCULADAS (últimas 24h) ===\n\n";
    
    $stmt = $db->query("
        SELECT 
            c.id,
            c.conversation_key,
            c.contact_external_id,
            c.contact_name,
            c.channel_id,
            c.is_incoming_lead,
            c.created_at,
            c.last_message_at
        FROM conversations c
        WHERE c.tenant_id IS NULL
          AND c.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY c.created_at DESC
        LIMIT 20
    ");
    
    $unlinked = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($unlinked)) {
        echo "✅ Nenhuma conversa não vinculada encontrada\n";
    } else {
        echo "⚠️ Encontradas " . count($unlinked) . " conversas não vinculadas:\n\n";
        
        foreach ($unlinked as $conv) {
            echo sprintf(
                "ID: %d | Criada: %s\n" .
                "  Nome: %s | Telefone: %s\n" .
                "  Lead: %s | Canal: %s\n",
                $conv['id'],
                $conv['created_at'],
                $conv['contact_name'] ?: 'NULL',
                $conv['contact_external_id'],
                $conv['is_incoming_lead'] ? 'SIM' : 'NÃO',
                $conv['channel_id'] ?: 'NULL'
            );
            echo str_repeat("-", 100) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
