<?php
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/../.env');
$db = PixelHub\Core\DB::getConnection();

$stmt = $db->query("
    SELECT ce.payload 
    FROM communication_media cm
    LEFT JOIN communication_events ce ON cm.event_id = ce.event_id
    WHERE cm.id = 287
");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && $row['payload']) {
    $p = json_decode($row['payload'], true);
    echo "=== PAYLOAD COMPLETO DA MÍDIA 287 (que funcionou) ===\n\n";
    echo json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "\n";
} else {
    echo "Payload não encontrado\n";
}
