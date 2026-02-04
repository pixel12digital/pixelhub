<?php
/**
 * Diagnóstico do evento 129276 (áudio 11:34 de 555381642320)
 */
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

$eventId = '129276';
$stmt = $db->prepare("SELECT * FROM communication_events WHERE id = ?");
$stmt->execute([$eventId]);
$ev = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ev) {
    echo "Evento 129276 não encontrado (talvez seja event_id, não id)\n";
    $stmt2 = $db->prepare("SELECT id, event_id, conversation_id, status, created_at, LEFT(payload, 1500) as payload_preview FROM communication_events WHERE id = ? OR event_id = ?");
    $stmt2->execute([$eventId, $eventId]);
    $ev = $stmt2->fetch(PDO::FETCH_ASSOC);
}

if (!$ev) {
    echo "Evento não encontrado.\n";
    exit(1);
}

echo "=== EVENTO id={$ev['id']} ===\n";
echo "event_id (UUID): " . ($ev['event_id'] ?? 'NULL') . "\n";
echo "conversation_id: " . ($ev['conversation_id'] ?? 'NULL') . "\n";
echo "status: " . ($ev['status'] ?? 'NULL') . "\n";
echo "created_at: " . ($ev['created_at'] ?? 'NULL') . "\n";
echo "\nPayload (primeiros 1500 chars):\n" . substr($ev['payload'] ?? $ev['payload_preview'] ?? '{}', 0, 1500) . "\n";

$uuid = $ev['event_id'] ?? null;
if ($uuid) {
    echo "\n=== COMMUNICATION_MEDIA para event_id={$uuid} ===\n";
    $mStmt = $db->prepare("SELECT * FROM communication_media WHERE event_id = ?");
    $mStmt->execute([$uuid]);
    $medias = $mStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($medias)) {
        echo "   ❌ Nenhum registro em communication_media\n";
    } else {
        foreach ($medias as $m) {
            echo "   id={$m['id']} media_type={$m['media_type']} stored_path={$m['stored_path']}\n";
            if (!empty($m['stored_path'])) {
                $path = __DIR__ . '/../storage/' . $m['stored_path'];
                echo "   file_exists=" . (file_exists($path) ? 'SIM' : 'NÃO') . "\n";
            }
        }
    }
}

echo "\n=== Mensagens da conversa 109 retornadas pela API do Inbox ===\n";
$convId = $ev['conversation_id'] ?? 109;
$apiStmt = $db->prepare("
    SELECT ce.id, ce.event_id, ce.created_at, ce.status,
           COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')),
                    JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')),
                    JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.type'))) AS msg_type
    FROM communication_events ce
    WHERE ce.conversation_id = ?
      AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY ce.created_at DESC
    LIMIT 15
");
$apiStmt->execute([$convId]);
$msgs = $apiStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($msgs as $m) {
    $mark = ($m['id'] == $eventId) ? ' <-- ESTE' : '';
    echo "   id={$m['id']} created={$m['created_at']} type={$m['msg_type']} status={$m['status']}{$mark}\n";
}
