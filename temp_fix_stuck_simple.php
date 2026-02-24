<?php
// Script simples para reprocessar eventos travados
$envFile = __DIR__ . '/.env';
$envVars = [];

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
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

echo "=== ANÁLISE DE EVENTOS TRAVADOS ===\n\n";

// 1. Buscar eventos em 'processing'
echo "--- EVENTOS TRAVADOS (status=processing) ---\n";
$stmt = $db->query("
    SELECT 
        id,
        event_id,
        event_type,
        source_system,
        tenant_id,
        payload,
        metadata,
        status,
        created_at
    FROM communication_events
    WHERE status = 'processing'
    ORDER BY created_at DESC
");
$stuckEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($stuckEvents) . " eventos\n\n";

if (count($stuckEvents) === 0) {
    echo "Nenhum evento travado encontrado.\n";
} else {
    foreach ($stuckEvents as $event) {
        $payload = json_decode($event['payload'], true);
        $from = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
        $to = $payload['to'] ?? $payload['message']['to'] ?? 'N/A';
        
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "ID: {$event['id']} | Event ID: {$event['event_id']}\n";
        echo "Tipo: {$event['event_type']} | Criado: {$event['created_at']}\n";
        echo "Tenant: " . ($event['tenant_id'] ?? 'NULL') . "\n";
        echo "From: $from\n";
        echo "To: $to\n\n";
    }
    
    echo "\n--- AÇÃO NECESSÁRIA ---\n";
    echo "Esses eventos estão travados e precisam ser reprocessados.\n";
    echo "Vou marcar como 'queued' para reprocessamento automático.\n\n";
    
    $confirm = readline("Deseja reprocessar esses eventos? (s/n): ");
    
    if (strtolower(trim($confirm)) === 's') {
        foreach ($stuckEvents as $event) {
            // Marca como queued para reprocessamento
            $updateStmt = $db->prepare("
                UPDATE communication_events 
                SET status = 'queued', processed_at = NULL
                WHERE event_id = ?
            ");
            $updateStmt->execute([$event['event_id']]);
            
            echo "✓ Evento {$event['id']} marcado como 'queued'\n";
        }
        
        echo "\nEventos marcados para reprocessamento.\n";
        echo "IMPORTANTE: Execute o worker de processamento para processar esses eventos.\n";
    } else {
        echo "Operação cancelada.\n";
    }
}

// 2. Verificar conversas não vinculadas
echo "\n--- CONVERSAS NÃO VINCULADAS (tenant_id=NULL) ---\n";
$stmt = $db->query("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        contact_name,
        last_message_at,
        unread_count,
        channel_type
    FROM conversations
    WHERE tenant_id IS NULL
    ORDER BY last_message_at DESC
    LIMIT 20
");
$unlinkedConversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($unlinkedConversations) . " conversas\n\n";

if (count($unlinkedConversations) > 0) {
    foreach ($unlinkedConversations as $conv) {
        echo sprintf(
            "ID: %3d | Contato: %-30s | Nome: %-25s | Última msg: %s | Não lidas: %d\n",
            $conv['id'],
            substr($conv['contact_external_id'], 0, 30),
            substr($conv['contact_name'] ?? 'N/A', 0, 25),
            $conv['last_message_at'],
            $conv['unread_count']
        );
    }
    
    echo "\n✓ Essas conversas aparecem no Inbox como 'Não vinculadas'\n";
    echo "  Você pode vinculá-las manualmente a um tenant ou criar um novo lead.\n";
} else {
    echo "Nenhuma conversa não vinculada encontrada.\n";
}

// 3. Verificar se Douglas tem conversa criada
echo "\n--- VERIFICAR DOUGLAS (3765) ---\n";
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
    WHERE contact_external_id LIKE '%3765%'
    ORDER BY last_message_at DESC
");
$stmt->execute();
$douglasConv = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($douglasConv) > 0) {
    echo "✓ Douglas TEM conversa criada:\n";
    foreach ($douglasConv as $conv) {
        echo "  ID: {$conv['id']} | Tenant: " . ($conv['tenant_id'] ?? 'NULL (não vinculada)') . "\n";
        echo "  Contato: {$conv['contact_external_id']}\n";
        echo "  Nome: " . ($conv['contact_name'] ?? 'N/A') . "\n";
        echo "  Última msg: {$conv['last_message_at']}\n";
    }
} else {
    echo "✗ Douglas NÃO tem conversa criada\n";
    echo "  Isso explica por que não aparece no Inbox.\n";
    echo "  Os eventos estão travados em 'processing' e não criaram a conversa.\n";
}

echo "\n=== FIM DA ANÁLISE ===\n";
