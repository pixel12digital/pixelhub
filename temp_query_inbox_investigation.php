<?php
// Carregar .env manualmente
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? '';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// Número do contato: +55 41 9505-9936
$phone = '5541950599936'; // Formato E.164 sem +

echo "=== INVESTIGAÇÃO INBOX - KELLY COSTA ===\n\n";

// 0. Verificar estrutura da tabela communication_events
echo "0. ESTRUTURA DA TABELA communication_events:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM communication_events");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}
echo "\n";

// 1. Buscar conversa Kelly Costa (ID 482)
echo "1. BUSCANDO CONVERSA KELLY COSTA:\n";
$stmt = $pdo->prepare("
    SELECT id, conversation_key, contact_external_id, contact_name, tenant_id, last_message_at, created_at
    FROM conversations
    WHERE id = 482
");
$stmt->execute();
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

if ($conv) {
    echo "✓ Conversa encontrada:\n";
    echo "  - ID: {$conv['id']}\n";
    echo "  - Key: {$conv['conversation_key']}\n";
    echo "  - Contact External ID: {$conv['contact_external_id']}\n";
    echo "  - Contact Name: {$conv['contact_name']}\n";
    echo "  - Tenant ID: {$conv['tenant_id']}\n";
    echo "  - Last Message: {$conv['last_message_at']}\n";
    echo "  - Created: {$conv['created_at']}\n\n";
} else {
    echo "❌ Conversa ID 482 não encontrada\n\n";
}

// 2. Buscar eventos de comunicação pela conversa (se encontrada)
if ($conv) {
    echo "\n2. EVENTOS DE COMUNICAÇÃO DA CONVERSA ID {$conv['id']} (últimos 50):\n";
    $stmt = $pdo->prepare("
        SELECT *
        FROM communication_events
        WHERE conversation_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$conv['id']]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($events)) {
        echo "❌ Nenhum evento encontrado para esta conversa\n\n";
    } else {
        echo "✓ Total de eventos: " . count($events) . "\n\n";
        foreach ($events as $idx => $event) {
            $payload = json_decode($event['payload'], true);
            $body = $payload['body'] ?? 'N/A';
            $fromMe = $payload['fromMe'] ?? false;
            $from = $payload['from'] ?? 'N/A';
            $to = $payload['to'] ?? 'N/A';
            
            echo "Evento #{$idx} - ID: {$event['id']}\n";
            echo "  - Event ID: {$event['event_id']}\n";
            echo "  - Type: {$event['event_type']}\n";
            echo "  - Source: {$event['source_system']}\n";
            echo "  - From: {$from}\n";
            echo "  - To: {$to}\n";
            echo "  - From Me: " . ($fromMe ? 'true' : 'false') . "\n";
            echo "  - Body: " . substr($body, 0, 100) . "\n";
            echo "  - Status: {$event['status']}\n";
            echo "  - Created: {$event['created_at']}\n\n";
        }
    }
}

// 3. Verificar estrutura de webhook_raw_logs
echo "\n3. ESTRUTURA DA TABELA webhook_raw_logs:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM webhook_raw_logs");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}
echo "\n";

// 4. Buscar payloads completos dos eventos
if ($conv) {
    echo "\n4. PAYLOADS COMPLETOS DOS EVENTOS:\n";
    $stmt = $pdo->prepare("
        SELECT id, event_id, event_type, source_system, payload, created_at
        FROM communication_events
        WHERE conversation_id = ?
        ORDER BY created_at ASC
        LIMIT 10
    ");
    $stmt->execute([$conv['id']]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($events as $idx => $event) {
        echo "\n--- Evento #{$idx} - ID: {$event['id']} ---\n";
        echo "Event ID: {$event['event_id']}\n";
        echo "Type: {$event['event_type']}\n";
        echo "Source: {$event['source_system']}\n";
        echo "Created: {$event['created_at']}\n";
        echo "Payload:\n";
        
        $payload = json_decode($event['payload'], true);
        if ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "PAYLOAD VAZIO OU INVÁLIDO\n";
            echo "Raw: " . substr($event['payload'], 0, 500) . "\n";
        }
    }
}

echo "\n=== FIM DA INVESTIGAÇÃO ===\n";
