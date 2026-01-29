<?php
/**
 * Script para reprocessar mídia do evento 87753 (áudio de 554796164699)
 * Rode após deploy da correção no WhatsAppMediaService
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Services\WhatsAppMediaService;

Env::load(__DIR__ . '/../.env');

echo "=== REPROCESSAR ÁUDIO EVENTO 87753 ===\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 60) . "\n\n";

try {
    $db = DB::getConnection();
    
    // Busca o evento
    $stmt = $db->query("SELECT * FROM communication_events WHERE id = 87753");
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        echo "❌ Evento 87753 não encontrado!\n";
        exit(1);
    }
    
    echo "Evento encontrado:\n";
    echo "  event_id: {$event['event_id']}\n";
    echo "  event_type: {$event['event_type']}\n";
    echo "  created_at: {$event['created_at']}\n\n";
    
    // Verifica se já existe mídia
    $stmt2 = $db->prepare("SELECT * FROM communication_media WHERE event_id = ?");
    $stmt2->execute([$event['event_id']]);
    $existingMedia = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    if ($existingMedia && !empty($existingMedia['stored_path'])) {
        echo "⚠️ Mídia já existe para este evento:\n";
        echo "  id: {$existingMedia['id']}\n";
        echo "  type: {$existingMedia['media_type']}\n";
        echo "  path: {$existingMedia['stored_path']}\n\n";
        echo "Se quiser reprocessar, delete o registro primeiro:\n";
        echo "  DELETE FROM communication_media WHERE event_id = '{$event['event_id']}';\n";
        exit(0);
    }
    
    echo "Processando mídia...\n\n";
    
    // Chama processMediaFromEvent
    $result = WhatsAppMediaService::processMediaFromEvent($event);
    
    if ($result) {
        echo "✅ SUCESSO! Mídia processada:\n";
        print_r($result);
    } else {
        echo "❌ FALHA: processMediaFromEvent retornou null.\n";
        echo "Verifique os logs (error_log) para mais detalhes.\n";
    }
    
} catch (Throwable $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== FIM ===\n";
