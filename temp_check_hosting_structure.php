<?php
// Conexão direta ao banco
$host = 'mysql.hostmidiabr.com.br';
$dbname = 'hostm255_pixelhub';
$user = 'hostm255_pixelhub';
$pass = 'Pixel@2024#Hub';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

echo "=== ESTRUTURA DA TABELA hosting_accounts ===\n\n";
$stmt = $db->query('DESCRIBE hosting_accounts');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("%-30s | %-20s | %-5s | %-5s | %s\n", 
        $row['Field'], 
        $row['Type'], 
        $row['Null'], 
        $row['Key'], 
        $row['Default'] ?? 'NULL'
    );
}

echo "\n=== EXEMPLO DE DADOS (1 registro) ===\n\n";
$stmt = $db->query('SELECT * FROM hosting_accounts LIMIT 1');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    foreach ($row as $field => $value) {
        echo sprintf("%-30s: %s\n", $field, $value ?? 'NULL');
    }
}

echo "\n=== ESTRUTURA DA TABELA hosting_plans ===\n\n";
$stmt = $db->query('DESCRIBE hosting_plans');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("%-30s | %-20s | %-5s | %-5s | %s\n", 
        $row['Field'], 
        $row['Type'], 
        $row['Null'], 
        $row['Key'], 
        $row['Default'] ?? 'NULL'
    );
}
