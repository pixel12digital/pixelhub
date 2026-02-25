<?php
require 'vendor/autoload.php';
require 'src/Core/DB.php';
require 'src/Core/Env.php';

\PixelHub\Core\Env::load(__DIR__);
$db = \PixelHub\Core\DB::getConnection();

echo "=== Colunas da tabela billing_invoices ===\n\n";

$stmt = $db->query('DESCRIBE billing_invoices');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . "\n";
}
