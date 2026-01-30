<?php
/**
 * Diagnóstico de timestamps das mensagens
 * Acesso: /diag-thread-timestamps.php?key=diag2026&conversation_id=121
 */

if (($_GET['key'] ?? '') !== 'diag2026') {
    http_response_code(403);
    die('Acesso negado');
}

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$conversationId = (int)($_GET['conversation_id'] ?? 121);

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $_ENV['DB_HOST'],
        $_ENV['DB_PORT'] ?? 3306,
        $_ENV['DB_DATABASE']
    );
    $pdo = new PDO($dsn, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Erro DB: " . $e->getMessage());
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DE TIMESTAMPS - THREAD ===\n\n";

// 1. Info do sistema
echo "1. SISTEMA:\n";
echo "   PHP timezone: " . date_default_timezone_get() . "\n";
echo "   PHP date('Y-m-d H:i:s'): " . date('Y-m-d H:i:s') . "\n";

$stmt = $pdo->query("SELECT NOW() as mysql_now, @@session.time_zone as tz");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   MySQL NOW(): " . $row['mysql_now'] . "\n";
echo "   MySQL timezone: " . $row['tz'] . "\n\n";

// 2. Busca contact_external_id da conversa
$stmt = $pdo->prepare("SELECT contact_external_id, last_message_at FROM conversations WHERE id = ?");
$stmt->execute([$conversationId]);
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conv) {
    die("Conversa {$conversationId} não encontrada");
}

echo "2. CONVERSA #{$conversationId}:\n";
echo "   contact_external_id: " . $conv['contact_external_id'] . "\n";
echo "   last_message_at (raw): " . $conv['last_message_at'] . "\n\n";

// 3. Busca últimos 5 eventos desta conversa
$contactPattern = '%' . preg_replace('/@.*$/', '', $conv['contact_external_id']) . '%';

$stmt = $pdo->prepare("
    SELECT 
        event_id,
        event_type,
        created_at,
        SUBSTRING(payload, 1, 200) as payload_preview
    FROM communication_events 
    WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
      AND (
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) LIKE ?
          OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) LIKE ?
          OR conversation_id = ?
      )
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$contactPattern, $contactPattern, $conversationId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "3. ÚLTIMOS 10 EVENTOS (communication_events.created_at):\n";
echo str_repeat("-", 80) . "\n";

foreach ($events as $i => $event) {
    $createdAt = $event['created_at'];
    
    // Simula o regex do thread.php
    $msgDateStr = 'Agora';
    if (preg_match('/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})/', $createdAt, $m)) {
        $msgDateStr = "{$m[3]}/{$m[2]} {$m[4]}:{$m[5]}";
    }
    
    echo sprintf(
        "   [%d] ID: %s\n       Type: %s\n       created_at RAW: %s\n       EXIBIÇÃO (regex): %s\n\n",
        $i + 1,
        $event['event_id'],
        $event['event_type'],
        $createdAt,
        $msgDateStr
    );
}

echo str_repeat("=", 80) . "\n";
echo "CONCLUSÃO:\n";
echo "- Se created_at mostra 15:22 e EXIBIÇÃO mostra 15:22 → código OK, problema é cache\n";
echo "- Se created_at mostra 12:22 e EXIBIÇÃO mostra 12:22 → dados estão em UTC no banco\n";
echo "- Se created_at mostra 15:22 mas tela mostra 12:22 → problema na renderização\n";
