<?php
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

$db = new PDO(
    'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== VERIFICAÇÃO TABELAS DE TEMPLATES ===\n\n";

// Verifica tabelas com "template" no nome
$stmt = $db->query("SHOW TABLES LIKE '%template%'");
$tables = $stmt->fetchAll(PDO::FETCH_NUM);

echo "Tabelas encontradas: " . count($tables) . "\n\n";

foreach ($tables as $table) {
    $tableName = $table[0];
    echo "Tabela: {$tableName}\n";
    
    // Mostra estrutura
    $stmt = $db->query("DESCRIBE {$tableName}");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    
    // Conta registros
    $stmt = $db->query("SELECT COUNT(*) as total FROM {$tableName}");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  Total de registros: {$count['total']}\n\n";
}

// Verifica se existe a tabela whatsapp_templates
$stmt = $db->query("SHOW TABLES LIKE 'whatsapp_templates'");
if ($stmt->rowCount() > 0) {
    echo "=== TEMPLATES APROVADOS ===\n\n";
    $stmt = $db->query("SELECT * FROM whatsapp_templates WHERE status = 'approved' LIMIT 5");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($templates) > 0) {
        foreach ($templates as $t) {
            echo "ID: {$t['id']}\n";
            foreach ($t as $key => $value) {
                if ($key !== 'id' && !is_null($value)) {
                    echo "  {$key}: " . (strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value) . "\n";
                }
            }
            echo "\n";
        }
    } else {
        echo "Nenhum template aprovado encontrado\n";
    }
}
