<?php

/**
 * Script para executar seed de categorias de serviço
 * 
 * Uso: php database/seed-service-types.php
 */

// Carrega autoload do Composer se existir, senão carrega manualmente
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Autoload manual simples
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

// Carrega .env
Env::load();

echo "=== Seed de Categorias de Serviço - Pixel Hub ===\n\n";

try {
    $db = DB::getConnection();
    
    // Carrega o seeder
    require_once __DIR__ . '/seeds/SeedBillingServiceTypes.php';
    
    $seeder = new SeedBillingServiceTypes();
    $seeder->run($db);
    
    echo "\n✓ Processo concluído!\n";
    
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    error_log("Erro no seed-service-types.php: " . $e->getMessage());
    exit(1);
}

