<?php
/**
 * Diagnóstico: Áudio não recebido no Inbox às 11:38 de 53 81642320
 * 
 * Uso: php database/diagnostico-audio-81642320-1138.php
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== DIAGNÓSTICO: Áudio 11:38 de 53 81642320 não recebido no Inbox ===\n";
echo "Data da consulta: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 80) . "\n\n";

$patterns = ['%81642320%', '%5581642320%', '%5381642320%', '%81642320@%'];

// 1) Eventos inbound (qualquer tipo) desse número hoje, janela 11:00–12:00
echo "1) TODOS os eventos INBOUND de 81642320 hoje (11:00–12:30):\n";
echo str_repeat("-", 80) . "\n";
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.conversation_id,
        ce.status,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) AS msg_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) AS from_root,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS channel_id,
        COALESCE(
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')),
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')),
            CASE 
                WHEN ce.payload LIKE '%audioMessage%' THEN 'audioMessage'
                WHEN ce.payload LIKE '%imageMessage%' THEN 'imageMessage'
                ELSE 'text/outro'
            END
        ) AS msg_type
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.inbound.message'
      AND ce.source_system = 'wpp_gateway'
      AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
      AND (
        ce.payload LIKE ?
        OR ce.payload LIKE ?
        OR ce.payload LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
      )
    ORDER BY ce.created_at ASC
");
$params1 = ['%81642320%', '%5581642320%', '%5381642320%', '%81642320%', '%81642320%'];
$stmt->execute($params1);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ Nenhum evento inbound encontrado na janela 11:00–12:30\n\n";
} else {
    echo "   ✅ Encontrados " . count($events) . " evento(s):\n\n";
    foreach ($events as $e) {
        echo "   [{$e['created_at']}] id={$e['id']} type={$e['msg_type']} status={$e['status']}\n";
        echo "       from={$e['msg_from']}{$e['from_root']} channel={$e['channel_id']} conv_id={$e['conversation_id']}\n";
    }
    echo "\n";
}

// 2) Áudios inbound de 81642320 (últimas 48h, qualquer horário)
echo "2) ÁUDIOS inbound de 81642320 (últimas 48h):\n";
echo str_repeat("-", 80) . "\n";
$stmt2 = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.conversation_id,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) AS msg_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS channel_id,
        CASE 
            WHEN ce.payload LIKE '%audioMessage%' THEN 'audioMessage'
            WHEN JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')) IN ('audio','ptt') THEN 'audio/ptt'
            ELSE 'outro'
        END AS tipo
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.inbound.message'
      AND ce.source_system = 'wpp_gateway'
      AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
      AND (
        ce.payload LIKE '%audioMessage%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')) IN ('audio','ptt')
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) = 'audio'
      )
      AND (
        ce.payload LIKE ?
        OR ce.payload LIKE ?
        OR ce.payload LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
      )
    ORDER BY ce.created_at DESC
    LIMIT 15
");
$stmt2->execute(['%81642320%', '%5581642320%', '%5381642320%', '%81642320%']);
$audios = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($audios)) {
    echo "   ❌ Nenhum áudio inbound de 81642320 nas últimas 48h\n\n";
} else {
    echo "   ✅ Encontrados " . count($audios) . " áudio(s):\n\n";
    foreach ($audios as $a) {
        echo "   [{$a['created_at']}] id={$a['id']} tipo={$a['tipo']} channel={$a['channel_id']}\n";
    }
    echo "\n";
}

// 3) communication_media para event_id dos áudios encontrados
echo "3) MÍDIA (communication_media) para os áudios acima:\n";
echo str_repeat("-", 80) . "\n";
if (!empty($audios)) {
    foreach ($audios as $a) {
        $evStmt = $db->prepare("
            SELECT id, event_id, media_type, mime_type, stored_path, file_size, created_at
            FROM communication_media
            WHERE event_id = ?
        ");
        $evStmt->execute([$a['event_id']]);
        $medias = $evStmt->fetchAll(PDO::FETCH_ASSOC);
        $exists = !empty($medias);
        $fileExists = false;
        if ($exists && !empty($medias[0]['stored_path'])) {
            $path = __DIR__ . '/../storage/' . $medias[0]['stored_path'];
            $fileExists = file_exists($path);
        }
        echo "   event_id={$a['event_id']} | media=" . ($exists ? 'SIM' : 'NÃO') . " | file_exists=" . ($fileExists ? 'SIM' : 'NÃO') . "\n";
        if ($exists) {
            foreach ($medias as $m) {
                echo "      path={$m['stored_path']} mime={$m['mime_type']}\n";
            }
        }
    }
    echo "\n";
} else {
    echo "   (sem áudios para verificar)\n\n";
}

// 4) Conversas com 81642320
echo "4) CONVERSAS com contato 81642320:\n";
echo str_repeat("-", 80) . "\n";
$stmt4 = $db->prepare("
    SELECT id, conversation_key, contact_external_id, contact_name, tenant_id, channel_id, last_message_at
    FROM conversations
    WHERE contact_external_id LIKE '%81642320%'
       OR contact_external_id LIKE '%5581642320%'
    ORDER BY last_message_at DESC
    LIMIT 5
");
$stmt4->execute();
$convs = $stmt4->fetchAll(PDO::FETCH_ASSOC);
if (empty($convs)) {
    echo "   ❌ Nenhuma conversa encontrada\n\n";
} else {
    foreach ($convs as $c) {
        echo "   id={$c['id']} key={$c['conversation_key']} ext_id={$c['contact_external_id']} channel={$c['channel_id']} last={$c['last_message_at']}\n";
    }
    echo "\n";
}

// 5) Timezone do banco
$tz = $db->query("SELECT @@session.time_zone AS tz, NOW() AS now_db")->fetch(PDO::FETCH_ASSOC);
echo "5) MySQL time_zone: {$tz['tz']} | NOW(): {$tz['now_db']}\n";
echo "   (11:38 BRT = 14:38 UTC se DB em UTC)\n\n";

// 6) Últimos 5 eventos inbound de QUALQUER número ~11:38 (para ver se webhook está recebendo)
echo "6) Últimos 5 eventos INBOUND do sistema (qualquer número) para verificar fluxo:\n";
echo str_repeat("-", 80) . "\n";
$stmt6 = $db->query("
    SELECT id, created_at, 
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS msg_from,
           JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
           CASE WHEN payload LIKE '%audioMessage%' THEN 'audio'
                WHEN JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.type')) IN ('audio','ptt') THEN 'audio'
                ELSE COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.type')), 'text') END AS tipo
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
      AND source_system = 'wpp_gateway'
    ORDER BY created_at DESC
    LIMIT 5
");
$last = $stmt6->fetchAll(PDO::FETCH_ASSOC);
foreach ($last as $l) {
    echo "   [{$l['created_at']}] id={$l['id']} tipo={$l['tipo']} from={$l['msg_from']} channel={$l['channel_id']}\n";
}

echo "\n=== FIM ===\n";
