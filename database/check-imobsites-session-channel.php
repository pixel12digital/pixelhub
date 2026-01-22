<?php

/**
 * Script para verificar se existe sessão WhatsApp 'ImobSites' e canal com 'Imob' no nome
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

echo "=== Verificação: Sessão WhatsApp 'ImobSites' e Canal 'Imob' ===\n\n";

try {
    $db = DB::getConnection();
    
    // Query 1: Verificar sessão WhatsApp 'ImobSites'
    echo "1. Verificando sessão WhatsApp 'ImobSites'...\n";
    echo str_repeat("-", 80) . "\n";
    
    $query1 = "SELECT * FROM whatsapp_sessions WHERE session_id = 'ImobSites'";
    $stmt1 = $db->prepare($query1);
    $stmt1->execute();
    $results1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results1)) {
        echo "✗ Nenhuma sessão encontrada com session_id = 'ImobSites'\n\n";
    } else {
        echo "✓ Encontrada(s) " . count($results1) . " sessão(ões)\n\n";
        foreach ($results1 as $index => $row) {
            echo "SESSÃO " . ($index + 1) . ":\n";
            foreach ($row as $key => $value) {
                echo "  {$key}: " . ($value ?? 'NULL') . "\n";
            }
            echo "\n";
        }
    }
    
    // Query 2: Verificar canal com 'Imob' no nome
    echo "\n2. Verificando canais com 'Imob' no nome...\n";
    echo str_repeat("-", 80) . "\n";
    
    $query2 = "SELECT * FROM channels WHERE name LIKE '%Imob%'";
    $stmt2 = $db->prepare($query2);
    $stmt2->execute();
    $results2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results2)) {
        echo "✗ Nenhum canal encontrado com 'Imob' no nome\n\n";
    } else {
        echo "✓ Encontrado(s) " . count($results2) . " canal(is)\n\n";
        foreach ($results2 as $index => $row) {
            echo "CANAL " . ($index + 1) . ":\n";
            foreach ($row as $key => $value) {
                echo "  {$key}: " . ($value ?? 'NULL') . "\n";
            }
            echo "\n";
        }
    }
    
    // Diagnóstico final
    echo str_repeat("=", 80) . "\n";
    echo "DIAGNÓSTICO:\n";
    echo str_repeat("-", 80) . "\n";
    
    if (empty($results1) && empty($results2)) {
        echo "✓ DIAGNÓSTICO FECHADO: Não existe sessão 'ImobSites' nem canal com 'Imob' no nome.\n";
        echo "  Isso confirma que o problema está relacionado à ausência desses recursos.\n";
    } else {
        echo "⚠ ATENÇÃO: Foram encontrados registros:\n";
        if (!empty($results1)) {
            echo "  - Sessão 'ImobSites' existe\n";
        }
        if (!empty($results2)) {
            echo "  - Canal(s) com 'Imob' existe(m)\n";
        }
    }
    echo "\n";
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    
    // Verifica se a tabela existe
    if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Unknown table") !== false) {
        echo "\n⚠ A tabela pode não existir no banco de dados.\n";
    }
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

