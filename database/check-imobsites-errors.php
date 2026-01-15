<?php

/**
 * Script para verificar erros registrados nos eventos do ImobSites
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

echo "=== Verificação: Erros nos Eventos do ImobSites ===\n\n";

try {
    $db = DB::getConnection();
    
    // Primeiro, verifica a estrutura da tabela para ver quais campos de erro existem
    echo "1. Verificando estrutura da tabela communication_events...\n";
    echo str_repeat("-", 80) . "\n";
    
    $queryStructure = "DESCRIBE communication_events";
    $stmtStructure = $db->prepare($queryStructure);
    $stmtStructure->execute();
    $structure = $stmtStructure->fetchAll(PDO::FETCH_ASSOC);
    
    $hasErrorMessage = false;
    $hasErrorCode = false;
    $hasErrorStack = false;
    
    foreach ($structure as $col) {
        if (stripos($col['Field'], 'error') !== false) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
            if (stripos($col['Field'], 'error_message') !== false) {
                $hasErrorMessage = true;
            }
            if (stripos($col['Field'], 'error_code') !== false) {
                $hasErrorCode = true;
            }
            if (stripos($col['Field'], 'error_stack') !== false) {
                $hasErrorStack = true;
            }
        }
    }
    
    if (!$hasErrorMessage && !$hasErrorCode && !$hasErrorStack) {
        echo "  ⚠ Nenhum campo de erro encontrado na tabela\n";
    }
    
    // Tenta a query com JSON_EXTRACT
    echo "\n2. Buscando eventos do ImobSites com JSON_EXTRACT...\n";
    echo str_repeat("-", 80) . "\n";
    
    $columns = ['id', 'status', 'tenant_id', 'event_type', 'source_system', 'created_at'];
    if ($hasErrorMessage) {
        $columns[] = 'error_message';
    }
    if ($hasErrorCode) {
        $columns[] = 'error_code';
    }
    if ($hasErrorStack) {
        $columns[] = 'error_stack';
    }
    $columns[] = 'payload';
    $columns[] = 'metadata';
    
    $columnsStr = implode(', ', $columns);
    
    try {
        $query = "
            SELECT 
              {$columnsStr}
            FROM communication_events
            WHERE source_system = 'wpp_gateway'
              AND (
                (JSON_EXTRACT(payload, '$.session.id') = 'ImobSites')
                OR (JSON_EXTRACT(payload, '$.session.name') = 'ImobSites')
                OR (JSON_EXTRACT(metadata, '$.channel_id') = 'ImobSites')
              )
            ORDER BY id DESC
            LIMIT 3
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($results)) {
            echo "✗ Nenhum evento encontrado com JSON_EXTRACT\n";
            echo "  Tentando query alternativa...\n\n";
            throw new \Exception("Nenhum resultado");
        }
        
        echo "✓ Encontrados " . count($results) . " evento(s)\n\n";
        
    } catch (\PDOException $e) {
        echo "✗ Erro com JSON_EXTRACT: " . $e->getMessage() . "\n";
        echo "  Tentando query alternativa com LIKE...\n\n";
        
        // Query alternativa usando LIKE
        $query = "
            SELECT 
              {$columnsStr}
            FROM communication_events
            WHERE source_system = 'wpp_gateway'
              AND (
                payload LIKE '%\"ImobSites\"%'
                OR metadata LIKE '%\"ImobSites\"%'
              )
            ORDER BY id DESC
            LIMIT 3
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($results)) {
            echo "✗ Nenhum evento encontrado com query alternativa\n";
            exit(0);
        }
        
        echo "✓ Encontrados " . count($results) . " evento(s) com query alternativa\n\n";
    } catch (\Exception $e) {
        // Continua com query alternativa
        $query = "
            SELECT 
              {$columnsStr}
            FROM communication_events
            WHERE source_system = 'wpp_gateway'
              AND (
                payload LIKE '%\"ImobSites\"%'
                OR metadata LIKE '%\"ImobSites\"%'
              )
            ORDER BY id DESC
            LIMIT 3
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($results)) {
            echo "✗ Nenhum evento encontrado\n";
            exit(0);
        }
        
        echo "✓ Encontrados " . count($results) . " evento(s)\n\n";
    }
    
    // Exibe os resultados
    foreach ($results as $index => $row) {
        echo str_repeat("=", 100) . "\n";
        echo "EVENTO " . ($index + 1) . ":\n";
        echo str_repeat("-", 100) . "\n";
        
        echo "ID:            " . ($row['id'] ?? 'NULL') . "\n";
        echo "Status:        " . ($row['status'] ?? 'NULL') . "\n";
        echo "Tenant ID:     " . ($row['tenant_id'] ?? 'NULL') . "\n";
        echo "Event Type:    " . ($row['event_type'] ?? 'NULL') . "\n";
        echo "Source System: " . ($row['source_system'] ?? 'NULL') . "\n";
        echo "Created At:    " . ($row['created_at'] ?? 'NULL') . "\n";
        
        // Campos de erro
        if ($hasErrorMessage) {
            echo "\nERROR_MESSAGE:\n";
            $errorMsg = $row['error_message'] ?? null;
            if ($errorMsg) {
                echo "  " . $errorMsg . "\n";
                
                // Verifica palavras-chave
                $keywords = ['channel not found', 'tenant_message_channel not found', 'cannot determine tenant', 
                            'missing mapping', 'channel', 'tenant', 'mapping', 'not found', 'invalid'];
                foreach ($keywords as $keyword) {
                    if (stripos($errorMsg, $keyword) !== false) {
                        echo "  ⚠ CONTÉM: '{$keyword}'\n";
                    }
                }
            } else {
                echo "  NULL\n";
            }
        }
        
        if ($hasErrorCode) {
            echo "\nERROR_CODE:\n";
            echo "  " . ($row['error_code'] ?? 'NULL') . "\n";
        }
        
        if ($hasErrorStack) {
            echo "\nERROR_STACK:\n";
            $errorStack = $row['error_stack'] ?? null;
            if ($errorStack) {
                if (is_string($errorStack) && strlen($errorStack) > 500) {
                    echo "  " . substr($errorStack, 0, 500) . "...\n";
                } else {
                    echo "  " . $errorStack . "\n";
                }
            } else {
                echo "  NULL\n";
            }
        }
        
        // Analisa payload e metadata para entender o contexto
        echo "\nPAYLOAD (análise):\n";
        $payload = $row['payload'] ?? null;
        if ($payload) {
            $payloadDecoded = null;
            if (is_string($payload)) {
                $payloadDecoded = json_decode($payload, true);
            } elseif (is_array($payload)) {
                $payloadDecoded = $payload;
            }
            
            if ($payloadDecoded && is_array($payloadDecoded)) {
                if (isset($payloadDecoded['session']['id'])) {
                    echo "  session.id: " . $payloadDecoded['session']['id'] . "\n";
                }
                if (isset($payloadDecoded['session']['name'])) {
                    echo "  session.name: " . $payloadDecoded['session']['name'] . "\n";
                }
            }
        } else {
            echo "  NULL\n";
        }
        
        echo "\nMETADATA (análise):\n";
        $metadata = $row['metadata'] ?? null;
        if ($metadata) {
            $metadataDecoded = null;
            if (is_string($metadata)) {
                $metadataDecoded = json_decode($metadata, true);
            } elseif (is_array($metadata)) {
                $metadataDecoded = $metadata;
            }
            
            if ($metadataDecoded && is_array($metadataDecoded)) {
                if (isset($metadataDecoded['channel_id'])) {
                    echo "  channel_id: " . $metadataDecoded['channel_id'] . "\n";
                }
            }
        } else {
            echo "  NULL\n";
        }
        
        echo "\n";
    }
    
    // Resumo final
    echo str_repeat("=", 100) . "\n";
    echo "RESUMO:\n";
    echo str_repeat("-", 100) . "\n";
    
    $foundErrors = false;
    foreach ($results as $row) {
        if ($hasErrorMessage && !empty($row['error_message'])) {
            $foundErrors = true;
            echo "✓ Erro encontrado no evento ID " . ($row['id'] ?? 'N/A') . ":\n";
            echo "  " . substr($row['error_message'], 0, 200) . "\n\n";
        }
    }
    
    if (!$foundErrors) {
        echo "⚠ Nenhuma mensagem de erro encontrada nos campos error_message\n";
        echo "  Os eventos podem ter falhado por outro motivo ou o erro está em outro lugar.\n";
    }
    
    echo "\n";
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    echo "\nPor favor, envie este erro para ajustar a query.\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

