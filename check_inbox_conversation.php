<?php
/**
 * Script para verificar se a conversa Meta apareceu no Inbox
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== VERIFICAR CONVERSA NO INBOX ===\n\n";

$db = DB::getConnection();

// Busca conversas criadas nos últimos 10 minutos
echo "1. Buscando conversas recentes (últimos 10 minutos)...\n\n";

$stmt = $db->query("
    SELECT 
        c.id,
        c.lead_id,
        c.status,
        c.last_message_at,
        c.created_at,
        l.name as lead_name,
        l.phone as lead_phone
    FROM conversations c
    LEFT JOIN leads l ON c.lead_id = l.id
    WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ORDER BY c.created_at DESC
    LIMIT 10
");

$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "❌ Nenhuma conversa criada nos últimos 10 minutos\n\n";
    echo "Isso significa que o EventIngestionService não criou a conversa.\n";
} else {
    echo "✅ " . count($conversations) . " conversa(s) encontrada(s):\n\n";
    
    foreach ($conversations as $conv) {
        echo "ID: {$conv['id']}\n";
        echo "Lead: {$conv['lead_name']} ({$conv['lead_phone']})\n";
        echo "Status: {$conv['status']}\n";
        echo "Criada em: {$conv['created_at']}\n";
        echo "Última mensagem: {$conv['last_message_at']}\n";
        echo str_repeat('-', 80) . "\n";
    }
}

// Busca eventos de comunicação Meta
echo "\n2. Buscando eventos de comunicação Meta...\n\n";

$stmt = $db->query("
    SELECT 
        ce.id,
        ce.event_type,
        ce.source_system,
        ce.message_body,
        ce.created_at
    FROM communication_events ce
    WHERE ce.source_system = 'meta_official'
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ORDER BY ce.created_at DESC
    LIMIT 10
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ Nenhum evento Meta encontrado\n";
} else {
    echo "✅ " . count($events) . " evento(s) Meta encontrado(s):\n\n";
    
    foreach ($events as $event) {
        echo "ID: {$event['id']}\n";
        echo "Tipo: {$event['event_type']}\n";
        echo "Mensagem: " . substr($event['message_body'], 0, 100) . "\n";
        echo "Data: {$event['created_at']}\n";
        echo str_repeat('-', 80) . "\n";
    }
}

echo "\n=== FIM ===\n";
