<?php

/**
 * Script para criar/atualizar o canal 'pixel12digital'
 * 
 * Uso: php database/fix-pixel12digital-channel.php [tenant_id]
 * Se não especificar tenant_id, cria um canal global (tenant_id=NULL)
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

$tenantId = isset($argv[1]) && is_numeric($argv[1]) ? (int) $argv[1] : null;

echo "=== Corrigindo canal 'pixel12digital' ===\n\n";
if ($tenantId) {
    echo "Tenant ID especificado: {$tenantId}\n";
} else {
    echo "Criando canal global (tenant_id=NULL) - disponível para todos os tenants\n";
}
echo "\n";

try {
    $db = DB::getConnection();
    
    // Verifica estrutura da tabela
    $stmt = $db->query("SHOW COLUMNS FROM tenant_message_channels");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $hasSessionId = in_array('session_id', $columns);
    
    // Busca canal existente (case-insensitive)
    $searchCondition = "LOWER(TRIM(channel_id)) = LOWER(?)";
    if ($hasSessionId) {
        $searchCondition = "LOWER(TRIM(channel_id)) = LOWER(?) OR LOWER(TRIM(session_id)) = LOWER(?)";
    }
    $stmt = $db->prepare("
        SELECT * 
        FROM tenant_message_channels 
        WHERE provider = 'wpp_gateway'
        AND ($searchCondition)
        LIMIT 1
    ");
    if ($hasSessionId) {
        $stmt->execute(['pixel12digital', 'pixel12digital']);
    } else {
        $stmt->execute(['pixel12digital']);
    }
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo "✓ Canal existente encontrado (ID: {$existing['id']})\n";
        echo "  - Channel ID: " . ($existing['channel_id'] ?? 'NULL') . "\n";
        if ($hasSessionId) {
            echo "  - Session ID: " . ($existing['session_id'] ?? 'NULL') . "\n";
        }
        echo "  - Tenant ID: " . ($existing['tenant_id'] ?? 'NULL') . "\n";
        echo "  - Enabled: " . ($existing['is_enabled'] ? 'SIM' : 'NÃO') . "\n\n";
        
        // Atualiza canal existente
        $updateFields = [];
        $updateParams = [];
        
        // Garante que is_enabled = 1
        if (!$existing['is_enabled']) {
            $updateFields[] = "is_enabled = 1";
            echo "  → Habilitando canal...\n";
        }
        
        // Atualiza tenant_id se fornecido
        if ($tenantId !== null && $existing['tenant_id'] != $tenantId) {
            $updateFields[] = "tenant_id = ?";
            $updateParams[] = $tenantId;
            echo "  → Atualizando tenant_id para {$tenantId}...\n";
        } elseif ($tenantId === null && $existing['tenant_id'] !== null) {
            // Se não especificou tenant_id, mantém o atual (ou torna global)
            // Não força tenant_id=NULL se já tem um específico
            echo "  → Mantendo tenant_id atual ({$existing['tenant_id']})...\n";
        }
        
        // Garante que channel_id está correto
        $expectedChannelId = 'pixel12digital';
        if (($existing['channel_id'] ?? '') !== $expectedChannelId) {
            $updateFields[] = "channel_id = ?";
            $updateParams[] = $expectedChannelId;
            echo "  → Atualizando channel_id para '{$expectedChannelId}'...\n";
        }
        
        // Atualiza session_id se a coluna existir
        if ($hasSessionId && ($existing['session_id'] ?? '') !== $expectedChannelId) {
            $updateFields[] = "session_id = ?";
            $updateParams[] = $expectedChannelId;
            echo "  → Atualizando session_id para '{$expectedChannelId}'...\n";
        }
        
        if (!empty($updateFields)) {
            $updateParams[] = $existing['id'];
            $sql = "UPDATE tenant_message_channels SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($updateParams);
            
            echo "\n✓ Canal atualizado com sucesso!\n\n";
        } else {
            echo "\n✓ Canal já está configurado corretamente.\n\n";
        }
        
    } else {
        echo "✗ Canal não encontrado. Criando novo canal...\n\n";
        
        // Cria novo canal
        if ($hasSessionId) {
            $sql = "INSERT INTO tenant_message_channels 
                    (tenant_id, provider, channel_id, session_id, is_enabled, created_at, updated_at) 
                    VALUES (?, 'wpp_gateway', ?, ?, 1, NOW(), NOW())";
            $params = [$tenantId, 'pixel12digital', 'pixel12digital'];
        } else {
            $sql = "INSERT INTO tenant_message_channels 
                    (tenant_id, provider, channel_id, is_enabled, created_at, updated_at) 
                    VALUES (?, 'wpp_gateway', ?, 1, NOW(), NOW())";
            $params = [$tenantId, 'pixel12digital'];
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $newId = $db->lastInsertId();
        
        echo "✓ Canal criado com sucesso (ID: {$newId})!\n";
        echo "  - Channel ID: pixel12digital\n";
        if ($hasSessionId) {
            echo "  - Session ID: pixel12digital\n";
        }
        echo "  - Tenant ID: " . ($tenantId ?? 'NULL (global)') . "\n";
        echo "  - Enabled: SIM\n\n";
    }
    
    // Verifica resultado final
    echo "Verificando resultado final...\n";
    $finalSearchCond = "LOWER(TRIM(channel_id)) = LOWER(?)";
    if ($hasSessionId) {
        $finalSearchCond = "LOWER(TRIM(channel_id)) = LOWER(?) OR LOWER(TRIM(session_id)) = LOWER(?)";
    }
    $stmt = $db->prepare("
        SELECT id, tenant_id, channel_id, " . ($hasSessionId ? "session_id, " : "") . "is_enabled
        FROM tenant_message_channels 
        WHERE provider = 'wpp_gateway'
        AND ($finalSearchCond)
        LIMIT 1
    ");
    if ($hasSessionId) {
        $stmt->execute(['pixel12digital', 'pixel12digital']);
    } else {
        $stmt->execute(['pixel12digital']);
    }
    $final = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($final && $final['is_enabled']) {
        echo "\n✓✓✓ SUCESSO! Canal 'pixel12digital' está configurado e habilitado!\n";
        echo "   Agora o erro CHANNEL_NOT_FOUND não deve mais ocorrer.\n\n";
    } else {
        echo "\n⚠️  AVISO: Canal encontrado mas ainda não está habilitado ou há outro problema.\n\n";
    }
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    if ($e->getCode() == 23000) {
        echo "\n💡 Dica: Pode haver uma constraint única. Verifique se já existe outro canal\n";
        echo "   com o mesmo tenant_id e provider.\n";
    }
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

