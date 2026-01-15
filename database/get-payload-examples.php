<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== EXEMPLOS DE PAYLOAD ===\n\n";

// 1) Payload de connection.update
echo "1) PAYLOAD: connection.update\n";
echo str_repeat("=", 100) . "\n";

$sql1 = "SELECT id, payload, event_type
FROM communication_events
WHERE source_system='wpp_gateway'
  AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) = 'connection.update'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id'))='Pixel12 Digital'
ORDER BY id DESC
LIMIT 1";

$stmt1 = $pdo->query($sql1);
$connEvent = $stmt1->fetch(PDO::FETCH_ASSOC);

if ($connEvent) {
    $payload1 = json_decode($connEvent['payload'], true);
    echo "Event ID: " . $connEvent['id'] . "\n";
    echo "Event Type: " . $connEvent['event_type'] . "\n\n";
    
    // Mostrar estrutura simplificada
    echo "Estrutura:\n";
    echo "  event: " . ($payload1['event'] ?? 'NULL') . "\n";
    echo "  session.id: " . ($payload1['session']['id'] ?? 'NULL') . "\n";
    echo "  connection.status: " . ($payload1['connection']['status'] ?? 'NULL') . "\n";
    echo "  Keys principais: " . implode(', ', array_keys($payload1)) . "\n";
    
    // Salvar exemplo completo em arquivo
    file_put_contents('database/payload-example-connection-update.json', json_encode($payload1, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "\n✅ Payload salvo em: database/payload-example-connection-update.json\n";
}

// 2) Payload de mensagem de grupo
echo "\n\n2) PAYLOAD: mensagem de grupo (@g.us)\n";
echo str_repeat("=", 100) . "\n";

$sql2 = "SELECT id, payload, event_type
FROM communication_events
WHERE source_system='wpp_gateway'
  AND event_type = 'whatsapp.inbound.message'
  AND payload LIKE '%@g.us%'
ORDER BY id DESC
LIMIT 1";

$stmt2 = $pdo->query($sql2);
$groupEvent = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($groupEvent) {
    $payload2 = json_decode($groupEvent['payload'], true);
    echo "Event ID: " . $groupEvent['id'] . "\n";
    echo "Event Type: " . $groupEvent['event_type'] . "\n\n";
    
    echo "Estrutura:\n";
    echo "  event: " . ($payload2['event'] ?? 'NULL') . "\n";
    echo "  message.from: " . ($payload2['message']['from'] ?? 'NULL') . "\n";
    echo "  message.key.remoteJid: " . ($payload2['message']['key']['remoteJid'] ?? 'NULL') . "\n";
    echo "  message.key.participant: " . ($payload2['message']['key']['participant'] ?? 'NULL') . "\n";
    echo "  Keys principais: " . implode(', ', array_keys($payload2)) . "\n";
    
    // Salvar exemplo completo
    file_put_contents('database/payload-example-group-message.json', json_encode($payload2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "\n✅ Payload salvo em: database/payload-example-group-message.json\n";
}

// 3) Payload de mensagem normal (funcionando)
echo "\n\n3) PAYLOAD: mensagem normal (@c.us) - FUNCIONANDO\n";
echo str_repeat("=", 100) . "\n";

$sql3 = "SELECT id, payload, event_type
FROM communication_events
WHERE source_system='wpp_gateway'
  AND event_type = 'whatsapp.inbound.message'
  AND status = 'processed'
  AND payload LIKE '%@c.us%'
ORDER BY id DESC
LIMIT 1";

$stmt3 = $pdo->query($sql3);
$normalEvent = $stmt3->fetch(PDO::FETCH_ASSOC);

if ($normalEvent) {
    $payload3 = json_decode($normalEvent['payload'], true);
    echo "Event ID: " . $normalEvent['id'] . "\n";
    echo "Event Type: " . $normalEvent['event_type'] . "\n\n";
    
    echo "Estrutura:\n";
    echo "  event: " . ($payload3['event'] ?? 'NULL') . "\n";
    echo "  message.from: " . ($payload3['message']['from'] ?? 'NULL') . "\n";
    echo "  message.to: " . ($payload3['message']['to'] ?? 'NULL') . "\n";
    echo "  Keys principais: " . implode(', ', array_keys($payload3)) . "\n";
    
    // Salvar exemplo completo
    file_put_contents('database/payload-example-normal-message.json', json_encode($payload3, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "\n✅ Payload salvo em: database/payload-example-normal-message.json\n";
}

echo "\n";

