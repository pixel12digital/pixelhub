<?php

/**
 * Script para ver detalhes completos dos eventos com ImobSites
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

echo "=== Detalhes: Eventos Communication Events com ImobSites ===\n\n";

try {
    $db = DB::getConnection();
    
    // Busca eventos com ImobSites
    $query = "SELECT * FROM communication_events WHERE payload LIKE '%ImobSites%' OR metadata LIKE '%ImobSites%' ORDER BY created_at DESC LIMIT 20";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "✗ Nenhum evento encontrado\n";
        exit(0);
    }
    
    echo "✓ Encontrados " . count($results) . " evento(s)\n\n";
    
    // Analisa cada evento
    $foundSessionId = false;
    $foundSession = false;
    $foundFrom = false;
    $foundNumber = false;
    $foundTenantId = false;
    $foundChannel = false;
    $foundSource = false;
    $foundEventType = false;
    
    foreach ($results as $index => $row) {
        echo str_repeat("=", 100) . "\n";
        echo "EVENTO " . ($index + 1) . ":\n";
        echo str_repeat("-", 100) . "\n";
        
        // Campos básicos
        echo "event_id:        " . ($row['event_id'] ?? 'NULL') . "\n";
        echo "status:          " . ($row['status'] ?? 'NULL') . "\n";
        echo "tenant_id:       " . ($row['tenant_id'] ?? 'NULL') . "\n";
        echo "event_type:      " . ($row['event_type'] ?? 'NULL') . "\n";
        echo "source_system:   " . ($row['source_system'] ?? 'NULL') . "\n";
        echo "created_at:      " . ($row['created_at'] ?? 'NULL') . "\n";
        echo "processed_at:    " . ($row['processed_at'] ?? 'NULL') . "\n";
        
        // Analisa payload
        $payload = $row['payload'] ?? null;
        $payloadDecoded = null;
        if ($payload) {
            if (is_string($payload)) {
                $payloadDecoded = json_decode($payload, true);
            } elseif (is_array($payload)) {
                $payloadDecoded = $payload;
            }
        }
        
        // Analisa metadata
        $metadata = $row['metadata'] ?? null;
        $metadataDecoded = null;
        if ($metadata) {
            if (is_string($metadata)) {
                $metadataDecoded = json_decode($metadata, true);
            } elseif (is_array($metadata)) {
                $metadataDecoded = $metadata;
            }
        }
        
        echo "\nPAYLOAD:\n";
        if ($payloadDecoded && is_array($payloadDecoded)) {
            echo json_encode($payloadDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            
            // Verifica campos específicos
            if (isset($payloadDecoded['sessionId']) || isset($payloadDecoded['session_id'])) {
                $foundSessionId = true;
                echo "\n✓ Campo 'sessionId' encontrado no payload\n";
            }
            if (isset($payloadDecoded['session'])) {
                $foundSession = true;
                echo "✓ Campo 'session' encontrado no payload\n";
            }
            if (isset($payloadDecoded['from'])) {
                $foundFrom = true;
                $fromValue = is_array($payloadDecoded['from']) ? json_encode($payloadDecoded['from']) : $payloadDecoded['from'];
                echo "✓ Campo 'from' encontrado no payload: " . substr($fromValue, 0, 100) . "\n";
                if (stripos($fromValue, '554796164699') !== false) {
                    $foundNumber = true;
                }
            }
            if (isset($payloadDecoded['message']['from'])) {
                $foundFrom = true;
                $msgFrom = $payloadDecoded['message']['from'];
                echo "✓ Campo 'message.from' encontrado no payload: " . substr($msgFrom, 0, 100) . "\n";
                if (stripos($msgFrom, '554796164699') !== false) {
                    $foundNumber = true;
                }
            }
        } else {
            echo ($payload ? substr($payload, 0, 500) : 'NULL') . "\n";
        }
        
        echo "\nMETADATA:\n";
        if ($metadataDecoded && is_array($metadataDecoded)) {
            echo json_encode($metadataDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            
            // Verifica campos específicos
            if (isset($metadataDecoded['tenant_id'])) {
                $foundTenantId = true;
                echo "\n✓ Campo 'tenant_id' encontrado no metadata\n";
            }
            if (isset($metadataDecoded['channel_id']) || isset($metadataDecoded['channel'])) {
                $foundChannel = true;
                echo "✓ Campo 'channel_id' encontrado no metadata\n";
            }
        } else {
            echo ($metadata ? substr($metadata, 0, 500) : 'NULL') . "\n";
        }
        
        // Verifica campos da tabela
        if ($row['tenant_id'] ?? null) {
            $foundTenantId = true;
        }
        if ($row['source_system'] ?? null) {
            $foundSource = true;
        }
        if ($row['event_type'] ?? null) {
            $foundEventType = true;
        }
        
        echo "\n";
    }
    
    // Resumo final
    echo str_repeat("=", 100) . "\n";
    echo "RESUMO DA ANÁLISE:\n";
    echo str_repeat("-", 100) . "\n";
    echo "Aparece 'sessionId' ou 'session'? " . ($foundSessionId || $foundSession ? "✓ SIM" : "✗ NÃO") . "\n";
    echo "Aparece 'from' com o número 554796164699? " . ($foundNumber ? "✓ SIM" : "✗ NÃO") . "\n";
    echo "Existe campo 'tenant_id'? " . ($foundTenantId ? "✓ SIM" : "✗ NÃO") . "\n";
    echo "Existe campo 'channel' ou 'channel_id'? " . ($foundChannel ? "✓ SIM" : "✗ NÃO") . "\n";
    echo "Existe campo 'source' ou 'source_system'? " . ($foundSource ? "✓ SIM" : "✗ NÃO") . "\n";
    echo "Existe campo 'eventType' ou 'event_type'? " . ($foundEventType ? "✓ SIM" : "✗ NÃO") . "\n";
    echo "\n";
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

