<?php
/**
 * Verifica estrutura das tabelas de conversas e contatos
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

header('Content-Type: text/plain; charset=utf-8');

$db = DB::getConnection();

echo "=== ESTRUTURA: conversations ===\n";
$stmt = $db->query('DESCRIBE conversations');
while ($row = $stmt->fetch()) {
    echo $row['Field'] . ' - ' . $row['Type'] . ($row['Null'] === 'YES' ? ' NULL' : ' NOT NULL') . "\n";
}

echo "\n=== EXEMPLO DE DADOS: conversations (Robson) ===\n";
$stmt = $db->query("SELECT * FROM conversations WHERE contact_external_id LIKE '%99884234%' OR contact_name LIKE '%Robson%'");
$rows = $stmt->fetchAll();
foreach ($rows as $row) {
    echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

// Verifica se existe tabela de contatos
echo "=== VERIFICAR TABELAS RELACIONADAS ===\n";
$stmt = $db->query("SHOW TABLES LIKE '%contact%'");
$tables = $stmt->fetchAll();
foreach ($tables as $t) {
    $name = array_values($t)[0];
    echo "Tabela: {$name}\n";
}

// Verifica como o nome é armazenado
echo "\n=== CAMPOS DE NOME NA TABELA conversations ===\n";
$stmt = $db->query("SELECT id, contact_name, contact_external_id, conversation_key FROM conversations LIMIT 10");
$rows = $stmt->fetchAll();
foreach ($rows as $row) {
    echo "ID: {$row['id']} | Nome: " . ($row['contact_name'] ?? 'NULL') . " | Número: {$row['contact_external_id']}\n";
}
