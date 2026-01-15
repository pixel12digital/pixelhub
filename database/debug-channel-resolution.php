<?php

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

try {
    $pdo = DB::getConnection();
    
    echo "=== DEBUG: Resolução de Canal ===\n\n";
    
    // 1. Verificar mapeamentos disponíveis
    echo "1. MAPEAMENTOS DISPONÍVEIS (tenant_message_channels):\n";
    echo str_repeat("-", 80) . "\n";
    $sql1 = "SELECT id, tenant_id, provider, channel_id, is_enabled 
             FROM tenant_message_channels 
             WHERE provider = 'wpp_gateway' 
             ORDER BY id";
    
    $stmt1 = $pdo->prepare($sql1);
    $stmt1->execute();
    $channels = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($channels as $ch) {
        echo sprintf("  ID: %d | Tenant: %d | Provider: %s | Channel ID: '%s' | Enabled: %d\n",
            $ch['id'],
            $ch['tenant_id'],
            $ch['provider'],
            $ch['channel_id'],
            $ch['is_enabled']
        );
    }
    
    // 2. Simular busca que o código faria
    echo "\n2. SIMULAÇÃO DA QUERY DE RESOLUÇÃO:\n";
    echo str_repeat("-", 80) . "\n";
    
    $tenantId = 2;
    $channelId = 'ImobSites';
    
    echo "Buscando: tenant_id = $tenantId, channel_id = '$channelId'\n\n";
    
    $sql2 = "SELECT id 
             FROM tenant_message_channels 
             WHERE tenant_id = ? 
             AND provider = 'wpp_gateway' 
             AND channel_id = ?
             AND is_enabled = 1
             LIMIT 1";
    
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([$tenantId, $channelId]);
    $result = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "✅ ENCONTRADO! channel_account_id = " . $result['id'] . "\n";
    } else {
        echo "❌ NÃO ENCONTRADO! Verificando possíveis causas...\n\n";
        
        // Verificar se o channel_id existe mas com espaços/trim
        $sql3 = "SELECT id, channel_id, LENGTH(channel_id) as len 
                 FROM tenant_message_channels 
                 WHERE tenant_id = ? 
                 AND provider = 'wpp_gateway'";
        $stmt3 = $pdo->prepare($sql3);
        $stmt3->execute([$tenantId]);
        $all = $stmt3->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Todos os channels do tenant $tenantId:\n";
        foreach ($all as $ch) {
            $exact = ($ch['channel_id'] === $channelId) ? '✅ MATCH EXATO' : '❌ DIFERENTE';
            echo sprintf("  ID: %d | Channel ID: '%s' (length: %d) | $exact\n",
                $ch['id'],
                $ch['channel_id'],
                $ch['len']
            );
        }
    }
    
    // 3. Verificar eventos recentes e seus metadados
    echo "\n3. EVENTOS RECENTES - METADADOS:\n";
    echo str_repeat("-", 80) . "\n";
    $sql4 = "SELECT id, event_id, metadata, tenant_id, status
             FROM communication_events
             WHERE event_type = 'whatsapp.inbound.message'
               AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) = 'ImobSites'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
             ORDER BY id DESC
             LIMIT 3";
    
    $stmt4 = $pdo->prepare($sql4);
    $stmt4->execute();
    $events = $stmt4->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($events as $event) {
        $metadata = json_decode($event['metadata'], true);
        echo sprintf("Event ID: %d | Tenant: %s | Status: %s\n",
            $event['id'],
            $event['tenant_id'] ?? 'NULL',
            $event['status']
        );
        echo "  Metadata channel_id: " . ($metadata['channel_id'] ?? 'NULL') . "\n";
        echo "  Metadata completo: " . substr($event['metadata'], 0, 200) . "\n\n";
    }
    
    echo "\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}

