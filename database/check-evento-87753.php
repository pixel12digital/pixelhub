<?php
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load(__DIR__ . '/../.env');
$db = DB::getConnection();

echo "=== EVENTO 87753 (áudio de 554796164699 ~18:30) ===\n\n";

$stmt = $db->query("SELECT id, event_id, event_type, status, created_at, payload FROM communication_events WHERE id = 87753");
$e = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$e) {
    echo "Evento não encontrado!\n";
    exit(1);
}

echo "id: {$e['id']}\n";
echo "event_id: {$e['event_id']}\n";
echo "event_type: {$e['event_type']}\n";
echo "status: {$e['status']}\n";
echo "created_at: {$e['created_at']}\n\n";

$p = json_decode($e['payload'], true);

echo "=== ESTRUTURA DO PAYLOAD ===\n";
echo "message.type: " . ($p['message']['type'] ?? 'N/A') . "\n";
echo "raw.payload.type: " . ($p['raw']['payload']['type'] ?? 'N/A') . "\n";
echo "raw.payload.mimetype: " . ($p['raw']['payload']['mimetype'] ?? 'N/A') . "\n";
echo "raw.payload.mediaKey (presente): " . (isset($p['raw']['payload']['mediaKey']) ? 'SIM' : 'NAO') . "\n";
echo "raw.payload.filehash (presente): " . (isset($p['raw']['payload']['filehash']) ? 'SIM' : 'NAO') . "\n";
echo "raw.payload.body (presente): " . (isset($p['raw']['payload']['body']) ? 'SIM ('.strlen($p['raw']['payload']['body']).' chars)' : 'NAO') . "\n";

echo "\n=== TODOS OS CAMPOS DE raw.payload ===\n";
if (isset($p['raw']['payload'])) {
    foreach ($p['raw']['payload'] as $k => $v) {
        if (is_scalar($v) && strlen((string)$v) < 100) {
            echo "  {$k}: {$v}\n";
        } elseif (is_scalar($v)) {
            echo "  {$k}: (" . strlen((string)$v) . " chars)\n";
        } else {
            echo "  {$k}: (" . (is_array($v) ? 'array' : 'object') . ")\n";
        }
    }
}

// Verifica mídia associada
echo "\n=== MÍDIA ASSOCIADA (communication_media) ===\n";
$stmt2 = $db->prepare("SELECT * FROM communication_media WHERE event_id = ?");
$stmt2->execute([$e['event_id']]);
$m = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($m) {
    foreach ($m as $k => $v) {
        echo "  {$k}: {$v}\n";
    }
} else {
    echo "  NENHUMA mídia encontrada para event_id: {$e['event_id']}\n";
}

// Também verifica pelo ID interno do evento
echo "\n=== BUSCA ALTERNATIVA: Mídias recentes do tenant ===\n";
$stmt3 = $db->query("
    SELECT cm.*, ce.id as ce_id, ce.created_at as ce_created
    FROM communication_media cm
    LEFT JOIN communication_events ce ON cm.event_id = ce.event_id
    WHERE cm.created_at >= '2026-01-29 18:25:00'
      AND cm.created_at <= '2026-01-29 18:35:00'
    ORDER BY cm.created_at DESC
");
$medias = $stmt3->fetchAll(PDO::FETCH_ASSOC);
echo "Mídias entre 18:25 e 18:35:\n";
foreach ($medias as $m) {
    echo "  [{$m['created_at']}] id={$m['id']} type={$m['media_type']} mime={$m['mime_type']}\n";
    echo "    path: {$m['stored_path']}\n";
    echo "    event_id: {$m['event_id']} (ce.id={$m['ce_id']})\n\n";
}

echo "\n=== FIM ===\n";
