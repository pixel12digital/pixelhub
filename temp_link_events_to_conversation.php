<?php
// Vincular eventos de Douglas à conversa 457

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

echo "=== VINCULAR EVENTOS À CONVERSA 457 ===\n\n";

// Atualizar eventos de Douglas para vincular à conversa 457
$stmt = $db->prepare("
    UPDATE communication_events
    SET conversation_id = 457
    WHERE (payload LIKE '%47953460858953%' OR payload LIKE '%3765%')
    AND event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND conversation_id IS NULL
");

$stmt->execute();
$affected = $stmt->rowCount();

echo "✓ {$affected} eventos vinculados à conversa 457\n\n";

// Verificar resultado
echo "--- EVENTOS AGORA VINCULADOS ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_type,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')) as type,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.body')) as body,
        created_at
    FROM communication_events
    WHERE conversation_id = 457
    ORDER BY created_at ASC
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($events) . " eventos\n\n";

foreach ($events as $event) {
    $type = $event['type'] ?? 'NULL';
    $isTechnical = in_array($type, ['e2e_notification', 'notification_template', 'ciphertext']);
    
    echo sprintf(
        "[%s] %s | Tipo: %-20s %s\n",
        substr($event['created_at'], 0, 16),
        substr($event['event_type'], 9),
        $type,
        $isTechnical ? '⚠️  TÉCNICO (será filtrado)' : '✓ OK'
    );
}

echo "\n=== PRÓXIMO PASSO ===\n";
echo "Acesse o Inbox e abra a conversa de Douglas.\n";
echo "As mensagens técnicas (e2e_notification, notification_template, ciphertext) não aparecerão mais.\n";
