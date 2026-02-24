<?php
// Script para reprocessar eventos travados em 'processing'
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Config\Database;
use PixelHub\Core\Env;
use PixelHub\Services\ConversationService;

Env::load(__DIR__);
$db = Database::getInstance()->getConnection();

echo "=== REPROCESSAMENTO DE EVENTOS TRAVADOS ===\n\n";

// 1. Buscar eventos em 'processing'
echo "--- BUSCANDO EVENTOS TRAVADOS ---\n";
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

echo "Total de eventos travados: " . count($stuckEvents) . "\n\n";

if (count($stuckEvents) === 0) {
    echo "Nenhum evento travado encontrado.\n";
    exit(0);
}

// 2. Processar cada evento
foreach ($stuckEvents as $event) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Processando evento ID: {$event['id']} | Event ID: {$event['event_id']}\n";
    echo "Tipo: {$event['event_type']} | Criado: {$event['created_at']}\n";
    echo "Tenant: " . ($event['tenant_id'] ?? 'NULL') . "\n\n";
    
    $payload = json_decode($event['payload'], true);
    $metadata = $event['metadata'] ? json_decode($event['metadata'], true) : null;
    
    // Extrai informações do contato
    $from = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
    $to = $payload['to'] ?? $payload['message']['to'] ?? 'N/A';
    
    echo "From: $from\n";
    echo "To: $to\n\n";
    
    // Tenta resolver conversa
    try {
        echo "Tentando resolver conversa...\n";
        
        $conversation = ConversationService::resolveConversation([
            'event_type' => $event['event_type'],
            'source_system' => $event['source_system'],
            'tenant_id' => $event['tenant_id'],
            'payload' => $payload,
            'metadata' => $metadata,
        ]);
        
        if ($conversation) {
            echo "✓ Conversa resolvida: ID {$conversation['id']}\n";
            echo "  Conversation Key: {$conversation['conversation_key']}\n";
            echo "  Tenant: " . ($conversation['tenant_id'] ?? 'NULL (não vinculada)') . "\n";
            
            // Atualiza evento com conversation_id
            $updateStmt = $db->prepare("
                UPDATE communication_events 
                SET conversation_id = ?, status = 'processed', processed_at = NOW()
                WHERE event_id = ?
            ");
            $updateStmt->execute([$conversation['id'], $event['event_id']]);
            
            echo "✓ Evento atualizado para 'processed'\n";
        } else {
            echo "✗ Conversa não pôde ser resolvida\n";
            
            // Marca como failed
            $updateStmt = $db->prepare("
                UPDATE communication_events 
                SET status = 'failed', error_message = 'conversation_not_resolved_manual_retry', processed_at = NOW()
                WHERE event_id = ?
            ");
            $updateStmt->execute([$event['event_id']]);
            
            echo "✗ Evento marcado como 'failed'\n";
        }
        
    } catch (Exception $e) {
        echo "✗ ERRO ao processar: " . $e->getMessage() . "\n";
        
        // Marca como failed com erro
        $updateStmt = $db->prepare("
            UPDATE communication_events 
            SET status = 'failed', error_message = ?, processed_at = NOW()
            WHERE event_id = ?
        ");
        $updateStmt->execute([
            'exception: ' . substr($e->getMessage(), 0, 200),
            $event['event_id']
        ]);
    }
    
    echo "\n";
}

echo "=== REPROCESSAMENTO CONCLUÍDO ===\n";

// 3. Verificar conversas não vinculadas
echo "\n--- CONVERSAS NÃO VINCULADAS ---\n";
$stmt = $db->query("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        contact_name,
        last_message_at,
        unread_count
    FROM conversations
    WHERE tenant_id IS NULL
    ORDER BY last_message_at DESC
    LIMIT 10
");
$unlinkedConversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de conversas não vinculadas: " . count($unlinkedConversations) . "\n\n";

foreach ($unlinkedConversations as $conv) {
    echo sprintf(
        "ID: %d | Contato: %s | Nome: %s | Última msg: %s | Não lidas: %d\n",
        $conv['id'],
        $conv['contact_external_id'],
        $conv['contact_name'] ?? 'N/A',
        $conv['last_message_at'],
        $conv['unread_count']
    );
}
