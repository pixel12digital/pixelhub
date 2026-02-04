<?php
/**
 * Verifica se há áudios inbound no sistema (qualquer número) - últimos 7 dias
 */
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== ÁUDIOS INBOUND (qualquer número) - últimos 7 dias ===\n";

$stmt = $db->query("
    SELECT 
        ce.id,
        ce.event_id,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) AS msg_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS channel_id,
        CASE 
            WHEN ce.payload LIKE '%audioMessage%' THEN 'audioMessage'
            WHEN ce.payload LIKE '%ptt%' THEN 'ptt'
            WHEN JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')) IN ('audio','ptt') THEN 'type_audio'
            ELSE 'outro'
        END AS tipo
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.inbound.message'
      AND ce.source_system = 'wpp_gateway'
      AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
      AND (
        ce.payload LIKE '%audioMessage%'
        OR ce.payload LIKE '%\"ptt\"%'
        OR ce.payload LIKE '%\"type\":\"ptt\"%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')) IN ('audio','ptt')
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) = 'audio'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.type')) IN ('ptt','audio')
      )
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$audios = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($audios)) {
    echo "   ❌ Nenhum áudio inbound no sistema nos últimos 7 dias\n";
    echo "   (Isso pode indicar que o gateway não envia webhooks para áudios)\n";
} else {
    echo "   ✅ Encontrados " . count($audios) . " áudio(s):\n\n";
    foreach ($audios as $a) {
        echo "   [{$a['created_at']}] id={$a['id']} tipo={$a['tipo']} from={$a['msg_from']} channel={$a['channel_id']}\n";
    }
}

echo "\n=== Eventos 555381642320 entre 11:30 e 12:00 em 03/02 ===\n";
$stmt2 = $db->prepare("
    SELECT id, created_at, 
           COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.type')), 
                    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')),
                    CASE WHEN payload LIKE '%audio%' THEN 'audio' WHEN payload LIKE '%ptt%' THEN 'ptt' ELSE 'text' END) AS tipo,
           LEFT(payload, 300) AS payload_preview
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
      AND source_system = 'wpp_gateway'
      AND (payload LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) LIKE ?)
      AND created_at >= '2026-02-03 11:30:00'
      AND created_at <  '2026-02-03 12:05:00'
    ORDER BY created_at ASC
");
$stmt2->execute(['%555381642320%', '%555381642320%']);
$evts = $stmt2->fetchAll(PDO::FETCH_ASSOC);
if (empty($evts)) {
    echo "   ❌ Nenhum evento desse contato entre 11:30-12:05 em 03/02\n";
} else {
    foreach ($evts as $e) {
        echo "   [{$e['created_at']}] id={$e['id']} tipo={$e['tipo']}\n";
    }
}

echo "\n=== FIM ===\n";
