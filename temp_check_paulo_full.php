<?php
/**
 * Script de diagnóstico completo: Paulo (554796517660)
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

use PixelHub\Core\DB;

try {
    $db = DB::getConnection();
    
    $pauloPhone = '554796517660';
    
    echo "=== DIAGNÓSTICO COMPLETO: Paulo ($pauloPhone) ===\n\n";
    
    // 1. Todas as conversas do Paulo
    echo "1. TODAS AS CONVERSAS DO PAULO:\n";
    echo str_repeat("-", 100) . "\n";
    
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.conversation_key,
            c.contact_external_id,
            c.contact_name,
            c.channel_id,
            c.channel_account_id,
            c.tenant_id,
            t.name as tenant_name,
            c.is_incoming_lead,
            c.unread_count,
            c.created_at,
            c.last_message_at
        FROM conversations c
        LEFT JOIN tenants t ON c.tenant_id = t.id
        WHERE c.contact_external_id = ?
           OR c.contact_external_id LIKE ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$pauloPhone, "%{$pauloPhone}%"]);
    
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($conversations)) {
        echo "❌ Nenhuma conversa encontrada\n\n";
    } else {
        echo "✅ Encontradas " . count($conversations) . " conversas:\n\n";
        
        foreach ($conversations as $conv) {
            echo sprintf(
                "ID: %d | Key: %s\n" .
                "  Contact: %s | Name: %s\n" .
                "  Channel: %s (account_id: %s)\n" .
                "  Tenant: %s (%s) | Lead: %s\n" .
                "  Criada: %s | Última msg: %s\n",
                $conv['id'],
                $conv['conversation_key'],
                $conv['contact_external_id'],
                $conv['contact_name'] ?: 'NULL',
                $conv['channel_id'] ?: 'NULL',
                $conv['channel_account_id'] ?: 'NULL',
                $conv['tenant_id'] ? "#{$conv['tenant_id']}" : 'NULL',
                $conv['tenant_name'] ?: 'não vinculado',
                $conv['is_incoming_lead'] ? 'SIM' : 'NÃO',
                $conv['created_at'],
                $conv['last_message_at'] ?: 'NULL'
            );
            echo str_repeat("-", 100) . "\n";
        }
    }
    
    // 2. Todos os eventos do Paulo
    echo "\n2. EVENTOS DO PAULO (últimas 48h):\n";
    echo str_repeat("-", 100) . "\n";
    
    $stmt = $db->prepare("
        SELECT 
            ce.id,
            ce.event_id,
            ce.event_type,
            ce.source_system,
            ce.tenant_id,
            t.name as tenant_name,
            JSON_EXTRACT(ce.payload, '$.to') as to_number,
            JSON_EXTRACT(ce.payload, '$.from') as from_number,
            JSON_EXTRACT(ce.payload, '$.text') as message_text,
            JSON_EXTRACT(ce.metadata, '$.sent_by_name') as sent_by,
            JSON_EXTRACT(ce.metadata, '$.channel_id') as channel_id,
            ce.created_at
        FROM communication_events ce
        LEFT JOIN tenants t ON ce.tenant_id = t.id
        WHERE ce.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
          AND (
              JSON_EXTRACT(ce.payload, '$.to') LIKE ?
              OR JSON_EXTRACT(ce.payload, '$.from') LIKE ?
          )
        ORDER BY ce.created_at DESC
    ");
    $stmt->execute(["%{$pauloPhone}%", "%{$pauloPhone}%"]);
    
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($events)) {
        echo "❌ Nenhum evento encontrado\n\n";
    } else {
        echo "✅ Encontrados " . count($events) . " eventos:\n\n";
        
        foreach ($events as $event) {
            $to = trim($event['to_number'] ?: 'NULL', '"');
            $from = trim($event['from_number'] ?: 'NULL', '"');
            $text = trim($event['message_text'] ?: '', '"');
            $text = strlen($text) > 50 ? substr($text, 0, 50) . '...' : $text;
            
            echo sprintf(
                "Event #%d | %s | %s\n" .
                "  From: %s → To: %s\n" .
                "  Tenant: %s (%s) | Canal: %s\n" .
                "  Enviado por: %s | Texto: %s\n",
                $event['id'],
                $event['created_at'],
                $event['event_type'],
                $from,
                $to,
                $event['tenant_id'] ? "#{$event['tenant_id']}" : 'NULL',
                $event['tenant_name'] ?: 'não vinculado',
                trim($event['channel_id'] ?: 'NULL', '"'),
                trim($event['sent_by'] ?: 'NULL', '"'),
                $text
            );
            echo str_repeat("-", 100) . "\n";
        }
    }
    
    // 3. Verificar se Paulo é um tenant
    echo "\n3. PAULO COMO TENANT:\n";
    echo str_repeat("-", 100) . "\n";
    
    $stmt = $db->prepare("
        SELECT 
            id,
            name,
            phone,
            email
        FROM tenants
        WHERE phone LIKE ?
           OR name LIKE '%Paulo%'
        ORDER BY id DESC
    ");
    $stmt->execute(["%{$pauloPhone}%"]);
    
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($tenants)) {
        echo "❌ Paulo não é um tenant cadastrado\n\n";
    } else {
        echo "✅ Encontrados " . count($tenants) . " tenants:\n\n";
        foreach ($tenants as $tenant) {
            echo sprintf(
                "Tenant #%d: %s\n  Phone: %s | Email: %s\n",
                $tenant['id'],
                $tenant['name'],
                $tenant['phone'] ?: 'NULL',
                $tenant['email'] ?: 'NULL'
            );
            echo str_repeat("-", 100) . "\n";
        }
    }
    
    // 4. Análise do problema
    echo "\n4. ANÁLISE DO PROBLEMA:\n";
    echo str_repeat("-", 100) . "\n";
    
    if (count($conversations) > 1) {
        echo "⚠️ PROBLEMA CONFIRMADO: Múltiplas conversas para o mesmo número!\n\n";
        
        $vinculadas = array_filter($conversations, fn($c) => $c['tenant_id'] !== null);
        $naoVinculadas = array_filter($conversations, fn($c) => $c['tenant_id'] === null);
        
        echo "Conversas vinculadas: " . count($vinculadas) . "\n";
        echo "Conversas NÃO vinculadas: " . count($naoVinculadas) . "\n\n";
        
        if (!empty($naoVinculadas)) {
            echo "⚠️ Conversas não vinculadas (PROBLEMA):\n";
            foreach ($naoVinculadas as $c) {
                echo sprintf(
                    "  - ID %d: criada em %s, lead=%s, canal=%s\n",
                    $c['id'],
                    $c['created_at'],
                    $c['is_incoming_lead'] ? 'SIM' : 'NÃO',
                    $c['channel_id'] ?: 'NULL'
                );
            }
            echo "\n";
        }
        
        if (!empty($vinculadas)) {
            echo "✅ Conversas vinculadas (CORRETAS):\n";
            foreach ($vinculadas as $c) {
                echo sprintf(
                    "  - ID %d: tenant #%d (%s), criada em %s\n",
                    $c['id'],
                    $c['tenant_id'],
                    $c['tenant_name'],
                    $c['created_at']
                );
            }
        }
    } else {
        echo "✅ Apenas 1 conversa encontrada (sem duplicidade)\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
