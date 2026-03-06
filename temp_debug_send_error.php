<?php
/**
 * Script de diagnóstico para investigar erro de envio de mensagem
 * Erro: CONTROLLER_EXCEPTION com lead_id=29
 */

require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;

try {
    $db = DB::getConnection();
    
    echo "=== INVESTIGAÇÃO: Erro ao enviar mensagem para lead_id=29 ===\n\n";
    
    // 1. Verificar dados do lead
    echo "1. DADOS DO LEAD:\n";
    $stmt = $db->prepare("
        SELECT 
            id,
            name,
            phone,
            email,
            converted_tenant_id,
            status,
            source
        FROM leads 
        WHERE id = 29
    ");
    $stmt->execute();
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lead) {
        echo "   ❌ Lead 29 não encontrado!\n\n";
        exit;
    }
    
    echo "   ID: {$lead['id']}\n";
    echo "   Nome: {$lead['name']}\n";
    echo "   Telefone: {$lead['phone']}\n";
    echo "   Email: {$lead['email']}\n";
    echo "   Converted Tenant ID: " . ($lead['converted_tenant_id'] ?: 'NULL') . "\n";
    echo "   Status: {$lead['status']}\n";
    echo "   Origem: {$lead['source']}\n\n";
    
    // 2. Verificar se há tenant vinculado
    if ($lead['converted_tenant_id']) {
        echo "2. DADOS DO TENANT VINCULADO:\n";
        $stmt = $db->prepare("
            SELECT 
                id,
                nome_fantasia,
                razao_social,
                telefone
            FROM tenants 
            WHERE id = ?
        ");
        $stmt->execute([$lead['converted_tenant_id']]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tenant) {
            echo "   ID: {$tenant['id']}\n";
            echo "   Nome Fantasia: {$tenant['nome_fantasia']}\n";
            echo "   Razão Social: {$tenant['razao_social']}\n";
            echo "   Telefone: {$tenant['telefone']}\n\n";
        } else {
            echo "   ❌ Tenant {$lead['converted_tenant_id']} não encontrado!\n\n";
        }
        
        // 3. Verificar canais WhatsApp do tenant
        echo "3. CANAIS WHATSAPP DO TENANT:\n";
        $stmt = $db->prepare("
            SELECT 
                id,
                tenant_id,
                channel_type,
                channel_id,
                display_name,
                is_active,
                provider_type
            FROM tenant_message_channels 
            WHERE tenant_id = ? AND channel_type = 'whatsapp'
        ");
        $stmt->execute([$lead['converted_tenant_id']]);
        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($channels)) {
            echo "   ⚠️ Nenhum canal WhatsApp configurado para este tenant!\n";
            echo "   CAUSA PROVÁVEL DO ERRO: Tenant não tem canal WhatsApp configurado\n\n";
        } else {
            foreach ($channels as $channel) {
                echo "   Canal ID: {$channel['id']}\n";
                echo "   Channel ID: {$channel['channel_id']}\n";
                echo "   Display Name: {$channel['display_name']}\n";
                echo "   Provider Type: {$channel['provider_type']}\n";
                echo "   Ativo: " . ($channel['is_active'] ? 'SIM' : 'NÃO') . "\n";
                echo "   ---\n";
            }
            echo "\n";
        }
    } else {
        echo "2. ⚠️ LEAD NÃO TEM TENANT VINCULADO (converted_tenant_id = NULL)\n";
        echo "   CAUSA PROVÁVEL DO ERRO: Lead não foi convertido em cliente ainda\n\n";
    }
    
    // 4. Verificar conversas do lead
    echo "4. CONVERSAS DO LEAD:\n";
    $stmt = $db->prepare("
        SELECT 
            id,
            tenant_id,
            lead_id,
            channel_type,
            channel_id,
            contact_external_id,
            provider_type,
            created_at
        FROM conversations 
        WHERE lead_id = 29
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($conversations)) {
        echo "   ⚠️ Nenhuma conversa encontrada para este lead\n\n";
    } else {
        foreach ($conversations as $conv) {
            echo "   Conversation ID: {$conv['id']}\n";
            echo "   Tenant ID: " . ($conv['tenant_id'] ?: 'NULL') . "\n";
            echo "   Lead ID: " . ($conv['lead_id'] ?: 'NULL') . "\n";
            echo "   Channel Type: {$conv['channel_type']}\n";
            echo "   Channel ID: " . ($conv['channel_id'] ?: 'NULL') . "\n";
            echo "   Contact External ID: {$conv['contact_external_id']}\n";
            echo "   Provider Type: " . ($conv['provider_type'] ?: 'NULL') . "\n";
            echo "   Created At: {$conv['created_at']}\n";
            echo "   ---\n";
        }
        echo "\n";
    }
    
    // 5. Verificar oportunidades do lead
    echo "5. OPORTUNIDADES DO LEAD:\n";
    $stmt = $db->prepare("
        SELECT 
            id,
            tenant_id,
            lead_id,
            conversation_id,
            status,
            created_at
        FROM opportunities 
        WHERE lead_id = 29
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $opportunities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($opportunities)) {
        echo "   ⚠️ Nenhuma oportunidade encontrada para este lead\n\n";
    } else {
        foreach ($opportunities as $opp) {
            echo "   Opportunity ID: {$opp['id']}\n";
            echo "   Tenant ID: " . ($opp['tenant_id'] ?: 'NULL') . "\n";
            echo "   Lead ID: " . ($opp['lead_id'] ?: 'NULL') . "\n";
            echo "   Conversation ID: " . ($opp['conversation_id'] ?: 'NULL') . "\n";
            echo "   Status: {$opp['status']}\n";
            echo "   Created At: {$opp['created_at']}\n";
            echo "   ---\n";
        }
        echo "\n";
    }
    
    // 6. DIAGNÓSTICO FINAL
    echo "=== DIAGNÓSTICO FINAL ===\n";
    
    if (!$lead['converted_tenant_id']) {
        echo "❌ PROBLEMA IDENTIFICADO:\n";
        echo "   O lead 29 NÃO está vinculado a nenhum tenant (converted_tenant_id = NULL)\n";
        echo "   Para enviar mensagens via API Meta, o lead precisa estar convertido em cliente.\n\n";
        echo "SOLUÇÃO:\n";
        echo "   1. Converter o lead em cliente (tenant) primeiro\n";
        echo "   2. OU usar o canal WhatsApp padrão (não Meta API)\n\n";
    } elseif (empty($channels)) {
        echo "❌ PROBLEMA IDENTIFICADO:\n";
        echo "   O tenant {$lead['converted_tenant_id']} NÃO tem canais WhatsApp configurados\n";
        echo "   Para enviar mensagens, é necessário configurar um canal WhatsApp.\n\n";
        echo "SOLUÇÃO:\n";
        echo "   1. Acessar Configurações → WhatsApp Providers\n";
        echo "   2. Configurar canal WPPConnect ou Meta Official API para o tenant\n\n";
    } else {
        echo "✅ Configuração parece OK. O erro pode estar em outro lugar.\n";
        echo "   Verifique os logs do servidor para mais detalhes.\n\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
