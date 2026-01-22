<?php

/**
 * Script para investigar por que as conversas foram vinculadas aos tenants errados
 * 
 * Uso: php database/investigar-vinculo-tenant-errado.php
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

// Carrega .env
try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();

echo "=== Investigando vínculo incorreto de tenants ===\n\n";

// IDs das conversas problemáticas
$conversationIds = [6, 19]; // Ponto do Golfe e Renato Silva
$tenantCorreto = 36; // Renato Silva da Silva Júnior | Ponto do Golfe

foreach ($conversationIds as $convId) {
    echo "=== CONVERSA ID: {$convId} ===\n";
    
    // 1. Busca dados da conversa
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.contact_external_id,
            c.contact_name,
            c.tenant_id,
            c.channel_id,
            c.channel_account_id,
            c.is_incoming_lead,
            c.created_at,
            c.last_message_at,
            t.name as tenant_name
        FROM conversations c
        LEFT JOIN tenants t ON c.tenant_id = t.id
        WHERE c.id = ?
    ");
    $stmt->execute([$convId]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conv) {
        echo "   ❌ Conversa não encontrada\n\n";
        continue;
    }
    
    echo "   Conversa: {$conv['contact_name']} ({$conv['contact_external_id']})\n";
    echo "   Tenant atual: ID {$conv['tenant_id']} - {$conv['tenant_name']}\n";
    echo "   Channel ID: " . ($conv['channel_id'] ?? 'NULL') . "\n";
    echo "   Channel Account ID: " . ($conv['channel_account_id'] ?? 'NULL') . "\n";
    echo "   Criada em: {$conv['created_at']}\n";
    echo "   Última mensagem: {$conv['last_message_at']}\n\n";
    
    // 2. Verifica mapeamento de channel_id para tenant
    if ($conv['channel_id']) {
        echo "   2. Verificando mapeamento channel_id '{$conv['channel_id']}' → tenant:\n";
        $stmt = $db->prepare("
            SELECT id, tenant_id, channel_id, is_enabled
            FROM tenant_message_channels
            WHERE channel_id = ?
            AND provider = 'wpp_gateway'
        ");
        $stmt->execute([$conv['channel_id']]);
        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($channels)) {
            echo "      ⚠️  NENHUM CANAL ENCONTRADO com este channel_id\n";
        } else {
            foreach ($channels as $ch) {
                $tenantStmt = $db->prepare("SELECT name FROM tenants WHERE id = ?");
                $tenantStmt->execute([$ch['tenant_id']]);
                $tenantName = $tenantStmt->fetchColumn();
                
                echo "      - Channel Account ID: {$ch['id']}\n";
                echo "        Tenant ID: {$ch['tenant_id']} - {$tenantName}\n";
                echo "        Enabled: " . ($ch['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
                echo "        " . ($ch['tenant_id'] == $conv['tenant_id'] ? '✅' : '❌') . " É o tenant vinculado à conversa\n";
            }
        }
        echo "\n";
    }
    
    // 3. Busca eventos que criaram/atualizaram esta conversa
    echo "   3. Buscando eventos relacionados a esta conversa:\n";
    $contactId = $conv['contact_external_id'];
    
    // Busca eventos com este contact_external_id
    $stmt = $db->prepare("
        SELECT 
            ce.id,
            ce.event_id,
            ce.event_type,
            ce.tenant_id as event_tenant_id,
            ce.source_system,
            ce.created_at,
            JSON_EXTRACT(ce.payload, '$.from') as payload_from,
            JSON_EXTRACT(ce.payload, '$.message.from') as payload_message_from,
            JSON_EXTRACT(ce.metadata, '$.channel_id') as metadata_channel_id,
            JSON_EXTRACT(ce.payload, '$.sessionId') as payload_sessionId,
            JSON_EXTRACT(ce.payload, '$.session.id') as payload_session_id
        FROM communication_events ce
        WHERE (
            JSON_EXTRACT(ce.payload, '$.from') LIKE ?
            OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE ?
            OR JSON_EXTRACT(ce.payload, '$.to') LIKE ?
            OR JSON_EXTRACT(ce.payload, '$.message.to') LIKE ?
        )
        AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        ORDER BY ce.created_at ASC
        LIMIT 10
    ");
    $stmt->execute(["%{$contactId}%", "%{$contactId}%", "%{$contactId}%", "%{$contactId}%"]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($events)) {
        echo "      ⚠️  NENHUM EVENTO ENCONTRADO\n";
    } else {
        echo "      ✅ Encontrados " . count($events) . " evento(s):\n";
        foreach ($events as $idx => $event) {
            $eventTenantStmt = $db->prepare("SELECT name FROM tenants WHERE id = ?");
            $eventTenantStmt->execute([$event['event_tenant_id']]);
            $eventTenantName = $eventTenantStmt->fetchColumn() ?: 'NULL';
            
            echo "      Evento #" . ($idx + 1) . ":\n";
            echo "        - Event ID: {$event['event_id']}\n";
            echo "        - Tipo: {$event['event_type']}\n";
            echo "        - Tenant ID no evento: " . ($event['event_tenant_id'] ?? 'NULL') . " - {$eventTenantName}\n";
            echo "        - Source: {$event['source_system']}\n";
            echo "        - Created: {$event['created_at']}\n";
            echo "        - From: " . ($event['payload_from'] ?? $event['payload_message_from'] ?? 'NULL') . "\n";
            echo "        - Metadata channel_id: " . ($event['metadata_channel_id'] ?? 'NULL') . "\n";
            echo "        - Payload sessionId: " . ($event['payload_sessionId'] ?? 'NULL') . "\n";
            echo "        - Payload session.id: " . ($event['payload_session_id'] ?? 'NULL') . "\n";
            
            // Verifica se o tenant_id do evento corresponde ao tenant da conversa
            if ($event['event_tenant_id'] == $conv['tenant_id']) {
                echo "        ✅ Tenant ID do evento CORRESPONDE ao tenant da conversa\n";
            } else {
                echo "        ❌ Tenant ID do evento DIFERE do tenant da conversa\n";
            }
            echo "\n";
        }
    }
    
    // 4. Verifica se há mapeamento por telefone/nome
    echo "   4. Verificando possível mapeamento por telefone/nome:\n";
    
    // Tenta extrair número do contact_external_id
    $phoneDigits = preg_replace('/[^0-9]/', '', $conv['contact_external_id']);
    if (strlen($phoneDigits) >= 10) {
        // Normaliza para E.164 (adiciona 55 se necessário)
        $phoneE164 = $phoneDigits;
        if (strlen($phoneDigits) == 11 && substr($phoneDigits, 0, 2) != '55') {
            $phoneE164 = '55' . $phoneDigits;
        }
        
        echo "      Número extraído: {$phoneE164}\n";
        
        // Busca tenants com este telefone
        $stmt = $db->prepare("
            SELECT id, name, phone
            FROM tenants
            WHERE phone LIKE ?
            OR phone LIKE ?
        ");
        $stmt->execute(["%{$phoneDigits}%", "%{$phoneE164}%"]);
        $tenantsComTelefone = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($tenantsComTelefone)) {
            echo "      ⚠️  Nenhum tenant encontrado com este telefone\n";
        } else {
            echo "      ✅ Tenants encontrados com este telefone:\n";
            foreach ($tenantsComTelefone as $t) {
                echo "        - ID: {$t['id']} - {$t['name']} (Phone: {$t['phone']})\n";
                if ($t['id'] == $conv['tenant_id']) {
                    echo "          ✅ É o tenant vinculado à conversa\n";
                } elseif ($t['id'] == $tenantCorreto) {
                    echo "          ⚠️  Este é o tenant CORRETO (ID {$tenantCorreto})\n";
                }
            }
        }
    } else {
        echo "      ⚠️  Não foi possível extrair número do contact_external_id\n";
    }
    
    // Verifica por nome
    if ($conv['contact_name']) {
        echo "\n      Verificando por nome '{$conv['contact_name']}':\n";
        $stmt = $db->prepare("
            SELECT id, name
            FROM tenants
            WHERE name LIKE ?
        ");
        $stmt->execute(["%{$conv['contact_name']}%"]);
        $tenantsComNome = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($tenantsComNome)) {
            echo "        ⚠️  Nenhum tenant encontrado com este nome\n";
        } else {
            echo "        ✅ Tenants encontrados com este nome:\n";
            foreach ($tenantsComNome as $t) {
                echo "          - ID: {$t['id']} - {$t['name']}\n";
                if ($t['id'] == $conv['tenant_id']) {
                    echo "            ✅ É o tenant vinculado à conversa\n";
                } elseif ($t['id'] == $tenantCorreto) {
                    echo "            ⚠️  Este é o tenant CORRETO (ID {$tenantCorreto})\n";
                }
            }
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
}

echo "=== FIM DA INVESTIGAÇÃO ===\n";

