<?php
// Verificar se há erro de sintaxe na query

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

echo "=== TESTE DA QUERY COM FILTRO ===\n\n";

// Testar a query que o Inbox usa
try {
    $stmt = $db->prepare("
        SELECT 
            ce.event_id,
            ce.event_type,
            ce.created_at,
            ce.payload,
            ce.metadata,
            ce.tenant_id
        FROM communication_events ce
        WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        AND (
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) NOT IN ('e2e_notification', 'notification_template', 'ciphertext')
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) IS NULL
        )
        AND (
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')) NOT IN ('e2e_notification', 'notification_template', 'ciphertext')
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')) IS NULL
        )
        AND (
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.type')) NOT IN ('e2e_notification', 'notification_template', 'ciphertext')
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.type')) IS NULL
        )
        AND ce.conversation_id = 457
        ORDER BY ce.created_at ASC
        LIMIT 10
    ");
    
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✓ Query executada com sucesso!\n";
    echo "Total de eventos: " . count($events) . "\n";
    
} catch (PDOException $e) {
    echo "✗ ERRO NA QUERY:\n";
    echo $e->getMessage() . "\n\n";
    echo "Código do erro: " . $e->getCode() . "\n";
}

// Testar query de conversas
echo "\n--- TESTE: LISTAR CONVERSAS ---\n";
try {
    $stmt = $db->prepare("
        SELECT id, contact_external_id, contact_name, tenant_id
        FROM conversations
        WHERE tenant_id IS NULL
        ORDER BY last_message_at DESC
        LIMIT 5
    ");
    
    $stmt->execute();
    $convs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✓ Query de conversas OK!\n";
    echo "Total: " . count($convs) . "\n";
    
} catch (PDOException $e) {
    echo "✗ ERRO:\n";
    echo $e->getMessage() . "\n";
}
