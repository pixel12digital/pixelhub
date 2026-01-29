<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
use PixelHub\Core\DB;
use PixelHub\Core\Env;
Env::load(__DIR__ . '/../.env');
$pdo = DB::getConnection();

echo "=== Últimos eventos da conversa 115 ===\n\n";
$sql = "SELECT id, event_id, event_type, 
               JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) as from_num,
               JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) as to_num,
               SUBSTRING(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.text')), 1, 60) as texto,
               created_at
        FROM communication_events 
        WHERE conversation_id = 115
        ORDER BY created_at DESC
        LIMIT 10";
$stmt = $pdo->query($sql);
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "[{$row['created_at']}] {$row['event_type']}\n";
    echo "  from={$row['from_num']} to={$row['to_num']}\n";
    echo "  texto: {$row['texto']}\n\n";
}

echo "\n=== Últimos 5 eventos INBOUND de qualquer conversa ===\n\n";
$sql = "SELECT conversation_id, event_type,
               JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) as from_num,
               created_at
        FROM communication_events 
        WHERE event_type = 'message_received'
        ORDER BY created_at DESC
        LIMIT 5";
$stmt = $pdo->query($sql);
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "[{$row['created_at']}] conv={$row['conversation_id']} from={$row['from_num']}\n";
}
