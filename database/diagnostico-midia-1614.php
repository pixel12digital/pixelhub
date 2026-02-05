<?php
/**
 * Diagnóstico: [Mídia] às 16:14 - áudios inbound da conversa 555381106484 (Alessandra)
 * Verifica eventos de áudio, communication_media e estrutura do payload
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();

echo "=== Diagnóstico: [Mídia] às 16:14 - Áudios 555381106484 ===\n\n";

// 1. TODOS os eventos (inbound+outbound) entre 16:00 e 16:30, conversa 9 / 5381106484
echo "1. Eventos 16:00-16:30 (conversa Alessandra 555381106484):\n";
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.conversation_id,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) as payload_type,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')) as msg_type,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.type')) as raw_type,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.content')) as raw_content_preview,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.data.type')) as data_type
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= '2026-02-05 16:00:00'
    AND ce.created_at <= '2026-02-05 16:30:00'
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE '%5381106484%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE '%5381106484%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE '%5381106484%'
        OR ce.conversation_id = 9
    )
    ORDER BY ce.created_at ASC
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   Nenhum evento encontrado nessa janela.\n\n";
}

foreach ($events as $i => $e) {
    $dir = ($e['event_type'] ?? '') === 'whatsapp.outbound.message' ? 'OUT' : 'IN';
    $rawPreview = $e['raw_content_preview'] ?? '';
    if (strlen($rawPreview) > 80) $rawPreview = substr($rawPreview, 0, 80) . '...';
    echo "   --- Evento " . ($i + 1) . " ($dir) ---\n";
    echo "   event_id: " . $e['event_id'] . "\n";
    echo "   created_at: " . $e['created_at'] . "\n";
    echo "   payload.type: " . ($e['payload_type'] ?? 'NULL') . "\n";
    echo "   message.type: " . ($e['msg_type'] ?? 'NULL') . "\n";
    echo "   raw.payload.type: " . ($e['raw_type'] ?? 'NULL') . "\n";
    echo "   data.type: " . ($e['data_type'] ?? 'NULL') . "\n";
    if ($rawPreview) echo "   raw.payload.content (preview): " . $rawPreview . "\n";
    echo "\n";
}

// 2. Para cada evento, verifica communication_media
echo "\n2. Registros em communication_media para esses eventos:\n";
if (!empty($events)) {
    echo "   (hasMediaIndicator = type em ['audio','ptt','image','video','document','sticker'])\n\n";
    foreach ($events as $e) {
        $stmtM = $db->prepare("SELECT event_id, media_type, stored_path, file_name, created_at FROM communication_media WHERE event_id = ?");
        $stmtM->execute([$e['event_id']]);
        $media = $stmtM->fetch(PDO::FETCH_ASSOC);
        $type = $e['payload_type'] ?? $e['msg_type'] ?? $e['raw_type'] ?? $e['data_type'] ?? 'NULL';
        $hasIndicator = $type && in_array(strtolower($type), ['audio', 'ptt', 'image', 'video', 'document', 'sticker']);
        echo "   event_id: " . substr($e['event_id'], 0, 8) . "...\n";
        echo "   payload type detectado: " . $type . " | hasMediaIndicator: " . ($hasIndicator ? 'SIM' : 'NAO') . "\n";
        if ($media) {
            $path = $media['stored_path'] ?? '';
            $fullPath = $path ? __DIR__ . '/../storage/' . $path : '';
            $exists = $fullPath && file_exists($fullPath);
            echo "   communication_media: SIM | path: " . $path . " | file_exists: " . ($exists ? 'SIM' : 'NAO') . "\n";
        } else {
            echo "   communication_media: NAO (mídia não baixada ou não processada)\n";
        }
        echo "\n";
    }
}

// 3. Estrutura completa do payload (primeiro evento de áudio para debug)
echo "\n3. Estrutura do payload (primeiro evento sem mídia, para debug):\n";
$firstWithoutMedia = null;
foreach ($events as $e) {
    $stmtM = $db->prepare("SELECT 1 FROM communication_media WHERE event_id = ?");
    $stmtM->execute([$e['event_id']]);
    if (!$stmtM->fetch()) {
        $firstWithoutMedia = $e['event_id'];
        break;
    }
}
if ($firstWithoutMedia) {
    $stmtP = $db->prepare("SELECT payload FROM communication_events WHERE event_id = ?");
    $stmtP->execute([$firstWithoutMedia]);
    $row = $stmtP->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $payload = json_decode($row['payload'], true);
        echo "   event_id: " . $firstWithoutMedia . "\n";
        echo "   Chaves do payload: " . implode(', ', array_keys($payload)) . "\n";
        if (isset($payload['raw']['payload'])) {
            echo "   raw.payload keys: " . implode(', ', array_keys($payload['raw']['payload'])) . "\n";
            echo "   raw.payload.type: " . ($payload['raw']['payload']['type'] ?? 'NULL') . "\n";
        }
        if (isset($payload['data'])) {
            echo "   data keys: " . implode(', ', array_keys($payload['data'])) . "\n";
        }
    }
} else {
    echo "   Todos os eventos têm mídia registrada.\n";
}

// 4. Fila media_process_queue (pendentes)
echo "\n4. Jobs pendentes em media_process_queue (últimas 24h):\n";
try {
    $stmtQ = $db->prepare("
        SELECT mpq.event_id, mpq.status, mpq.attempts, mpq.created_at, ce.event_type
        FROM media_process_queue mpq
        JOIN communication_events ce ON ce.event_id = mpq.event_id
        WHERE mpq.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY mpq.created_at DESC
        LIMIT 10
    ");
    $stmtQ->execute();
    $jobs = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
    if (empty($jobs)) {
        echo "   Nenhum job na fila (ou tabela não existe).\n";
    } else {
        foreach ($jobs as $j) {
            echo "   event_id: " . substr($j['event_id'], 0, 8) . "... | status: " . $j['status'] . " | attempts: " . $j['attempts'] . " | created: " . $j['created_at'] . "\n";
        }
    }
} catch (\Exception $ex) {
    echo "   Erro ao consultar fila: " . $ex->getMessage() . "\n";
}

// 5. source_system dos eventos 16:14 (verificar duplicata send+webhook)
echo "\n5. source_system dos eventos 16:14 (duplicata?):\n";
$stmt5 = $db->prepare("
    SELECT event_id, source_system, created_at, idempotency_key
    FROM communication_events
    WHERE created_at >= '2026-02-05 16:14:00' AND created_at <= '2026-02-05 16:16:00'
    AND event_type = 'whatsapp.outbound.message'
    AND (conversation_id = 9 OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) LIKE '%5381106484%')
    ORDER BY created_at
");
$stmt5->execute();
$evs = $stmt5->fetchAll(PDO::FETCH_ASSOC);
foreach ($evs as $e) {
    echo "   " . substr($e['event_id'], 0, 8) . "... | source: " . $e['source_system'] . " | " . $e['created_at'] . " | key: " . substr($e['idempotency_key'] ?? 'NULL', 0, 50) . "\n";
}

echo "\n=== Fim do diagnóstico ===\n";
