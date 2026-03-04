<?php
/**
 * Script para monitorar recebimento de mensagens via Meta webhook
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== MONITORAMENTO DE MENSAGENS META ===\n\n";
echo "Aguardando mensagens...\n";
echo "Envie uma mensagem do seu celular para +55 47 9647-4223\n\n";

$db = DB::getConnection();
$lastCheck = time();

// Busca últimas mensagens recebidas
echo "Últimas mensagens nos últimos 5 minutos:\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->query("
    SELECT 
        wrl.id,
        wrl.event_type,
        wrl.created_at,
        JSON_EXTRACT(wrl.payload_json, '$.entry[0].changes[0].value.messages[0].from') as phone_from,
        JSON_EXTRACT(wrl.payload_json, '$.entry[0].changes[0].value.messages[0].text.body') as message_text,
        wrl.processed
    FROM webhook_raw_logs wrl
    WHERE wrl.event_type LIKE 'meta_%'
    AND wrl.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY wrl.created_at DESC
    LIMIT 10
");

$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($messages)) {
    echo "❌ Nenhuma mensagem recebida nos últimos 5 minutos\n\n";
    echo "Verifique:\n";
    echo "1. Webhook está configurado no Meta Business Suite?\n";
    echo "2. Campos 'messages' estão inscritos?\n";
    echo "3. Número está ativo e registrado?\n";
} else {
    echo "✅ " . count($messages) . " mensagem(ns) encontrada(s):\n\n";
    
    foreach ($messages as $msg) {
        $phone = trim($msg['phone_from'], '"');
        $text = trim($msg['message_text'], '"');
        $processed = $msg['processed'] ? 'SIM' : 'NÃO';
        
        echo "ID: {$msg['id']}\n";
        echo "De: {$phone}\n";
        echo "Mensagem: {$text}\n";
        echo "Processado: {$processed}\n";
        echo "Data: {$msg['created_at']}\n";
        echo str_repeat('-', 80) . "\n";
    }
}

// Verifica eventos ingeridos
echo "\nEventos ingeridos (communication_events):\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->query("
    SELECT 
        ce.id,
        ce.event_type,
        ce.contact_external_id,
        ce.message_body,
        ce.created_at
    FROM communication_events ce
    WHERE ce.source_system = 'meta_official'
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY ce.created_at DESC
    LIMIT 10
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ Nenhum evento ingerido nos últimos 5 minutos\n";
} else {
    echo "✅ " . count($events) . " evento(s) encontrado(s):\n\n";
    
    foreach ($events as $event) {
        echo "ID: {$event['id']}\n";
        echo "Tipo: {$event['event_type']}\n";
        echo "Contato: {$event['contact_external_id']}\n";
        echo "Mensagem: " . substr($event['message_body'], 0, 100) . "\n";
        echo "Data: {$event['created_at']}\n";
        echo str_repeat('-', 80) . "\n";
    }
}

// Verifica conversas criadas
echo "\nConversas no Inbox:\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->query("
    SELECT 
        c.id,
        c.lead_id,
        c.status,
        c.last_message_at,
        l.name as lead_name,
        l.phone as lead_phone
    FROM conversations c
    LEFT JOIN leads l ON c.lead_id = l.id
    WHERE c.updated_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY c.updated_at DESC
    LIMIT 5
");

$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "❌ Nenhuma conversa atualizada nos últimos 5 minutos\n";
} else {
    echo "✅ " . count($conversations) . " conversa(s) encontrada(s):\n\n";
    
    foreach ($conversations as $conv) {
        echo "ID: {$conv['id']}\n";
        echo "Lead: {$conv['lead_name']} ({$conv['lead_phone']})\n";
        echo "Status: {$conv['status']}\n";
        echo "Última mensagem: {$conv['last_message_at']}\n";
        echo str_repeat('-', 80) . "\n";
    }
}

echo "\n=== FIM ===\n";
