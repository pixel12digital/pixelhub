<?php
/**
 * Script para garantir vínculo do tenant 25 com o canal pixel12digital
 * 
 * OBJETIVO: Garantir que o tenant 25 tenha acesso ao canal pixel12digital
 * na tabela tenant_message_channels
 * 
 * USO: php database/fix-tenant-25-channel.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

echo "=== Fix Tenant 25 Channel Link ===\n\n";

try {
    $db = DB::getConnection();
    
    $tenantId = 25;
    $channelId = 'pixel12digital'; // Pode ser 'pixel12digital', 'Pixel12 Digital', etc.
    $provider = 'wpp_gateway';
    
    // 1. Verifica se o tenant 25 existe
    $stmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
    $stmt->execute([$tenantId]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        echo "❌ ERRO: Tenant 25 não encontrado!\n";
        exit(1);
    }
    
    echo "✓ Tenant encontrado: ID={$tenant['id']}, Nome={$tenant['name']}\n\n";
    
    // 2. Verifica se já existe vínculo para este tenant
    $stmt = $db->prepare("
        SELECT id, channel_id, session_id, is_enabled, tenant_id
        FROM tenant_message_channels
        WHERE tenant_id = ? AND provider = ?
    ");
    $stmt->execute([$tenantId, $provider]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        echo "⚠️  Vínculo existente encontrado:\n";
        echo "   ID: {$existing['id']}\n";
        echo "   channel_id (ANTES): {$existing['channel_id']}\n";
        echo "   session_id (ANTES): " . ($existing['session_id'] ?? 'NULL') . "\n";
        echo "   is_enabled: " . ($existing['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
        echo "   tenant_id: {$existing['tenant_id']}\n\n";
        
        // Verifica se o channel_id corresponde ao pixel12digital (case-insensitive)
        $existingChannelIdLower = strtolower(trim($existing['channel_id']));
        $targetChannelIdLower = strtolower(trim($channelId));
        $normalizedExisting = strtolower(preg_replace('/\s+/', '', $existingChannelIdLower));
        $normalizedTarget = strtolower(preg_replace('/\s+/', '', $targetChannelIdLower));
        
        if ($normalizedExisting === $normalizedTarget || 
            $existingChannelIdLower === $targetChannelIdLower ||
            strpos($normalizedExisting, 'pixel12') !== false) {
            echo "✓ O canal já está vinculado ao tenant 25 (channel_id: {$existing['channel_id']})\n";
            
            // Garante que está habilitado
            if (!$existing['is_enabled']) {
                echo "  Habilitando canal...\n";
                $stmt = $db->prepare("UPDATE tenant_message_channels SET is_enabled = 1 WHERE id = ?");
                $stmt->execute([$existing['id']]);
                echo "✓ Canal habilitado!\n\n";
            } else {
                echo "  O vínculo parece correto. Se ainda houver problemas, verifique:\n";
                echo "  1. Se o channel_id no banco corresponde ao que está sendo enviado no POST\n";
                echo "  2. Se há problemas de normalização (espaços, case, etc.)\n\n";
            }
        } else {
            echo "⚠️  ATENÇÃO: O canal vinculado ({$existing['channel_id']}) não corresponde ao esperado ({$channelId})\n";
            echo "  Atualizando para o canal correto...\n\n";
            
            // Busca o canal correto para usar como referência
            $stmt = $db->prepare("
                SELECT id, channel_id, session_id, is_enabled, tenant_id
                FROM tenant_message_channels
                WHERE provider = ?
                AND (
                    LOWER(TRIM(channel_id)) = LOWER(TRIM(?))
                    OR LOWER(REPLACE(channel_id, ' ', '')) = LOWER(REPLACE(?, ' ', ''))
                    OR LOWER(channel_id) LIKE '%pixel12%'
                )
                AND is_enabled = 1
                ORDER BY id ASC
                LIMIT 1
            ");
            $stmt->execute([$provider, $channelId, $channelId]);
            $sourceChannel = $stmt->fetch();
            
            if ($sourceChannel) {
                // UPDATE do registro existente
                $checkSessionId = $db->query("SHOW COLUMNS FROM tenant_message_channels LIKE 'session_id'")->fetch();
                $hasSessionId = $checkSessionId && $checkSessionId['Field'] === 'session_id';
                
                if ($hasSessionId) {
                    $stmt = $db->prepare("
                        UPDATE tenant_message_channels 
                        SET channel_id = ?, session_id = ?, is_enabled = 1, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $sourceChannel['channel_id'],
                        $sourceChannel['session_id'] ?? $sourceChannel['channel_id'],
                        $existing['id']
                    ]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE tenant_message_channels 
                        SET channel_id = ?, is_enabled = 1, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $sourceChannel['channel_id'],
                        $existing['id']
                    ]);
                }
                
                echo "✓ Registro atualizado!\n";
                echo "   channel_id (DEPOIS): {$sourceChannel['channel_id']}\n";
                echo "   session_id (DEPOIS): " . ($sourceChannel['session_id'] ?? 'NULL') . "\n\n";
            } else {
                echo "❌ Nenhum canal habilitado encontrado para usar como referência.\n";
                echo "  Você precisa criar o canal primeiro ou habilitar um existente.\n\n";
            }
        }
    } else {
        // 3. Busca canais existentes com channel_id similar a pixel12digital
        $stmt = $db->prepare("
            SELECT id, channel_id, session_id, tenant_id, is_enabled
            FROM tenant_message_channels
            WHERE provider = ?
            AND (
                LOWER(TRIM(channel_id)) = LOWER(TRIM(?))
                OR LOWER(REPLACE(channel_id, ' ', '')) = LOWER(REPLACE(?, ' ', ''))
                OR LOWER(channel_id) LIKE '%pixel12%'
            )
            ORDER BY is_enabled DESC, id ASC
            LIMIT 5
        ");
        $stmt->execute([$provider, $channelId, $channelId]);
        $similarChannels = $stmt->fetchAll();
        
        if (!empty($similarChannels)) {
            echo "📋 Canais similares encontrados:\n";
            foreach ($similarChannels as $ch) {
                echo "   ID: {$ch['id']}, channel_id: {$ch['channel_id']}, tenant_id: " . ($ch['tenant_id'] ?? 'NULL') . ", is_enabled: " . ($ch['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
            }
            echo "\n";
            
            // Usa o primeiro canal habilitado encontrado
            $sourceChannel = null;
            foreach ($similarChannels as $ch) {
                if ($ch['is_enabled']) {
                    $sourceChannel = $ch;
                    break;
                }
            }
            
            if (!$sourceChannel && !empty($similarChannels)) {
                $sourceChannel = $similarChannels[0]; // Usa o primeiro mesmo se não estiver habilitado
            }
            
            if ($sourceChannel) {
                echo "📝 Criando vínculo para tenant 25 usando canal existente:\n";
                echo "   channel_id: {$sourceChannel['channel_id']}\n";
                echo "   session_id: " . ($sourceChannel['session_id'] ?? 'NULL') . "\n\n";
                
                // Verifica se existe coluna session_id
                $checkSessionId = $db->query("SHOW COLUMNS FROM tenant_message_channels LIKE 'session_id'")->fetch();
                $hasSessionId = $checkSessionId && $checkSessionId['Field'] === 'session_id';
                
                if ($hasSessionId) {
                    $stmt = $db->prepare("
                        INSERT INTO tenant_message_channels 
                        (tenant_id, provider, channel_id, session_id, is_enabled, created_at, updated_at)
                        VALUES (?, ?, ?, ?, 1, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $tenantId,
                        $provider,
                        $sourceChannel['channel_id'],
                        $sourceChannel['session_id'] ?? $sourceChannel['channel_id']
                    ]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO tenant_message_channels 
                        (tenant_id, provider, channel_id, is_enabled, created_at, updated_at)
                        VALUES (?, ?, ?, 1, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $tenantId,
                        $provider,
                        $sourceChannel['channel_id']
                    ]);
                }
                
                $newId = $db->lastInsertId();
                echo "✓ Vínculo criado com sucesso! ID: {$newId}\n\n";
            } else {
                echo "❌ Nenhum canal habilitado encontrado para usar como referência.\n";
                echo "  Você precisa criar o canal primeiro ou habilitar um existente.\n\n";
            }
        } else {
            echo "⚠️  Nenhum canal similar encontrado no banco.\n";
            echo "  Criando novo registro com channel_id = '{$channelId}'...\n\n";
            
            // Verifica se existe coluna session_id
            $checkSessionId = $db->query("SHOW COLUMNS FROM tenant_message_channels LIKE 'session_id'")->fetch();
            $hasSessionId = $checkSessionId && $checkSessionId['Field'] === 'session_id';
            
            if ($hasSessionId) {
                $stmt = $db->prepare("
                    INSERT INTO tenant_message_channels 
                    (tenant_id, provider, channel_id, session_id, is_enabled, created_at, updated_at)
                    VALUES (?, ?, ?, ?, 1, NOW(), NOW())
                ");
                $stmt->execute([
                    $tenantId,
                    $provider,
                    $channelId,
                    $channelId // session_id = channel_id por padrão
                ]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO tenant_message_channels 
                    (tenant_id, provider, channel_id, is_enabled, created_at, updated_at)
                    VALUES (?, ?, ?, 1, NOW(), NOW())
                ");
                $stmt->execute([
                    $tenantId,
                    $provider,
                    $channelId
                ]);
            }
            
            $newId = $db->lastInsertId();
            echo "✓ Novo canal criado e vinculado ao tenant 25! ID: {$newId}\n\n";
        }
    }
    
    // 4. Verifica novamente o vínculo final
    $stmt = $db->prepare("
        SELECT id, channel_id, session_id, is_enabled, tenant_id
        FROM tenant_message_channels
        WHERE tenant_id = ? AND provider = ?
    ");
    $stmt->execute([$tenantId, $provider]);
    $final = $stmt->fetch();
    
    if ($final) {
        echo "✅ Vínculo final confirmado:\n";
        echo "   ID: {$final['id']}\n";
        echo "   channel_id: {$final['channel_id']}\n";
        echo "   session_id: " . ($final['session_id'] ?? 'NULL') . "\n";
        echo "   is_enabled: " . ($final['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
        echo "   tenant_id: {$final['tenant_id']}\n\n";
        
        if (!$final['is_enabled']) {
            echo "⚠️  ATENÇÃO: O canal está desabilitado (is_enabled = 0)!\n";
            echo "  Habilitando...\n";
            $stmt = $db->prepare("UPDATE tenant_message_channels SET is_enabled = 1 WHERE id = ?");
            $stmt->execute([$final['id']]);
            echo "✓ Canal habilitado!\n\n";
        }
    }
    
    echo "✓ Processo concluído!\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

