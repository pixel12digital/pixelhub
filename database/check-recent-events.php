<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../');
$db = DB::getConnection();

$stmt = $db->query("
    SELECT event_id, event_type, created_at, status 
    FROM communication_events 
    WHERE event_type LIKE '%whatsapp%' 
    ORDER BY created_at DESC 
    LIMIT 10
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Eventos recentes (últimos 10):\n\n";
foreach ($events as $e) {
    echo sprintf(
        "%s | %s | %s | %s\n",
        substr($e['event_id'], 0, 8) . '...',
        $e['event_type'],
        $e['created_at'],
        $e['status']
    );
}

