<?php

/**
 * Script para testar eventos após envio de mensagens (Passo 5)
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

echo "=== PASSO 5: Teste de Eventos após Mensagens ===\n\n";

try {
    $db = DB::getConnection();
    
    $query = "
        SELECT id, status, tenant_id, event_type, error_message
        FROM communication_events
        WHERE event_type='whatsapp.inbound.message'
        ORDER BY id DESC
        LIMIT 10
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "✗ Nenhum evento encontrado\n\n";
    } else {
        echo "✓ Encontrados " . count($results) . " evento(s) recente(s):\n\n";
        
        printf("%-8s %-12s %-10s %-30s %-40s\n",
            "ID", "Status", "Tenant ID", "Event Type", "Error Message");
        echo str_repeat("-", 100) . "\n";
        
        $processedCount = 0;
        $failedCount = 0;
        $hasWhatsapp35 = false;
        $hasNull = false;
        
        foreach ($results as $row) {
            $errorMsg = $row['error_message'] ?? 'NULL';
            if (strlen($errorMsg) > 38) {
                $errorMsg = substr($errorMsg, 0, 35) . '...';
            }
            
            printf("%-8s %-12s %-10s %-30s %-40s\n",
                $row['id'] ?? 'NULL',
                $row['status'] ?? 'NULL',
                $row['tenant_id'] ?? 'NULL',
                substr($row['event_type'] ?? 'NULL', 0, 29),
                $errorMsg
            );
            
            if ($row['status'] === 'processed') {
                $processedCount++;
            } elseif ($row['status'] === 'failed') {
                $failedCount++;
            }
        }
        
        // Verifica channel_ids nos eventos mais recentes
        $query2 = "
            SELECT id,
              JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
              JSON_UNQUOTE(JSON_EXTRACT(payload,'$.session.id')) AS session_id,
              status, tenant_id
            FROM communication_events
            WHERE event_type='whatsapp.inbound.message'
            ORDER BY id DESC
            LIMIT 10
        ";
        
        try {
            $stmt2 = $db->prepare($query2);
            $stmt2->execute();
            $results2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            
            echo "\n" . str_repeat("-", 100) . "\n";
            echo "Channel IDs nos eventos mais recentes:\n";
            foreach ($results2 as $row) {
                $channelId = $row['channel_id'] ?? 'NULL';
                echo "  ID {$row['id']}: channel_id = '{$channelId}' | status = {$row['status']} | tenant_id = {$row['tenant_id']}\n";
                
                if (stripos($channelId, 'whatsapp_35') !== false) {
                    $hasWhatsapp35 = true;
                }
                if ($channelId === 'null' || $channelId === 'NULL' || empty($channelId)) {
                    $hasNull = true;
                }
            }
        } catch (\Exception $e) {
            echo "\n⚠ Não foi possível extrair channel_ids do JSON\n";
        }
        
        echo "\n" . str_repeat("-", 100) . "\n";
        echo "ESTATÍSTICAS:\n";
        echo "  - processed: {$processedCount}\n";
        echo "  - failed: {$failedCount}\n";
        
        if ($hasWhatsapp35) {
            echo "\n⚠ ATENÇÃO: Ainda aparecem eventos com channel_id 'whatsapp_35'\n";
            echo "  Será necessário ajustar o parser de channel_id no Hub.\n";
        }
        
        if ($hasNull) {
            echo "\n⚠ ATENÇÃO: Ainda aparecem eventos com channel_id 'null'\n";
            echo "  Será necessário ajustar o parser de channel_id no Hub.\n";
        }
        
        if (!$hasWhatsapp35 && !$hasNull && $processedCount > 0) {
            echo "\n✅ SUCESSO: Eventos sendo processados corretamente!\n";
        }
    }
    
    echo "\n";
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

