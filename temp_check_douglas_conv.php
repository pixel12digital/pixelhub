<?php
// Verificar eventos da conversa de Douglas

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

echo "=== ANÁLISE DA CONVERSA DE DOUGLAS ===\n\n";

// 1. Dados da conversa
echo "--- CONVERSA 457 ---\n";
$stmt = $db->prepare("
    SELECT *
    FROM conversations
    WHERE id = 457
");
$stmt->execute();
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

if ($conv) {
    echo "ID: {$conv['id']}\n";
    echo "Contato: {$conv['contact_external_id']}\n";
    echo "Nome: " . ($conv['contact_name'] ?? 'NULL') . "\n";
    echo "Tenant: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
    echo "Criada: {$conv['created_at']}\n";
    echo "Última msg: {$conv['last_message_at']}\n";
} else {
    echo "Conversa não encontrada!\n";
    exit(1);
}

// 2. Eventos vinculados à conversa
echo "\n--- EVENTOS VINCULADOS (conversation_id=457) ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        conversation_id,
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
    $isTechnical = in_array($event['type'], ['e2e_notification', 'notification_template', 'ciphertext']);
    echo sprintf(
        "[%s] %s | Tipo: %-20s | Body: %s %s\n",
        substr($event['created_at'], 0, 16),
        substr($event['event_type'], 9),
        $event['type'] ?? 'NULL',
        substr($event['body'] ?? 'NULL', 0, 50),
        $isTechnical ? '⚠️  TÉCNICO' : ''
    );
}

// 3. Eventos de Douglas que NÃO estão vinculados
echo "\n--- EVENTOS DE DOUGLAS SEM CONVERSA ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        conversation_id,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')) as type,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.body')) as body,
        created_at
    FROM communication_events
    WHERE (payload LIKE '%47953460858953%' OR payload LIKE '%3765%')
    AND conversation_id IS NULL
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$orphanEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($orphanEvents) . " eventos órfãos\n\n";

foreach ($orphanEvents as $event) {
    $isTechnical = in_array($event['type'], ['e2e_notification', 'notification_template', 'ciphertext']);
    echo sprintf(
        "[%s] %s | Tipo: %-20s %s\n",
        substr($event['created_at'], 0, 16),
        substr($event['event_type'], 9),
        $event['type'] ?? 'NULL',
        $isTechnical ? '⚠️  TÉCNICO' : ''
    );
}

echo "\n=== DIAGNÓSTICO ===\n";
echo "A conversa 457 foi criada mas os eventos ainda não foram vinculados a ela.\n";
echo "Isso pode acontecer se o worker processou os eventos antes da correção.\n";
