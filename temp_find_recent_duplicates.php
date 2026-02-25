<?php
/**
 * Buscar conversas recentes que podem estar duplicando
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

use PixelHub\Core\DB;

try {
    $db = DB::getConnection();
    
    echo "=== BUSCAR DUPLICIDADES RECENTES ===\n\n";
    
    // 1. Buscar números com múltiplas conversas
    echo "1. NÚMEROS COM MÚLTIPLAS CONVERSAS:\n";
    echo str_repeat("-", 100) . "\n";
    
    $stmt = $db->query("
        SELECT 
            contact_external_id,
            COUNT(*) as total,
            GROUP_CONCAT(id ORDER BY created_at DESC) as conversation_ids,
            GROUP_CONCAT(COALESCE(tenant_id, 'NULL') ORDER BY created_at DESC) as tenant_ids,
            GROUP_CONCAT(channel_id ORDER BY created_at DESC) as channel_ids,
            MAX(created_at) as last_created
        FROM conversations
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY contact_external_id
        HAVING COUNT(*) > 1
        ORDER BY last_created DESC
        LIMIT 20
    ");
    
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo "✅ Nenhuma duplicidade encontrada nos últimos 7 dias\n\n";
    } else {
        echo "⚠️ Encontrados " . count($duplicates) . " números com múltiplas conversas:\n\n";
        
        foreach ($duplicates as $dup) {
            echo sprintf(
                "Número: %s (%d conversas)\n" .
                "  IDs: %s\n" .
                "  Tenants: %s\n" .
                "  Canais: %s\n" .
                "  Última criação: %s\n",
                $dup['contact_external_id'],
                $dup['total'],
                $dup['conversation_ids'],
                $dup['tenant_ids'],
                $dup['channel_ids'],
                $dup['last_created']
            );
            echo str_repeat("-", 100) . "\n";
        }
    }
    
    // 2. Buscar conversas criadas nas últimas 2 horas (período do problema)
    echo "\n2. CONVERSAS CRIADAS NAS ÚLTIMAS 2 HORAS:\n";
    echo str_repeat("-", 100) . "\n";
    
    $stmt = $db->query("
        SELECT 
            c.id,
            c.contact_external_id,
            c.contact_name,
            c.channel_id,
            c.tenant_id,
            t.name as tenant_name,
            c.is_incoming_lead,
            c.created_at,
            c.conversation_key
        FROM conversations c
        LEFT JOIN tenants t ON c.tenant_id = t.id
        WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ORDER BY c.created_at DESC
    ");
    
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Encontradas " . count($recent) . " conversas:\n\n";
    
    foreach ($recent as $conv) {
        echo sprintf(
            "ID: %d | %s\n" .
            "  Número: %s | Nome: %s\n" .
            "  Tenant: %s (%s) | Lead: %s\n" .
            "  Canal: %s | Key: %s\n",
            $conv['id'],
            $conv['created_at'],
            $conv['contact_external_id'],
            $conv['contact_name'] ?: 'NULL',
            $conv['tenant_id'] ? "#{$conv['tenant_id']}" : 'NULL',
            $conv['tenant_name'] ?: 'não vinculado',
            $conv['is_incoming_lead'] ? 'SIM' : 'NÃO',
            $conv['channel_id'] ?: 'NULL',
            $conv['conversation_key']
        );
        echo str_repeat("-", 100) . "\n";
    }
    
    // 3. Analisar padrão de conversation_key
    echo "\n3. ANÁLISE DE CONVERSATION_KEYS:\n";
    echo str_repeat("-", 100) . "\n";
    
    // Agrupar por número e verificar se têm keys diferentes
    $byNumber = [];
    foreach ($recent as $conv) {
        $num = $conv['contact_external_id'];
        if (!isset($byNumber[$num])) {
            $byNumber[$num] = [];
        }
        $byNumber[$num][] = $conv;
    }
    
    foreach ($byNumber as $num => $convs) {
        if (count($convs) > 1) {
            echo "⚠️ Número $num tem " . count($convs) . " conversas:\n";
            foreach ($convs as $c) {
                echo sprintf(
                    "  - ID %d: key=%s, tenant=%s, channel_account_id=%s\n",
                    $c['id'],
                    $c['conversation_key'],
                    $c['tenant_id'] ?: 'NULL',
                    preg_match('/whatsapp_(\d+)_/', $c['conversation_key'], $m) ? $m[1] : 'N/A'
                );
            }
            echo "\n";
        }
    }
    
    // 4. Verificar eventos outbound recentes que podem ter causado duplicatas
    echo "\n4. EVENTOS OUTBOUND RECENTES (últimas 2h):\n";
    echo str_repeat("-", 100) . "\n";
    
    $stmt = $db->query("
        SELECT 
            ce.id,
            ce.event_id,
            ce.tenant_id,
            t.name as tenant_name,
            JSON_EXTRACT(ce.payload, '$.to') as to_number,
            JSON_EXTRACT(ce.metadata, '$.channel_id') as channel_id,
            JSON_EXTRACT(ce.metadata, '$.sent_by_name') as sent_by,
            ce.created_at
        FROM communication_events ce
        LEFT JOIN tenants t ON ce.tenant_id = t.id
        WHERE ce.event_type = 'whatsapp.outbound.message'
          AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ORDER BY ce.created_at DESC
        LIMIT 20
    ");
    
    $outboundEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Encontrados " . count($outboundEvents) . " eventos outbound:\n\n";
    
    foreach ($outboundEvents as $event) {
        $to = trim($event['to_number'] ?: 'NULL', '"');
        
        // Verificar se existe conversa para este número
        $checkStmt = $db->prepare("
            SELECT COUNT(*) as total
            FROM conversations
            WHERE contact_external_id = ?
        ");
        $checkStmt->execute([$to]);
        $convCount = $checkStmt->fetchColumn();
        
        echo sprintf(
            "Event #%d | %s\n" .
            "  Para: %s (%d conversas existentes)\n" .
            "  Tenant: %s (%s) | Canal: %s\n",
            $event['id'],
            $event['created_at'],
            $to,
            $convCount,
            $event['tenant_id'] ? "#{$event['tenant_id']}" : 'NULL',
            $event['tenant_name'] ?: 'não vinculado',
            trim($event['channel_id'] ?: 'NULL', '"')
        );
        
        if ($convCount > 1) {
            echo "  ⚠️ DUPLICIDADE DETECTADA!\n";
        }
        
        echo str_repeat("-", 100) . "\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
