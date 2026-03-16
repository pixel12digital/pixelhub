<?php
require_once __DIR__ . '/vendor/autoload.php';
use PixelHub\Core\Env;
Env::load();
$cfg = require __DIR__ . '/config/database.php';
$pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8", $cfg['username'], $cfg['password']);
$row = $pdo->query("SELECT * FROM whatsapp_provider_configs LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($row as $r) {
    echo "provider_type: " . $r['provider_type'] . "\n";
    echo "whapi_channel_id: " . $r['whapi_channel_id'] . "\n";
    echo "config_metadata: " . $r['config_metadata'] . "\n";
    echo "is_active: " . $r['is_active'] . "\n";
    echo "---\n";
}
