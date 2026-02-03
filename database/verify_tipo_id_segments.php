<?php

/**
 * Verifica se a coluna tipo_id existe em agenda_block_segments
 * Usa as credenciais do .env do projeto
 * 
 * Uso: php database/verify_tipo_id_segments.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
\PixelHub\Core\Env::load();

$db = \PixelHub\Core\DB::getConnection();

echo "=== Verificação: tipo_id em agenda_block_segments ===\n\n";

try {
    $stmt = $db->query("SHOW COLUMNS FROM agenda_block_segments LIKE 'tipo_id'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($col) {
        echo "✓ Coluna tipo_id existe\n";
        echo "  Tipo: {$col['Type']}\n";
        echo "  Null: {$col['Null']}\n";
        echo "  Default: " . ($col['Default'] ?? 'NULL') . "\n";
    } else {
        echo "✗ Coluna tipo_id NÃO existe. Execute: php database/migrate.php\n";
        exit(1);
    }
    
    // Verifica FK
    $stmt = $db->query("
        SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'agenda_block_segments'
        AND COLUMN_NAME = 'tipo_id'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $fk = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fk) {
        echo "  FK: {$fk['CONSTRAINT_NAME']} → {$fk['REFERENCED_TABLE_NAME']}({$fk['REFERENCED_COLUMN_NAME']})\n";
    }
    
    echo "\n✓ Verificação concluída.\n";
} catch (\Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
