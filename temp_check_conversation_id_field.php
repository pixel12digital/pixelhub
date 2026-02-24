<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== VERIFICANDO CAMPO conversation_id ===\n\n";

// Verifica se a coluna conversation_id existe
$stmt = $db->query("SHOW COLUMNS FROM communication_events LIKE 'conversation_id'");
$column = $stmt->fetch();

if ($column) {
    echo "✓ Coluna conversation_id existe na tabela communication_events\n\n";
} else {
    echo "✗ Coluna conversation_id NÃO existe na tabela communication_events\n";
    echo "  Isso explica por que as mensagens não aparecem no Inbox!\n\n";
    exit(1);
}

// Verifica o evento do áudio
echo "=== EVENTO DO ÁUDIO (ID 190866) ===\n\n";
$stmt = $db->prepare("
    SELECT id, event_id, event_type, conversation_id, created_at,
           JSON_EXTRACT(payload, '$.from') as msg_from,
           JSON_EXTRACT(payload, '$.message.from') as msg_from2
    FROM communication_events 
    WHERE id = 190866
");
$stmt->execute();
$audioEvent = $stmt->fetch(PDO::FETCH_ASSOC);

if ($audioEvent) {
    echo "Event ID: {$audioEvent['event_id']}\n";
    echo "Tipo: {$audioEvent['event_type']}\n";
    echo "Conversation ID: " . ($audioEvent['conversation_id'] ?: 'NULL ⚠️') . "\n";
    echo "From: " . ($audioEvent['msg_from'] ?: $audioEvent['msg_from2']) . "\n";
    echo "Criado: {$audioEvent['created_at']}\n\n";
    
    if (!$audioEvent['conversation_id']) {
        echo "⚠️ PROBLEMA: conversation_id está NULL!\n";
        echo "   O evento não está vinculado à conversa, por isso não aparece no Inbox.\n\n";
    }
}

// Verifica todos os eventos de hoje sem conversation_id
echo "=== EVENTOS SEM conversation_id HOJE ===\n\n";
$stmt = $db->prepare("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN event_type = 'whatsapp.inbound.message' THEN 1 ELSE 0 END) as inbound,
           SUM(CASE WHEN event_type = 'whatsapp.outbound.message' THEN 1 ELSE 0 END) as outbound
    FROM communication_events
    WHERE DATE(created_at) = '2026-02-24'
    AND conversation_id IS NULL
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Total de eventos sem conversation_id hoje: {$stats['total']}\n";
echo "  - Inbound: {$stats['inbound']}\n";
echo "  - Outbound: {$stats['outbound']}\n\n";

// Busca o contact_external_id da conversa 459
echo "=== CONVERSA 459 ===\n\n";
$stmt = $db->prepare("SELECT contact_external_id FROM conversations WHERE id = 459");
$stmt->execute();
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

if ($conv) {
    $contactId = $conv['contact_external_id'];
    echo "Contact External ID: {$contactId}\n\n";
    
    // Busca eventos que deveriam estar vinculados a essa conversa
    echo "=== EVENTOS QUE DEVERIAM ESTAR VINCULADOS ===\n\n";
    $stmt = $db->prepare("
        SELECT id, event_id, event_type, conversation_id, created_at,
               JSON_EXTRACT(payload, '$.from') as msg_from,
               JSON_EXTRACT(payload, '$.message.from') as msg_from2,
               JSON_EXTRACT(payload, '$.to') as msg_to
        FROM communication_events
        WHERE DATE(created_at) = '2026-02-24'
        AND (
            JSON_EXTRACT(payload, '$.from') LIKE ?
            OR JSON_EXTRACT(payload, '$.message.from') LIKE ?
            OR JSON_EXTRACT(payload, '$.to') LIKE ?
        )
        ORDER BY created_at DESC
    ");
    
    // Busca por variações do telefone
    $patterns = [
        '%' . $contactId . '%',
        '%79023187173540%', // @lid
        '%555599235045%'    // E.164
    ];
    
    foreach ($patterns as $pattern) {
        $stmt->execute([$pattern, $pattern, $pattern]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($events) > 0) {
            echo "Padrão: {$pattern}\n";
            foreach ($events as $event) {
                $from = $event['msg_from'] ?: $event['msg_from2'];
                echo "  Event {$event['id']} | Conv ID: " . ($event['conversation_id'] ?: 'NULL') . " | From: {$from} | To: {$event['msg_to']} | {$event['created_at']}\n";
            }
            echo "\n";
            break;
        }
    }
}
