<?php

/**
 * Script para verificar se a migration de gravações de tela foi executada
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

echo "=== Verificação de Migration de Gravações de Tela ===\n\n";

try {
    $db = DB::getConnection();
    
    // Verifica se a migration foi executada
    $migrationName = '20250125_add_recording_fields_to_task_attachments';
    $stmt = $db->prepare("SELECT migration_name, run_at FROM migrations WHERE migration_name = ?");
    $stmt->execute([$migrationName]);
    $migration = $stmt->fetch();
    
    if ($migration) {
        echo "✓ Migration '{$migrationName}' executada em: {$migration['run_at']}\n\n";
    } else {
        echo "✗ Migration '{$migrationName}' NÃO foi executada!\n\n";
    }
    
    // Verifica estrutura da tabela task_attachments
    echo "=== Estrutura da Tabela task_attachments ===\n";
    $columns = $db->query("SHOW COLUMNS FROM task_attachments")->fetchAll(PDO::FETCH_ASSOC);
    
    $expectedColumns = ['recording_type', 'duration'];
    $foundColumns = [];
    
    foreach ($columns as $column) {
        if (in_array($column['Field'], $expectedColumns)) {
            $foundColumns[] = $column['Field'];
            echo "✓ {$column['Field']} - {$column['Type']} - " . ($column['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
        }
    }
    
    echo "\n=== Status ===\n";
    $missingColumns = array_diff($expectedColumns, $foundColumns);
    
    if (empty($missingColumns)) {
        echo "✓ Todas as colunas necessárias estão presentes!\n";
    } else {
        echo "✗ Colunas faltando:\n";
        foreach ($missingColumns as $col) {
            echo "  - {$col}\n";
        }
        echo "\n⚠️  É necessário executar a migration: {$migrationName}\n";
    }
    
    // Verifica se há gravações no banco
    echo "\n=== Gravações de Tela no Banco ===\n";
    $stmt = $db->query("SELECT COUNT(*) as total FROM task_attachments WHERE recording_type = 'screen_recording'");
    $count = $stmt->fetchColumn();
    echo "Total de gravações: {$count}\n";
    
    if ($count > 0) {
        $stmt = $db->query("SELECT id, original_name, duration, uploaded_at FROM task_attachments WHERE recording_type = 'screen_recording' ORDER BY uploaded_at DESC LIMIT 5");
        $recordings = $stmt->fetchAll();
        echo "\nÚltimas 5 gravações:\n";
        foreach ($recordings as $rec) {
            echo "  - ID: {$rec['id']}, Nome: {$rec['original_name']}, Duração: " . ($rec['duration'] ?? 'N/A') . "s, Data: {$rec['uploaded_at']}\n";
        }
    }
    
    echo "\n✓ Verificação concluída!\n";
    
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}












