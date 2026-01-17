<?php
/**
 * PATCH J — Normalizar histórico inbound com tenant_id=NULL e unificar conversas
 * 
 * Objetivo:
 * Garantir que mensagens recebidas antes da criação do canal (quando tenant_id era NULL)
 * não fiquem "órfãs" e que a UI não pareça quebrada.
 * 
 * ATENÇÃO: Execute em MODO DRY-RUN primeiro para ver o que será alterado!
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== PATCH J — NORMALIZAR INBOUND ÓRFÃOS ===\n\n";

// Modo: dry-run (apenas visualiza) ou apply (executa)
$mode = $_SERVER['argv'][1] ?? 'dry-run';
$targetTenantId = isset($_SERVER['argv'][2]) ? (int) $_SERVER['argv'][2] : 121;
$channelId = 'pixel12digital';

if ($mode !== 'dry-run' && $mode !== 'apply') {
    echo "❌ Modo inválido. Use: dry-run ou apply\n";
    echo "   Exemplo: php patch-j-normalizar-inbound-orphans.php dry-run\n";
    echo "   Exemplo: php patch-j-normalizar-inbound-orphans.php apply 121\n";
    exit(1);
}

echo "Modo: " . strtoupper($mode) . "\n";
echo "Tenant ID alvo: {$targetTenantId}\n";
echo "Channel ID: {$channelId}\n\n";

if ($mode === 'dry-run') {
    echo "⚠️  MODO DRY-RUN: Nenhuma alteração será feita. Apenas visualização.\n\n";
}

$db = DB::getConnection();

try {
    $db->beginTransaction();
    
    // ==========================================
    // A) DIAGNÓSTICO: Eventos órfãos
    // ==========================================
    
    echo "1. DIAGNÓSTICO: Eventos órfãos (tenant_id=NULL)\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->prepare("
        SELECT COUNT(*) AS qtd
        FROM communication_events
        WHERE source_system = 'wpp_gateway'
          AND (tenant_id IS NULL OR tenant_id = 0)
          AND (
              JSON_EXTRACT(metadata, '$.channel_id') = ?
              OR JSON_EXTRACT(payload, '$.session.id') = ?
              OR JSON_EXTRACT(payload, '$.sessionId') = ?
              OR JSON_EXTRACT(payload, '$.channelId') = ?
          )
    ");
    $stmt->execute([$channelId, $channelId, $channelId, $channelId]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    $orphanCount = (int) ($count['qtd'] ?? 0);
    
    echo "   Total de eventos órfãos encontrados: {$orphanCount}\n\n";
    
    if ($orphanCount === 0) {
        echo "✅ Nenhum evento órfão encontrado. Nada a fazer.\n\n";
        $db->rollBack();
        exit(0);
    }
    
    // Amostra dos últimos órfãos
    $stmt = $db->prepare("
        SELECT id, event_id, created_at, tenant_id,
               JSON_EXTRACT(metadata, '$.channel_id') AS metadata_channel_id,
               JSON_EXTRACT(metadata, '$.thread_id') AS thread_id,
               JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) AS from_id,
               JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS message_from
        FROM communication_events
        WHERE source_system = 'wpp_gateway'
          AND (tenant_id IS NULL OR tenant_id = 0)
          AND (
              JSON_EXTRACT(metadata, '$.channel_id') = ?
              OR JSON_EXTRACT(payload, '$.session.id') = ?
              OR JSON_EXTRACT(payload, '$.sessionId') = ?
              OR JSON_EXTRACT(payload, '$.channelId') = ?
          )
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$channelId, $channelId, $channelId, $channelId]);
    $orphans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Amostra dos últimos {$orphanCount} evento(s) órfãos:\n\n";
    foreach ($orphans as $idx => $orphan) {
        $fromId = $orphan['from_id'] ?: $orphan['message_from'] ?: 'N/A';
        echo sprintf(
            "   [%d] ID=%d | event_id=%s | tenant_id=%s | created_at=%s | from=%s | thread_id=%s\n",
            $idx + 1,
            $orphan['id'],
            substr($orphan['event_id'], 0, 8) . '...',
            $orphan['tenant_id'] ?: 'NULL',
            $orphan['created_at'],
            substr($fromId, 0, 20),
            $orphan['thread_id'] ?: 'NULL'
        );
    }
    
    echo "\n\n";
    
    // ==========================================
    // B) DIAGNÓSTICO: Conversations órfãs
    // ==========================================
    
    echo "2. DIAGNÓSTICO: Conversations órfãs (tenant_id=NULL)\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->prepare("
        SELECT id, conversation_key, tenant_id, channel_id, contact_external_id, created_at
        FROM conversations
        WHERE (tenant_id IS NULL OR tenant_id = 0)
          AND channel_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$channelId]);
    $orphanConvs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $orphanConvCount = count($orphanConvs);
    
    echo "   Total de conversations órfãs encontradas: {$orphanConvCount}\n\n";
    
    if ($orphanConvCount > 0) {
        echo "   Conversations órfãs:\n\n";
        foreach ($orphanConvs as $idx => $conv) {
            echo sprintf(
                "   [%d] ID=%d | conversation_key=%s | tenant_id=%s | channel_id=%s | contact=%s | created_at=%s\n",
                $idx + 1,
                $conv['id'],
                $conv['conversation_key'] ?: 'NULL',
                $conv['tenant_id'] ?: 'NULL',
                $conv['channel_id'],
                $conv['contact_external_id'] ?: 'NULL',
                $conv['created_at']
            );
        }
    } else {
        echo "   ✅ Nenhuma conversation órfã encontrada.\n";
    }
    
    echo "\n\n";
    
    // ==========================================
    // C) VALIDAÇÃO: Verificar se tenant existe
    // ==========================================
    
    echo "3. VALIDAÇÃO: Verificar tenant alvo\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
    $stmt->execute([$targetTenantId]);
    $targetTenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$targetTenant) {
        echo "❌ ERRO: Tenant ID {$targetTenantId} não encontrado!\n";
        $db->rollBack();
        exit(1);
    }
    
    echo "   ✅ Tenant encontrado: ID={$targetTenant['id']}, Nome='{$targetTenant['name']}'\n\n";
    
    // Verificar se tenant tem canal habilitado
    $stmt = $db->prepare("
        SELECT id, channel_id, is_enabled 
        FROM tenant_message_channels 
        WHERE tenant_id = ? 
          AND provider = 'wpp_gateway' 
          AND channel_id = ?
          AND is_enabled = 1
    ");
    $stmt->execute([$targetTenantId, $channelId]);
    $channel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$channel) {
        echo "⚠️  AVISO: Tenant {$targetTenantId} não possui canal '{$channelId}' habilitado!\n";
        echo "   Será atualizado mesmo assim, mas verifique se isso é correto.\n\n";
    } else {
        echo "   ✅ Canal habilitado encontrado: channel_id='{$channel['channel_id']}'\n\n";
    }
    
    // ==========================================
    // D) APLICAÇÃO: Atualizar eventos órfãos
    // ==========================================
    
    if ($mode === 'apply') {
        echo "4. APLICAÇÃO: Atualizando eventos órfãos\n";
        echo str_repeat("-", 80) . "\n";
        
        $stmt = $db->prepare("
            UPDATE communication_events
            SET tenant_id = ?,
                updated_at = NOW()
            WHERE source_system = 'wpp_gateway'
              AND (tenant_id IS NULL OR tenant_id = 0)
              AND (
                  JSON_EXTRACT(metadata, '$.channel_id') = ?
                  OR JSON_EXTRACT(payload, '$.session.id') = ?
                  OR JSON_EXTRACT(payload, '$.sessionId') = ?
                  OR JSON_EXTRACT(payload, '$.channelId') = ?
              )
        ");
        $stmt->execute([$targetTenantId, $channelId, $channelId, $channelId, $channelId]);
        $affectedEvents = $stmt->rowCount();
        
        echo "   ✅ {$affectedEvents} evento(s) atualizado(s) para tenant_id={$targetTenantId}\n\n";
        
        // ==========================================
        // E) APLICAÇÃO: Atualizar conversations órfãs
        // ==========================================
        
        echo "5. APLICAÇÃO: Atualizando conversations órfãs\n";
        echo str_repeat("-", 80) . "\n";
        
        if ($orphanConvCount > 0) {
            $stmt = $db->prepare("
                UPDATE conversations
                SET tenant_id = ?,
                    updated_at = NOW()
                WHERE (tenant_id IS NULL OR tenant_id = 0)
                  AND channel_id = ?
            ");
            $stmt->execute([$targetTenantId, $channelId]);
            $affectedConvs = $stmt->rowCount();
            
            echo "   ✅ {$affectedConvs} conversation(s) atualizada(s) para tenant_id={$targetTenantId}\n\n";
        } else {
            echo "   ℹ️  Nenhuma conversation órfã para atualizar.\n\n";
        }
        
        $db->commit();
        
        echo "✅ PATCH J aplicado com sucesso!\n\n";
    } else {
        echo "4. SIMULAÇÃO: O que seria atualizado (MODO DRY-RUN)\n";
        echo str_repeat("-", 80) . "\n";
        echo "   Se executado em modo 'apply', seriam atualizados:\n";
        echo "   - {$orphanCount} evento(s) em communication_events\n";
        echo "   - {$orphanConvCount} conversation(s) em conversations\n";
        echo "   - Todos para tenant_id={$targetTenantId}\n\n";
        
        $db->rollBack();
        
        echo "ℹ️  Para aplicar as mudanças, execute:\n";
        echo "   php patch-j-normalizar-inbound-orphans.php apply {$targetTenantId}\n\n";
    }
    
    // ==========================================
    // F) VALIDAÇÃO FINAL
    // ==========================================
    
    if ($mode === 'apply') {
        echo "6. VALIDAÇÃO FINAL\n";
        echo str_repeat("-", 80) . "\n";
        
        // Verifica se ainda há eventos órfãos
        $stmt = $db->prepare("
            SELECT COUNT(*) AS qtd
            FROM communication_events
            WHERE source_system = 'wpp_gateway'
              AND (tenant_id IS NULL OR tenant_id = 0)
              AND (
                  JSON_EXTRACT(metadata, '$.channel_id') = ?
                  OR JSON_EXTRACT(payload, '$.session.id') = ?
                  OR JSON_EXTRACT(payload, '$.sessionId') = ?
                  OR JSON_EXTRACT(payload, '$.channelId') = ?
              )
        ");
        $stmt->execute([$channelId, $channelId, $channelId, $channelId]);
        $remainingCount = $stmt->fetch(PDO::FETCH_ASSOC)['qtd'] ?? 0;
        
        if ($remainingCount === 0) {
            echo "   ✅ Nenhum evento órfão restante. Normalização completa!\n";
        } else {
            echo "   ⚠️  Ainda existem {$remainingCount} evento(s) órfão(s). Verifique a query.\n";
        }
        
        // Verifica se ainda há conversations órfãs
        $stmt = $db->prepare("
            SELECT COUNT(*) AS qtd
            FROM conversations
            WHERE (tenant_id IS NULL OR tenant_id = 0)
              AND channel_id = ?
        ");
        $stmt->execute([$channelId]);
        $remainingConvCount = $stmt->fetch(PDO::FETCH_ASSOC)['qtd'] ?? 0;
        
        if ($remainingConvCount === 0) {
            echo "   ✅ Nenhuma conversation órfã restante.\n";
        } else {
            echo "   ⚠️  Ainda existem {$remainingConvCount} conversation(s) órfã(s). Verifique.\n";
        }
        
        echo "\n";
    }
    
    echo str_repeat("=", 80) . "\n";
    echo "Patch J concluído.\n";
    
} catch (\Exception $e) {
    $db->rollBack();
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

