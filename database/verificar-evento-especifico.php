<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

$eventId = $argv[1] ?? 'de42a032-43af-41d2-a024-7de678010a75';

echo "=== VERIFICAÇÃO DO EVENTO ===\n\n";

try {
    $db = DB::getConnection();
    
    // Busca evento
    $stmt = $db->prepare("SELECT * FROM communication_events WHERE event_id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    
    if (!$event) {
        echo "❌ Evento não encontrado: {$eventId}\n";
        exit;
    }
    
    echo "EVENTO ENCONTRADO:\n";
    echo "  event_id:        {$event['event_id']}\n";
    echo "  event_type:      {$event['event_type']}\n";
    echo "  conversation_id: " . ($event['conversation_id'] ?? 'NULL') . "\n";
    echo "  tenant_id:       {$event['tenant_id']}\n";
    echo "  created_at:      {$event['created_at']}\n";
    
    $payload = json_decode($event['payload'], true);
    echo "\nPAYLOAD:\n";
    echo "  to:   " . ($payload['to'] ?? 'N/A') . "\n";
    echo "  from: " . ($payload['from'] ?? 'N/A') . "\n";
    echo "  type: " . ($payload['type'] ?? $payload['message']['type'] ?? 'N/A') . "\n";
    
    // Busca conversa
    echo "\n--- CONVERSA ---\n";
    if ($event['conversation_id']) {
        $stmt = $db->prepare("SELECT * FROM communication_conversations WHERE id = ?");
        $stmt->execute([$event['conversation_id']]);
        $conv = $stmt->fetch();
        
        if ($conv) {
            echo "CONVERSA ENCONTRADA:\n";
            echo "  id:                  {$conv['id']}\n";
            echo "  conversation_key:    {$conv['conversation_key']}\n";
            echo "  contact_external_id: {$conv['contact_external_id']}\n";
        } else {
            echo "❌ Conversa ID {$event['conversation_id']} não encontrada!\n";
        }
    } else {
        echo "⚠️ Evento sem conversation_id!\n";
    }
    
    // Busca conversa pela thread whatsapp_112
    echo "\n--- CONVERSA VIA THREAD whatsapp_112 ---\n";
    $threadId = 'whatsapp_112';
    if (preg_match('/^whatsapp_(\d+)$/', $threadId, $m)) {
        $convId = (int) $m[1];
        $stmt = $db->prepare("SELECT * FROM communication_conversations WHERE id = ?");
        $stmt->execute([$convId]);
        $conv = $stmt->fetch();
        
        if ($conv) {
            echo "CONVERSA {$convId}:\n";
            echo "  contact_external_id: {$conv['contact_external_id']}\n";
            
            // Normaliza para comparação
            $normalizedContact = preg_replace('/\D/', '', $conv['contact_external_id']);
            $normalizedTo = preg_replace('/\D/', '', $payload['to'] ?? '');
            
            echo "\nCOMPARAÇÃO:\n";
            echo "  contact normalizado: {$normalizedContact}\n";
            echo "  to normalizado:      {$normalizedTo}\n";
            echo "  São iguais?          " . ($normalizedContact === $normalizedTo ? 'SIM' : 'NÃO') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
