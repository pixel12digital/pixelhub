<?php
// Carrega o ambiente
define('ROOT_PATH', __DIR__ . '/');
require_once ROOT_PATH . 'src/Core/Env.php';

PixelHub\Core\Env::load();

// Pega configurações do banco
$config = require ROOT_PATH . 'config/database.php';

try {
    $db = new PDO("mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}", $config['username'], $config['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

echo "=== INVESTIGAÇÃO: MENSAGENS DUPLICADAS (TIMEOUT + REENVIO) ===\n\n";

// Buscar eventos recentes com delivery_uncertain=true (timeout)
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        tenant_id,
        source_system,
        JSON_EXTRACT(metadata, '$.text') as message_text,
        JSON_EXTRACT(metadata, '$.to') as to_number,
        JSON_EXTRACT(metadata, '$.from') as from_number,
        JSON_EXTRACT(metadata, '$.delivery_uncertain') as delivery_uncertain,
        JSON_EXTRACT(metadata, '$.gateway_message_id') as gateway_message_id,
        JSON_EXTRACT(payload, '$.direction') as direction,
        created_at
    FROM communication_events
    WHERE event_type LIKE '%message%'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        AND JSON_EXTRACT(metadata, '$.delivery_uncertain') = true
    ORDER BY created_at DESC
    LIMIT 30
");
$stmt->execute();
$timeoutEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "--- Eventos com TIMEOUT (delivery_uncertain=true) nas últimas 24h ---\n";
foreach ($timeoutEvents as $event) {
    echo sprintf(
        "ID: %d | Tenant: %d | Canal: %s | Contato: %s | Texto: %s | Gateway ID: %s | Criado: %s\n",
        $event['id'],
        $event['tenant_id'],
        $event['channel_account_id'],
        $event['contact_external_id'],
        substr($event['message_text'], 0, 50),
        $event['gateway_message_id'],
        $event['created_at']
    );
}

echo "\n--- BUSCANDO DUPLICATAS (mesmo contato, mesmo texto, próximos no tempo) ---\n";

// Para cada evento com timeout, buscar possíveis duplicatas
foreach ($timeoutEvents as $timeoutEvent) {
    $messageText = json_decode($timeoutEvent['message_text']);
    
    $stmt = $db->prepare("
        SELECT 
            id,
            JSON_EXTRACT(metadata, '$.text') as message_text,
            JSON_EXTRACT(metadata, '$.delivery_uncertain') as delivery_uncertain,
            JSON_EXTRACT(metadata, '$.gateway_message_id') as gateway_message_id,
            created_at,
            TIMESTAMPDIFF(SECOND, :timeout_created, created_at) as seconds_diff
        FROM communication_events
        WHERE tenant_id = :tenant_id
            AND channel_account_id = :channel_account_id
            AND contact_external_id = :contact_external_id
            AND direction = 'outbound'
            AND id != :timeout_id
            AND created_at BETWEEN 
                DATE_SUB(:timeout_created, INTERVAL 10 MINUTE) AND 
                DATE_ADD(:timeout_created, INTERVAL 10 MINUTE)
            AND JSON_EXTRACT(metadata, '$.text') = :message_text
        ORDER BY created_at
    ");
    
    $stmt->execute([
        'tenant_id' => $timeoutEvent['tenant_id'],
        'channel_account_id' => $timeoutEvent['channel_account_id'],
        'contact_external_id' => $timeoutEvent['contact_external_id'],
        'timeout_id' => $timeoutEvent['id'],
        'timeout_created' => $timeoutEvent['created_at'],
        'message_text' => $messageText
    ]);
    
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($duplicates) > 0) {
        echo "\n🔴 DUPLICATAS ENCONTRADAS para evento ID {$timeoutEvent['id']}:\n";
        echo "   Timeout original: {$timeoutEvent['created_at']}\n";
        echo "   Texto: " . substr($messageText, 0, 60) . "...\n";
        
        foreach ($duplicates as $dup) {
            echo sprintf(
                "   → ID: %d | Criado: %s | Diff: %ds | Delivery Uncertain: %s | Gateway ID: %s\n",
                $dup['id'],
                $dup['created_at'],
                $dup['seconds_diff'],
                $dup['delivery_uncertain'] ?? 'null',
                $dup['gateway_message_id']
            );
        }
    }
}

echo "\n\n=== ANÁLISE DE IDEMPOTÊNCIA ===\n";

// Verificar se há eventos com mesmo idempotency_key
$stmt = $db->prepare("
    SELECT 
        idempotency_key,
        COUNT(*) as count,
        GROUP_CONCAT(id ORDER BY created_at) as event_ids,
        MIN(created_at) as first_created,
        MAX(created_at) as last_created
    FROM communication_events
    WHERE direction = 'outbound'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND idempotency_key IS NOT NULL
    GROUP BY idempotency_key
    HAVING count > 1
    ORDER BY count DESC
    LIMIT 10
");
$stmt->execute();
$duplicateKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($duplicateKeys) > 0) {
    echo "🔴 FALHA DE IDEMPOTÊNCIA - Eventos com mesmo idempotency_key:\n";
    foreach ($duplicateKeys as $dup) {
        echo sprintf(
            "Key: %s | Count: %d | IDs: %s | First: %s | Last: %s\n",
            $dup['idempotency_key'],
            $dup['count'],
            $dup['event_ids'],
            $dup['first_created'],
            $dup['last_created']
        );
    }
} else {
    echo "✅ Nenhuma duplicação de idempotency_key encontrada.\n";
}
