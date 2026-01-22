<?php
require_once __DIR__ . '/../public/index.php';
use PixelHub\Core\DB;

$phone = '5511965221349';
$db = DB::getConnection();

echo "Buscando eventos do numero {$phone}\n";

$sql = "SELECT event_id, created_at, LEFT(payload, 500) as payload_preview FROM communication_events WHERE event_type = 'whatsapp.inbound.message' AND (payload LIKE ? OR payload LIKE ?) ORDER BY created_at DESC LIMIT 10";
$stmt = $db->prepare($sql);
$stmt->execute(["%{$phone}%", "%" . preg_replace('/[^0-9]/', '', $phone) . "%"]);

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Encontrados: " . count($events) . " eventos\n\n";

foreach ($events as $e) {
    $p = json_decode($e['payload_preview'], true);
    if (!$p) continue;
    $t = $p['type'] ?? $p['message']['type'] ?? 'text';
    if (in_array(strtolower($t), ['audio','ptt','voice','image','video'])) {
        echo "MIDIA ENCONTRADA:\n";
        echo "Event ID: {$e['event_id']}\n";
        echo "Tipo: {$t}\n";
        echo "Data: {$e['created_at']}\n";
        
        $m = $db->prepare("SELECT * FROM communication_media WHERE event_id = ?");
        $m->execute([$e['event_id']]);
        $media = $m->fetch(PDO::FETCH_ASSOC);
        if ($media) {
            echo "Processada: SIM\n";
            echo "Caminho: {$media['stored_path']}\n";
        } else {
            echo "Processada: NAO\n";
        }
        echo "\n";
        break;
    }
}







