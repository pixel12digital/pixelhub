<?php

/**
 * Script para verificar canais cadastrados no tenant_message_channels
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

echo "=== Verificação: Canais cadastrados ===\n\n";

try {
    $db = DB::getConnection();
    
    $query = "
        SELECT
          id,
          tenant_id,
          provider,
          channel_id,
          is_enabled,
          webhook_configured,
          LEFT(metadata, 300) AS metadata_preview,
          created_at,
          updated_at
        FROM tenant_message_channels
        WHERE provider = 'wpp_gateway'
          AND channel_id IN ('Pixel12 Digital', 'ImobSites')
        ORDER BY updated_at DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "✗ Nenhum canal encontrado para 'Pixel12 Digital' ou 'ImobSites'\n\n";
        echo "Isso explica por que tenant_id e channel_id ficam NULL nas conversas!\n";
        echo "Os eventos chegam com esses channel_id, mas não há mapeamento na tabela.\n\n";
        
        // Verifica se há outros canais cadastrados
        $allChannelsStmt = $db->query("
            SELECT channel_id, tenant_id, is_enabled 
            FROM tenant_message_channels 
            WHERE provider = 'wpp_gateway'
            ORDER BY updated_at DESC
        ");
        $allChannels = $allChannelsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($allChannels)) {
            echo "Canais cadastrados (wpp_gateway):\n";
            foreach ($allChannels as $channel) {
                echo "  - channel_id: '{$channel['channel_id']}' | tenant_id: {$channel['tenant_id']} | enabled: " . ($channel['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
            }
        } else {
            echo "⚠ Nenhum canal wpp_gateway cadastrado na tabela!\n";
        }
        
        exit(0);
    }
    
    echo "✓ Encontrados " . count($results) . " canal(is)\n\n";
    echo str_repeat("=", 200) . "\n";
    
    // Cabeçalho
    printf("%-5s | %-10s | %-15s | %-20s | %-10s | %-10s | %-50s | %-19s | %-19s\n",
        "ID", "Tenant ID", "Provider", "Channel ID", "Enabled", "Webhook", "Metadata Preview", "Created At", "Updated At");
    echo str_repeat("-", 200) . "\n";
    
    // Linhas
    foreach ($results as $row) {
        printf("%-5s | %-10s | %-15s | %-20s | %-10s | %-10s | %-50s | %-19s | %-19s\n",
            $row['id'] ?? 'NULL',
            $row['tenant_id'] ?? 'NULL',
            $row['provider'] ?? 'NULL',
            $row['channel_id'] ?? 'NULL',
            $row['is_enabled'] ? 'SIM' : 'NÃO',
            $row['webhook_configured'] ? 'SIM' : 'NÃO',
            substr($row['metadata_preview'] ?? 'NULL', 0, 50),
            $row['created_at'] ?? 'NULL',
            $row['updated_at'] ?? 'NULL'
        );
    }
    
    echo str_repeat("=", 200) . "\n\n";
    
    // Análise detalhada
    echo "Análise detalhada:\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($results as $row) {
        echo "\nCanal: {$row['channel_id']} (ID: {$row['id']})\n";
        echo "  - Tenant ID: " . ($row['tenant_id'] ?? 'NULL') . "\n";
        echo "  - Habilitado: " . ($row['is_enabled'] ? 'SIM ✓' : 'NÃO ✗') . "\n";
        echo "  - Webhook configurado: " . ($row['webhook_configured'] ? 'SIM ✓' : 'NÃO ✗') . "\n";
        echo "  - Metadata: " . ($row['metadata_preview'] ?: 'NULL') . "\n";
        echo "  - Criado em: {$row['created_at']}\n";
        echo "  - Atualizado em: {$row['updated_at']}\n";
        
        if (!$row['is_enabled']) {
            echo "  ⚠️  ATENÇÃO: Canal está DESABILITADO!\n";
        }
        if (empty($row['tenant_id'])) {
            echo "  ⚠️  ATENÇÃO: Tenant ID é NULL!\n";
        }
    }
    
    // Verifica se há eventos com esses channel_id mas sem tenant_id
    echo "\n\nVerificando eventos com esses channel_id:\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($results as $row) {
        $channelId = $row['channel_id'];
        $tenantId = $row['tenant_id'];
        
        $eventsStmt = $db->prepare("
            SELECT COUNT(*) as total, 
                   SUM(CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END) as sem_tenant
            FROM communication_events
            WHERE event_type = 'whatsapp.inbound.message'
            AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) = ?
        ");
        $eventsStmt->execute([$channelId]);
        $events = $eventsStmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Channel: {$channelId}\n";
        echo "  - Total de eventos: {$events['total']}\n";
        echo "  - Eventos sem tenant_id: {$events['sem_tenant']}\n";
        
        if ($events['sem_tenant'] > 0 && $tenantId) {
            echo "  ⚠️  ATENÇÃO: {$events['sem_tenant']} eventos sem tenant_id, mas canal tem tenant_id={$tenantId}\n";
        }
    }
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}










