<?php
require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'], $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== Eventos outbound 11:45-11:52 hoje ===" . PHP_EOL;
$rows = $pdo->query("
    SELECT id, created_at, payload, metadata
    FROM communication_events
    WHERE event_type = 'whatsapp.outbound.message'
      AND created_at BETWEEN '2026-04-01 11:45:00' AND '2026-04-01 11:52:00'
    ORDER BY created_at
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    $p = json_decode($row['payload'], true);
    $m = json_decode($row['metadata'], true);
    echo "  [{$row['created_at']}] to=" . ($p['to'] ?? 'N/A')
        . " | channel=" . ($m['channel_id'] ?? 'N/A')
        . " | msg_id=" . ($m['message_id'] ?? 'N/A') . PHP_EOL;
    if (!empty($p['text'])) echo "    text=" . substr($p['text'], 0, 60) . PHP_EOL;
}

echo PHP_EOL . "=== Conversa Studio Di Capelli ===" . PHP_EOL;
$rows2 = $pdo->query("
    SELECT id, contact_name, contact_external_id, channel_id, updated_at
    FROM conversations
    WHERE contact_name LIKE '%Capelli%'
    ORDER BY updated_at DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows2 as $r) {
    echo "  conv={$r['id']} | ext_id={$r['contact_external_id']} | name={$r['contact_name']} | upd={$r['updated_at']}" . PHP_EOL;
}

echo PHP_EOL . "=== prospecting_results Studio Di Capelli ===" . PHP_EOL;
$rows3 = $pdo->query("
    SELECT id, name, phone, created_at
    FROM prospecting_results
    WHERE name LIKE '%Capelli%'
    ORDER BY created_at DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows3 as $r) {
    echo "  id={$r['id']} | name={$r['name']} | phone={$r['phone']} | created={$r['created_at']}" . PHP_EOL;
}

echo PHP_EOL . "=== Normaliza o phone do prospecting ===" . PHP_EOL;
foreach ($rows3 as $r) {
    $raw = $r['phone'];
    $digits = preg_replace('/[^0-9]/', '', $raw);
    if (strlen($digits) >= 12 && substr($digits, 0, 2) === '55') {
        $normalized = $digits;
    } elseif (strlen($digits) === 11) {
        $normalized = '55' . $digits;
    } elseif (strlen($digits) === 10) {
        $normalized = '55' . $digits;
    } else {
        $normalized = $digits;
    }
    echo "  phone_raw='{$raw}' | digits={$digits} (" . strlen($digits) . " dig) | normalized={$normalized}" . PHP_EOL;
}
