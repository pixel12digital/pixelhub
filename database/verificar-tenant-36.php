<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

$db = DB::getConnection();

$stmt = $db->query('SELECT id, name, phone FROM tenants WHERE id = 36');
$tenant = $stmt->fetch();

echo "Tenant 36:\n";
echo json_encode($tenant, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
