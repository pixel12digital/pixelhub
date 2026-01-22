<?php
/**
 * Valida se a conversa do ServPro foi atualizada corretamente
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

echo "=== VALIDAÇÃO: Conversa ServPro ===\n\n";

// Busca a conversa do ServPro
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        contact_name,
        tenant_id,
        last_message_at,
        last_message_direction,
        unread_count,
        message_count,
        updated_at,
        created_at
    FROM conversations
    WHERE contact_external_id = '554796474223'
    OR conversation_key LIKE '%554796474223%'
    ORDER BY last_message_at DESC
    LIMIT 1
");

$stmt->execute();
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    echo "❌ Conversa do ServPro não encontrada no banco!\n";
    exit(1);
}

echo "📋 CONVERSA ENCONTRADA:\n";
echo "   conversation_id: {$conversation['id']}\n";
echo "   conversation_key: {$conversation['conversation_key']}\n";
echo "   contact_external_id: {$conversation['contact_external_id']}\n";
echo "   contact_name: " . ($conversation['contact_name'] ?: 'NULL') . "\n";
echo "   tenant_id: " . ($conversation['tenant_id'] ?: 'NULL') . "\n";
echo "   message_count: {$conversation['message_count']}\n";
echo "   updated_at: {$conversation['updated_at']}\n";
echo "   created_at: {$conversation['created_at']}\n\n";

echo "🔍 VALIDAÇÃO DOS CAMPOS CRÍTICOS:\n\n";

// 1. last_message_at
$lastMessageAt = $conversation['last_message_at'];
$now = new DateTime();
$lastMessageAtDt = $lastMessageAt ? new DateTime($lastMessageAt) : null;

echo "1️⃣  last_message_at:\n";
if ($lastMessageAt) {
    echo "   Valor: {$lastMessageAt}\n";
    if ($lastMessageAtDt) {
        $diffMinutes = ($now->getTimestamp() - $lastMessageAtDt->getTimestamp()) / 60;
        echo "   Diferença do horário atual: " . round($diffMinutes, 1) . " minutos\n";
        
        // Verifica se é recente (últimos 30 minutos)
        if ($diffMinutes <= 30) {
            echo "   ✅ RECENTE (últimos 30 minutos)\n";
        } else {
            echo "   ❌ ANTIGO (mais de 30 minutos)\n";
        }
    }
} else {
    echo "   ❌ NULL - não foi atualizado!\n";
}

// 2. unread_count
$unreadCount = (int) $conversation['unread_count'];
echo "\n2️⃣  unread_count:\n";
echo "   Valor: {$unreadCount}\n";
if ($unreadCount > 0) {
    echo "   ✅ Incrementado (> 0)\n";
} else {
    echo "   ❌ Não incrementou (= 0)\n";
}

// 3. last_message_direction
$direction = $conversation['last_message_direction'];
echo "\n3️⃣  last_message_direction:\n";
echo "   Valor: " . ($direction ?: 'NULL') . "\n";
if ($direction === 'inbound') {
    echo "   ✅ Correto (inbound)\n";
} else {
    echo "   ❌ Incorreto (esperado: inbound, recebido: " . ($direction ?: 'NULL') . ")\n";
}

// Busca evento mais recente do ServPro
echo "\n📋 EVENTO MAIS RECENTE DO SERVPRO:\n";
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.status,
        ce.processed_at
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND (
        ce.payload LIKE '%554796474223%'
        OR ce.payload LIKE '%10523374551225@lid%'
        OR ce.payload LIKE '%TESTE SERVPRO%'
    )
    ORDER BY ce.created_at DESC
    LIMIT 1
");

$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if ($event) {
    echo "   event_id: {$event['event_id']}\n";
    echo "   event_type: {$event['event_type']}\n";
    echo "   created_at: {$event['created_at']}\n";
    echo "   status: {$event['status']}\n";
    echo "   processed_at: " . ($event['processed_at'] ?: 'NULL') . "\n";
    
    // Compara timestamps
    if ($lastMessageAt && $event['created_at']) {
        $eventDt = new DateTime($event['created_at']);
        $diffSeconds = abs($lastMessageAtDt->getTimestamp() - $eventDt->getTimestamp());
        
        echo "\n   📊 COMPARAÇÃO:\n";
        echo "   last_message_at: {$lastMessageAt}\n";
        echo "   event.created_at: {$event['created_at']}\n";
        echo "   Diferença: " . round($diffSeconds, 1) . " segundos\n";
        
        if ($diffSeconds <= 60) {
            echo "   ✅ Timestamps próximos (diferença <= 60s)\n";
        } else {
            echo "   ⚠️  Timestamps distantes (diferença > 60s)\n";
        }
    }
} else {
    echo "   ❌ Nenhum evento recente encontrado!\n";
}

// Resumo final
echo "\n=== RESUMO DA VALIDAÇÃO ===\n";
$allOk = true;

if (!$lastMessageAt || ($lastMessageAtDt && ($now->getTimestamp() - $lastMessageAtDt->getTimestamp()) > 1800)) {
    echo "❌ last_message_at: NÃO atualizado ou muito antigo\n";
    $allOk = false;
} else {
    echo "✅ last_message_at: Atualizado corretamente\n";
}

if ($unreadCount === 0) {
    echo "❌ unread_count: NÃO incrementou\n";
    $allOk = false;
} else {
    echo "✅ unread_count: Incrementado ({$unreadCount})\n";
}

if ($direction !== 'inbound') {
    echo "❌ last_message_direction: NÃO é 'inbound' ({$direction})\n";
    $allOk = false;
} else {
    echo "✅ last_message_direction: Correto (inbound)\n";
}

echo "\n" . ($allOk ? "✅ TODAS AS VALIDAÇÕES PASSARAM" : "❌ ALGUMAS VALIDAÇÕES FALHARAM") . "\n";

