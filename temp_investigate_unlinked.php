<?php
$host = 'r225us.hmservers.net';
$dbname = 'pixel12digital_pixelhub';
$user = 'pixel12digital_pixelhub';
$pass = 'Los@ngo#081081';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

echo "=== INVESTIGAÇÃO: CONVERSAS NÃO VINCULADAS ===\n\n";

// 1. Verificar conversas sem tenant (não vinculadas)
echo "1. CONVERSAS NÃO VINCULADAS (sem tenant_id):\n";
$stmt = $pdo->query("
    SELECT c.id, c.conversation_key, c.contact_external_id,
           c.last_message_at, c.status, c.message_count,
           c.created_at
    FROM conversations c
    WHERE c.tenant_id IS NULL
      AND c.last_message_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY c.last_message_at DESC
    LIMIT 20
");
$unlinked = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($unlinked)) {
    echo "   ❌ NENHUMA conversa não vinculada nas últimas 24h\n";
} else {
    echo "   Total: " . count($unlinked) . " conversas não vinculadas\n\n";
    foreach ($unlinked as $conv) {
        echo "   Conv [{$conv['id']}] | Status: {$conv['status']}\n";
        echo "      Contact: {$conv['contact_external_id']}\n";
        echo "      Última msg: {$conv['last_message_at']}\n";
        echo "      Total mensagens: {$conv['message_count']}\n";
        echo "      Criada em: {$conv['created_at']}\n";
        echo "\n";
    }
}

// 2. Buscar especificamente por mensagens do Luiz nas conversas não vinculadas
echo "\n2. BUSCANDO LUIZ (16981404507) NAS CONVERSAS NÃO VINCULADAS:\n";
$stmt = $pdo->query("
    SELECT c.id, c.conversation_key, c.contact_external_id,
           c.last_message_at, c.message_count
    FROM conversations c
    WHERE c.tenant_id IS NULL
      AND c.contact_external_id LIKE '%16981404507%'
    ORDER BY c.last_message_at DESC
");
$luizConvs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($luizConvs)) {
    echo "   ❌ Luiz NÃO encontrado nas conversas não vinculadas\n";
} else {
    echo "   ✓ ENCONTRADO!\n";
    foreach ($luizConvs as $conv) {
        echo "      Conv ID: {$conv['id']}\n";
        echo "      Contact: {$conv['contact_external_id']}\n";
        echo "      Última msg: {$conv['last_message_at']}\n";
        echo "      Total mensagens: {$conv['message_count']}\n";
    }
}

// 3. Verificar webhooks NÃO processados (processed = 0)
echo "\n\n3. WEBHOOKS NÃO PROCESSADOS (últimas 2 horas):\n";
$stmt = $pdo->query("
    SELECT id, received_at, event_type, processed, error_message,
           SUBSTRING(payload_json, 1, 200) as payload_preview
    FROM webhook_raw_logs
    WHERE processed = 0
      AND received_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY received_at DESC
    LIMIT 10
");
$unprocessed = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($unprocessed)) {
    echo "   ✓ Todos os webhooks foram processados\n";
} else {
    echo "   ⚠️  Total: " . count($unprocessed) . " webhooks NÃO processados\n\n";
    foreach ($unprocessed as $w) {
        echo "   Webhook [{$w['id']}] {$w['received_at']} | {$w['event_type']}\n";
        if ($w['error_message']) {
            echo "      Erro: {$w['error_message']}\n";
        }
        
        // Tentar extrair informações do payload
        $payload = json_decode($w['payload_json'], true);
        if ($payload) {
            $from = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
            $body = $payload['body'] ?? $payload['message']['text']['body'] ?? 'N/A';
            echo "      From: {$from}\n";
            echo "      Body: " . substr($body, 0, 80) . "\n";
        }
        echo "\n";
    }
}

// 4. Verificar eventos COM erro (status = failed)
echo "\n4. EVENTOS COM ERRO (últimas 2 horas):\n";
$stmt = $pdo->query("
    SELECT id, created_at, event_type, source_system, 
           status, error_message,
           JSON_EXTRACT(payload, '$.from') as from_number
    FROM communication_events
    WHERE status = 'failed'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC
    LIMIT 10
");
$failedEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($failedEvents)) {
    echo "   ✓ Nenhum evento com erro\n";
} else {
    echo "   ⚠️  Total: " . count($failedEvents) . " eventos com erro\n\n";
    foreach ($failedEvents as $evt) {
        echo "   Evento [{$evt['id']}] {$evt['created_at']}\n";
        echo "      Type: {$evt['event_type']} | Source: {$evt['source_system']}\n";
        echo "      From: {$evt['from_number']}\n";
        echo "      Erro: {$evt['error_message']}\n";
        echo "\n";
    }
}

// 5. Verificar estrutura da tabela conversations para entender tenant_id
echo "\n5. ESTRUTURA DA TABELA CONVERSATIONS:\n";
$stmt = $pdo->query("DESCRIBE conversations");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    if (in_array($col['Field'], ['id', 'tenant_id', 'conversation_key', 'contact_external_id', 'status', 'last_message_at'])) {
        $null = $col['Null'] == 'YES' ? 'NULL' : 'NOT NULL';
        echo "   - {$col['Field']} ({$col['Type']}) {$null}\n";
    }
}

// 6. Verificar últimos webhooks recebidos com detalhes
echo "\n\n6. ÚLTIMOS 5 WEBHOOKS RECEBIDOS (detalhado):\n";
$stmt = $pdo->query("
    SELECT id, received_at, event_type, processed, payload_json
    FROM webhook_raw_logs
    ORDER BY received_at DESC
    LIMIT 5
");
$recentWebhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($recentWebhooks as $w) {
    $proc = $w['processed'] ? '✓ PROCESSADO' : '✗ NÃO PROCESSADO';
    echo "   [{$w['id']}] {$proc} | {$w['received_at']}\n";
    
    $payload = json_decode($w['payload_json'], true);
    if ($payload) {
        // Tentar diferentes estruturas de payload (WPPConnect vs Meta)
        $from = $payload['from'] ?? 
                $payload['message']['from'] ?? 
                $payload['entry'][0]['changes'][0]['value']['messages'][0]['from'] ?? 
                'N/A';
        
        $body = $payload['body'] ?? 
                $payload['message']['text']['body'] ?? 
                $payload['entry'][0]['changes'][0]['value']['messages'][0]['text']['body'] ?? 
                'N/A';
        
        echo "      From: {$from}\n";
        echo "      Body: " . substr($body, 0, 100) . "\n";
    }
    echo "\n";
}

echo "\n=== FIM DA INVESTIGAÇÃO ===\n";
