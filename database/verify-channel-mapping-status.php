<?php

/**
 * Script para verificar status dos eventos e mapeamentos de canais
 * A) Últimos eventos de entrada
 * B) Channel_id exato recebido no metadata
 * C) Mapeamentos existentes
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

echo "=== Verificação: Status dos Eventos e Mapeamentos de Canais ===\n\n";

try {
    $db = DB::getConnection();
    
    // A) Últimos eventos de entrada
    echo "A) ÚLTIMOS EVENTOS DE ENTRADA (ImobSites e Pixel12 Digital):\n";
    echo str_repeat("=", 100) . "\n";
    
    $queryA = "
        SELECT id, created_at, status, tenant_id, event_type, error_message
        FROM communication_events
        WHERE event_type='whatsapp.inbound.message'
        ORDER BY id DESC
        LIMIT 30
    ";
    
    $stmtA = $db->prepare($queryA);
    $stmtA->execute();
    $resultsA = $stmtA->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($resultsA)) {
        echo "✗ Nenhum evento encontrado\n\n";
    } else {
        echo "✓ Encontrados " . count($resultsA) . " evento(s)\n\n";
        
        // Cabeçalho
        printf("%-8s %-20s %-12s %-10s %-30s %-30s\n",
            "ID", "Created At", "Status", "Tenant ID", "Event Type", "Error Message");
        echo str_repeat("-", 100) . "\n";
        
        $conversationNotResolvedCount = 0;
        $processedCount = 0;
        $failedCount = 0;
        
        foreach ($resultsA as $row) {
            $errorMsg = $row['error_message'] ?? 'NULL';
            if (strlen($errorMsg) > 28) {
                $errorMsg = substr($errorMsg, 0, 25) . '...';
            }
            
            printf("%-8s %-20s %-12s %-10s %-30s %-30s\n",
                $row['id'] ?? 'NULL',
                substr($row['created_at'] ?? 'NULL', 0, 19),
                $row['status'] ?? 'NULL',
                $row['tenant_id'] ?? 'NULL',
                substr($row['event_type'] ?? 'NULL', 0, 29),
                $errorMsg
            );
            
            // Conta estatísticas
            if ($row['status'] === 'failed' && stripos($row['error_message'] ?? '', 'conversation_not_resolved') !== false) {
                $conversationNotResolvedCount++;
            } elseif ($row['status'] === 'processed') {
                $processedCount++;
            } elseif ($row['status'] === 'failed') {
                $failedCount++;
            }
        }
        
        echo "\n" . str_repeat("-", 100) . "\n";
        echo "ESTATÍSTICAS:\n";
        echo "  - conversation_not_resolved: {$conversationNotResolvedCount}\n";
        echo "  - processed: {$processedCount}\n";
        echo "  - failed (outros): {$failedCount}\n";
        
        if ($conversationNotResolvedCount > 0) {
            echo "\n⚠ ATENÇÃO: Ainda há eventos com 'conversation_not_resolved'\n";
            echo "  Isso indica problema de mapeamento.\n";
        } else {
            echo "\n✓ Nenhum erro de mapeamento encontrado!\n";
        }
    }
    
    // B) Channel_id exato recebido no metadata
    echo "\n\nB) CHANNEL_ID EXATO RECEBIDO NO METADATA:\n";
    echo str_repeat("=", 100) . "\n";
    
    try {
        $queryB = "
            SELECT id,
              JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
              JSON_UNQUOTE(JSON_EXTRACT(payload,'$.session.id')) AS session_id,
              JSON_UNQUOTE(JSON_EXTRACT(payload,'$.message.from')) AS wa_from,
              status, tenant_id, error_message
            FROM communication_events
            WHERE event_type='whatsapp.inbound.message'
            ORDER BY id DESC
            LIMIT 30
        ";
        
        $stmtB = $db->prepare($queryB);
        $stmtB->execute();
        $resultsB = $stmtB->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($resultsB)) {
            echo "✗ Nenhum evento encontrado\n\n";
        } else {
            echo "✓ Encontrados " . count($resultsB) . " evento(s)\n\n";
            
            // Cabeçalho
            printf("%-8s %-25s %-25s %-30s %-12s %-10s %-30s\n",
                "ID", "Channel ID", "Session ID", "WA From", "Status", "Tenant ID", "Error Message");
            echo str_repeat("-", 100) . "\n";
            
            $channelIds = [];
            
            foreach ($resultsB as $row) {
                $channelId = $row['channel_id'] ?? 'NULL';
                $sessionId = $row['session_id'] ?? 'NULL';
                $waFrom = $row['wa_from'] ?? 'NULL';
                $errorMsg = $row['error_message'] ?? 'NULL';
                
                if (strlen($waFrom) > 28) {
                    $waFrom = substr($waFrom, 0, 25) . '...';
                }
                if (strlen($errorMsg) > 28) {
                    $errorMsg = substr($errorMsg, 0, 25) . '...';
                }
                
                printf("%-8s %-25s %-25s %-30s %-12s %-10s %-30s\n",
                    $row['id'] ?? 'NULL',
                    substr($channelId, 0, 24),
                    substr($sessionId, 0, 24),
                    $waFrom,
                    $row['status'] ?? 'NULL',
                    $row['tenant_id'] ?? 'NULL',
                    $errorMsg
                );
                
                // Coleta channel_ids únicos
                if ($channelId && $channelId !== 'NULL') {
                    if (!isset($channelIds[$channelId])) {
                        $channelIds[$channelId] = 0;
                    }
                    $channelIds[$channelId]++;
                }
            }
            
            echo "\n" . str_repeat("-", 100) . "\n";
            echo "CHANNEL_IDS ÚNICOS ENCONTRADOS:\n";
            if (empty($channelIds)) {
                echo "  ✗ Nenhum channel_id encontrado no metadata\n";
            } else {
                foreach ($channelIds as $channelId => $count) {
                    echo "  - '{$channelId}' (aparece {$count} vez(es))\n";
                }
                echo "\n⚠ IMPORTANTE: O mapeamento precisa bater EXATAMENTE com esses valores!\n";
                echo "  Verifique se há espaços, caracteres especiais, encoding, etc.\n";
            }
        }
    } catch (\PDOException $e) {
        echo "✗ Erro ao executar query B: " . $e->getMessage() . "\n";
        echo "  Tentando query alternativa...\n\n";
        
        // Query alternativa sem JSON_EXTRACT
        $queryBAlt = "
            SELECT id, status, tenant_id, error_message, metadata, payload
            FROM communication_events
            WHERE event_type='whatsapp.inbound.message'
            ORDER BY id DESC
            LIMIT 10
        ";
        
        $stmtBAlt = $db->prepare($queryBAlt);
        $stmtBAlt->execute();
        $resultsBAlt = $stmtBAlt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Eventos (análise manual do JSON):\n";
        foreach ($resultsBAlt as $row) {
            echo "ID: {$row['id']}\n";
            $metadata = json_decode($row['metadata'] ?? '{}', true);
            $payload = json_decode($row['payload'] ?? '{}', true);
            
            if (isset($metadata['channel_id'])) {
                echo "  channel_id (metadata): {$metadata['channel_id']}\n";
            }
            if (isset($payload['session']['id'])) {
                echo "  session.id (payload): {$payload['session']['id']}\n";
            }
            echo "\n";
        }
    }
    
    // C) Mapeamentos existentes
    echo "\n\nC) MAPEAMENTOS EXISTENTES:\n";
    echo str_repeat("=", 100) . "\n";
    
    $queryC = "
        SELECT id, tenant_id, provider, channel_id, is_enabled, webhook_configured
        FROM tenant_message_channels
        WHERE provider='wpp_gateway'
        ORDER BY id ASC
    ";
    
    $stmtC = $db->prepare($queryC);
    $stmtC->execute();
    $resultsC = $stmtC->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($resultsC)) {
        echo "✗ Nenhum mapeamento encontrado\n\n";
    } else {
        echo "✓ Encontrados " . count($resultsC) . " mapeamento(s)\n\n";
        
        // Cabeçalho
        printf("%-5s %-10s %-15s %-30s %-12s %-20s\n",
            "ID", "Tenant ID", "Provider", "Channel ID", "Enabled", "Webhook Config");
        echo str_repeat("-", 100) . "\n";
        
        foreach ($resultsC as $row) {
            printf("%-5s %-10s %-15s %-30s %-12s %-20s\n",
                $row['id'] ?? 'NULL',
                $row['tenant_id'] ?? 'NULL',
                $row['provider'] ?? 'NULL',
                substr($row['channel_id'] ?? 'NULL', 0, 29),
                $row['is_enabled'] ? 'SIM' : 'NÃO',
                $row['webhook_configured'] ? 'SIM' : 'NÃO'
            );
        }
        
        // Compara com channel_ids encontrados
        if (!empty($channelIds)) {
            echo "\n" . str_repeat("-", 100) . "\n";
            echo "COMPARAÇÃO: Channel IDs recebidos vs Mapeamentos:\n";
            
            $mappedChannels = array_column($resultsC, 'channel_id');
            
            foreach ($channelIds as $receivedChannelId => $count) {
                $found = false;
                foreach ($mappedChannels as $mappedChannel) {
                    if (trim($receivedChannelId) === trim($mappedChannel)) {
                        $found = true;
                        break;
                    }
                }
                
                if ($found) {
                    echo "  ✓ '{$receivedChannelId}' - MAPEADO\n";
                } else {
                    echo "  ✗ '{$receivedChannelId}' - NÃO MAPEADO (aparece {$count} vez(es))\n";
                }
            }
        }
    }
    
    // Resumo final
    echo "\n\n" . str_repeat("=", 100) . "\n";
    echo "RESUMO FINAL:\n";
    echo str_repeat("-", 100) . "\n";
    
    if ($conversationNotResolvedCount > 0) {
        echo "⚠ PROBLEMA: Ainda há eventos com 'conversation_not_resolved'\n";
        echo "  Verifique se os channel_ids nos mapeamentos batem EXATAMENTE com os recebidos.\n";
    } else {
        echo "✓ SUCESSO: Nenhum erro de mapeamento encontrado!\n";
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

