<?php
/**
 * Verifica índices da tabela conversations para otimização de performance
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

header('Content-Type: text/plain; charset=utf-8');

$db = DB::getConnection();

echo "=== ÍNDICES ATUAIS DA TABELA conversations ===\n\n";

$stmt = $db->query("SHOW INDEX FROM conversations");
$indexes = $stmt->fetchAll();

foreach ($indexes as $idx) {
    echo "Index: {$idx['Key_name']} | Coluna: {$idx['Column_name']} | Unique: " . ($idx['Non_unique'] ? 'Não' : 'Sim') . "\n";
}

echo "\n=== CONTAGEM POR STATUS ===\n\n";

$stmt = $db->query("SELECT status, COUNT(*) as total FROM conversations GROUP BY status ORDER BY total DESC");
$statusCounts = $stmt->fetchAll();
foreach ($statusCounts as $row) {
    echo "Status: " . ($row['status'] ?? 'NULL') . " → {$row['total']} conversas\n";
}

echo "\n=== CONTAGEM incoming_lead ===\n\n";

$stmt = $db->query("SELECT is_incoming_lead, COUNT(*) as total FROM conversations GROUP BY is_incoming_lead");
$leadCounts = $stmt->fetchAll();
foreach ($leadCounts as $row) {
    echo "is_incoming_lead=" . ($row['is_incoming_lead'] ?? 'NULL') . " → {$row['total']} conversas\n";
}

echo "\n=== VERIFICAÇÃO DE ÍNDICES RECOMENDADOS ===\n\n";

// Verifica se existem índices importantes
$requiredIndexes = ['status', 'channel_type', 'is_incoming_lead', 'tenant_id', 'last_message_at'];
$existingColumns = array_column($indexes, 'Column_name');

foreach ($requiredIndexes as $col) {
    $hasIndex = in_array($col, $existingColumns);
    echo "Índice em '{$col}': " . ($hasIndex ? "✓ SIM" : "✗ NÃO (RECOMENDADO CRIAR)") . "\n";
}

echo "\n=== SQL PARA CRIAR ÍNDICES FALTANTES ===\n\n";

$missingIndexes = array_diff($requiredIndexes, $existingColumns);
if (empty($missingIndexes)) {
    echo "Todos os índices recomendados já existem.\n";
} else {
    foreach ($missingIndexes as $col) {
        echo "ALTER TABLE conversations ADD INDEX idx_{$col} ({$col});\n";
    }
}
