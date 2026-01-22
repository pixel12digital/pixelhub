<?php

/**
 * Script para verificar tabela hosting_providers
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

echo "=== Verificação: hosting_providers ===\n\n";

try {
    $db = DB::getConnection();
    
    // Verifica se a tabela existe
    $stmt = $db->query("SHOW TABLES LIKE 'hosting_providers'");
    if ($stmt->rowCount() === 0) {
        echo "✗ ERRO: Tabela hosting_providers não existe!\n";
        echo "\nSQL para criar:\n";
        echo "CREATE TABLE IF NOT EXISTS hosting_providers (\n";
        echo "    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n";
        echo "    name VARCHAR(255) NOT NULL,\n";
        echo "    slug VARCHAR(100) NOT NULL UNIQUE,\n";
        echo "    is_active TINYINT(1) NOT NULL DEFAULT 1,\n";
        echo "    sort_order INT NOT NULL DEFAULT 0,\n";
        echo "    created_at DATETIME NULL,\n";
        echo "    updated_at DATETIME NULL\n";
        echo ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n";
        exit(1);
    }
    
    echo "✓ Tabela hosting_providers existe\n\n";
    
    // Conta registros
    $stmt = $db->query("SELECT COUNT(*) FROM hosting_providers");
    $count = $stmt->fetchColumn();
    echo "Registros: {$count}\n\n";
    
    if ($count == 0) {
        echo "⚠️  ATENÇÃO: Tabela está vazia!\n";
        echo "Isso pode causar erro se HostingProviderService::getSlugToNameMap() for chamado.\n";
        echo "Mas o código já trata isso com try/catch, então não deveria causar 500.\n";
    } else {
        echo "=== Provedores ===\n";
        $stmt = $db->query("SELECT id, name, slug, is_active FROM hosting_providers ORDER BY sort_order, name");
        $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($providers as $provider) {
            echo "  - {$provider['name']} ({$provider['slug']}) - " . ($provider['is_active'] ? 'Ativo' : 'Inativo') . "\n";
        }
    }
    
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

