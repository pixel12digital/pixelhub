<?php

/**
 * Script para verificar se os campos de credenciais existem na tabela hosting_accounts
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

echo "=== Verificação de Campos de Credenciais - hosting_accounts ===\n\n";

try {
    $db = DB::getConnection();
    
    // Lista todas as colunas da tabela hosting_accounts
    $columns = $db->query("SHOW COLUMNS FROM hosting_accounts")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total de colunas: " . count($columns) . "\n\n";
    
    // Campos esperados de credenciais
    $expectedFields = [
        'hosting_panel_url',
        'hosting_panel_username',
        'hosting_panel_password',
        'site_admin_url',
        'site_admin_username',
        'site_admin_password'
    ];
    
    echo "=== Campos de Credenciais ===\n";
    $foundFields = [];
    foreach ($columns as $column) {
        if (in_array($column['Field'], $expectedFields)) {
            $foundFields[] = $column['Field'];
            echo "✓ {$column['Field']} - {$column['Type']} - " . ($column['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
        }
    }
    
    echo "\n=== Status ===\n";
    $missingFields = array_diff($expectedFields, $foundFields);
    
    if (empty($missingFields)) {
        echo "✓ Todos os campos de credenciais estão presentes!\n";
    } else {
        echo "✗ Campos faltando:\n";
        foreach ($missingFields as $field) {
            echo "  - {$field}\n";
        }
        echo "\n⚠️  É necessário executar a migration: 20250129_alter_hosting_accounts_add_credentials\n";
    }
    
    // Verifica migrations executadas
    echo "\n=== Verificação de Migration ===\n";
    $migrationName = '20250129_alter_hosting_accounts_add_credentials';
    $stmt = $db->prepare("SELECT migration_name, run_at FROM migrations WHERE migration_name = ?");
    $stmt->execute([$migrationName]);
    $migration = $stmt->fetch();
    
    if ($migration) {
        echo "✓ Migration '{$migrationName}' executada em: {$migration['run_at']}\n";
    } else {
        echo "✗ Migration '{$migrationName}' NÃO foi executada!\n";
    }
    
    echo "\n✓ Verificação concluída!\n";
    
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

