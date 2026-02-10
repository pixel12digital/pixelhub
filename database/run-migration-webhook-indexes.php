<?php

/**
 * Executa a migration de índices para performance do webhook (tenant_message_channels)
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
        if (file_exists($file)) require_once $file;
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== Migration: índices webhook (tenant_message_channels) ===\n\n";

$db = DB::getConnection();
require_once __DIR__ . '/migrations/20260213_add_webhook_performance_indexes.php';

$migration = new AddWebhookPerformanceIndexes();

try {
    $migration->up($db);
    echo "✅ Migration executada com sucesso.\n";
    $stmt = $db->query("SHOW INDEX FROM tenant_message_channels WHERE Key_name = 'idx_provider_enabled'");
    if ($stmt->rowCount() > 0) {
        echo "   Índice idx_provider_enabled existe em tenant_message_channels.\n";
    }
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";
