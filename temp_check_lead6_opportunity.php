<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== INVESTIGANDO ORIGEM DO LEAD #6 ===\n\n";

// 1. Busca oportunidade vinculada ao Lead #6
$stmt = $db->prepare("
    SELECT 
        o.id,
        o.name,
        o.lead_id,
        o.value,
        o.stage,
        o.created_at,
        o.source
    FROM opportunities o
    WHERE o.lead_id = 6
");
$stmt->execute();
$opportunities = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Oportunidades do Lead #6:\n";
foreach ($opportunities as $opp) {
    echo "  ID: {$opp['id']}\n";
    echo "  Nome: " . ($opp['name'] ?: 'NULL') . "\n";
    echo "  Valor: " . ($opp['value'] ?: 'NULL') . "\n";
    echo "  Stage: {$opp['stage']}\n";
    echo "  Source: " . ($opp['source'] ?: 'NULL') . "\n";
    echo "  Criado: {$opp['created_at']}\n\n";
}

// 2. Busca informações completas do Lead #6
$stmt = $db->prepare("SELECT * FROM leads WHERE id = 6");
$stmt->execute();
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Informações completas do Lead #6:\n";
foreach ($lead as $key => $value) {
    if ($value !== null && $value !== '') {
        echo "  {$key}: {$value}\n";
    }
}

echo "\n";

// 3. Busca todos os payloads de eventos para análise
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.source_system,
        ce.created_at,
        ce.payload
    FROM communication_events ce
    INNER JOIN conversations c ON ce.conversation_id = c.id
    WHERE c.lead_id = 6
    ORDER BY ce.created_at ASC
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Análise detalhada dos eventos:\n\n";
foreach ($events as $event) {
    $payload = json_decode($event['payload'], true);
    
    echo "Evento: {$event['event_id']}\n";
    echo "  Tipo: {$event['event_type']}\n";
    echo "  Source: {$event['source_system']}\n";
    echo "  Data: {$event['created_at']}\n";
    echo "  To: " . ($payload['to'] ?? 'N/A') . "\n";
    
    // Extrai texto da mensagem
    $text = $payload['text'] ?? $payload['body'] ?? $payload['message']['text'] ?? '';
    if ($text) {
        echo "  Mensagem: " . substr($text, 0, 150) . "...\n";
    }
    
    echo "\n";
}
