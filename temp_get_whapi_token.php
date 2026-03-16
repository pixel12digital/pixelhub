<?php
require_once __DIR__ . '/vendor/autoload.php';
use PixelHub\Core\Env;
Env::load();

$cfg = require __DIR__ . '/config/database.php';
$pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8", $cfg['username'], $cfg['password']);

$row = $pdo->query("SELECT whapi_api_token FROM whatsapp_provider_configs WHERE provider='whapi' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if ($row && $row['whapi_api_token']) {
    // Token is encrypted - decrypt it
    $key = Env::get('APP_KEY');
    $token = $row['whapi_api_token'];
    // Try base64 decode first
    $decoded = base64_decode($token, true);
    echo "Token (raw): " . $token . "\n";
    if ($decoded) echo "Token (decoded): " . $decoded . "\n";
} else {
    echo "Token not found\n";
}
