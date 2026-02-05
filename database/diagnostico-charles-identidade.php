<?php
/**
 * Diagnóstico: Resolução de identidade - Charles Dietrich (47 vs 11)
 * Busca evento de teste e campos de identidade no payload
 */
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';
\PixelHub\Core\Env::load(__DIR__ . '/../.env');
$db = \PixelHub\Core\DB::getConnection();

echo "=== 1) BUSCA EVENTO DE TESTE (texto ou número 47) ===\n\n";

$terms = ['teste1310', 'Teste-17-40', 'envio para imobsites', 'imobsites', '47996164699', '5547996164699', '99616-4699'];
foreach ($terms as $term) {
    $stmt = $db->prepare("
        SELECT ce.event_id, ce.event_type, ce.created_at, ce.conversation_id, ce.tenant_id,
               ce.payload
        FROM communication_events ce
        WHERE ce.payload LIKE ?
        ORDER BY ce.created_at DESC
        LIMIT 3
    ");
    $stmt->execute(['%' . $term . '%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        echo "--- Termo: {$term} ---\n";
        foreach ($rows as $r) {
            echo "  event_id={$r['event_id']} | conv={$r['conversation_id']} | {$r['created_at']}\n";
        }
        echo "\n";
    }
}

echo "\n=== 2) EVENTOS COM 47996164699 ou 5547996164699 (Charles 47) ===\n\n";

$stmt = $db->prepare("
    SELECT ce.event_id, ce.event_type, ce.created_at, ce.conversation_id, ce.tenant_id,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_p,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_p,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as msg_from,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) as msg_to,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.remoteJid')) as remoteJid,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.key.remoteJid')) as key_remoteJid,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.participant')) as participant,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.author')) as author,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.participant')) as raw_participant,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.author')) as raw_author,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.pushName')) as pushName,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.from')) as raw_from,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.to')) as raw_to,
           LEFT(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')), 80) as text_p,
           LEFT(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')), 80) as msg_text
    FROM communication_events ce
    WHERE ce.payload LIKE '%47996164699%' OR ce.payload LIKE '%5547996164699%'
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt->execute();
$events47 = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($events47 as $e) {
    echo "--- event_id={$e['event_id']} | conv={$e['conversation_id']} | {$e['created_at']} | type={$e['event_type']}\n";
    echo "   from=$e[from_p] | to=$e[to_p]\n";
    echo "   msg.from=$e[msg_from] | msg.to=$e[msg_to]\n";
    echo "   remoteJid=$e[remoteJid] | key.remoteJid=$e[key_remoteJid]\n";
    echo "   participant=$e[participant] | author=$e[author]\n";
    echo "   raw.participant=$e[raw_participant] | raw.author=$e[raw_author]\n";
    echo "   pushName=$e[pushName] | raw.from=$e[raw_from] | raw.to=$e[raw_to]\n";
    echo "   text: " . ($e['text_p'] ?: $e['msg_text'] ?: '-') . "\n\n";
}

echo "\n=== 3) EVENTOS COM 5511940863773 (número 11 - possível conflito) ===\n\n";

$stmt2 = $db->prepare("
    SELECT ce.event_id, ce.conversation_id, ce.created_at,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_p,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_p,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as msg_from,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.pushName')) as pushName
    FROM communication_events ce
    WHERE ce.payload LIKE '%5511940863773%' OR ce.payload LIKE '%11940863773%'
    ORDER BY ce.created_at DESC
    LIMIT 5
");
$stmt2->execute();
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $e) {
    echo "  event={$e['event_id']} conv={$e['conversation_id']} | from={$e['from_p']} to={$e['to_p']} | pushName={$e['pushName']}\n";
}

echo "\n=== 4) CONVERSAS COM contact_external_id contendo 47 ou 11 ===\n\n";

$stmt3 = $db->query("
    SELECT c.id, c.contact_external_id, c.contact_name, c.channel_id, c.tenant_id,
           (SELECT COUNT(*) FROM communication_events ce WHERE ce.conversation_id = c.id) as events_count
    FROM conversations c
    WHERE c.contact_external_id LIKE '%47996164699%'
       OR c.contact_external_id LIKE '%5511940863773%'
       OR c.contact_name LIKE '%Charles%'
    ORDER BY c.updated_at DESC
");
foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo "  conv_id={$c['id']} | contact_external_id={$c['contact_external_id']} | contact_name={$c['contact_name']} | channel={$c['channel_id']} | events={$c['events_count']}\n";
}

echo "\n=== 5) PAYLOAD COMPLETO DO PRIMEIRO EVENTO 47 (se houver) ===\n\n";
if (!empty($events47)) {
    $firstId = $events47[0]['event_id'];
    $p = $db->prepare("SELECT payload, metadata FROM communication_events WHERE event_id = ?");
    $p->execute([$firstId]);
    $row = $p->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $payload = json_decode($row['payload'], true);
        echo "metadata: " . json_encode(json_decode($row['metadata'], true), JSON_PRETTY_PRINT) . "\n\n";
        echo "payload (campos identidade):\n";
        $keys = ['from','to','remoteJid','participant','author','message','raw'];
        foreach ($keys as $k) {
            if (isset($payload[$k])) {
                echo "  $k: " . json_encode($payload[$k], JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
        if (isset($payload['raw']['payload'])) {
            $rp = $payload['raw']['payload'];
            echo "  raw.payload (identidade): from=" . ($rp['from']??'-') . " to=" . ($rp['to']??'-') . " participant=" . ($rp['participant']??'-') . " author=" . ($rp['author']??'-') . " pushName=" . ($rp['pushName']??'-') . "\n";
        }
    }
}
