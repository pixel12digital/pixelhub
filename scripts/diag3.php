<?php
require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'], $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== Colunas de prospecting_results ===" . PHP_EOL;
foreach ($pdo->query("DESCRIBE prospecting_results")->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "  {$col['Field']} ({$col['Type']})" . PHP_EOL;
}

echo PHP_EOL . "=== Studio Di Capelli em prospecting_results ===" . PHP_EOL;
foreach ($pdo->query("SELECT * FROM prospecting_results WHERE name LIKE '%Capelli%' LIMIT 5")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    foreach ($r as $k => $v) {
        if (!empty($v) && !in_array($k, ['created_at','updated_at','id'])) {
            echo "  {$k} = {$v}" . PHP_EOL;
        }
    }
    echo "---" . PHP_EOL;
}

echo PHP_EOL . "=== Eventos conversa 758 (Studio Di Capelli) ===" . PHP_EOL;
foreach ($pdo->query("
    SELECT ce.created_at, ce.event_type, ce.source_system,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_phone,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_phone,
           SUBSTRING(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')),1,60) as text
    FROM communication_events ce
    JOIN conversations c ON c.contact_external_id = JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to'))
                        OR c.contact_external_id = JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from'))
    WHERE c.id = 758
    ORDER BY ce.created_at DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  [{$r['created_at']}] {$r['event_type']} | from={$r['from_phone']} | to={$r['to_phone']}" . PHP_EOL;
    if ($r['text']) echo "    >> {$r['text']}" . PHP_EOL;
}

echo PHP_EOL . "=== Inbox display: ContactHelper parse de 5547999945553 ===" . PHP_EOL;
$digits = '5547999945553';
// Remove country code 55
if (strlen($digits) > 11 && substr($digits, 0, 2) === '55') {
    $digits = substr($digits, 2);
}
echo "  After strip 55: {$digits} (" . strlen($digits) . " digits)" . PHP_EOL;
if (strlen($digits) === 11) {
    $formatted = sprintf('(%s) %s-%s', substr($digits,0,2), substr($digits,2,5), substr($digits,7));
    echo "  Formatted 11-digit: {$formatted}" . PHP_EOL;
} elseif (strlen($digits) === 10) {
    $formatted = sprintf('(%s) %s-%s', substr($digits,0,2), substr($digits,2,4), substr($digits,6));
    echo "  Formatted 10-digit: {$formatted}" . PHP_EOL;
}
