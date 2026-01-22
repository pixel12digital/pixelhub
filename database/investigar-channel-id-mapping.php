<?php

/**
 * Script para investigar mapeamento de channel_id e por que foi vinculado errado
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();

echo "=== Investigando mapeamento de channel_id ===\n\n";

$channelId = 'pixel12digital';
$tenantCorreto = 36; // Ponto do Golfe

// 1. Verifica TODOS os canais com este channel_id
echo "1. Todos os canais com channel_id '{$channelId}':\n";
$stmt = $db->prepare("
    SELECT 
        tmc.id,
        tmc.tenant_id,
        tmc.channel_id,
        tmc.is_enabled,
        t.name as tenant_name
    FROM tenant_message_channels tmc
    LEFT JOIN tenants t ON tmc.tenant_id = t.id
    WHERE tmc.channel_id = ?
    AND tmc.provider = 'wpp_gateway'
    ORDER BY tmc.is_enabled DESC, tmc.id ASC
");
$stmt->execute([$channelId]);
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($channels)) {
    echo "   ❌ NENHUM CANAL ENCONTRADO\n";
} else {
    echo "   ✅ Encontrados " . count($channels) . " canal(is):\n";
    foreach ($channels as $ch) {
        echo "   - Channel Account ID: {$ch['id']}\n";
        echo "     Tenant ID: {$ch['tenant_id']} - {$ch['tenant_name']}\n";
        echo "     Enabled: " . ($ch['is_enabled'] ? 'SIM ✅' : 'NÃO ❌') . "\n";
        if ($ch['tenant_id'] == $tenantCorreto) {
            echo "     ⚠️  Este é o tenant CORRETO (Ponto do Golfe)\n";
        }
        echo "\n";
    }
}

// 2. Verifica se o tenant 36 (Ponto do Golfe) tem algum canal configurado
echo "\n2. Canais do tenant 36 (Ponto do Golfe):\n";
$stmt = $db->prepare("
    SELECT 
        tmc.id,
        tmc.tenant_id,
        tmc.channel_id,
        tmc.is_enabled,
        t.name as tenant_name
    FROM tenant_message_channels tmc
    LEFT JOIN tenants t ON tmc.tenant_id = t.id
    WHERE tmc.tenant_id = ?
    AND tmc.provider = 'wpp_gateway'
    ORDER BY tmc.is_enabled DESC
");
$stmt->execute([$tenantCorreto]);
$channelsPontoGolfe = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($channelsPontoGolfe)) {
    echo "   ❌ NENHUM CANAL CONFIGURADO para o tenant 36\n";
    echo "   ⚠️  PROBLEMA: O tenant Ponto do Golfe não tem canal configurado!\n";
} else {
    echo "   ✅ Encontrados " . count($channelsPontoGolfe) . " canal(is):\n";
    foreach ($channelsPontoGolfe as $ch) {
        echo "   - Channel Account ID: {$ch['id']}\n";
        echo "     Channel ID: '{$ch['channel_id']}'\n";
        echo "     Enabled: " . ($ch['is_enabled'] ? 'SIM ✅' : 'NÃO ❌') . "\n";
        echo "\n";
    }
}

// 3. Verifica histórico de atualizações das conversas
echo "\n3. Verificando histórico de tenant_id nas conversas:\n";
$conversationIds = [6, 19];

foreach ($conversationIds as $convId) {
    echo "\n   Conversa ID {$convId}:\n";
    
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.contact_name,
            c.tenant_id,
            c.channel_id,
            c.channel_account_id,
            c.created_at,
            c.updated_at,
            t.name as tenant_name
        FROM conversations c
        LEFT JOIN tenants t ON c.tenant_id = t.id
        WHERE c.id = ?
    ");
    $stmt->execute([$convId]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($conv) {
        echo "     - Tenant atual: ID {$conv['tenant_id']} - {$conv['tenant_name']}\n";
        echo "     - Channel ID: '{$conv['channel_id']}'\n";
        echo "     - Channel Account ID: " . ($conv['channel_account_id'] ?? 'NULL') . "\n";
        echo "     - Criada em: {$conv['created_at']}\n";
        echo "     - Atualizada em: {$conv['updated_at']}\n";
        
        // Verifica qual tenant o channel_id deveria resolver
        if ($conv['channel_id']) {
            $stmt2 = $db->prepare("
                SELECT tenant_id, is_enabled
                FROM tenant_message_channels
                WHERE channel_id = ?
                AND provider = 'wpp_gateway'
                AND is_enabled = 1
                LIMIT 1
            ");
            $stmt2->execute([$conv['channel_id']]);
            $channelMapping = $stmt2->fetch(PDO::FETCH_ASSOC);
            
            if ($channelMapping) {
                $stmt3 = $db->prepare("SELECT name FROM tenants WHERE id = ?");
                $stmt3->execute([$channelMapping['tenant_id']]);
                $expectedTenantName = $stmt3->fetchColumn();
                
                echo "     - Resolução por channel_id: ID {$channelMapping['tenant_id']} - {$expectedTenantName}\n";
                
                if ($channelMapping['tenant_id'] == $conv['tenant_id']) {
                    echo "       ✅ Tenant da conversa CORRESPONDE à resolução por channel_id\n";
                } else {
                    echo "       ❌ Tenant da conversa DIFERE da resolução por channel_id\n";
                    echo "       ⚠️  PROBLEMA: A conversa foi vinculada ao tenant errado\n";
                }
            }
        }
    }
}

// 4. Verifica se há múltiplos canais habilitados com mesmo channel_id
echo "\n\n4. Verificando duplicidade de channel_id entre tenants:\n";
$stmt = $db->prepare("
    SELECT 
        channel_id,
        COUNT(*) as total,
        GROUP_CONCAT(DISTINCT tenant_id ORDER BY tenant_id) as tenant_ids,
        GROUP_CONCAT(DISTINCT CASE WHEN is_enabled = 1 THEN tenant_id END ORDER BY tenant_id) as enabled_tenant_ids
    FROM tenant_message_channels
    WHERE provider = 'wpp_gateway'
    AND channel_id = ?
    GROUP BY channel_id
");
$stmt->execute([$channelId]);
$duplicidade = $stmt->fetch(PDO::FETCH_ASSOC);

if ($duplicidade && $duplicidade['total'] > 1) {
    echo "   ⚠️  PROBLEMA: channel_id '{$channelId}' está mapeado para múltiplos tenants!\n";
    echo "   - Total de canais: {$duplicidade['total']}\n";
    echo "   - Tenant IDs: {$duplicidade['tenant_ids']}\n";
    echo "   - Tenants habilitados: " . ($duplicidade['enabled_tenant_ids'] ?: 'Nenhum') . "\n";
    echo "   ⚠️  Isso pode causar resolução incorreta de tenant_id!\n";
} else {
    echo "   ✅ Não há duplicidade\n";
}

echo "\n=== CONCLUSÃO ===\n";
echo "O sistema resolve tenant_id usando o channel_id via resolveTenantByChannelId().\n";
echo "Se o channel_id 'pixel12digital' está mapeado para o tenant errado, todas as\n";
echo "conversas criadas com esse channel_id serão vinculadas ao tenant errado.\n";
echo "\n=== FIM ===\n";

