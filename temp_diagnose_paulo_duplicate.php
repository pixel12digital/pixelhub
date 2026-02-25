<?php
/**
 * Diagnóstico detalhado: Por que a conversa do Paulo está duplicando?
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

use PixelHub\Core\DB;

try {
    $db = DB::getConnection();
    
    $pauloPhone = '554796517660';
    
    echo "=== DIAGNÓSTICO: Por que Paulo duplica? ===\n\n";
    
    // 1. Verificar TODAS as conversas do Paulo (incluindo deletadas se houver soft delete)
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
            c.created_at,
            c.last_message_at,
            c.status
        FROM conversations c
        LEFT JOIN tenants t ON c.tenant_id = t.id
        WHERE c.contact_external_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$pauloPhone]);
    
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Encontradas " . count($conversations) . " conversas:\n\n";
    
    foreach ($conversations as $conv) {
        echo sprintf(
            "ID: %d | Key: %s\n" .
            "  Tenant: %s (%s) | Channel: %s (account_id: %s)\n" .
            "  Lead: %s | Status: %s\n" .
            "  Criada: %s | Última msg: %s\n",
            $conv['id'],
            $conv['conversation_key'],
            $conv['tenant_id'] ? "#{$conv['tenant_id']}" : 'NULL',
            $conv['tenant_name'] ?: 'não vinculado',
            $conv['channel_id'] ?: 'NULL',
            $conv['channel_account_id'] ?: 'NULL',
            $conv['is_incoming_lead'] ? 'SIM' : 'NÃO',
            $conv['status'],
            $conv['created_at'],
            $conv['last_message_at'] ?: 'NULL'
        );
        echo str_repeat("-", 100) . "\n";
    }
    
    // 2. Analisar as conversation_keys
    echo "\n2. ANÁLISE DAS CONVERSATION KEYS:\n";
    echo str_repeat("-", 100) . "\n";
    
    $keys = array_unique(array_column($conversations, 'conversation_key'));
    
    if (count($keys) > 1) {
        echo "⚠️ PROBLEMA: Múltiplas conversation_keys diferentes para o mesmo contato!\n\n";
        foreach ($keys as $key) {
            $convsWithKey = array_filter($conversations, fn($c) => $c['conversation_key'] === $key);
            echo "Key: $key\n";
            foreach ($convsWithKey as $c) {
                echo "  - ID {$c['id']}: tenant={$c['tenant_id']}, channel_account_id={$c['channel_account_id']}\n";
            }
            echo "\n";
        }
    } else {
        echo "✅ Todas as conversas têm a mesma conversation_key: {$keys[0]}\n\n";
    }
    
    // 3. Verificar tenant do Paulo
    echo "3. PAULO COMO TENANT:\n";
    echo str_repeat("-", 100) . "\n";
    
    $stmt = $db->prepare("
        SELECT id, name, phone
        FROM tenants
        WHERE phone = ? OR phone LIKE ?
    ");
    $stmt->execute([$pauloPhone, "%{$pauloPhone}%"]);
    
    $pauloTenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pauloTenant) {
        echo "✅ Paulo É um tenant:\n";
        echo "  - ID: {$pauloTenant['id']}\n";
        echo "  - Nome: {$pauloTenant['name']}\n";
        echo "  - Phone: {$pauloTenant['phone']}\n\n";
        
        // Verificar se alguma conversa está vinculada a ele
        $vinculadaAoPaulo = array_filter($conversations, fn($c) => $c['tenant_id'] == $pauloTenant['id']);
        if (!empty($vinculadaAoPaulo)) {
            echo "✅ Existe(m) " . count($vinculadaAoPaulo) . " conversa(s) vinculada(s) ao tenant Paulo:\n";
            foreach ($vinculadaAoPaulo as $c) {
                echo "  - ID {$c['id']}: criada em {$c['created_at']}\n";
            }
        } else {
            echo "⚠️ NENHUMA conversa está vinculada ao tenant Paulo (ID {$pauloTenant['id']})\n";
        }
    } else {
        echo "❌ Paulo NÃO é um tenant cadastrado\n\n";
    }
    
    // 4. Simular busca de conversa existente (como o código deveria fazer)
    echo "\n4. SIMULAÇÃO: Como o código deveria buscar a conversa?\n";
    echo str_repeat("-", 100) . "\n";
    
    // Simular busca por conversation_key
    $conversationKey = "whatsapp_3_{$pauloPhone}"; // channel_account_id=3 (pixel12digital)
    
    echo "Buscando por conversation_key: $conversationKey\n";
    
    $stmt = $db->prepare("
        SELECT id, tenant_id, channel_account_id, created_at
        FROM conversations
        WHERE conversation_key = ?
    ");
    $stmt->execute([$conversationKey]);
    
    $found = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($found) {
        echo "✅ ENCONTRADA: ID {$found['id']}, tenant_id={$found['tenant_id']}, created_at={$found['created_at']}\n";
    } else {
        echo "❌ NÃO ENCONTRADA pela conversation_key\n";
    }
    
    // Buscar por contact_external_id + channel_id
    echo "\nBuscando por contact_external_id + channel_id:\n";
    
    $stmt = $db->prepare("
        SELECT id, tenant_id, channel_id, channel_account_id, conversation_key, created_at
        FROM conversations
        WHERE contact_external_id = ?
          AND channel_id = 'pixel12digital'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$pauloPhone]);
    
    $foundByContact = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($foundByContact) {
        echo "✅ ENCONTRADA: ID {$foundByContact['id']}\n";
        echo "  - tenant_id: {$foundByContact['tenant_id']}\n";
        echo "  - channel_account_id: {$foundByContact['channel_account_id']}\n";
        echo "  - conversation_key: {$foundByContact['conversation_key']}\n";
        echo "  - created_at: {$foundByContact['created_at']}\n";
    } else {
        echo "❌ NÃO ENCONTRADA por contact_external_id + channel_id\n";
    }
    
    // 5. Verificar channel_account_id
    echo "\n5. VERIFICAR CHANNEL_ACCOUNT_ID:\n";
    echo str_repeat("-", 100) . "\n";
    
    $stmt = $db->query("
        SELECT id, tenant_id, channel_id, is_enabled
        FROM tenant_message_channels
        WHERE channel_id = 'pixel12digital'
          AND is_enabled = 1
    ");
    
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Canais 'pixel12digital' habilitados:\n";
    foreach ($channels as $ch) {
        echo "  - ID {$ch['id']}: tenant_id={$ch['tenant_id']}, channel_id={$ch['channel_id']}\n";
    }
    
    // 6. Conclusão
    echo "\n6. CONCLUSÃO:\n";
    echo str_repeat("-", 100) . "\n";
    
    if (count($conversations) > 1) {
        echo "⚠️ DUPLICIDADE CONFIRMADA: " . count($conversations) . " conversas para o mesmo número\n\n";
        
        echo "Possíveis causas:\n";
        
        // Verificar se têm channel_account_id diferentes
        $accountIds = array_unique(array_column($conversations, 'channel_account_id'));
        if (count($accountIds) > 1) {
            echo "  1. ⚠️ Conversas com channel_account_id DIFERENTES: " . implode(', ', $accountIds) . "\n";
            echo "     Isso causa conversation_keys diferentes e permite duplicatas\n";
        }
        
        // Verificar se têm tenant_id diferentes
        $tenantIds = array_unique(array_column($conversations, 'tenant_id'));
        if (count($tenantIds) > 1) {
            echo "  2. ⚠️ Conversas vinculadas a tenants DIFERENTES: " . implode(', ', array_map(fn($t) => $t ?: 'NULL', $tenantIds)) . "\n";
            echo "     Cada tenant pode ter sua própria conversa com o mesmo contato\n";
        }
        
        // Verificar se a busca por conversation_key está falhando
        if (!$found && $foundByContact) {
            echo "  3. ⚠️ Busca por conversation_key FALHA, mas busca por contact_external_id FUNCIONA\n";
            echo "     O código pode estar gerando conversation_keys inconsistentes\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
