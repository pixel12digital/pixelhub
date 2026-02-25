<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== INVESTIGANDO DUPLICAÇÃO DE CONVERSA - LEAD #6 ===\n\n";

// 1. Busca dados do Lead #6
$stmt = $db->prepare("
    SELECT 
        l.id,
        l.name,
        l.phone,
        l.email,
        l.created_at
    FROM leads l
    WHERE l.id = 6
");
$stmt->execute();
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    die("Lead #6 não encontrado!\n");
}

echo "LEAD #6:\n";
echo "Nome: {$lead['name']}\n";
echo "Telefone: {$lead['phone']}\n";
echo "Email: {$lead['email']}\n";
echo "Criado em: {$lead['created_at']}\n\n";

// 2. Busca todas as conversas vinculadas ao Lead #6
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.conversation_key,
        c.channel_type,
        c.channel_account_id,
        c.contact_external_id,
        c.contact_name,
        c.lead_id,
        c.tenant_id,
        c.created_at,
        c.updated_at
    FROM conversations c
    WHERE c.lead_id = 6
    ORDER BY c.created_at DESC
");
$stmt->execute();
$linkedConversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "CONVERSAS VINCULADAS AO LEAD #6: " . count($linkedConversations) . "\n";
foreach ($linkedConversations as $conv) {
    echo "\n--- Conversa ID: {$conv['id']} ---\n";
    echo "Conversation Key: {$conv['conversation_key']}\n";
    echo "Canal: {$conv['channel_type']} - Channel Account ID: {$conv['channel_account_id']}\n";
    echo "Contact External ID: {$conv['contact_external_id']}\n";
    echo "Contact Name: {$conv['contact_name']}\n";
    echo "Criado: {$conv['created_at']}\n";
    echo "Atualizado: {$conv['updated_at']}\n";
}

// 3. Busca a conversa "Contato Desconhecido" (ID 194 do script anterior)
echo "\n\n" . str_repeat("=", 80) . "\n";
echo "CONVERSA NÃO VINCULADA (Contato Desconhecido - ID 194):\n\n";

$stmt = $db->prepare("
    SELECT 
        c.id,
        c.conversation_key,
        c.channel_type,
        c.channel_account_id,
        c.contact_external_id,
        c.contact_name,
        c.lead_id,
        c.tenant_id,
        c.created_at,
        c.updated_at
    FROM conversations c
    WHERE c.id = 194
");
$stmt->execute();
$unlinkedConv = $stmt->fetch(PDO::FETCH_ASSOC);

if ($unlinkedConv) {
    echo "Conversa ID: {$unlinkedConv['id']}\n";
    echo "Conversation Key: {$unlinkedConv['conversation_key']}\n";
    echo "Canal: {$unlinkedConv['channel_type']} - Channel Account ID: {$unlinkedConv['channel_account_id']}\n";
    echo "Contact External ID: {$unlinkedConv['contact_external_id']}\n";
    echo "Contact Name: " . ($unlinkedConv['contact_name'] ?? 'NULL') . "\n";
    echo "Lead ID: " . ($unlinkedConv['lead_id'] ?? 'NULL') . "\n";
    echo "Tenant ID: " . ($unlinkedConv['tenant_id'] ?? 'NULL') . "\n";
    echo "Criado: {$unlinkedConv['created_at']}\n";
    echo "Atualizado: {$unlinkedConv['updated_at']}\n";
}

// 4. Busca eventos de comunicação do Lead #6 nas últimas 24h
echo "\n\n" . str_repeat("=", 80) . "\n";
echo "EVENTOS DE COMUNICAÇÃO DO LEAD #6 (últimas 24h):\n\n";

$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.source_system,
        ce.conversation_id,
        ce.created_at,
        ce.payload,
        c.lead_id,
        c.contact_external_id as conv_contact_id
    FROM communication_events ce
    LEFT JOIN conversations c ON ce.conversation_id = c.id
    WHERE ce.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
      AND (c.lead_id = 6 OR ce.conversation_id = 194)
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos: " . count($events) . "\n\n";
foreach ($events as $event) {
    $payload = json_decode($event['payload'], true);
    echo "--- Evento: {$event['event_id']} ---\n";
    echo "Tipo: {$event['event_type']}\n";
    echo "Source: {$event['source_system']}\n";
    echo "Conversation ID: {$event['conversation_id']} (Lead ID: " . ($event['lead_id'] ?? 'NULL') . ")\n";
    echo "Contact ID da conversa: {$event['conv_contact_id']}\n";
    echo "Criado: {$event['created_at']}\n";
    
    // Extrai informações relevantes do payload
    $to = $payload['to'] ?? $payload['message']['to'] ?? 'N/A';
    $from = $payload['from'] ?? 'N/A';
    $text = $payload['text'] ?? $payload['body'] ?? $payload['message']['text'] ?? '';
    
    echo "To: {$to}\n";
    echo "From: {$from}\n";
    if ($text) {
        echo "Texto: " . substr($text, 0, 100) . "...\n";
    }
    echo "\n";
}

// 5. Compara os contact_external_id
echo "\n" . str_repeat("=", 80) . "\n";
echo "ANÁLISE DE CONTACT_EXTERNAL_ID:\n\n";

if (!empty($linkedConversations)) {
    echo "Contact External ID das conversas vinculadas ao Lead #6:\n";
    foreach ($linkedConversations as $conv) {
        echo "  - Conversa {$conv['id']}: {$conv['contact_external_id']}\n";
    }
}

if ($unlinkedConv) {
    echo "\nContact External ID da conversa não vinculada (ID 194):\n";
    echo "  - {$unlinkedConv['contact_external_id']}\n";
}

echo "\n\nTelefone do Lead #6: {$lead['phone']}\n";

// 6. Busca no cache de resolução de LID
echo "\n" . str_repeat("=", 80) . "\n";
echo "CACHE DE RESOLUÇÃO LID → TELEFONE:\n\n";

$stmt = $db->prepare("
    SELECT 
        lid,
        phone_e164,
        created_at,
        updated_at
    FROM wa_pnlid_cache
    WHERE lid LIKE '%242137924943982%' OR phone_e164 = ?
    ORDER BY updated_at DESC
");
$stmt->execute([$lead['phone']]);
$lidCache = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($lidCache)) {
    echo "Entradas no cache:\n";
    foreach ($lidCache as $cache) {
        echo "  LID: {$cache['lid']} → Telefone: {$cache['phone_e164']}\n";
        echo "  Criado: {$cache['created_at']} | Atualizado: {$cache['updated_at']}\n\n";
    }
} else {
    echo "Nenhuma entrada encontrada no cache para o LID 242137924943982 ou telefone {$lead['phone']}\n";
}
