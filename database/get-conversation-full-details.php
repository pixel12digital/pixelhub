<?php
/**
 * Script para buscar detalhes completos de uma conversa
 * 
 * Uso: php database/get-conversation-full-details.php [conversation_id]
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

$conversationId = $argv[1] ?? 80;

echo "=== DETALHES COMPLETOS DA CONVERSA ===\n\n";
echo "Conversation ID: {$conversationId}\n\n";

// 1. Informações da conversa
echo "1. INFORMAÇÕES DA CONVERSA:\n";
echo str_repeat("=", 70) . "\n";

$stmt = $db->prepare("
    SELECT 
        c.*,
        t.name as tenant_name,
        t.phone as tenant_phone,
        t.email as tenant_email,
        t.cpf_cnpj as tenant_cpf_cnpj
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.id = ?
");
$stmt->execute([$conversationId]);
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    die("❌ Conversa não encontrada!\n");
}

foreach ($conversation as $key => $value) {
    if ($value !== null) {
        echo sprintf("%-25s: %s\n", $key, $value);
    }
}
echo "\n";

// 2. Buscar mapeamento LID se existir
$contactId = $conversation['contact_external_id'];
$normalizedContact = preg_replace('/@.*$/', '', $contactId);
$normalizedContact = preg_replace('/[^0-9]/', '', $normalizedContact);

if (strpos($contactId, '@lid') !== false) {
    echo "2. MAPEAMENTO LID:\n";
    echo str_repeat("=", 70) . "\n";
    
    $lidNumber = str_replace('@lid', '', $contactId);
    
    $stmt = $db->prepare("
        SELECT *
        FROM whatsapp_business_ids
        WHERE business_id = ?
        OR business_id LIKE ?
        OR phone_number = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $lidPattern = "%{$lidNumber}%";
    $stmt->execute([$lidNumber, $lidPattern, $normalizedContact]);
    $lidMappings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($lidMappings)) {
        foreach ($lidMappings as $mapping) {
            echo "Business ID: {$mapping['business_id']}\n";
            echo "Phone Number ID: {$mapping['phone_number_id']}\n";
            echo "Phone Number: " . ($mapping['phone_number'] ?? 'NULL') . "\n";
            echo "Created At: {$mapping['created_at']}\n";
            echo "\n";
        }
    } else {
        echo "Nenhum mapeamento LID encontrado.\n\n";
    }
}

// 3. Buscar eventos relacionados
echo "3. EVENTOS DE MENSAGENS:\n";
echo str_repeat("=", 70) . "\n";

// Busca eventos com múltiplos padrões
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.tenant_id,
        ce.created_at,
        JSON_EXTRACT(ce.payload, '$.from') as event_from,
        JSON_EXTRACT(ce.payload, '$.to') as event_to,
        JSON_EXTRACT(ce.payload, '$.message.body') as message_body,
        JSON_EXTRACT(ce.payload, '$.message.type') as message_type,
        JSON_EXTRACT(ce.metadata, '$.channel_id') as event_channel_id,
        ce.payload as full_payload
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message', 'whatsapp.message.status')
    AND (
        JSON_EXTRACT(ce.payload, '$.from') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.to') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.message.to') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.from') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.to') LIKE ?
    )
    ORDER BY ce.created_at DESC
    LIMIT 50
");

$pattern1 = "%{$contactId}%";
$pattern2 = "%{$normalizedContact}%";
$pattern3 = "%" . str_replace('@lid', '', $contactId) . "%";

$stmt->execute([$pattern1, $pattern1, $pattern2, $pattern2, $pattern3, $pattern3]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ Nenhum evento encontrado.\n\n";
    
    // Tenta buscar pelo channel_id
    if ($conversation['channel_id']) {
        echo "Buscando por channel_id: {$conversation['channel_id']}\n";
        $stmt = $db->prepare("
            SELECT 
                ce.id,
                ce.event_id,
                ce.event_type,
                ce.created_at,
                JSON_EXTRACT(ce.payload, '$.from') as event_from,
                JSON_EXTRACT(ce.payload, '$.to') as event_to
            FROM communication_events ce
            WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
            AND (
                JSON_EXTRACT(ce.metadata, '$.channel_id') = ?
                OR JSON_EXTRACT(ce.payload, '$.session.id') = ?
            )
            ORDER BY ce.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$conversation['channel_id'], $conversation['channel_id']]);
        $channelEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($channelEvents)) {
            echo "✅ Encontrados " . count($channelEvents) . " evento(s) por channel_id:\n\n";
            foreach ($channelEvents as $event) {
                echo "Event ID: {$event['event_id']}\n";
                echo "Type: {$event['event_type']}\n";
                echo "From: {$event['event_from']}\n";
                echo "To: {$event['event_to']}\n";
                echo "Created: {$event['created_at']}\n";
                echo "\n";
            }
        }
    }
} else {
    echo "✅ Encontrados " . count($events) . " evento(s):\n\n";
    
    foreach ($events as $idx => $event) {
        echo "--- Evento #" . ($idx + 1) . " ---\n";
        echo "ID: {$event['id']}\n";
        echo "Event ID: {$event['event_id']}\n";
        echo "Type: {$event['event_type']}\n";
        echo "From: {$event['event_from']}\n";
        echo "To: {$event['event_to']}\n";
        echo "Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n";
        echo "Channel ID: {$event['event_channel_id']}\n";
        echo "Message Type: {$event['message_type']}\n";
        
        $messageBody = $event['message_body'];
        if ($messageBody) {
            $messageBody = trim($messageBody, '"');
            if (strlen($messageBody) > 200) {
                $messageBody = substr($messageBody, 0, 200) . "...";
            }
            echo "Message: {$messageBody}\n";
        }
        
        echo "Created: {$event['created_at']}\n";
        echo "\n";
    }
}

// 4. Buscar possíveis tenants relacionados pelo telefone
echo "4. POSSÍVEIS TENANTS RELACIONADOS:\n";
echo str_repeat("=", 70) . "\n";

if ($normalizedContact) {
    // Remove DDI se tiver
    $phoneWithoutDDI = $normalizedContact;
    if (strlen($phoneWithoutDDI) >= 12 && substr($phoneWithoutDDI, 0, 2) === '55') {
        $phoneWithoutDDI = substr($phoneWithoutDDI, 2);
    }
    
    $stmt = $db->prepare("
        SELECT id, name, phone, email, cpf_cnpj
        FROM tenants
        WHERE phone LIKE ?
        OR phone LIKE ?
        OR phone LIKE ?
        LIMIT 10
    ");
    
    $pattern1 = "%{$normalizedContact}%";
    $pattern2 = "%{$phoneWithoutDDI}%";
    $pattern3 = "%" . substr($phoneWithoutDDI, -9) . "%"; // últimos 9 dígitos
    
    $stmt->execute([$pattern1, $pattern2, $pattern3]);
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($tenants)) {
        echo "✅ Encontrados " . count($tenants) . " tenant(s) possível(is):\n\n";
        foreach ($tenants as $tenant) {
            echo "ID: {$tenant['id']}\n";
            echo "Nome: {$tenant['name']}\n";
            echo "Telefone: {$tenant['phone']}\n";
            echo "Email: " . ($tenant['email'] ?? 'NULL') . "\n";
            echo "CPF/CNPJ: " . ($tenant['cpf_cnpj'] ?? 'NULL') . "\n";
            echo "\n";
        }
    } else {
        echo "❌ Nenhum tenant encontrado com telefone relacionado.\n\n";
    }
}

// 5. Estatísticas
echo "5. ESTATÍSTICAS:\n";
echo str_repeat("=", 70) . "\n";

$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_messages,
        SUM(CASE WHEN event_type = 'whatsapp.inbound.message' THEN 1 ELSE 0 END) as inbound,
        SUM(CASE WHEN event_type = 'whatsapp.outbound.message' THEN 1 ELSE 0 END) as outbound,
        MIN(created_at) as first_message,
        MAX(created_at) as last_message
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND (
        JSON_EXTRACT(ce.payload, '$.from') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.to') LIKE ?
    )
");
$stmt->execute(["%{$normalizedContact}%", "%{$normalizedContact}%"]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Total de Mensagens: " . ($stats['total_messages'] ?? 0) . "\n";
echo "Entrantes: " . ($stats['inbound'] ?? 0) . "\n";
echo "Saídas: " . ($stats['outbound'] ?? 0) . "\n";
echo "Primeira Mensagem: " . ($stats['first_message'] ?? 'N/A') . "\n";
echo "Última Mensagem: " . ($stats['last_message'] ?? 'N/A') . "\n";

echo "\n=== FIM ===\n";

