<?php
/**
 * Reprocessa webhooks não processados da tabela webhook_raw_logs.
 *
 * Usa ingestão direta (EventIngestionService) em vez de HTTP, evitando
 * necessidade de base URL, secret e duplicação. Opcionalmente processa
 * mídia de eventos inbound após ingestão.
 *
 * Uso: php scripts/reprocessar_webhook_raw.php [--limit=10] [--ids=1,2,3] [--no-media]
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

\PixelHub\Core\Env::load();

$options = getopt('', ['limit:', 'ids:', 'no-media']);
$limit = isset($options['limit']) ? (int) $options['limit'] : 10;
$idsArg = $options['ids'] ?? null;
$processMedia = !isset($options['no-media']);

$db = \PixelHub\Core\DB::getConnection();

// Verifica se tabela existe
$check = $db->query("SHOW TABLES LIKE 'webhook_raw_logs'");
if ($check->rowCount() === 0) {
    echo "Tabela webhook_raw_logs não existe. Execute: php database/migrate.php\n";
    exit(1);
}

echo "=== REPROCESSAMENTO WEBHOOK RAW ===\n";
echo "Processar mídia após ingestão: " . ($processMedia ? 'sim' : 'não') . "\n\n";

if ($idsArg) {
    $ids = array_map('intval', array_filter(explode(',', $idsArg)));
    if (empty($ids)) {
        echo "Nenhum ID válido em --ids.\n";
        exit(1);
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT id, event_type, payload_json, received_at FROM webhook_raw_logs WHERE processed = 0 AND id IN ($placeholders) ORDER BY id ASC");
    $stmt->execute($ids);
} else {
    $stmt = $db->prepare("SELECT id, event_type, payload_json, received_at FROM webhook_raw_logs WHERE processed = 0 ORDER BY id ASC LIMIT ?");
    $stmt->execute([$limit]);
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Registros não processados: " . count($rows) . "\n\n";

if (empty($rows)) {
    echo "Nada a reprocessar.\n";
    exit(0);
}

$controller = new \PixelHub\Controllers\WhatsAppWebhookController();

$ok = 0;
$fail = 0;
$skipped = 0;

foreach ($rows as $row) {
    $payload = json_decode($row['payload_json'], true);
    if (!is_array($payload)) {
        $db->prepare("UPDATE webhook_raw_logs SET error_message = ? WHERE id = ?")
            ->execute(['Payload JSON inválido', $row['id']]);
        echo "  [id={$row['id']}] {$row['received_at']} type={$row['event_type']} ... FAIL (JSON inválido)\n";
        $fail++;
        continue;
    }

    echo "  [id={$row['id']}] {$row['received_at']} type={$row['event_type']} ... ";

    $result = $controller->processPayload($payload);

    if ($result['skipped']) {
        $db->prepare("UPDATE webhook_raw_logs SET processed = 1, error_message = ? WHERE id = ?")
            ->execute([$result['error'] ?? 'skipped', $row['id']]);
        echo "SKIP ({$result['error']})\n";
        $skipped++;
        continue;
    }

    if ($result['saved']) {
        $db->prepare("UPDATE webhook_raw_logs SET processed = 1, event_id = ? WHERE id = ?")
            ->execute([$result['event_id'], $row['id']]);

        // Processa mídia para inbound (opcional)
        if ($processMedia && $result['event_id']) {
            $evt = $payload['event'] ?? $payload['type'] ?? null;
            $fromMe = $payload['fromMe'] ?? $payload['message']['fromMe'] ?? $payload['raw']['payload']['fromMe'] ?? false;
            $isInbound = ($evt === 'message' && !$fromMe) || in_array($evt, ['onmessage', 'message.received'], true);
            if ($isInbound) {
                try {
                    $event = \PixelHub\Services\EventIngestionService::findByEventId($result['event_id']);
                    if ($event) {
                        \PixelHub\Services\WhatsAppMediaService::processMediaFromEvent($event);
                    }
                } catch (\Throwable $e) {
                    echo "(mídia: " . substr($e->getMessage(), 0, 40) . ") ";
                }
            }
        }

        echo "OK (event_id={$result['event_id']})\n";
        $ok++;
    } else {
        $err = substr($result['error'] ?? 'Unknown', 0, 500);
        $db->prepare("UPDATE webhook_raw_logs SET error_message = ? WHERE id = ?")
            ->execute([$err, $row['id']]);
        echo "FAIL ($err)\n";
        $fail++;
    }
}

echo "\nConcluído: $ok OK, $fail falhas, $skipped ignorados.\n";
