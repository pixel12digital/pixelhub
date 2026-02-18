<?php
require_once __DIR__ . '/src/Core/DB.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== Estrutura agenda_manual_items ===\n";
$cols = $db->query('DESCRIBE agenda_manual_items')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo $col['Field'] . ' - ' . $col['Type'] . "\n";
}

echo "\n=== Verificando agenda_manual_items ID 2 ===\n";
$stmt = $db->prepare('SELECT * FROM agenda_manual_items WHERE id = 2');
$stmt->execute();
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if ($item) {
    foreach ($item as $key => $value) {
        echo $key . ': ' . $value . "\n";
    }
} else {
    echo "Item ID 2 não encontrado\n";
}
?>
