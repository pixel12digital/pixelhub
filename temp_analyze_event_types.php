<?php
// Analisar tipos de eventos de Douglas

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

echo "=== ANÁLISE DETALHADA DOS EVENTOS DE DOUGLAS ===\n\n";

$stmt = $db->prepare("
    SELECT 
        id,
        event_type,
        payload,
        created_at
    FROM communication_events
    WHERE conversation_id = 457
    ORDER BY created_at ASC
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($events as $event) {
    $payload = json_decode($event['payload'], true);
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "ID: {$event['id']} | Data: {$event['created_at']}\n";
    echo "Tipo: {$event['event_type']}\n\n";
    
    // Extrair todos os possíveis campos de tipo
    $types = [
        'payload.type' => $payload['type'] ?? null,
        'payload.message.type' => $payload['message']['type'] ?? null,
        'payload.raw.payload.type' => $payload['raw']['payload']['type'] ?? null,
        'payload.data.type' => $payload['data']['type'] ?? null,
    ];
    
    echo "Tipos encontrados:\n";
    foreach ($types as $path => $value) {
        if ($value !== null) {
            echo "  $path: $value\n";
        }
    }
    
    // Extrair conteúdo
    $body = $payload['body'] 
        ?? $payload['message']['body'] 
        ?? $payload['text']
        ?? $payload['message']['text']
        ?? null;
    
    if ($body) {
        echo "\nConteúdo: " . substr($body, 0, 100) . "\n";
    } else {
        echo "\nConteúdo: NULL\n";
    }
    
    // Verificar se é notificação técnica
    $allTypes = array_filter($types);
    $isTechnical = false;
    foreach ($allTypes as $type) {
        if (in_array($type, ['e2e_notification', 'notification_template', 'ciphertext'])) {
            $isTechnical = true;
            echo "\n⚠️  EVENTO TÉCNICO - Tipo: $type\n";
            break;
        }
    }
    
    if (!$isTechnical && empty($allTypes)) {
        echo "\n✓ Evento sem tipo definido (provavelmente mensagem de texto)\n";
    } elseif (!$isTechnical) {
        echo "\n✓ Evento válido\n";
    }
    
    echo "\n";
}

echo "=== RESUMO ===\n";
echo "Total de eventos: " . count($events) . "\n";
echo "\nAgora faça upload do arquivo corrigido para o servidor e teste no Inbox.\n";
