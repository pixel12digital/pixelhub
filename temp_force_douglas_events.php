<?php
// Força reprocessamento dos eventos de Douglas travados em 'processing'

$envFile = __DIR__ . '/.env';
$envVars = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $envVars[trim($key)] = trim($value);
    }
}

$host = $envVars['DB_HOST'] ?? 'localhost';
$dbname = $envVars['DB_NAME'] ?? '';
$username = $envVars['DB_USER'] ?? '';
$password = $envVars['DB_PASS'] ?? '';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage() . "\n");
}

echo "=== FORÇAR REPROCESSAMENTO - EVENTOS DE DOUGLAS ===\n\n";

// Marcar eventos de Douglas como 'queued'
$stmt = $db->prepare("
    UPDATE communication_events
    SET status = 'queued',
        retry_count = 0,
        next_retry_at = NULL,
        updated_at = NOW()
    WHERE id IN (190842, 190841, 190839)
    AND status = 'processing'
");

$stmt->execute();
$affected = $stmt->rowCount();

echo "✓ {$affected} eventos marcados como 'queued'\n";
echo "\nAgora execute o worker:\n";
echo "  php scripts/event_queue_worker.php\n";
