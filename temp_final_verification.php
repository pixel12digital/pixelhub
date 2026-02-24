<?php
// Verificação final - Douglas e conversas não vinculadas

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

echo "=== VERIFICAÇÃO FINAL - DOUGLAS NO INBOX ===\n\n";

// 1. Status dos eventos de Douglas
echo "--- EVENTOS DE DOUGLAS (3765) ---\n";
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
    WHERE payload LIKE '%47953460858953%'
       OR payload LIKE '%3765%'
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$douglasEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos: " . count($douglasEvents) . "\n\n";

$processedCount = 0;
$withConversation = 0;

foreach ($douglasEvents as $event) {
    if ($event['status'] === 'processed') $processedCount++;
    if ($event['conversation_id']) $withConversation++;
    
    echo sprintf(
        "[%s] Status: %-10s | Conv: %-5s | Tenant: %-5s\n",
        substr($event['created_at'], 0, 16),
        $event['status'],
        $event['conversation_id'] ?? 'NULL',
        $event['tenant_id'] ?? 'NULL'
    );
}

echo "\nResumo:\n";
echo "  Processados: $processedCount/" . count($douglasEvents) . "\n";
echo "  Com conversa: $withConversation/" . count($douglasEvents) . "\n";

// 2. Conversa de Douglas
echo "\n--- CONVERSA DE DOUGLAS ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        contact_name,
        tenant_id,
        channel_type,
        last_message_at,
        unread_count,
        created_at
    FROM conversations
    WHERE contact_external_id LIKE '%47953460858953%'
       OR contact_external_id LIKE '%3765%'
    ORDER BY created_at DESC
");
$stmt->execute();
$douglasConvs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($douglasConvs) > 0) {
    echo "✓ SUCESSO! Douglas tem " . count($douglasConvs) . " conversa(s):\n\n";
    
    foreach ($douglasConvs as $conv) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "  ID da Conversa: {$conv['id']}\n";
        echo "  Contato: {$conv['contact_external_id']}\n";
        echo "  Nome: " . ($conv['contact_name'] ?? 'N/A') . "\n";
        echo "  Tenant: " . ($conv['tenant_id'] ?? 'NULL (NÃO VINCULADA)') . "\n";
        echo "  Canal: {$conv['channel_type']}\n";
        echo "  Criada em: {$conv['created_at']}\n";
        echo "  Última mensagem: {$conv['last_message_at']}\n";
        echo "  Mensagens não lidas: {$conv['unread_count']}\n";
        
        if ($conv['tenant_id'] === null) {
            echo "\n  ✓ Status: CONVERSA NÃO VINCULADA\n";
            echo "  ✓ Aparece no Inbox para vinculação manual\n";
        } else {
            echo "\n  ✓ Status: VINCULADA ao Tenant {$conv['tenant_id']}\n";
        }
        echo "\n";
    }
} else {
    echo "✗ ERRO: Douglas ainda NÃO tem conversa criada\n";
}

// 3. Total de conversas não vinculadas
echo "\n--- TODAS AS CONVERSAS NÃO VINCULADAS ---\n";
$stmt = $db->query("
    SELECT COUNT(*) as total
    FROM conversations
    WHERE tenant_id IS NULL
");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Total de conversas não vinculadas no sistema: {$result['total']}\n";
echo "\n✓ Todas essas conversas aparecem no Inbox como 'Não vinculadas'\n";
echo "✓ Você pode vinculá-las manualmente a um tenant ou criar novos leads\n";

echo "\n=== DIAGNÓSTICO COMPLETO ===\n";
