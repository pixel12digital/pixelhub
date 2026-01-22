<?php

/**
 * Script para verificar tabelas criadas no banco
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

echo "=== Verificação de Tabelas - Pixel Hub ===\n\n";

try {
    $db = DB::getConnection();
    
    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tabelas criadas no banco: " . count($tables) . "\n\n";
    
    foreach ($tables as $table) {
        echo "✓ {$table}\n";
    }
    
    // Verifica migrations executadas
    echo "\n=== Migrations Executadas ===\n";
    $migrations = $db->query('SELECT migration_name, run_at FROM migrations ORDER BY run_at')->fetchAll();
    foreach ($migrations as $migration) {
        echo "✓ {$migration['migration_name']} - {$migration['run_at']}\n";
    }
    
    // Verifica usuário admin
    echo "\n=== Usuário Admin ===\n";
    $admin = $db->query("SELECT id, name, email, is_internal FROM users WHERE email = 'admin@pixel12.test'")->fetch();
    if ($admin) {
        echo "✓ Usuário encontrado:\n";
        echo "  ID: {$admin['id']}\n";
        echo "  Nome: {$admin['name']}\n";
        echo "  Email: {$admin['email']}\n";
        echo "  Interno: " . ($admin['is_internal'] ? 'Sim' : 'Não') . "\n";
    }
    
    // Verifica tenant exemplo
    echo "\n=== Tenant Exemplo ===\n";
    $tenant = $db->query("SELECT id, name, status FROM tenants WHERE name = 'Cliente Exemplo'")->fetch();
    if ($tenant) {
        echo "✓ Tenant encontrado:\n";
        echo "  ID: {$tenant['id']}\n";
        echo "  Nome: {$tenant['name']}\n";
        echo "  Status: {$tenant['status']}\n";
    }
    
    echo "\n✓ Verificação concluída!\n";
    
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

