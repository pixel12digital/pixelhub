<?php
/**
 * Diagnóstico: Mensagens da conversa 139 (Manoel / ImobSites)
 * Execução: php database/diagnostico-manoel-139.php
 */
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';
\PixelHub\Core\Env::load(__DIR__ . '/../.env');
$db = \PixelHub\Core\DB::getConnection();

$conversationId = 139;

echo "=== CONVERSA 139 (Manoel) ===\n\n";

$conv = $db->prepare("SELECT id, contact_external_id, channel_id, tenant_id, channel_type FROM conversations WHERE id = ?");
$conv->execute([$conversationId]);
$c = $conv->fetch(PDO::FETCH_ASSOC);
print_r($c);

echo "\n=== EVENTOS (communication_events) - ÚLTIMOS 20 ===\n\n";

$stmt = $db->prepare("
    SELECT ce.event_id, ce.event_type, ce.created_at, ce.conversation_id, ce.tenant_id,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_payload,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_payload,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) as type_payload,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) as text_payload,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as msg_text,
           LEFT(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')), 80) as text_preview
    FROM communication_events ce
    WHERE ce.conversation_id = ?
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$stmt->execute([$conversationId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($events as $e) {
    echo "--- event_id={$e['event_id']} | {$e['created_at']} | type={$e['type_payload']} | from={$e['from_payload']} | to={$e['to_payload']}\n";
    echo "   text: " . ($e['text_payload'] ?: $e['msg_text'] ?: '(vazio)') . "\n";
    echo "   text_preview: " . ($e['text_preview'] ?: '-') . "\n\n";
}

echo "\n=== EVENTOS SEM conversation_id (possível mensagem de hoje perdida) ===\n\n";

$stmt2 = $db->prepare("
    SELECT ce.event_id, ce.event_type, ce.created_at, ce.conversation_id,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_payload,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_payload,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) as type_payload,
           LEFT(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')), 100) as text_preview,
           JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as meta_channel
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= '2026-02-05 00:00:00'
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE '%8779%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE '%8779%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE '%8779%'
    )
    ORDER BY ce.created_at DESC
    LIMIT 15
");
$stmt2->execute();
$events2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

foreach ($events2 as $e) {
    echo "--- event_id={$e['event_id']} | conv_id={$e['conversation_id']} | {$e['created_at']} | type={$e['type_payload']}\n";
    echo "   from={$e['from_payload']} to={$e['to_payload']} channel={$e['meta_channel']}\n";
    echo "   text: " . ($e['text_preview'] ?: '-') . "\n\n";
}

echo "\n=== EVENTOS POR PADRÃO @lid ou 8779 (sem filtro conversation_id) ===\n\n";

$stmt3 = $db->prepare("
    SELECT ce.event_id, ce.event_type, ce.created_at, ce.conversation_id,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_p,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_p,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as msg_from,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) as msg_to,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) as type_p,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) as text_p,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as msg_text,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')) as body_p
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND (
        ce.payload LIKE '%193952250601573@lid%'
        OR ce.payload LIKE '%557187799910%'
        OR ce.payload LIKE '%8779%'
    )
    ORDER BY ce.created_at DESC
    LIMIT 15
");
$stmt3->execute();
$events3 = $stmt3->fetchAll(PDO::FETCH_ASSOC);

foreach ($events3 as $e) {
    $from = $e['from_p'] ?: $e['msg_from'];
    $to = $e['to_p'] ?: $e['msg_to'];
    $text = $e['text_p'] ?: $e['msg_text'] ?: $e['body_p'];
    echo "--- event_id={$e['event_id']} | conv_id={$e['conversation_id']} | {$e['created_at']} | type={$e['type_p']}\n";
    echo "   from={$from} to={$to}\n";
    echo "   text: " . substr($text ?: '(vazio)', 0, 120) . "\n\n";
}

echo "\n=== PAYLOAD COMPLETO DO PRIMEIRO EVENTO (se houver) ===\n\n";
if (!empty($events3)) {
    $firstId = $events3[0]['event_id'];
    $p = $db->prepare("SELECT payload FROM communication_events WHERE event_id = ?");
    $p->execute([$firstId]);
    $row = $p->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $payload = json_decode($row['payload'], true);
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
