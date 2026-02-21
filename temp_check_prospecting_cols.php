<?php
require_once __DIR__ . '/vendor/autoload.php';
$config = require __DIR__ . '/config/database.php';
$dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
$pdo = new PDO($dsn, $config['username'], $config['password']);

echo "=== prospecting_recipes columns ===\n";
$cols = $pdo->query("SHOW COLUMNS FROM prospecting_recipes")->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $c) echo $c['Field'] . ' | ' . $c['Type'] . "\n";

echo "\n=== prospecting_results columns ===\n";
$cols2 = $pdo->query("SHOW COLUMNS FROM prospecting_results")->fetchAll(PDO::FETCH_ASSOC);
foreach($cols2 as $c) echo $c['Field'] . ' | ' . $c['Type'] . "\n";

echo "\n=== Recent PHP error log ===\n";
$logPaths = [
    '/home/pixel12d/logs/pixelhub.log',
    __DIR__ . '/logs/error.log',
    ini_get('error_log'),
];
foreach($logPaths as $p) {
    if($p && file_exists($p)) {
        echo "Log: $p\n";
        $lines = file($p);
        $last = array_slice($lines, -30);
        echo implode('', $last);
        break;
    }
}
