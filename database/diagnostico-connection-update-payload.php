<?php
/**
 * Diagnóstico: Payload de connection.update para ver estrutura e estado (connected/disconnected)
 *
 * ONDE RODAR: HostMedia (precisa acessar banco)
 *
 * Uso: php database/diagnostico-connection-update-payload.php [--session=pixel12digital]
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require_once $file;
    });
}

\PixelHub\Core\Env::load();
$db = \PixelHub\Core\DB::getConnection();

$session = 'pixel12digital';
foreach ($argv as $a) {
    if (strpos($a, '--session=') === 0) {
        $session = substr($a, 10);
        break;
    }
}

echo "=== Payload connection.update (session=$session) ===\n\n";

$stmt = $db->prepare("
    SELECT id, event_type, created_at, payload_json
    FROM webhook_raw_logs
    WHERE event_type = 'connection.update'
    AND payload_json LIKE ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute(["%{$session}%"]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo "Nenhum connection.update encontrado para $session.\n";
    exit(0);
}

$payload = json_decode($row['payload_json'], true);
echo "id={$row['id']} | {$row['created_at']}\n\n";
echo "Payload (JSON formatado):\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n=== FIM ===\n";
