<?php

/**
 * Script para verificar o tenant real da conversa whatsapp_5
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

$threadId = $argv[1] ?? 'whatsapp_5';

echo "=== Verificação: Tenant real da conversa '{$threadId}' ===\n\n";

try {
    $db = DB::getConnection();
    
    // Extrai conversation_id do thread_id
    if (preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
        $conversationId = (int) $matches[1];
    } else {
        echo "✗ Thread ID inválido: {$threadId}\n";
        exit(1);
    }
    
    echo "1. Buscando conversa (conversation_id={$conversationId})...\n";
    $stmt = $db->prepare("
        SELECT id, tenant_id, channel_id, contact_external_id
        FROM conversations
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$conversationId]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conv) {
        echo "✗ Conversa não encontrada!\n";
        exit(1);
    }
    
    echo "✓ Conversa encontrada!\n";
    echo "   - ID: {$conv['id']}\n";
    echo "   - Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
    echo "   - Channel ID: " . ($conv['channel_id'] ?? 'NULL') . "\n";
    echo "   - Contact External ID: " . ($conv['contact_external_id'] ?? 'NULL') . "\n\n";
    
    $tenantId = $conv['tenant_id'] ?? null;
    
    if ($tenantId) {
        echo "2. Verificando sessões habilitadas para tenant_id={$tenantId}...\n";
        $stmt = $db->prepare("
            SELECT tenant_id, provider, channel_id, is_enabled
            FROM tenant_message_channels
            WHERE provider = 'wpp_gateway'
              AND tenant_id = ?
              AND is_enabled = 1
        ");
        $stmt->execute([$tenantId]);
        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($channels)) {
            echo "   ⚠️  Nenhuma sessão habilitada para este tenant!\n\n";
        } else {
            echo "   ✓ " . count($channels) . " sessão(ões) habilitada(s):\n";
            foreach ($channels as $ch) {
                echo "     - Channel ID: '{$ch['channel_id']}' (enabled: SIM)\n";
            }
            echo "\n";
        }
    } else {
        echo "2. ⚠️  Tenant ID é NULL na conversa!\n\n";
    }
    
    // Compara com o que o frontend está enviando
    $frontendTenantId = isset($argv[2]) ? (int) $argv[2] : 121;
    echo "3. Comparação:\n";
    echo "   - Tenant ID do banco (conversa): " . ($tenantId ?? 'NULL') . "\n";
    echo "   - Tenant ID do frontend (POST): {$frontendTenantId}\n";
    
    if ($tenantId && $tenantId != $frontendTenantId) {
        echo "\n   ❌ PROBLEMA IDENTIFICADO!\n";
        echo "   O tenant_id do frontend ({$frontendTenantId}) é diferente do tenant_id real ({$tenantId})!\n";
        echo "   Isso explica o erro CHANNEL_NOT_FOUND.\n";
        echo "\n   → Solução: Implementar PATCH I para usar o tenant_id do banco quando thread_id existir.\n";
    } elseif ($tenantId && $tenantId == $frontendTenantId) {
        echo "\n   ✓ Tenant IDs coincidem.\n";
    } else {
        echo "\n   ⚠️  Tenant ID do banco é NULL - pode ser necessário resolver pelo channel_id.\n";
    }
    
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

