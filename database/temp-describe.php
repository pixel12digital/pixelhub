<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
use PixelHub\Core\DB;
use PixelHub\Core\Env;
Env::load(__DIR__ . '/../.env');
$pdo = DB::getConnection();
$stmt = $pdo->query('DESCRIBE conversations');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' - ' . $row['Type'] . PHP_EOL;
}
