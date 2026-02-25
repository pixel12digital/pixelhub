<?php
/**
 * Script de diagnóstico: Investigar conversas duplicadas do Paulo (final 760)
 * 
 * Objetivo: Identificar porque o sistema está criando conversas não vinculadas
 * ao encaminhar mensagens para o Paulo.
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

use PixelHub\Core\DB;

try {
    $db = DB::getConnection();
    
    echo "=== DIAGNÓSTICO: Conversas do Paulo (final 760) ===\n\n";
    
    // 1. Buscar todas as conversas com números terminando em 760
    echo "1. CONVERSAS COM NÚMEROS TERMINANDO EM 760:\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->query("
        SELECT 
            c.id,
            c.conversation_key,
            c.channel_type,
            c.channel_id,
            c.channel_account_id,
            c.contact_external_id,
            c.contact_name,
            c.tenant_id,
            t.name as tenant_name,
            c.is_incoming_lead,
            c.unread_count,
            c.last_message_at,
            c.created_at
        FROM conversations c
        LEFT JOIN tenants t ON c.tenant_id = t.id
        WHERE c.contact_external_id LIKE '%760'
           OR c.contact_external_id LIKE '%760@%'
        ORDER BY c.created_at DESC
    ");
    
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($conversations)) {
        echo "❌ NENHUMA CONVERSA ENCONTRADA com número terminando em 760\n\n";
    } else {
        echo "✅ Encontradas " . count($conversations) . " conversas:\n\n";
        
        foreach ($conversations as $conv) {
            echo sprintf(
                "ID: %d | Key: %s\n" .
                "  Contact: %s | Name: %s\n" .
                "  Channel: %s (account_id: %s)\n" .
                "  Tenant: %s (%s) | Lead: %s\n" .
                "  Unread: %d | Last Msg: %s | Created: %s\n",
                $conv['id'],
                $conv['conversation_key'],
                $conv['contact_external_id'],
                $conv['contact_name'] ?: 'NULL',
                $conv['channel_id'] ?: 'NULL',
                $conv['channel_account_id'] ?: 'NULL',
                $conv['tenant_id'] ? "#{$conv['tenant_id']}" : 'NULL',
                $conv['tenant_name'] ?: 'não vinculado',
                $conv['is_incoming_lead'] ? 'SIM' : 'NÃO',
                $conv['unread_count'],
                $conv['last_message_at'] ?: 'NULL',
                $conv['created_at']
            );
            echo str_repeat("-", 80) . "\n";
        }
    }
    
    // 2. Buscar eventos recentes relacionados a esses números
    echo "\n2. EVENTOS RECENTES (últimas 24h) COM NÚMEROS TERMINANDO EM 760:\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->query("
        SELECT 
            ce.id,
            ce.event_id,
            ce.event_type,
            ce.source_system,
            ce.tenant_id,
            t.name as tenant_name,
            JSON_EXTRACT(ce.payload, '$.to') as payload_to,
            JSON_EXTRACT(ce.payload, '$.from') as payload_from,
            JSON_EXTRACT(ce.payload, '$.message.to') as message_to,
            JSON_EXTRACT(ce.payload, '$.message.from') as message_from,
            JSON_EXTRACT(ce.metadata, '$.channel_id') as metadata_channel_id,
            JSON_EXTRACT(ce.metadata, '$.sent_by_name') as sent_by_name,
            ce.created_at
        FROM communication_events ce
        LEFT JOIN tenants t ON ce.tenant_id = t.id
        WHERE ce.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
          AND (
              JSON_EXTRACT(ce.payload, '$.to') LIKE '%760%'
              OR JSON_EXTRACT(ce.payload, '$.from') LIKE '%760%'
              OR JSON_EXTRACT(ce.payload, '$.message.to') LIKE '%760%'
              OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE '%760%'
          )
        ORDER BY ce.created_at DESC
        LIMIT 20
    ");
    
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($events)) {
        echo "❌ NENHUM EVENTO RECENTE encontrado\n\n";
    } else {
        echo "✅ Encontrados " . count($events) . " eventos:\n\n";
        
        foreach ($events as $event) {
            $to = trim($event['payload_to'] ?: $event['message_to'] ?: 'NULL', '"');
            $from = trim($event['payload_from'] ?: $event['message_from'] ?: 'NULL', '"');
            $channelId = trim($event['metadata_channel_id'] ?: 'NULL', '"');
            $sentBy = trim($event['sent_by_name'] ?: 'NULL', '"');
            
            echo sprintf(
                "Event #%d | Type: %s | Source: %s\n" .
                "  From: %s → To: %s\n" .
                "  Channel: %s | Tenant: %s (%s)\n" .
                "  Sent by: %s | Created: %s\n",
                $event['id'],
                $event['event_type'],
                $event['source_system'],
                $from,
                $to,
                $channelId,
                $event['tenant_id'] ? "#{$event['tenant_id']}" : 'NULL',
                $event['tenant_name'] ?: 'não vinculado',
                $sentBy,
                $event['created_at']
            );
            echo str_repeat("-", 80) . "\n";
        }
    }
    
    // 3. Análise de duplicidade
    echo "\n3. ANÁLISE DE DUPLICIDADE:\n";
    echo str_repeat("-", 80) . "\n";
    
    if (count($conversations) > 1) {
        echo "⚠️ PROBLEMA DETECTADO: Múltiplas conversas para o mesmo contato!\n\n";
        
        // Agrupa por contact_external_id normalizado
        $grouped = [];
        foreach ($conversations as $conv) {
            $normalized = preg_replace('/[^0-9]/', '', $conv['contact_external_id']);
            if (!isset($grouped[$normalized])) {
                $grouped[$normalized] = [];
            }
            $grouped[$normalized][] = $conv;
        }
        
        foreach ($grouped as $phone => $convs) {
            if (count($convs) > 1) {
                echo "📱 Número normalizado: {$phone} - " . count($convs) . " conversas:\n";
                foreach ($convs as $c) {
                    echo sprintf(
                        "  - ID %d: tenant=%s, channel=%s, external_id=%s, lead=%s\n",
                        $c['id'],
                        $c['tenant_id'] ? "#{$c['tenant_id']}" : 'NULL',
                        $c['channel_id'] ?: 'NULL',
                        $c['contact_external_id'],
                        $c['is_incoming_lead'] ? 'SIM' : 'NÃO'
                    );
                }
                echo "\n";
            }
        }
    } else {
        echo "✅ Nenhuma duplicidade detectada (apenas 1 conversa encontrada)\n\n";
    }
    
    // 4. Verificar tenant do Paulo
    echo "\n4. INFORMAÇÕES DO TENANT 'PAULO':\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->query("
        SELECT 
            t.id,
            t.name,
            t.phone,
            t.email,
            COUNT(DISTINCT c.id) as total_conversations
        FROM tenants t
        LEFT JOIN conversations c ON c.tenant_id = t.id
        WHERE t.name LIKE '%Paulo%'
           OR t.phone LIKE '%760'
        GROUP BY t.id
    ");
    
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($tenants)) {
        echo "❌ NENHUM TENANT encontrado com nome Paulo ou telefone terminando em 760\n\n";
    } else {
        foreach ($tenants as $tenant) {
            echo sprintf(
                "Tenant #%d: %s\n" .
                "  Phone: %s | Email: %s\n" .
                "  Total de conversas: %d\n",
                $tenant['id'],
                $tenant['name'],
                $tenant['phone'] ?: 'NULL',
                $tenant['email'] ?: 'NULL',
                $tenant['total_conversations']
            );
            echo str_repeat("-", 80) . "\n";
        }
    }
    
    // 5. Verificar canais WhatsApp
    echo "\n5. CANAIS WHATSAPP CONFIGURADOS:\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->query("
        SELECT 
            id,
            tenant_id,
            channel_id,
            provider,
            is_enabled,
            is_default
        FROM tenant_message_channels
        WHERE provider = 'wpp_gateway'
          AND is_enabled = 1
        ORDER BY tenant_id, channel_id
    ");
    
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($channels as $channel) {
        echo sprintf(
            "Canal #%d: %s\n" .
            "  Tenant: %s | Default: %s\n",
            $channel['id'],
            $channel['channel_id'],
            $channel['tenant_id'] ? "#{$channel['tenant_id']}" : 'NULL',
            $channel['is_default'] ? 'SIM' : 'NÃO'
        );
        echo str_repeat("-", 80) . "\n";
    }
    
    echo "\n=== FIM DO DIAGNÓSTICO ===\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
