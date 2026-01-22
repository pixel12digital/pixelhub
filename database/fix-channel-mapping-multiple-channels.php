<?php

/**
 * Script para ajustar índice único e recriar mapeamento do Pixel12 Digital
 * Passos 1-5: Conferência, ajuste de índice, inserção e validação
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

echo "=== Ajuste de Índice e Mapeamento de Canais ===\n\n";

try {
    $db = DB::getConnection();
    
    // PASSO 1: Conferência rápida
    echo "PASSO 1: Conferência rápida (antes de mexer)\n";
    echo str_repeat("=", 100) . "\n";
    
    $query1 = "
        SELECT id, tenant_id, provider, channel_id, is_enabled, webhook_configured
        FROM tenant_message_channels
        WHERE tenant_id = 2 AND provider = 'wpp_gateway'
    ";
    
    $stmt1 = $db->prepare($query1);
    $stmt1->execute();
    $results1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results1)) {
        echo "✗ Nenhum registro encontrado\n\n";
    } else {
        echo "✓ Encontrados " . count($results1) . " registro(s):\n\n";
        
        printf("%-5s %-10s %-15s %-30s %-12s %-20s\n",
            "ID", "Tenant ID", "Provider", "Channel ID", "Enabled", "Webhook Config");
        echo str_repeat("-", 100) . "\n";
        
        foreach ($results1 as $row) {
            printf("%-5s %-10s %-15s %-30s %-12s %-20s\n",
                $row['id'] ?? 'NULL',
                $row['tenant_id'] ?? 'NULL',
                $row['provider'] ?? 'NULL',
                substr($row['channel_id'] ?? 'NULL', 0, 29),
                $row['is_enabled'] ? 'SIM' : 'NÃO',
                $row['webhook_configured'] ? 'SIM' : 'NÃO'
            );
        }
    }
    
    // PASSO 2: Ajuste do índice
    echo "\n\nPASSO 2: Ajuste do índice (mudar a constraint)\n";
    echo str_repeat("=", 100) . "\n";
    echo "⚠ ATENÇÃO: Esta operação pode levar alguns segundos...\n\n";
    
    try {
        $query2 = "
            ALTER TABLE tenant_message_channels
              DROP INDEX unique_tenant_provider,
              ADD UNIQUE KEY unique_tenant_provider_channel (tenant_id, provider, channel_id)
        ";
        
        $db->exec($query2);
        
        echo "✓ Índice ajustado com sucesso!\n";
        echo "  - Removido: unique_tenant_provider (tenant_id, provider)\n";
        echo "  - Adicionado: unique_tenant_provider_channel (tenant_id, provider, channel_id)\n\n";
        
    } catch (\PDOException $e) {
        echo "✗ Erro ao ajustar índice: " . $e->getMessage() . "\n";
        echo "SQL State: " . $e->getCode() . "\n";
        
        // Verifica se o índice já foi alterado
        if (strpos($e->getMessage(), "doesn't exist") !== false) {
            echo "\n⚠ O índice unique_tenant_provider pode já ter sido removido.\n";
            echo "  Tentando apenas adicionar o novo índice...\n\n";
            
            try {
                $query2b = "ALTER TABLE tenant_message_channels ADD UNIQUE KEY unique_tenant_provider_channel (tenant_id, provider, channel_id)";
                $db->exec($query2b);
                echo "✓ Novo índice adicionado com sucesso!\n\n";
            } catch (\PDOException $e2) {
                if (strpos($e2->getMessage(), "Duplicate key name") !== false) {
                    echo "⚠ O índice unique_tenant_provider_channel já existe.\n";
                    echo "  Continuando...\n\n";
                } else {
                    throw $e2;
                }
            }
        } else {
            throw $e;
        }
    }
    
    // PASSO 3: Recriar mapeamento do Pixel12 Digital
    echo "\nPASSO 3: Recriar mapeamento do Pixel12 Digital\n";
    echo str_repeat("=", 100) . "\n";
    
    $query3 = "
        INSERT INTO tenant_message_channels
          (tenant_id, provider, channel_id, is_enabled, webhook_configured, metadata)
        VALUES
          (2, 'wpp_gateway', 'Pixel12 Digital', 1, 0, NULL)
        ON DUPLICATE KEY UPDATE
          is_enabled = VALUES(is_enabled),
          webhook_configured = VALUES(webhook_configured),
          updated_at = CURRENT_TIMESTAMP()
    ";
    
    $stmt3 = $db->prepare($query3);
    $stmt3->execute();
    $insertId = $db->lastInsertId();
    
    if ($insertId > 0) {
        echo "✓ Novo registro inserido (ID: {$insertId})\n\n";
    } else {
        echo "✓ Registro atualizado (já existia)\n\n";
    }
    
    // PASSO 4: Validar que agora tem os 2 canais
    echo "\nPASSO 4: Validar que agora tem os 2 canais\n";
    echo str_repeat("=", 100) . "\n";
    
    $query4 = "
        SELECT id, tenant_id, provider, channel_id, is_enabled, webhook_configured
        FROM tenant_message_channels
        WHERE tenant_id = 2 AND provider = 'wpp_gateway'
        ORDER BY id
    ";
    
    $stmt4 = $db->prepare($query4);
    $stmt4->execute();
    $results4 = $stmt4->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results4)) {
        echo "✗ Nenhum registro encontrado\n\n";
    } else {
        echo "✓ Encontrados " . count($results4) . " canal(is):\n\n";
        
        printf("%-5s %-10s %-15s %-30s %-12s %-20s\n",
            "ID", "Tenant ID", "Provider", "Channel ID", "Enabled", "Webhook Config");
        echo str_repeat("-", 100) . "\n";
        
        foreach ($results4 as $row) {
            printf("%-5s %-10s %-15s %-30s %-12s %-20s\n",
                $row['id'] ?? 'NULL',
                $row['tenant_id'] ?? 'NULL',
                $row['provider'] ?? 'NULL',
                substr($row['channel_id'] ?? 'NULL', 0, 29),
                $row['is_enabled'] ? 'SIM' : 'NÃO',
                $row['webhook_configured'] ? 'SIM' : 'NÃO'
            );
        }
        
        // Verifica se ambos os canais estão presentes
        $channels = array_column($results4, 'channel_id');
        $hasImobSites = in_array('ImobSites', $channels);
        $hasPixel12 = in_array('Pixel12 Digital', $channels);
        
        echo "\n" . str_repeat("-", 100) . "\n";
        echo "VALIDAÇÃO:\n";
        echo "  - ImobSites: " . ($hasImobSites ? "✓ PRESENTE" : "✗ AUSENTE") . "\n";
        echo "  - Pixel12 Digital: " . ($hasPixel12 ? "✓ PRESENTE" : "✗ AUSENTE") . "\n";
        
        if ($hasImobSites && $hasPixel12) {
            echo "\n✅ SUCESSO: Ambos os canais estão mapeados!\n";
        } else {
            echo "\n⚠ ATENÇÃO: Faltam canais no mapeamento.\n";
        }
    }
    
    // PASSO 5: Teste (aguardar mensagens)
    echo "\n\nPASSO 5: Teste (aguardando mensagens)\n";
    echo str_repeat("=", 100) . "\n";
    echo "📌 Por favor, envie:\n";
    echo "  1. 1 mensagem para Pixel12 Digital\n";
    echo "  2. 1 mensagem para ImobSites\n";
    echo "\n";
    echo "Aguardando 10 segundos antes de verificar eventos...\n";
    sleep(10);
    
    $query5 = "
        SELECT id, status, tenant_id, event_type, error_message
        FROM communication_events
        WHERE event_type='whatsapp.inbound.message'
        ORDER BY id DESC
        LIMIT 10
    ";
    
    $stmt5 = $db->prepare($query5);
    $stmt5->execute();
    $results5 = $stmt5->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results5)) {
        echo "✗ Nenhum evento encontrado\n\n";
    } else {
        echo "✓ Encontrados " . count($results5) . " evento(s) recente(s):\n\n";
        
        printf("%-8s %-12s %-10s %-30s %-40s\n",
            "ID", "Status", "Tenant ID", "Event Type", "Error Message");
        echo str_repeat("-", 100) . "\n";
        
        $processedCount = 0;
        $failedCount = 0;
        $hasWhatsapp35 = false;
        $hasNull = false;
        
        foreach ($results5 as $row) {
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
        $query5b = "
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
            $stmt5b = $db->prepare($query5b);
            $stmt5b->execute();
            $results5b = $stmt5b->fetchAll(PDO::FETCH_ASSOC);
            
            echo "\n" . str_repeat("-", 100) . "\n";
            echo "Channel IDs nos eventos mais recentes:\n";
            foreach ($results5b as $row) {
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
            // Ignora erro se não conseguir extrair JSON
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
    
    echo "\n" . str_repeat("=", 100) . "\n";
    echo "CONCLUSÃO:\n";
    echo str_repeat("-", 100) . "\n";
    echo "✓ Passos 1-4 concluídos com sucesso\n";
    echo "📌 Aguarde o envio das mensagens de teste para validar o passo 5.\n";
    echo "\n";
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

