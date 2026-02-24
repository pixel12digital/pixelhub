<?php
// Investigar mensagens de Douglas - por que aparecem criptografadas?

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

echo "=== INVESTIGAÇÃO: MENSAGENS DE DOUGLAS ===\n\n";

// Buscar eventos de Douglas
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        payload,
        created_at,
        conversation_id
    FROM communication_events
    WHERE (payload LIKE '%47953460858953%' OR payload LIKE '%3765%')
    AND event_type LIKE '%message%'
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos encontrados: " . count($events) . "\n\n";

foreach ($events as $event) {
    $payload = json_decode($event['payload'], true);
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Evento ID: {$event['id']}\n";
    echo "Tipo: {$event['event_type']}\n";
    echo "Data: {$event['created_at']}\n";
    echo "Conversa: " . ($event['conversation_id'] ?? 'NULL') . "\n\n";
    
    // Analisar estrutura do payload
    echo "Estrutura do Payload:\n";
    
    // Tipo de mensagem
    $messageType = $payload['type'] 
        ?? $payload['message']['type'] 
        ?? $payload['data']['type']
        ?? $payload['raw']['payload']['type']
        ?? 'unknown';
    echo "  Tipo de mensagem: $messageType\n";
    
    // Body/texto
    $body = $payload['body'] 
        ?? $payload['message']['body'] 
        ?? $payload['data']['body']
        ?? $payload['message']['text']
        ?? $payload['raw']['payload']['body']
        ?? null;
    
    if ($body) {
        echo "  Body: " . substr($body, 0, 100) . "\n";
    } else {
        echo "  Body: NULL\n";
    }
    
    // Verificar se é notificação técnica
    if (isset($payload['subtype'])) {
        echo "  Subtype: {$payload['subtype']}\n";
    }
    
    // Verificar se tem conteúdo de mensagem
    if (isset($payload['message'])) {
        echo "  Tem 'message': SIM\n";
        if (isset($payload['message']['conversation'])) {
            echo "  Tem 'message.conversation': " . $payload['message']['conversation'] . "\n";
        }
    }
    
    // Verificar se é e2e_notification
    if ($messageType === 'e2e_notification' || $messageType === 'notification_template') {
        echo "  ⚠️  NOTIFICAÇÃO TÉCNICA - NÃO DEVERIA APARECER NO INBOX\n";
    }
    
    // Verificar se tem isEncrypted
    if (isset($payload['isEncrypted'])) {
        echo "  isEncrypted: " . ($payload['isEncrypted'] ? 'true' : 'false') . "\n";
    }
    
    echo "\n";
}

// Verificar como o Inbox está buscando as mensagens
echo "\n--- COMO O INBOX BUSCA MENSAGENS ---\n";
echo "O Inbox usa JSON_EXTRACT do campo 'payload' para exibir mensagens.\n";
echo "Vamos verificar o que está sendo extraído:\n\n";

$stmt = $db->prepare("
    SELECT 
        id,
        event_type,
        JSON_EXTRACT(payload, '$.type') as msg_type,
        JSON_EXTRACT(payload, '$.body') as body,
        JSON_EXTRACT(payload, '$.message.body') as message_body,
        JSON_EXTRACT(payload, '$.message.type') as message_type,
        JSON_EXTRACT(payload, '$.subtype') as subtype,
        created_at
    FROM communication_events
    WHERE conversation_id = 457
    ORDER BY created_at ASC
");
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($messages as $msg) {
    echo "ID: {$msg['id']} | Tipo: {$msg['event_type']}\n";
    echo "  msg_type: " . ($msg['msg_type'] ?? 'NULL') . "\n";
    echo "  body: " . ($msg['body'] ?? 'NULL') . "\n";
    echo "  message.body: " . ($msg['message_body'] ?? 'NULL') . "\n";
    echo "  message.type: " . ($msg['message_type'] ?? 'NULL') . "\n";
    echo "  subtype: " . ($msg['subtype'] ?? 'NULL') . "\n";
    echo "\n";
}

echo "=== FIM DA INVESTIGAÇÃO ===\n";
