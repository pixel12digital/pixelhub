<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

// Carrega .env
Env::load();

// Conexão via classe DB do projeto
$db = DB::getConnection();

echo "=== INVESTIGANDO CONTATO DESCONHECIDO ===\n\n";

// 1. Busca conversas recentes sem nome de contato
$stmt = $db->query("
    SELECT 
        c.id,
        c.conversation_key,
        c.channel_type,
        c.channel_account_id,
        c.contact_external_id,
        c.contact_name,
        c.tenant_id,
        c.created_at,
        c.updated_at,
        t.name as tenant_name
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.contact_name IS NULL 
       OR c.contact_name = ''
       OR c.contact_name = 'Contato Desconhecido'
    ORDER BY c.updated_at DESC
    LIMIT 5
");

$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Conversas sem nome de contato:\n";
foreach ($conversations as $conv) {
    echo "\n--- Conversa ID: {$conv['id']} ---\n";
    echo "Tenant: {$conv['tenant_name']} (ID: {$conv['tenant_id']})\n";
    echo "Canal: {$conv['channel_type']} - {$conv['channel_account_id']}\n";
    echo "Contact External ID: {$conv['contact_external_id']}\n";
    echo "Criado: {$conv['created_at']}\n";
    echo "Atualizado: {$conv['updated_at']}\n";
    
    // Busca eventos dessa conversa
    $eventsStmt = $db->prepare("
        SELECT 
            event_id,
            event_type,
            source_system,
            created_at,
            payload
        FROM communication_events
        WHERE conversation_id = ?
        ORDER BY created_at ASC
        LIMIT 10
    ");
    $eventsStmt->execute([$conv['id']]);
    $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nEventos (total: " . count($events) . "):\n";
    foreach ($events as $event) {
        $payload = json_decode($event['payload'], true);
        $from = $payload['from'] ?? 'N/A';
        $to = $payload['to'] ?? 'N/A';
        $body = $payload['body'] ?? '';
        $type = $payload['type'] ?? 'N/A';
        
        echo "  - {$event['created_at']} | {$event['event_type']} | source: {$event['source_system']}\n";
        echo "    From: {$from} | To: {$to}\n";
        if ($body) {
            echo "    Body: " . substr($body, 0, 100) . "\n";
        }
        echo "    Type: {$type}\n";
    }
    
    // Mostra o payload completo do primeiro evento
    if (!empty($events)) {
        echo "\n  PAYLOAD COMPLETO DO PRIMEIRO EVENTO:\n";
        echo "  " . json_encode(json_decode($events[0]['payload'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    
    echo "\n" . str_repeat("-", 80) . "\n";
}
