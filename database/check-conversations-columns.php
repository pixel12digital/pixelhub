<?php

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

$stmt = $db->query('DESCRIBE conversations');
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== COLUNAS DA TABELA conversations ===\n\n";
foreach($cols as $col) {
    echo $col['Field'] . ' (' . $col['Type'] . ')' . PHP_EOL;
}

