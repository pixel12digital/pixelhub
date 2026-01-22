<?php

/**
 * Script de diagnóstico: Verifica inbound do Charles
 * 
 * Objetivo: Provar se o webhook do Charles está chegando e processando
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
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
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== DIAGNÓSTICO: INBOUND CHARLES ===\n\n";

$db = DB::getConnection();

// Números do Charles (com e sem 9º dígito)
$charlesNumbers = ['554796164699', '5547996164699'];

echo "1. VERIFICANDO EVENTOS DO CHARLES EM communication_events:\n";
echo "   Buscando eventos com from contendo 554796164699 ou 5547996164699\n\n";

$stmt = $db->prepare("
    SELECT 
        event_id,
        event_type,
        tenant_id,
        status,
        created_at,
        JSON_EXTRACT(payload, '$.from') as from_field,
        JSON_EXTRACT(payload, '$.message.from') as message_from,
        JSON_EXTRACT(metadata, '$.channel_id') as channel_id_meta,
        JSON_EXTRACT(payload, '$.session.id') as session_id,
        JSON_EXTRACT(payload, '$.channel') as channel_payload
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    AND (
        JSON_EXTRACT(payload, '$.from') LIKE ?
        OR JSON_EXTRACT(payload, '$.from') LIKE ?
        OR JSON_EXTRACT(payload, '$.message.from') LIKE ?
        OR JSON_EXTRACT(payload, '$.message.from') LIKE ?
    )
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([
    '%554796164699%',
    '%5547996164699%',
    '%554796164699%',
    '%5547996164699%'
]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ NENHUM evento encontrado para o Charles!\n";
    echo "   ⚠️  Isso indica que o webhook NÃO está chegando ou não está sendo processado.\n\n";
} else {
    echo "   ✅ Encontrados " . count($events) . " evento(s) do Charles:\n\n";
    foreach ($events as $event) {
        echo "   - Event ID: {$event['event_id']}\n";
        echo "     Created: {$event['created_at']}\n";
        echo "     Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
        echo "     Status: {$event['status']}\n";
        echo "     From (payload): {$event['from_field']}\n";
        echo "     From (message): {$event['message_from']}\n";
        echo "     Channel ID (metadata): {$event['channel_id_meta']}\n";
        echo "     Session ID (payload): {$event['session_id']}\n";
        echo "     Channel (payload): {$event['channel_payload']}\n";
        echo "\n";
    }
}

echo "\n";

echo "2. VERIFICANDO CONVERSAS DO CHARLES:\n";
echo "   Buscando conversas com contact_external_id = 554796164699 ou 5547996164699\n\n";

foreach ($charlesNumbers as $number) {
    $stmt = $db->prepare("
        SELECT * FROM conversations 
        WHERE contact_external_id = ?
        ORDER BY last_message_at DESC
        LIMIT 1
    ");
    $stmt->execute([$number]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($conversation) {
        echo "   ✅ Conversa encontrada para {$number}:\n";
        echo "      - ID: {$conversation['id']}\n";
        echo "      - Key: {$conversation['conversation_key']}\n";
        echo "      - Contact: {$conversation['contact_external_id']}\n";
        echo "      - Channel ID: " . ($conversation['channel_id'] ?: 'NULL') . "\n";
        echo "      - Tenant ID: " . ($conversation['tenant_id'] ?: 'NULL') . "\n";
        echo "      - Last Message At: {$conversation['last_message_at']}\n";
        echo "      - Updated At: {$conversation['updated_at']}\n";
        echo "      - Message Count: {$conversation['message_count']}\n";
        echo "      - Unread Count: {$conversation['unread_count']}\n";
        echo "      - Status: {$conversation['status']}\n";
        echo "\n";
    } else {
        echo "   ❌ Nenhuma conversa encontrada para {$number}\n\n";
    }
}

echo "\n";

echo "3. COMPARANDO COM SERVPRO (que está funcionando):\n";
$stmt = $db->prepare("
    SELECT * FROM conversations 
    WHERE contact_external_id = '554796474223'
    ORDER BY last_message_at DESC
    LIMIT 1
");
$stmt->execute();
$servpro = $stmt->fetch(PDO::FETCH_ASSOC);

if ($servpro) {
    echo "   ✅ ServPro (funcionando):\n";
    echo "      - Last Message At: {$servpro['last_message_at']}\n";
    echo "      - Updated At: {$servpro['updated_at']}\n";
    echo "      - Message Count: {$servpro['message_count']}\n";
    echo "      - Unread Count: {$servpro['unread_count']}\n";
    echo "      - Channel ID: " . ($servpro['channel_id'] ?: 'NULL') . "\n";
    echo "      - Tenant ID: " . ($servpro['tenant_id'] ?: 'NULL') . "\n";
} else {
    echo "   ⚠️  ServPro não encontrado (pode ter sido deletado)\n";
}

echo "\n";

echo "4. ÚLTIMOS 5 EVENTOS DE QUALQUER NÚMERO (para comparação):\n";
$stmt = $db->query("
    SELECT 
        event_id,
        event_type,
        tenant_id,
        created_at,
        JSON_EXTRACT(payload, '$.from') as from_field,
        JSON_EXTRACT(metadata, '$.channel_id') as channel_id
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    ORDER BY created_at DESC
    LIMIT 5
");
$recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($recentEvents as $event) {
    $from = $event['from_field'] ? trim($event['from_field'], '"') : 'NULL';
    $isCharles = (strpos($from, '554796164699') !== false || strpos($from, '5547996164699') !== false);
    $marker = $isCharles ? ' 👤 CHARLES' : '';
    echo "   - {$event['created_at']} | From: {$from} | Channel: {$event['channel_id']}{$marker}\n";
}

echo "\n";

echo str_repeat("=", 60) . "\n";
echo "DIAGNÓSTICO COMPLETO\n";
echo str_repeat("=", 60) . "\n";
echo "\n";
echo "PRÓXIMOS PASSOS:\n";
echo "1. Se NENHUM evento do Charles foi encontrado:\n";
echo "   → O webhook não está chegando. Verificar gateway/logs do gateway.\n";
echo "\n";
echo "2. Se eventos existem mas conversa não atualiza:\n";
echo "   → Problema em resolveConversation() ou updateConversationMetadata()\n";
echo "   → Verificar logs [CONVERSATION UPSERT] no error_log do PHP\n";
echo "\n";
echo "3. Se conversa atualiza mas UI não mostra:\n";
echo "   → Problema em check-updates ou ordenação da lista\n";
echo "   → Verificar endpoint /communication-hub/check-updates\n";
echo "\n";

