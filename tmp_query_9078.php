<?php
require __DIR__ . '/src/Core/Env.php';
require __DIR__ . '/src/Core/DB.php';
require __DIR__ . '/src/Services/PhoneNormalizer.php';

PixelHub\Core\Env::load();
$db = PixelHub\Core\DB::getConnection();

$num = '9078';
$like = '%' . $num;

function printRows($label, $rows) {
    echo "-- {$label} --\n";
    foreach ($rows as $r) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

// conversations
$stmt = $db->prepare("SELECT id, contact_external_id, contact_name, tenant_id, lead_id, channel_id, conversation_key, created_at, updated_at FROM conversations WHERE contact_external_id LIKE ? OR contact_name='Pixel12Digital' ORDER BY updated_at DESC LIMIT 50");
$stmt->execute([$like]);
printRows('conversations', $stmt->fetchAll());

// leads
$stmt = $db->prepare("SELECT id, name, phone, status FROM leads WHERE phone LIKE ? OR name='Pixel12Digital' ORDER BY id DESC LIMIT 50");
$stmt->execute([$like]);
printRows('leads', $stmt->fetchAll());

// contacts (table may not exist)
try {
    $stmt = $db->prepare("SELECT id, name, phone FROM contacts WHERE phone LIKE ? OR name='Pixel12Digital' ORDER BY id DESC LIMIT 50");
    $stmt->execute([$like]);
    printRows('contacts', $stmt->fetchAll());
} catch (\Exception $e) {
    echo "-- contacts table not available: " . $e->getMessage() . "\n";
}

// communication_events payload check (summary)
$likeJid = '%' . $num . '%';
$stmt = $db->prepare("
    SELECT 
        id,
        event_type,
        created_at,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) AS from_field,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS message_from,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.notifyName')) AS notify_name,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.notifyName')) AS msg_notify_name,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.notifyName')) AS raw_notify_name,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.sender.name')) AS raw_sender_name,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.sender.verifiedName')) AS raw_sender_verified,
        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id
    FROM communication_events 
    WHERE event_type LIKE 'whatsapp.%' 
      AND (
        JSON_EXTRACT(payload, '$.from') LIKE ? 
        OR JSON_EXTRACT(payload, '$.message.from') LIKE ? 
        OR JSON_EXTRACT(payload, '$.to') LIKE ? 
        OR JSON_EXTRACT(payload, '$.message.to') LIKE ?
      )
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$likeJid, $likeJid, $likeJid, $likeJid]);
printRows('communication_events', $stmt->fetchAll());
