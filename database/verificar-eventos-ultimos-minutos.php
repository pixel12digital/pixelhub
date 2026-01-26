<?php

/**
 * Script para verificar eventos dos últimos minutos em tempo real
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();

echo "=== Monitoramento em Tempo Real ===\n";
echo "Pressione Ctrl+C para parar\n\n";

$lastEventId = null;

while (true) {
    // Busca eventos muito recentes (últimos 30 segundos)
    $stmt = $db->prepare("
        SELECT 
            ce.id,
            ce.event_id,
            ce.event_type,
            ce.status,
            ce.created_at,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as text,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as from_field,
            JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as channel_id
        FROM communication_events ce
        WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
        " . ($lastEventId ? "AND ce.id > {$lastEventId}" : "") . "
        ORDER BY ce.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($events)) {
        foreach ($events as $event) {
            $time = date('H:i:s', strtotime($event['created_at']));
            $text = substr(($event['text'] ?: 'NULL'), 0, 50);
            echo "[{$time}] ✅ NOVO EVENTO: {$text} | Status: {$event['status']} | Channel: " . ($event['channel_id'] ?: 'NULL') . "\n";
            
            if ($lastEventId === null || $event['id'] > $lastEventId) {
                $lastEventId = $event['id'];
            }
        }
    }
    
    sleep(5); // Verifica a cada 5 segundos
}

