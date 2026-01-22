<?php
/**
 * Verifica o evento mais recente do ServPro
 */

// Autoloader simples
spl_autoload_register(function ($class) {
    $prefix = 'PixelHub\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../');
$db = DB::getConnection();

echo "=== EVENTOS RECENTES DO SERVPRO ===\n\n";

// Busca eventos dos últimos 30 minutos
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.status,
        ce.processed_at,
        SUBSTRING(ce.payload, 1, 200) as payload_preview
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND (
        ce.payload LIKE '%554796474223%'
        OR ce.payload LIKE '%10523374551225@lid%'
        OR ce.payload LIKE '%ServPro%'
    )
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ORDER BY ce.created_at DESC
    LIMIT 5
");

$stmt->execute();
$events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ Nenhum evento recente encontrado.\n";
    exit(1);
}

echo "📋 EVENTOS ENCONTRADOS: " . count($events) . "\n\n";

foreach ($events as $event) {
    echo "event_id: {$event['event_id']}\n";
    echo "event_type: {$event['event_type']}\n";
    echo "created_at: {$event['created_at']}\n";
    echo "status: {$event['status']}\n";
    echo "processed_at: " . ($event['processed_at'] ?: 'NULL') . "\n";
    
    if ($event['status'] === 'queued') {
        echo "⚠️  STATUS: QUEUED (não processado)\n";
    } elseif ($event['status'] === 'processed') {
        echo "✅ STATUS: PROCESSED\n";
    }
    
    echo "\n";
}

// Verifica estado atual da conversa
echo "📋 ESTADO ATUAL DA CONVERSA:\n";
$stmt = $db->prepare("
    SELECT 
        id,
        last_message_at,
        unread_count,
        last_message_direction,
        updated_at
    FROM conversations
    WHERE contact_external_id = '554796474223'
    OR conversation_key LIKE '%554796474223%'
    ORDER BY last_message_at DESC
    LIMIT 1
");
$stmt->execute();
$conv = $stmt->fetch(\PDO::FETCH_ASSOC);

if ($conv) {
    echo "conversation_id: {$conv['id']}\n";
    echo "last_message_at: {$conv['last_message_at']}\n";
    echo "unread_count: {$conv['unread_count']}\n";
    echo "last_message_direction: {$conv['last_message_direction']}\n";
    echo "updated_at: {$conv['updated_at']}\n";
} else {
    echo "❌ Conversa não encontrada\n";
}

