<?php
/**
 * Processa um único evento de mídia e exibe resultado/erro detalhado.
 * Uso: php database/processar-evento-midia.php --event_id=xxx
 */
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($c) {
        if (strncmp('PixelHub\\', $c, 9) !== 0) return;
        $f = __DIR__ . '/../src/' . str_replace('\\', '/', substr($c, 9)) . '.php';
        if (file_exists($f)) require $f;
    });
}
\PixelHub\Core\Env::load();

$opt = getopt('', ['event_id:']);
$eventId = $opt['event_id'] ?? null;
if (!$eventId) {
    echo "Uso: php database/processar-evento-midia.php --event_id=xxx\n";
    exit(1);
}

$db = \PixelHub\Core\DB::getConnection();
$stmt = $db->prepare("SELECT * FROM communication_events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "Evento não encontrado: {$eventId}\n";
    exit(1);
}

$payload = json_decode($event['payload'], true);
$rawPayload = $payload['raw']['payload'] ?? null;
$mediaId = $rawPayload['id'] ?? $payload['message']['id'] ?? $payload['id'] ?? null;
$sessionId = $payload['session']['id'] ?? $payload['raw']['payload']['session'] ?? null;

echo "=== PROCESSANDO EVENTO {$eventId} ===\n\n";
echo "event_type: {$event['event_type']}\n";
echo "session.id: " . ($sessionId ?: 'NULL') . "\n";
echo "mediaId (raw.payload.id): " . ($mediaId ?: 'NULL') . "\n";
echo "raw.payload.type: " . ($rawPayload['type'] ?? 'NULL') . "\n\n";

echo "Chamando WhatsAppMediaService::processMediaFromEvent()...\n\n";

try {
    $result = \PixelHub\Services\WhatsAppMediaService::processMediaFromEvent($event);
    if ($result && !empty($result['url'])) {
        echo "OK - URL: {$result['url']}\n";
    } elseif ($result) {
        echo "Processado mas sem URL (media_failed ou stored_path vazio)\n";
        print_r($result);
    } else {
        echo "Retornou null - mediaId não encontrado ou payload sem mídia\n";
    }
} catch (\Throwable $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
}
