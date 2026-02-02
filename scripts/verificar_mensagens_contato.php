<?php
/**
 * Verifica mensagens salvas de um contato (conversa 127 - 5599580895)
 */
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}
\PixelHub\Core\Env::load(__DIR__ . '/../');
$db = \PixelHub\Core\DB::getConnection();

$stmt = $db->prepare("
    SELECT event_id, event_type, created_at, payload
    FROM communication_events
    WHERE (payload LIKE '%222132118278317@lid%' OR payload LIKE '%5599580895%')
    AND event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY created_at ASC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\n=== MENSAGENS SALVAS - Contato (55) 9958-0895 / 222132118278317@lid ===\n\n";
echo "Total de eventos: " . count($rows) . "\n\n";

foreach ($rows as $r) {
    $payload = json_decode($r['payload'], true);
    $direction = strpos($r['event_type'], 'inbound') !== false ? 'INBOUND' : 'OUTBOUND';
    $msg = $payload['message'] ?? $payload['data'] ?? $payload;
    $text = $msg['text'] ?? $msg['body'] ?? $payload['text'] ?? $payload['body'] ?? $msg['caption'] ?? null;
    $type = $msg['type'] ?? $payload['type'] ?? 'text';
    
    echo "--- {$r['created_at']} | {$direction} | tipo: {$type} ---\n";
    if ($text !== null && $text !== '') {
        echo "Texto: " . substr($text, 0, 500) . (strlen($text) > 500 ? '...' : '') . "\n";
    } else {
        echo "Conteúdo: [mídia ou sem texto]\n";
        if (!empty($msg['caption'])) {
            echo "Legenda: " . $msg['caption'] . "\n";
        }
        if (!empty($msg['mimetype']) || !empty($msg['mediaUrl'])) {
            echo "Mídia: " . ($msg['mimetype'] ?? '') . " " . ($msg['mediaUrl'] ?? '') . "\n";
        }
        echo "Keys no payload: " . implode(', ', array_keys($payload)) . "\n";
        if (!empty($msg)) {
            echo "Keys em message: " . implode(', ', array_keys($msg)) . "\n";
            echo "message[text] = " . json_encode($msg['text'] ?? 'NULL') . "\n";
        }
        if (!empty($payload['event']['message'])) {
            echo "event.message: " . json_encode(array_keys($payload['event']['message'])) . "\n";
        }
        if (!empty($payload['raw'])) {
            $raw = is_string($payload['raw']) ? json_decode($payload['raw'], true) : $payload['raw'];
            if ($raw && isset($raw['body'])) echo "raw.body: " . substr($raw['body'], 0, 200) . "\n";
            if ($raw && isset($raw['caption'])) echo "raw.caption: " . substr($raw['caption'], 0, 200) . "\n";
        }
    }
    echo "\n";
}

echo "=== Fim ===\n";
