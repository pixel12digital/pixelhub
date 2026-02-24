<?php
// Testar se o Inbox funciona localmente

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

echo "=== TESTE LOCAL DO INBOX ===\n\n";

// 1. Testar listagem de conversas não vinculadas
echo "--- CONVERSAS NÃO VINCULADAS ---\n";
try {
    $stmt = $db->prepare("
        SELECT 
            id,
            conversation_key,
            contact_external_id,
            contact_name,
            tenant_id,
            last_message_at,
            unread_count
        FROM conversations
        WHERE tenant_id IS NULL
        ORDER BY last_message_at DESC
        LIMIT 10
    ");
    
    $stmt->execute();
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✓ Query OK - Total: " . count($conversations) . " conversas\n\n";
    
    foreach ($conversations as $conv) {
        echo sprintf(
            "ID: %3d | Contato: %-30s | Nome: %-20s\n",
            $conv['id'],
            substr($conv['contact_external_id'], 0, 30),
            substr($conv['contact_name'] ?? 'N/A', 0, 20)
        );
    }
    
} catch (PDOException $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
}

// 2. Testar mensagens da conversa 457 (Douglas) COM filtro
echo "\n--- MENSAGENS DA CONVERSA 457 (COM FILTRO) ---\n";
try {
    $stmt = $db->prepare("
        SELECT 
            ce.event_id,
            ce.event_type,
            ce.created_at,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) as type,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')) as body
        FROM communication_events ce
        WHERE ce.conversation_id = 457
        AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
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
        ORDER BY ce.created_at ASC
    ");
    
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✓ Query OK - Total: " . count($messages) . " mensagens\n\n";
    
    foreach ($messages as $msg) {
        echo sprintf(
            "[%s] %s | Tipo: %-15s | Body: %s\n",
            substr($msg['created_at'], 0, 16),
            substr($msg['event_type'], 9),
            $msg['type'] ?? 'NULL',
            substr($msg['body'] ?? 'NULL', 0, 50)
        );
    }
    
} catch (PDOException $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
}

// 3. Testar mensagens SEM filtro (para comparação)
echo "\n--- MENSAGENS DA CONVERSA 457 (SEM FILTRO) ---\n";
try {
    $stmt = $db->prepare("
        SELECT 
            ce.event_id,
            ce.event_type,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) as type,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.type')) as raw_type
        FROM communication_events ce
        WHERE ce.conversation_id = 457
        AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        ORDER BY ce.created_at ASC
    ");
    
    $stmt->execute();
    $allMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total SEM filtro: " . count($allMessages) . " mensagens\n";
    echo "Total COM filtro: " . count($messages) . " mensagens\n";
    echo "Diferença (filtrados): " . (count($allMessages) - count($messages)) . " eventos técnicos\n";
    
} catch (PDOException $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
}

echo "\n=== CONCLUSÃO ===\n";
echo "Se as queries acima funcionaram, o código está OK localmente.\n";
echo "O problema pode estar no servidor (sintaxe ou cache).\n";
