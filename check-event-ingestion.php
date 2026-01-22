<?php
require 'src/Core/DB.php';
require 'src/Core/Env.php';

\PixelHub\Core\Env::load();
$db = \PixelHub\Core\DB::getConnection();

// Busca por idempotency_key (calcula do payload de teste)
// O payload tinha: event="message", sessionId="Pixel12 Digital", message.id="gwtest-123"
// A idempotency_key seria: wpp_gateway:whatsapp.inbound.message:gwtest-123

$testMessageId = 'gwtest-123';
$idempotencyKey = 'wpp_gateway:whatsapp.inbound.message:' . $testMessageId;

echo "=== Verificando Deduplicação ===\n";
echo "Buscando idempotency_key: $idempotencyKey\n\n";

$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        idempotency_key,
        event_type,
        status,
        created_at
    FROM communication_events 
    WHERE idempotency_key = ?
");

$stmt->execute([$idempotencyKey]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "✅ Evento encontrado (foi deduplicado ou já existe):\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "❌ Evento NÃO encontrado - não foi salvo no banco\n\n";
}

// Verifica eventos com message.id similar
echo "\n=== Buscando eventos com message.id similar ===\n";
$stmt2 = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        status,
        created_at,
        JSON_EXTRACT(payload, '$.message.id') as message_id
    FROM communication_events 
    WHERE JSON_EXTRACT(payload, '$.message.id') LIKE ?
    ORDER BY created_at DESC
    LIMIT 10
");

$stmt2->execute(['%gwtest%']);
$results = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if ($results) {
    echo "Encontrados " . count($results) . " eventos:\n";
    foreach ($results as $r) {
        echo sprintf(
            "[%s] event_id: %s | message_id: %s | status: %s\n",
            $r['created_at'],
            substr($r['event_id'], 0, 8) . '...',
            $r['message_id'] ?: 'NULL',
            $r['status']
        );
    }
} else {
    echo "Nenhum evento com message.id contendo 'gwtest' encontrado\n";
}

// Verifica últimos eventos de mensagem
echo "\n=== Últimos 5 eventos whatsapp.inbound.message ===\n";
$stmt3 = $db->prepare("
    SELECT 
        id,
        event_id,
        correlation_id,
        event_type,
        status,
        created_at,
        JSON_EXTRACT(payload, '$.message.id') as message_id,
        JSON_EXTRACT(payload, '$.message.from') as message_from
    FROM communication_events 
    WHERE event_type = 'whatsapp.inbound.message'
    ORDER BY created_at DESC
    LIMIT 5
");

$stmt3->execute();
$results3 = $stmt3->fetchAll(PDO::FETCH_ASSOC);

foreach ($results3 as $r) {
    echo sprintf(
        "[%s] event_id: %s | from: %s | status: %s\n",
        $r['created_at'],
        substr($r['event_id'], 0, 8) . '...',
        $r['message_from'] ?: 'NULL',
        $r['status']
    );
}

