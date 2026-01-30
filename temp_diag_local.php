<?php
// Credenciais diretas do .env
$dsn = 'mysql:host=r225us.hmservers.net;port=3306;dbname=pixel12digital_pixelhub;charset=utf8mb4';
$pdo = new PDO($dsn, 'pixel12digital_pixelhub', 'Los@ngo#081081');

echo "=== TIMESTAMPS DO THREAD (conversation_id=121) ===\n\n";

// MySQL info
$row = $pdo->query("SELECT NOW() as n, @@session.time_zone as tz")->fetch();
echo "MySQL NOW(): " . $row['n'] . "\n";
echo "MySQL TZ: " . $row['tz'] . "\n\n";

// Últimos 5 eventos
$stmt = $pdo->prepare("
    SELECT event_id, event_type, created_at
    FROM communication_events 
    WHERE conversation_id = 121
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "EVENTOS (created_at RAW do banco):\n";
foreach ($events as $e) {
    $raw = $e['created_at'];
    // Simula regex do thread.php
    preg_match('/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})/', $raw, $m);
    $display = $m ? "{$m[3]}/{$m[2]} {$m[4]}:{$m[5]}" : 'Agora';
    echo "  ID: " . substr($e['event_id'], 0, 8) . "... | Type: " . $e['event_type'] . " | RAW: " . $raw . " | EXIBIR: " . $display . "\n";
}
