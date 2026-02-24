<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== CORRIGINDO conversation_id PARA EVENTO DO ÁUDIO ===\n\n";

// Evento do áudio
$eventId = 190866;
$conversationId = 459;

// Verifica estado atual
$stmt = $db->prepare("
    SELECT id, event_id, conversation_id, event_type, created_at,
           JSON_EXTRACT(payload, '$.from') as msg_from,
           JSON_EXTRACT(payload, '$.message.from') as msg_from2
    FROM communication_events 
    WHERE id = ?
");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "✗ Evento {$eventId} não encontrado!\n";
    exit(1);
}

echo "Estado ANTES da correção:\n";
echo "  Event ID: {$event['event_id']}\n";
echo "  Conversation ID: " . ($event['conversation_id'] ?: 'NULL') . "\n";
echo "  From: " . ($event['msg_from'] ?: $event['msg_from2']) . "\n";
echo "  Criado: {$event['created_at']}\n\n";

// Atualiza conversation_id
$updateStmt = $db->prepare("
    UPDATE communication_events 
    SET conversation_id = ? 
    WHERE id = ?
");

$result = $updateStmt->execute([$conversationId, $eventId]);

if ($result) {
    echo "✓ conversation_id atualizado com sucesso!\n\n";
    
    // Verifica resultado
    $stmt->execute([$eventId]);
    $eventAfter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Estado DEPOIS da correção:\n";
    echo "  Event ID: {$eventAfter['event_id']}\n";
    echo "  Conversation ID: " . ($eventAfter['conversation_id'] ?: 'NULL') . "\n";
    echo "  From: " . ($eventAfter['msg_from'] ?: $eventAfter['msg_from2']) . "\n\n";
} else {
    echo "✗ Erro ao atualizar conversation_id\n";
    exit(1);
}

// Verifica se há outros eventos sem conversation_id que deveriam estar vinculados
echo "=== VERIFICANDO OUTROS EVENTOS SEM conversation_id ===\n\n";

$stmt = $db->prepare("
    SELECT ce.id, ce.event_id, ce.event_type, ce.created_at,
           JSON_EXTRACT(ce.payload, '$.from') as msg_from,
           JSON_EXTRACT(ce.payload, '$.message.from') as msg_from2,
           JSON_EXTRACT(ce.payload, '$.to') as msg_to
    FROM communication_events ce
    WHERE ce.conversation_id IS NULL
    AND DATE(ce.created_at) = '2026-02-24'
    AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY ce.created_at DESC
");
$stmt->execute();
$orphanEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos órfãos hoje: " . count($orphanEvents) . "\n\n";

if (count($orphanEvents) > 0) {
    echo "Eventos que precisam ser vinculados:\n";
    foreach ($orphanEvents as $orphan) {
        $from = $orphan['msg_from'] ?: $orphan['msg_from2'];
        echo "  Event {$orphan['id']} | Tipo: {$orphan['event_type']} | From: {$from} | To: {$orphan['msg_to']} | {$orphan['created_at']}\n";
    }
    echo "\n";
    echo "⚠️ Estes eventos não aparecerão no Inbox até serem vinculados a conversas.\n";
}

echo "\n✓ Correção concluída! Recarregue o Inbox para ver o áudio.\n";
