<?php
/**
 * Payload completo do evento teste1310 (conv 140) - campos de identidade
 */
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';
\PixelHub\Core\Env::load(__DIR__ . '/../.env');
$db = \PixelHub\Core\DB::getConnection();

$eventId = '0c4efde7-92a1-4316-ad95-353e24966e31'; // teste1310

$stmt = $db->prepare("SELECT event_id, event_type, conversation_id, tenant_id, created_at, payload, metadata FROM communication_events WHERE event_id = ?");
$stmt->execute([$eventId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo "Evento não encontrado\n";
    exit(1);
}

echo "=== EVENTO teste1310 (conv 140) - CAMPOS IDENTIDADE ===\n\n";
echo "event_id: {$row['event_id']}\n";
echo "event_type: {$row['event_type']}\n";
echo "conversation_id: {$row['conversation_id']}\n";
echo "tenant_id: {$row['tenant_id']}\n";
echo "created_at: {$row['created_at']}\n\n";

$payload = json_decode($row['payload'], true);
$metadata = json_decode($row['metadata'] ?? '{}', true);

echo "--- PAYLOAD (campos identidade) ---\n";
echo "from: " . ($payload['from'] ?? 'N/A') . "\n";
echo "to: " . ($payload['to'] ?? 'N/A') . "\n";
echo "remoteJid: " . ($payload['remoteJid'] ?? 'N/A') . "\n";
echo "participant: " . ($payload['participant'] ?? 'N/A') . "\n";
echo "author: " . ($payload['author'] ?? 'N/A') . "\n";

if (isset($payload['message'])) {
    $m = $payload['message'];
    echo "\nmessage.from: " . ($m['from'] ?? 'N/A') . "\n";
    echo "message.to: " . ($m['to'] ?? 'N/A') . "\n";
    echo "message.text: " . substr($m['text'] ?? '', 0, 80) . "\n";
    if (isset($m['key'])) {
        echo "message.key.remoteJid: " . ($m['key']['remoteJid'] ?? 'N/A') . "\n";
        echo "message.key.participant: " . ($m['key']['participant'] ?? 'N/A') . "\n";
    }
}

if (isset($payload['raw']['payload'])) {
    $rp = $payload['raw']['payload'];
    echo "\n--- raw.payload ---\n";
    echo "from: " . ($rp['from'] ?? 'N/A') . "\n";
    echo "to: " . ($rp['to'] ?? 'N/A') . "\n";
    echo "participant: " . ($rp['participant'] ?? 'N/A') . "\n";
    echo "author: " . ($rp['author'] ?? 'N/A') . "\n";
    echo "pushName: " . ($rp['pushName'] ?? 'N/A') . "\n";
    echo "notifyName: " . ($rp['notifyName'] ?? 'N/A') . "\n";
    echo "sender.id: " . ($rp['sender']['id'] ?? 'N/A') . "\n";
    echo "fromMe: " . ($rp['fromMe'] ?? 'N/A') . "\n";
}

echo "\n--- metadata ---\n";
print_r($metadata);

echo "\n--- CONVERSA 140 ---\n";
$c = $db->prepare("SELECT id, contact_external_id, contact_name, channel_id, tenant_id FROM conversations WHERE id = 140");
$c->execute();
$conv = $c->fetch(PDO::FETCH_ASSOC);
print_r($conv);
