<?php
// Verificar se conversas não vinculadas foram criadas

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

echo "=== VERIFICAÇÃO DE CONVERSAS E EVENTOS ===\n\n";

// 1. Status dos eventos
echo "--- STATUS DOS EVENTOS ---\n";
$stmt = $db->query("
    SELECT status, COUNT(*) as total
    FROM communication_events
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
    GROUP BY status
    ORDER BY status
");
$statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($statusCounts as $row) {
    echo sprintf("%-15s: %d eventos\n", $row['status'], $row['total']);
}

// 2. Eventos de Douglas
echo "\n--- EVENTOS DE DOUGLAS (3765) ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        status,
        conversation_id,
        tenant_id,
        created_at,
        processed_at
    FROM communication_events
    WHERE payload LIKE '%3765%'
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute();
$douglasEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($douglasEvents) . " eventos\n\n";

foreach ($douglasEvents as $event) {
    echo sprintf(
        "[%s] ID: %d | Status: %-10s | Conv: %-5s | Tenant: %-5s | Processado: %s\n",
        $event['created_at'],
        $event['id'],
        $event['status'],
        $event['conversation_id'] ?? 'NULL',
        $event['tenant_id'] ?? 'NULL',
        $event['processed_at'] ?? 'NULL'
    );
}

// 3. Conversas não vinculadas
echo "\n--- CONVERSAS NÃO VINCULADAS (tenant_id=NULL) ---\n";
$stmt = $db->query("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        contact_name,
        last_message_at,
        unread_count,
        created_at
    FROM conversations
    WHERE tenant_id IS NULL
    ORDER BY last_message_at DESC
    LIMIT 10
");
$unlinkedConvs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($unlinkedConvs) . " conversas\n\n";

if (count($unlinkedConvs) > 0) {
    foreach ($unlinkedConvs as $conv) {
        echo sprintf(
            "ID: %3d | Contato: %-30s | Nome: %-20s | Criada: %s | Última msg: %s\n",
            $conv['id'],
            substr($conv['contact_external_id'], 0, 30),
            substr($conv['contact_name'] ?? 'N/A', 0, 20),
            $conv['created_at'],
            $conv['last_message_at']
        );
    }
} else {
    echo "⚠️  Nenhuma conversa não vinculada encontrada!\n";
}

// 4. Conversa específica de Douglas
echo "\n--- CONVERSA DE DOUGLAS (3765) ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        contact_name,
        tenant_id,
        last_message_at,
        unread_count,
        created_at
    FROM conversations
    WHERE contact_external_id LIKE '%3765%'
       OR contact_external_id LIKE '%47953460858953%'
    ORDER BY last_message_at DESC
");
$stmt->execute();
$douglasConvs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($douglasConvs) > 0) {
    echo "✓ Douglas TEM conversa(s) criada(s):\n\n";
    foreach ($douglasConvs as $conv) {
        echo "  ID: {$conv['id']}\n";
        echo "  Contato: {$conv['contact_external_id']}\n";
        echo "  Nome: " . ($conv['contact_name'] ?? 'N/A') . "\n";
        echo "  Tenant: " . ($conv['tenant_id'] ?? 'NULL (não vinculada)') . "\n";
        echo "  Criada: {$conv['created_at']}\n";
        echo "  Última msg: {$conv['last_message_at']}\n";
        echo "  Não lidas: {$conv['unread_count']}\n";
        echo "\n";
    }
    
    if ($douglasConvs[0]['tenant_id'] === null) {
        echo "✓ Conversa está CORRETAMENTE marcada como 'não vinculada'\n";
        echo "✓ Deve aparecer no Inbox para vinculação manual\n";
    }
} else {
    echo "✗ Douglas NÃO tem conversa criada\n";
    echo "  Isso indica que os eventos ainda não foram processados corretamente.\n";
}

echo "\n=== FIM DA VERIFICAÇÃO ===\n";
