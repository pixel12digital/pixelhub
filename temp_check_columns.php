<?php
require 'vendor/autoload.php';

use PixelHub\Core\DB;

$db = DB::getConnection();
$stmt = $db->query('SHOW COLUMNS FROM prospecting_results');

echo "=== COLUNAS DA TABELA prospecting_results ===\n";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
