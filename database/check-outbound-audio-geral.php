<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
PixelHub\Core\Env::load();
$db = PixelHub\Core\DB::getConnection();

echo "=== CHECK OUTBOUND AUDIO GERAL ===\n\n";

$r = $db->query("SELECT COUNT(*) as c FROM communication_events WHERE event_type = 'whatsapp.outbound.message'");
echo "Total outbound messages: " . $r->fetch()['c'] . "\n\n";

// Qualquer outbound com type=audio (payload.type ou payload.message.type)
$stmt = $db->query("
    SELECT event_id, payload, created_at
    FROM communication_events
    WHERE event_type = 'whatsapp.outbound.message'
    AND (
        JSON_EXTRACT(payload, '$.type') = '\"audio\"'
        OR JSON_EXTRACT(payload, '$.message.type') = '\"audio\"'
    )
    ORDER BY created_at DESC
    LIMIT 10
");
$audios = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Áudios outbound (type=audio): " . count($audios) . "\n\n";

foreach ($audios as $e) {
    $p = json_decode($e['payload'], true);
    $to = $p['message']['to'] ?? $p['to'] ?? 'N/A';
    echo "  {$e['created_at']} | to={$to} | event_id={$e['event_id']}\n";
}

// Últimos 3 outbound quaisquer (para ver estrutura)
echo "\n--- Estrutura payload (últimos 3 outbound) ---\n";
$stmt2 = $db->query("
    SELECT event_id, payload, created_at
    FROM communication_events
    WHERE event_type = 'whatsapp.outbound.message'
    ORDER BY created_at DESC
    LIMIT 3
");
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $e) {
    $p = json_decode($e['payload'], true);
    echo "\n{$e['created_at']}:\n";
    echo "  payload keys: " . implode(', ', array_keys($p ?? [])) . "\n";
    echo "  payload.type (top): " . ($p['type'] ?? 'N/A') . "\n";
    if (!empty($p['message'])) {
        echo "  message keys: " . implode(', ', array_keys($p['message'])) . "\n";
        echo "  message.to: " . ($p['message']['to'] ?? 'N/A') . "\n";
        echo "  message.type: " . ($p['message']['type'] ?? 'N/A') . "\n";
    }
}

// Eventos outbound para 81642320 (qualquer tipo)
echo "\n--- Outbound para 81642320 (qualquer tipo) ---\n";
$stmt3 = $db->query("
    SELECT event_id, payload, created_at
    FROM communication_events
    WHERE event_type = 'whatsapp.outbound.message'
    AND (
        payload LIKE '%81642320%'
        OR payload LIKE '%555381642320%'
    )
    ORDER BY created_at DESC
    LIMIT 5
");
$to8164 = $stmt3->fetchAll(PDO::FETCH_ASSOC);
echo "Encontrados: " . count($to8164) . "\n";
foreach ($to8164 as $e) {
    $p = json_decode($e['payload'], true);
    $to = $p['message']['to'] ?? $p['to'] ?? 'N/A';
    $type = $p['type'] ?? $p['message']['type'] ?? 'N/A';
    echo "  {$e['created_at']} | to={$to} | type={$type}\n";
}
