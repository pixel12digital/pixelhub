<?php

/**
 * Script para verificar tenant_id das conversations antes de cadastrar canal
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
use PDO;

echo "=== Verificação de tenant_id das Conversations ===\n\n";

try {
    Env::load();
    $db = DB::getConnection();
    
    // Verifica conversations 31 e 32
    $stmt = $db->prepare("
        SELECT 
            id,
            tenant_id,
            contact_external_id,
            conversation_key,
            channel_type,
            status
        FROM conversations 
        WHERE id IN (31, 32)
        ORDER BY id
    ");
    $stmt->execute();
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($conversations)) {
        echo "⚠️  Nenhuma conversation encontrada com IDs 31 ou 32\n";
    } else {
        echo "Conversations encontradas:\n\n";
        foreach ($conversations as $conv) {
            echo "📱 Conversation ID: {$conv['id']}\n";
            echo "   Thread ID: whatsapp_{$conv['id']}\n";
            echo "   Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
            echo "   Contato: " . ($conv['contact_external_id'] ?? 'N/A') . "\n";
            echo "   Conversation Key: " . ($conv['conversation_key'] ?? 'N/A') . "\n";
            echo "   Status: {$conv['status']}\n";
            echo "\n";
        }
        
        // Verifica se todas têm o mesmo tenant_id
        $tenantIds = array_filter(array_column($conversations, 'tenant_id'));
        $uniqueTenantIds = array_unique($tenantIds);
        
        if (count($uniqueTenantIds) === 0) {
            echo "⚠️  ATENÇÃO: Nenhuma conversation tem tenant_id definido!\n";
            echo "   Você precisará escolher um tenant_id manualmente.\n\n";
        } elseif (count($uniqueTenantIds) === 1) {
            $tenantId = reset($uniqueTenantIds);
            echo "✓ Todas as conversations usam o mesmo tenant_id: {$tenantId}\n";
            echo "   Use este tenant_id para cadastrar o canal.\n\n";
            
            // Verifica se o tenant existe
            $tenantStmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
            $tenantStmt->execute([$tenantId]);
            $tenant = $tenantStmt->fetch();
            
            if ($tenant) {
                echo "✓ Tenant encontrado: {$tenant['name']} (ID: {$tenant['id']})\n\n";
            } else {
                echo "⚠️  Tenant ID {$tenantId} não encontrado na tabela tenants!\n\n";
            }
        } else {
            echo "⚠️  ATENÇÃO: Conversations usam tenant_ids diferentes!\n";
            echo "   Tenant IDs encontrados: " . implode(', ', $uniqueTenantIds) . "\n";
            echo "   Você precisará escolher qual usar ou cadastrar canais para cada tenant.\n\n";
        }
    }
    
    // Verifica todas as conversations de WhatsApp para estatística
    $allStmt = $db->query("
        SELECT 
            COUNT(*) as total,
            COUNT(DISTINCT tenant_id) as unique_tenants,
            COUNT(CASE WHEN tenant_id IS NULL THEN 1 END) as without_tenant
        FROM conversations 
        WHERE channel_type = 'whatsapp'
    ");
    $stats = $allStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "=== Estatísticas Gerais ===\n";
    echo "Total de conversations WhatsApp: {$stats['total']}\n";
    echo "Tenants únicos: {$stats['unique_tenants']}\n";
    echo "Sem tenant_id: {$stats['without_tenant']}\n\n";
    
} catch (\Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

echo "✓ Verificação concluída!\n";

