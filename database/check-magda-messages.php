<?php

/**
 * Script para verificar mensagens da Magda que não aparecem na thread
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== VERIFICANDO MENSAGENS DA MAGDA ===\n\n";

$magdaPhone = '5511940863773';
$conversationId = 5; // ID da conversa da Magda

// 1. Verifica conversa
echo "1. Verificando conversa ID {$conversationId}...\n";
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        contact_name,
        tenant_id,
        channel_id,
        remote_key,
        message_count,
        last_message_at
    FROM conversations
    WHERE id = ?
");
$stmt->execute([$conversationId]);
$conversation = $stmt->fetch();

if ($conversation) {
    echo "   Conversa encontrada:\n";
    echo "     - ID: {$conversation['id']}\n";
    echo "     - Key: {$conversation['conversation_key']}\n";
    echo "     - Contact: {$conversation['contact_external_id']}\n";
    echo "     - Nome: {$conversation['contact_name']}\n";
    echo "     - Tenant: " . ($conversation['tenant_id'] ?: 'NULL') . "\n";
    echo "     - Channel ID: " . ($conversation['channel_id'] ?: 'NULL') . "\n";
    echo "     - Remote Key: " . ($conversation['remote_key'] ?: 'NULL') . "\n";
    echo "     - Mensagens: {$conversation['message_count']}\n";
    echo "     - Última mensagem: " . ($conversation['last_message_at'] ?: 'NULL') . "\n";
} else {
    echo "   ❌ Conversa não encontrada!\n";
    exit(1);
}

echo "\n";

// 2. Busca eventos recentes com o número da Magda
echo "2. Buscando eventos recentes com número {$magdaPhone}...\n";
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.tenant_id,
        JSON_EXTRACT(ce.payload, '$.from') as from_field,
        JSON_EXTRACT(ce.payload, '$.message.from') as message_from,
        JSON_EXTRACT(ce.payload, '$.to') as to_field,
        JSON_EXTRACT(ce.payload, '$.message.to') as message_to,
        JSON_EXTRACT(ce.payload, '$.text') as text,
        JSON_EXTRACT(ce.payload, '$.message.text') as message_text
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
      AND (
          JSON_EXTRACT(ce.payload, '$.from') LIKE ?
          OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE ?
          OR JSON_EXTRACT(ce.payload, '$.to') LIKE ?
          OR JSON_EXTRACT(ce.payload, '$.message.to') LIKE ?
      )
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$pattern = "%{$magdaPhone}%";
$stmt->execute([$pattern, $pattern, $pattern, $pattern]);
$events = $stmt->fetchAll();

echo "   Encontrados " . count($events) . " eventos:\n\n";
foreach ($events as $event) {
    $from = trim($event['from_field'] ?? $event['message_from'] ?? '', '"');
    $to = trim($event['to_field'] ?? $event['message_to'] ?? '', '"');
    $text = trim($event['text'] ?? $event['message_text'] ?? '', '"');
    $direction = $event['event_type'] === 'whatsapp.inbound.message' ? 'INBOUND' : 'OUTBOUND';
    
    echo "   - {$direction} | {$event['created_at']}\n";
    echo "     From: {$from}\n";
    echo "     To: {$to}\n";
    echo "     Text: " . substr($text, 0, 100) . (strlen($text) > 100 ? '...' : '') . "\n";
    echo "     Tenant: " . ($event['tenant_id'] ?: 'NULL') . "\n";
    echo "\n";
}

echo "\n";

// 3. Verifica se há eventos com variações do número
echo "3. Verificando variações do número...\n";
$variations = [
    $magdaPhone,
    $magdaPhone . '@c.us',
    substr($magdaPhone, 0, 4) . '9' . substr($magdaPhone, 4), // Com 9º dígito
];

foreach ($variations as $variation) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM communication_events
        WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
          AND (
              JSON_EXTRACT(payload, '$.from') LIKE ?
              OR JSON_EXTRACT(payload, '$.message.from') LIKE ?
          )
    ");
    $pattern = "%{$variation}%";
    $stmt->execute([$pattern, $pattern]);
    $count = $stmt->fetchColumn();
    echo "   - {$variation}: {$count} eventos\n";
}

echo "\n";

// 4. Verifica mapeamento @lid
echo "4. Verificando mapeamento @lid...\n";
$stmt = $db->prepare("
    SELECT business_id, phone_number
    FROM whatsapp_business_ids
    WHERE phone_number = ?
");
$stmt->execute([$magdaPhone]);
$mappings = $stmt->fetchAll();

if (count($mappings) > 0) {
    echo "   Mapeamentos encontrados:\n";
    foreach ($mappings as $m) {
        echo "     - business_id: {$m['business_id']}, phone: {$m['phone_number']}\n";
        
        // Busca eventos com esse @lid
        $lidStmt = $db->prepare("
            SELECT COUNT(*) as total
            FROM communication_events
            WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
              AND (
                  JSON_EXTRACT(payload, '$.from') LIKE ?
                  OR JSON_EXTRACT(payload, '$.message.from') LIKE ?
              )
        ");
        $lidPattern = "%{$m['business_id']}%";
        $lidStmt->execute([$lidPattern, $lidPattern]);
        $lidCount = $lidStmt->fetchColumn();
        echo "       Eventos com esse @lid: {$lidCount}\n";
    }
} else {
    echo "   Nenhum mapeamento encontrado\n";
}

echo "\n";







