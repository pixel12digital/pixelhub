<?php
/**
 * Script para verificar se a tabela screen_recordings existe no banco remoto
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

echo "=== Verificação da Tabela screen_recordings ===\n\n";

try {
    // Carrega .env
    Env::load();
    
    // Obtém conexão
    $db = DB::getConnection();
    
    // Verifica qual banco está conectado
    $currentDb = $db->query('SELECT DATABASE() as db')->fetch();
    echo "Banco de dados conectado: {$currentDb['db']}\n";
    
    $host = Env::get('DB_HOST', 'localhost');
    echo "Host: {$host}\n\n";
    
    // Verifica se a tabela existe
    $tables = $db->query("SHOW TABLES LIKE 'screen_recordings'")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "✗ Tabela 'screen_recordings' NÃO existe!\n\n";
        echo "É necessário executar a migration:\n";
        echo "  php database/migrate.php\n\n";
        exit(1);
    }
    
    echo "✓ Tabela 'screen_recordings' existe!\n\n";
    
    // Verifica estrutura da tabela
    echo "=== Estrutura da Tabela ===\n";
    $columns = $db->query("SHOW COLUMNS FROM screen_recordings")->fetchAll(PDO::FETCH_ASSOC);
    
    $expectedColumns = [
        'id', 'task_id', 'file_path', 'file_name', 'original_name',
        'mime_type', 'size_bytes', 'duration_seconds', 'has_audio',
        'public_token', 'created_at', 'created_by'
    ];
    
    $foundColumns = [];
    foreach ($columns as $column) {
        $foundColumns[] = $column['Field'];
        echo "  - {$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\n=== Verificação de Colunas ===\n";
    $missingColumns = array_diff($expectedColumns, $foundColumns);
    
    if (empty($missingColumns)) {
        echo "✓ Todas as colunas esperadas estão presentes!\n";
    } else {
        echo "✗ Colunas faltando:\n";
        foreach ($missingColumns as $col) {
            echo "  - {$col}\n";
        }
        echo "\n⚠️  É necessário executar a migration novamente.\n";
        exit(1);
    }
    
    // Verifica se há gravações
    $count = $db->query("SELECT COUNT(*) FROM screen_recordings")->fetchColumn();
    echo "\n=== Dados ===\n";
    echo "Total de gravações na biblioteca: {$count}\n";
    
    // Verifica migration
    echo "\n=== Status da Migration ===\n";
    $migrationName = '20250128_create_screen_recordings_table';
    $stmt = $db->prepare("SELECT migration_name, run_at FROM migrations WHERE migration_name = ?");
    $stmt->execute([$migrationName]);
    $migration = $stmt->fetch();
    
    if ($migration) {
        echo "✓ Migration '{$migrationName}' executada em: {$migration['run_at']}\n";
    } else {
        echo "✗ Migration '{$migrationName}' NÃO foi registrada!\n";
        echo "  (A tabela existe, mas a migration não está registrada)\n";
    }
    
    echo "\n✓ Verificação concluída com sucesso!\n";
    
} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}












