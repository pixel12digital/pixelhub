<?php

/**
 * Script para verificar e corrigir o canal 'pixel12digital'
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

echo "=== Verificação: Canal 'pixel12digital' ===\n\n";

try {
    $db = DB::getConnection();
    
    // Verifica estrutura da tabela
    echo "1. Verificando estrutura da tabela...\n";
    $stmt = $db->query("SHOW COLUMNS FROM tenant_message_channels");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $hasSessionId = in_array('session_id', $columns);
    echo "   - Coluna 'session_id' existe: " . ($hasSessionId ? 'SIM' : 'NÃO') . "\n";
    echo "   - Coluna 'channel_id' existe: " . (in_array('channel_id', $columns) ? 'SIM' : 'NÃO') . "\n\n";
    
    // Busca canal 'pixel12digital' (case-insensitive)
    echo "2. Buscando canal 'pixel12digital'...\n";
    $searchCondition = "LOWER(TRIM(channel_id)) = LOWER(?)";
    if ($hasSessionId) {
        $searchCondition = "LOWER(TRIM(channel_id)) = LOWER(?) OR LOWER(TRIM(session_id)) = LOWER(?)";
    }
    $stmt = $db->prepare("
        SELECT * 
        FROM tenant_message_channels 
        WHERE provider = 'wpp_gateway'
        AND ($searchCondition)
    ");
    if ($hasSessionId) {
        $stmt->execute(['pixel12digital', 'pixel12digital']);
    } else {
        $stmt->execute(['pixel12digital']);
    }
    $channel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($channel) {
        echo "   ✓ Canal encontrado!\n";
        echo "   - ID: {$channel['id']}\n";
        echo "   - Tenant ID: " . ($channel['tenant_id'] ?? 'NULL') . "\n";
        echo "   - Provider: {$channel['provider']}\n";
        echo "   - Channel ID: " . ($channel['channel_id'] ?? 'NULL') . "\n";
        if ($hasSessionId) {
            echo "   - Session ID: " . ($channel['session_id'] ?? 'NULL') . "\n";
        }
        echo "   - Enabled: " . ($channel['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
        echo "   - Webhook: " . (($channel['webhook_configured'] ?? 0) ? 'SIM' : 'NÃO') . "\n\n";
        
        if (!$channel['is_enabled']) {
            echo "   ⚠️  PROBLEMA: Canal está DESABILITADO!\n";
            echo "   → Isso explica o erro CHANNEL_NOT_FOUND\n\n";
        }
        
        // Busca variações do nome
        echo "3. Verificando variações do nome...\n";
        $variations = ['pixel12digital', 'Pixel12 Digital', 'Pixel12Digital', 'PIXEL12DIGITAL', 'pixel12 digital'];
        foreach ($variations as $var) {
            $searchCond = "LOWER(TRIM(channel_id)) = LOWER(?)";
            $selectCols = "id, channel_id, tenant_id, is_enabled";
            if ($hasSessionId) {
                $searchCond = "LOWER(TRIM(channel_id)) = LOWER(?) OR LOWER(TRIM(session_id)) = LOWER(?)";
                $selectCols = "id, channel_id, session_id, tenant_id, is_enabled";
            }
            $stmt = $db->prepare("
                SELECT $selectCols
                FROM tenant_message_channels 
                WHERE provider = 'wpp_gateway'
                AND ($searchCond)
                LIMIT 1
            ");
            if ($hasSessionId) {
                $stmt->execute([$var, $var]);
            } else {
                $stmt->execute([$var]);
            }
            $found = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($found) {
                echo "   - '{$var}': ID={$found['id']}, enabled=" . ($found['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
            }
        }
        echo "\n";
    } else {
        echo "   ✗ Canal 'pixel12digital' NÃO encontrado!\n\n";
        
        // Lista todos os canais wpp_gateway
        echo "3. Canais wpp_gateway cadastrados:\n";
        $stmt = $db->query("
            SELECT id, tenant_id, channel_id, " . ($hasSessionId ? "session_id, " : "") . "is_enabled
            FROM tenant_message_channels 
            WHERE provider = 'wpp_gateway'
            ORDER BY updated_at DESC
            LIMIT 20
        ");
        $allChannels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($allChannels)) {
            echo "   ⚠️  Nenhum canal wpp_gateway cadastrado!\n\n";
        } else {
            foreach ($allChannels as $ch) {
                $chId = $ch['channel_id'] ?? 'NULL';
                $sId = ($hasSessionId && isset($ch['session_id'])) ? $ch['session_id'] : 'NULL';
                $tid = $ch['tenant_id'] ?? 'NULL';
                $en = $ch['is_enabled'] ? 'SIM' : 'NÃO';
                echo "   - ID={$ch['id']}: channel_id='{$chId}', session_id='{$sId}', tenant_id={$tid}, enabled={$en}\n";
            }
            echo "\n";
        }
    }
    
    // Verifica qual tenant está tentando usar o canal
    echo "4. Para corrigir o problema:\n";
    echo "   Execute: php database/fix-pixel12digital-channel.php [tenant_id]\n";
    echo "   Onde [tenant_id] é o ID do tenant que precisa usar o canal.\n";
    echo "   Se não especificar tenant_id, criará um canal global (tenant_id=NULL)\n\n";
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

