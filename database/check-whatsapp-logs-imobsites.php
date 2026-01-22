<?php

/**
 * Script para verificar se o Hub está registrando ImobSites nos logs do WhatsApp
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

echo "=== Verificação: Logs WhatsApp - ImobSites ===\n\n";

try {
    $db = DB::getConnection();
    
    // Primeiro, verifica se há logs com ImobSites
    echo "1. Verificando logs com 'ImobSites' no payload...\n";
    echo str_repeat("-", 80) . "\n";
    
    $queryFiltered = "SELECT id, created_at, payload FROM whatsapp_generic_logs WHERE payload LIKE '%ImobSites%' ORDER BY id DESC LIMIT 50";
    $stmtFiltered = $db->prepare($queryFiltered);
    $stmtFiltered->execute();
    $resultsFiltered = $stmtFiltered->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($resultsFiltered)) {
        echo "✓ Encontrados " . count($resultsFiltered) . " log(s) com 'ImobSites'\n\n";
    } else {
        echo "✗ Nenhum log encontrado com 'ImobSites' no payload\n\n";
    }
    
    // Busca os últimos 20 logs gerais
    echo "\n2. Últimos 20 logs gerais do WhatsApp...\n";
    echo str_repeat("-", 80) . "\n";
    
    $queryGeneral = "SELECT id, created_at, payload FROM whatsapp_generic_logs ORDER BY id DESC LIMIT 20";
    $stmtGeneral = $db->prepare($queryGeneral);
    $stmtGeneral->execute();
    $resultsGeneral = $stmtGeneral->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($resultsGeneral)) {
        echo "✗ Nenhum log encontrado na tabela\n\n";
        exit(0);
    }
    
    echo "✓ Encontrados " . count($resultsGeneral) . " log(s)\n\n";
    
    // Analisa os logs
    $foundImobSites = false;
    $foundNumber = false;
    $foundFields = [];
    
    foreach ($resultsGeneral as $index => $row) {
        $payload = $row['payload'] ?? '';
        $payloadDecoded = null;
        
        // Tenta decodificar JSON se for string JSON
        if (is_string($payload) && !empty($payload)) {
            $payloadDecoded = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $payloadDecoded = null;
            }
        }
        
        // Verifica se contém ImobSites
        if (stripos($payload, 'ImobSites') !== false || 
            (is_array($payloadDecoded) && stripos(json_encode($payloadDecoded), 'ImobSites') !== false)) {
            $foundImobSites = true;
        }
        
        // Verifica se contém o número 554796164699
        if (stripos($payload, '554796164699') !== false || 
            (is_array($payloadDecoded) && stripos(json_encode($payloadDecoded), '554796164699') !== false)) {
            $foundNumber = true;
        }
        
        // Verifica campos comuns
        if (is_array($payloadDecoded)) {
            if (isset($payloadDecoded['sessionId']) || isset($payloadDecoded['session'])) {
                $foundFields['sessionId/session'] = true;
            }
            if (isset($payloadDecoded['tenant_id']) || isset($payloadDecoded['tenantId'])) {
                $foundFields['tenant_id'] = true;
            }
            if (isset($payloadDecoded['channel']) || isset($payloadDecoded['channel_id'])) {
                $foundFields['channel'] = true;
            }
            if (isset($payloadDecoded['source']) || isset($payloadDecoded['source_system'])) {
                $foundFields['source'] = true;
            }
            if (isset($payloadDecoded['eventType']) || isset($payloadDecoded['event_type'])) {
                $foundFields['eventType'] = true;
            }
            if (isset($payloadDecoded['from'])) {
                $foundFields['from'] = true;
            }
        }
    }
    
    // Exibe resumo da análise
    echo "\n3. Análise dos logs...\n";
    echo str_repeat("-", 80) . "\n";
    echo "Aparece 'ImobSites' em algum payload? " . ($foundImobSites ? "✓ SIM" : "✗ NÃO") . "\n";
    echo "Aparece 'from' com o número 554796164699? " . ($foundNumber ? "✓ SIM" : "✗ NÃO") . "\n";
    echo "\nCampos encontrados nos payloads:\n";
    if (empty($foundFields)) {
        echo "  ✗ Nenhum campo específico encontrado (tenant_id, channel, source, eventType)\n";
    } else {
        foreach ($foundFields as $field => $value) {
            echo "  ✓ {$field}\n";
        }
    }
    
    // Exibe logs filtrados se existirem
    if (!empty($resultsFiltered)) {
        echo "\n\n4. Logs com 'ImobSites' (detalhado):\n";
        echo str_repeat("=", 80) . "\n";
        
        foreach ($resultsFiltered as $index => $row) {
            echo "\nLOG " . ($index + 1) . ":\n";
            echo str_repeat("-", 80) . "\n";
            echo "ID:         " . ($row['id'] ?? 'NULL') . "\n";
            echo "Created At: " . ($row['created_at'] ?? 'NULL') . "\n";
            echo "Payload:\n";
            
            $payload = $row['payload'] ?? '';
            $payloadDecoded = json_decode($payload, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($payloadDecoded)) {
                // Exibe payload formatado
                echo json_encode($payloadDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                // Exibe payload como string
                echo substr($payload, 0, 500) . (strlen($payload) > 500 ? '...' : '') . "\n";
            }
        }
    }
    
    // Exibe alguns logs gerais para análise
    echo "\n\n5. Amostra dos últimos logs gerais (primeiros 5):\n";
    echo str_repeat("=", 80) . "\n";
    
    $sampleSize = min(5, count($resultsGeneral));
    for ($i = 0; $i < $sampleSize; $i++) {
        $row = $resultsGeneral[$i];
        echo "\nLOG " . ($i + 1) . ":\n";
        echo str_repeat("-", 80) . "\n";
        echo "ID:         " . ($row['id'] ?? 'NULL') . "\n";
        echo "Created At: " . ($row['created_at'] ?? 'NULL') . "\n";
        echo "Payload (primeiros 300 caracteres):\n";
        
        $payload = $row['payload'] ?? '';
        $payloadDecoded = json_decode($payload, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($payloadDecoded)) {
            // Exibe estrutura do JSON
            echo "  Estrutura JSON detectada. Chaves principais: " . implode(', ', array_keys($payloadDecoded)) . "\n";
            // Exibe preview
            $preview = json_encode($payloadDecoded, JSON_UNESCAPED_UNICODE);
            echo "  Preview: " . substr($preview, 0, 300) . (strlen($preview) > 300 ? '...' : '') . "\n";
        } else {
            echo "  " . substr($payload, 0, 300) . (strlen($payload) > 300 ? '...' : '') . "\n";
        }
    }
    
    // Diagnóstico final
    echo "\n\n" . str_repeat("=", 80) . "\n";
    echo "DIAGNÓSTICO FINAL:\n";
    echo str_repeat("-", 80) . "\n";
    
    if ($foundImobSites) {
        echo "✓ O Hub ESTÁ registrando 'ImobSites' nos logs\n";
    } else {
        echo "✗ O Hub NÃO está registrando 'ImobSites' nos logs\n";
    }
    
    if ($foundNumber) {
        echo "✓ O número 554796164699 aparece nos logs\n";
    } else {
        echo "✗ O número 554796164699 NÃO aparece nos logs\n";
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

