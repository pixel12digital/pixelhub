<?php

/**
 * Script para verificar tabela channels e listar tabelas disponíveis
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

echo "=== Verificação: Tabela Channels e Estrutura do Banco ===\n\n";

try {
    $db = DB::getConnection();
    
    // Verificar se a tabela channels existe
    echo "1. Verificando se a tabela 'channels' existe...\n";
    echo str_repeat("-", 80) . "\n";
    
    try {
        $query1 = "SELECT * FROM channels WHERE name LIKE '%Imob%'";
        $stmt1 = $db->prepare($query1);
        $stmt1->execute();
        $results1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($results1)) {
            echo "✓ Tabela 'channels' existe, mas nenhum canal encontrado com 'Imob' no nome\n\n";
        } else {
            echo "✓ Encontrado(s) " . count($results1) . " canal(is) com 'Imob' no nome\n\n";
            foreach ($results1 as $index => $row) {
                echo "CANAL " . ($index + 1) . ":\n";
                foreach ($row as $key => $value) {
                    echo "  {$key}: " . ($value ?? 'NULL') . "\n";
                }
                echo "\n";
            }
        }
    } catch (\PDOException $e) {
        echo "✗ Tabela 'channels' não existe: " . $e->getMessage() . "\n\n";
    }
    
    // Listar todas as tabelas do banco
    echo "\n2. Listando todas as tabelas do banco de dados...\n";
    echo str_repeat("-", 80) . "\n";
    
    $query2 = "SHOW TABLES";
    $stmt2 = $db->prepare($query2);
    $stmt2->execute();
    $tables = $stmt2->fetchAll(PDO::FETCH_COLUMN);
    
    echo "✓ Total de tabelas: " . count($tables) . "\n\n";
    
    // Procurar tabelas relacionadas a WhatsApp ou sessões
    $whatsappTables = array_filter($tables, function($table) {
        return stripos($table, 'whatsapp') !== false || 
               stripos($table, 'session') !== false ||
               stripos($table, 'channel') !== false;
    });
    
    if (!empty($whatsappTables)) {
        echo "Tabelas relacionadas a WhatsApp/Sessões/Canais:\n";
        foreach ($whatsappTables as $table) {
            echo "  - {$table}\n";
        }
        echo "\n";
    }
    
    // Diagnóstico final
    echo str_repeat("=", 80) . "\n";
    echo "DIAGNÓSTICO:\n";
    echo str_repeat("-", 80) . "\n";
    
    $hasWhatsappSessions = in_array('whatsapp_sessions', $tables);
    $hasChannels = in_array('channels', $tables);
    
    if (!$hasWhatsappSessions && !$hasChannels) {
        echo "✗ Tabelas 'whatsapp_sessions' e 'channels' não existem no banco.\n";
    } elseif (!$hasWhatsappSessions) {
        echo "✗ Tabela 'whatsapp_sessions' não existe no banco.\n";
        echo "✓ Tabela 'channels' existe.\n";
    } elseif (!$hasChannels) {
        echo "✓ Tabela 'whatsapp_sessions' existe.\n";
        echo "✗ Tabela 'channels' não existe no banco.\n";
    } else {
        echo "✓ Ambas as tabelas existem.\n";
    }
    
    if (empty($results1 ?? [])) {
        echo "\n✓ DIAGNÓSTICO FECHADO: Não existe sessão 'ImobSites' nem canal com 'Imob' no nome.\n";
        echo "  Isso confirma que o problema está relacionado à ausência desses recursos.\n";
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

