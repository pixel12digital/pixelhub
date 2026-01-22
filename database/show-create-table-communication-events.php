<?php

/**
 * Script para mostrar a estrutura completa da tabela communication_events
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

echo "=== SHOW CREATE TABLE communication_events ===\n\n";

try {
    $db = DB::getConnection();
    
    $query = "SHOW CREATE TABLE communication_events";
    
    $stmt = $db->query($query);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (empty($result)) {
        echo "✗ Tabela communication_events não encontrada.\n";
        exit(1);
    }
    
    // O resultado tem duas colunas: Table e Create Table
    $createTable = $result['Create Table'] ?? $result['CREATE TABLE'] ?? null;
    
    if ($createTable) {
        echo $createTable . "\n";
    } else {
        // Se não encontrou na chave padrão, tenta todas as chaves
        echo "Resultado completo:\n";
        print_r($result);
    }
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

