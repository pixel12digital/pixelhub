<?php
// Testar se o filtro está funcionando corretamente

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

echo "=== TESTE DO FILTRO DE EVENTOS TÉCNICOS ===\n\n";

// Buscar eventos da conversa 457 (Douglas) COM filtro
echo "--- COM FILTRO (eventos técnicos excluídos) ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_type,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')) as type,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.type')) as message_type,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.type')) as raw_type,
        created_at
    FROM communication_events
    WHERE conversation_id = 457
    AND event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')) NOT IN ('e2e_notification', 'notification_template', 'ciphertext')
        OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')) IS NULL
    )
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.type')) NOT IN ('e2e_notification', 'notification_template', 'ciphertext')
        OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.type')) IS NULL
    )
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.type')) NOT IN ('e2e_notification', 'notification_template', 'ciphertext')
        OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.type')) IS NULL
    )
    ORDER BY created_at ASC
");
$stmt->execute();
$filtered = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos COM filtro: " . count($filtered) . "\n\n";

foreach ($filtered as $event) {
    echo sprintf(
        "[%s] Tipo: %-10s | payload.type: %-20s | message.type: %-20s | raw.type: %-20s\n",
        substr($event['created_at'], 0, 16),
        substr($event['event_type'], 9, 10),
        $event['type'] ?? 'NULL',
        $event['message_type'] ?? 'NULL',
        $event['raw_type'] ?? 'NULL'
    );
}

// Buscar eventos da conversa 457 (Douglas) SEM filtro
echo "\n--- SEM FILTRO (todos os eventos) ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_type,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')) as type,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.type')) as message_type,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.type')) as raw_type,
        created_at
    FROM communication_events
    WHERE conversation_id = 457
    AND event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY created_at ASC
");
$stmt->execute();
$unfiltered = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos SEM filtro: " . count($unfiltered) . "\n\n";

foreach ($unfiltered as $event) {
    $type = $event['type'] ?? $event['message_type'] ?? $event['raw_type'] ?? 'NULL';
    $isTechnical = in_array($type, ['e2e_notification', 'notification_template', 'ciphertext']);
    
    echo sprintf(
        "[%s] Tipo: %-10s | payload.type: %-20s %s\n",
        substr($event['created_at'], 0, 16),
        substr($event['event_type'], 9, 10),
        $type,
        $isTechnical ? '⚠️  TÉCNICO (será filtrado)' : '✓ OK'
    );
}

echo "\n=== RESUMO ===\n";
echo "Eventos filtrados: " . (count($unfiltered) - count($filtered)) . "\n";
echo "Eventos que aparecerão no Inbox: " . count($filtered) . "\n";
