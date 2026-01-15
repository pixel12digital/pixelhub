<?php

/**
 * Script para verificar se o Hub está registrando ImobSites nos logs do WhatsApp
 * Busca na coluna 'message' da tabela whatsapp_generic_logs
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
    
    // Query filtrada por ImobSites na coluna message
    echo "1. Verificando logs com 'ImobSites' na coluna 'message'...\n";
    echo str_repeat("-", 80) . "\n";
    
    $queryFiltered = "SELECT id, created_at, tenant_id, phone, message FROM whatsapp_generic_logs WHERE message LIKE '%ImobSites%' ORDER BY id DESC LIMIT 50";
    $stmtFiltered = $db->prepare($queryFiltered);
    $stmtFiltered->execute();
    $resultsFiltered = $stmtFiltered->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($resultsFiltered)) {
        echo "✓ Encontrados " . count($resultsFiltered) . " log(s) com 'ImobSites'\n\n";
    } else {
        echo "✗ Nenhum log encontrado com 'ImobSites' na coluna 'message'\n\n";
    }
    
    // Busca os últimos 20 logs gerais
    echo "\n2. Últimos 20 logs gerais do WhatsApp...\n";
    echo str_repeat("-", 80) . "\n";
    
    $queryGeneral = "SELECT id, created_at, tenant_id, phone, message FROM whatsapp_generic_logs ORDER BY id DESC LIMIT 20";
    $stmtGeneral = $db->prepare($queryGeneral);
    $stmtGeneral->execute();
    $resultsGeneral = $stmtGeneral->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($resultsGeneral)) {
        echo "✗ Nenhum log encontrado na tabela\n\n";
    } else {
        echo "✓ Encontrados " . count($resultsGeneral) . " log(s)\n\n";
    }
    
    // Analisa os logs
    $foundImobSites = false;
    $foundNumber = false;
    $foundSessionId = false;
    $foundSession = false;
    $foundFrom = false;
    
    foreach ($resultsGeneral as $row) {
        $message = $row['message'] ?? '';
        $phone = $row['phone'] ?? '';
        $allText = $message . ' ' . $phone . ' ' . json_encode($row);
        
        // Verifica se contém ImobSites
        if (stripos($allText, 'ImobSites') !== false) {
            $foundImobSites = true;
        }
        
        // Verifica se contém sessionId ou session
        if (stripos($allText, 'sessionId') !== false || stripos($allText, 'session') !== false) {
            $foundSessionId = true;
        }
        
        // Verifica se contém o número 554796164699
        if (stripos($allText, '554796164699') !== false) {
            $foundNumber = true;
        }
        
        // Verifica se contém 'from'
        if (stripos($allText, '"from"') !== false || stripos($allText, "'from'") !== false) {
            $foundFrom = true;
        }
    }
    
    // Exibe resumo da análise
    echo "\n3. Análise dos logs...\n";
    echo str_repeat("-", 80) . "\n";
    echo "Aparece 'ImobSites' em algum registro? " . ($foundImobSites ? "✓ SIM" : "✗ NÃO") . "\n";
    echo "Aparece 'sessionId' ou 'session'? " . ($foundSessionId ? "✓ SIM" : "✗ NÃO") . "\n";
    echo "Aparece 'from' com o número 554796164699? " . ($foundNumber ? "✓ SIM" : "✗ NÃO") . "\n";
    echo "Aparece campo 'from'? " . ($foundFrom ? "✓ SIM" : "✗ NÃO") . "\n";
    
    echo "\nCampos encontrados na tabela whatsapp_generic_logs:\n";
    echo "  ✓ id\n";
    echo "  ✓ tenant_id\n";
    echo "  ✓ template_id\n";
    echo "  ✓ phone\n";
    echo "  ✓ message\n";
    echo "  ✓ sent_at\n";
    echo "  ✓ created_at\n";
    echo "\n";
    echo "⚠ NOTA: Esta tabela parece armazenar mensagens ENVIADAS (templates), não eventos RECEBIDOS.\n";
    echo "  Para eventos recebidos, verifique a tabela 'communication_events'.\n";
    
    // Exibe logs filtrados se existirem
    if (!empty($resultsFiltered)) {
        echo "\n\n4. Logs com 'ImobSites' (detalhado):\n";
        echo str_repeat("=", 80) . "\n";
        
        foreach ($resultsFiltered as $index => $row) {
            echo "\nLOG " . ($index + 1) . ":\n";
            echo str_repeat("-", 80) . "\n";
            echo "ID:         " . ($row['id'] ?? 'NULL') . "\n";
            echo "Tenant ID:  " . ($row['tenant_id'] ?? 'NULL') . "\n";
            echo "Phone:      " . ($row['phone'] ?? 'NULL') . "\n";
            echo "Created At: " . ($row['created_at'] ?? 'NULL') . "\n";
            echo "Message:\n";
            echo substr($row['message'] ?? '', 0, 500) . (strlen($row['message'] ?? '') > 500 ? '...' : '') . "\n";
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
        echo "Tenant ID:  " . ($row['tenant_id'] ?? 'NULL') . "\n";
        echo "Phone:      " . ($row['phone'] ?? 'NULL') . "\n";
        echo "Created At: " . ($row['created_at'] ?? 'NULL') . "\n";
        echo "Message (primeiros 200 caracteres):\n";
        echo "  " . substr($row['message'] ?? '', 0, 200) . (strlen($row['message'] ?? '') > 200 ? '...' : '') . "\n";
    }
    
    // Verifica também na tabela communication_events (se existir)
    echo "\n\n6. Verificando tabela communication_events para eventos recebidos...\n";
    echo str_repeat("=", 80) . "\n";
    
    try {
        $queryEvents = "SELECT event_id, created_at, tenant_id, event_type, source_system FROM communication_events WHERE payload LIKE '%ImobSites%' OR metadata LIKE '%ImobSites%' ORDER BY created_at DESC LIMIT 10";
        $stmtEvents = $db->prepare($queryEvents);
        $stmtEvents->execute();
        $resultsEvents = $stmtEvents->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($resultsEvents)) {
            echo "✓ Encontrados " . count($resultsEvents) . " evento(s) com 'ImobSites' em communication_events\n\n";
            foreach ($resultsEvents as $index => $row) {
                echo "EVENTO " . ($index + 1) . ":\n";
                echo str_repeat("-", 80) . "\n";
                foreach ($row as $key => $value) {
                    echo "  {$key}: " . ($value ?? 'NULL') . "\n";
                }
                echo "\n";
            }
        } else {
            echo "✗ Nenhum evento encontrado com 'ImobSites' em communication_events\n\n";
        }
    } catch (\PDOException $e) {
        echo "⚠ Erro ao consultar communication_events: " . $e->getMessage() . "\n";
        echo "  (Tabela pode não existir ou ter estrutura diferente)\n\n";
    }
    
    // Diagnóstico final
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "DIAGNÓSTICO FINAL:\n";
    echo str_repeat("-", 80) . "\n";
    
    if ($foundImobSites || !empty($resultsFiltered)) {
        echo "✓ O Hub ESTÁ registrando 'ImobSites' nos logs (whatsapp_generic_logs)\n";
    } else {
        echo "✗ O Hub NÃO está registrando 'ImobSites' nos logs (whatsapp_generic_logs)\n";
    }
    
    if ($foundNumber) {
        echo "✓ O número 554796164699 aparece nos logs\n";
    } else {
        echo "✗ O número 554796164699 NÃO aparece nos logs\n";
    }
    
    if ($foundSessionId) {
        echo "✓ Campos 'sessionId' ou 'session' aparecem nos logs\n";
    } else {
        echo "✗ Campos 'sessionId' ou 'session' NÃO aparecem nos logs\n";
    }
    
    if ($foundFrom) {
        echo "✓ Campo 'from' aparece nos logs\n";
    } else {
        echo "✗ Campo 'from' NÃO aparece nos logs\n";
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

