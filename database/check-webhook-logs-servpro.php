<?php

/**
 * Script para verificar logs do webhook e identificar por que ServPro não está sendo capturado
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PDO;

Env::load();
$db = DB::getConnection();

echo "=== ANÁLISE DE LOGS DO WEBHOOK - ServPro vs Charles ===\n\n";

// Busca mensagens recentes (últimas 2 horas) para ambos os números
$contacts = ['554796164699', '554796474223']; // Charles, ServPro
$timeWindow = new DateTime('-2 hours');

echo "Buscando mensagens dos últimos 2 horas para:\n";
echo "- Charles: 554796164699\n";
echo "- ServPro: 554796474223\n\n";

$placeholders = implode(',', array_fill(0, count($contacts), '?'));
$params = array_merge([$timeWindow->format('Y-m-d H:i:s')], $contacts, $contacts, $contacts, $contacts);

$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.created_at,
        ce.event_type,
        ce.tenant_id,
        ce.source_system,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as payload_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as payload_to,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as message_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) as message_to,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.channel')) as channel,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.session.id')) as session_id,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as metadata_channel_id,
        c.conversation_key as thread_id
    FROM communication_events ce
    LEFT JOIN conversations c ON c.contact_external_id = 
        (CASE 
            WHEN JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) IS NOT NULL THEN REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')), '@c.us', '')
            WHEN JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) IS NOT NULL THEN REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')), '@c.us', '')
            WHEN JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) IS NOT NULL THEN REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')), '@c.us', '')
            WHEN JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) IS NOT NULL THEN REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')), '@c.us', '')
            ELSE NULL
        END)
    WHERE ce.created_at >= ?
      AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
      AND (
            REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')), '@c.us', '') IN ({$placeholders})
         OR REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')), '@c.us', '') IN ({$placeholders})
         OR REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')), '@c.us', '') IN ({$placeholders})
         OR REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')), '@c.us', '') IN ({$placeholders})
      )
    ORDER BY ce.created_at DESC
    LIMIT 50
");
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupa por contato
$byContact = [];
foreach ($messages as $msg) {
    $from = $msg['payload_from'] ?? $msg['message_from'] ?? null;
    $to = $msg['payload_to'] ?? $msg['message_to'] ?? null;
    $contact = $from ?: $to;
    
    // Normaliza contato
    $normalized = preg_replace('/@.*$/', '', (string) $contact);
    $normalized = preg_replace('/[^0-9]/', '', $normalized);
    
    if (strpos($normalized, '554796164699') !== false) {
        $key = 'Charles (554796164699)';
    } elseif (strpos($normalized, '554796474223') !== false) {
        $key = 'ServPro (554796474223)';
    } else {
        $key = $normalized;
    }
    
    if (!isset($byContact[$key])) {
        $byContact[$key] = [];
    }
    $byContact[$key][] = $msg;
}

echo "=== RESULTADOS ===\n\n";

foreach ($byContact as $contact => $msgs) {
    echo "📱 {$contact}: " . count($msgs) . " mensagem(ns)\n";
    echo str_repeat('-', 60) . "\n";
    
    foreach ($msgs as $msg) {
        $from = $msg['payload_from'] ?? $msg['message_from'] ?? 'N/A';
        $to = $msg['payload_to'] ?? $msg['message_to'] ?? 'N/A';
        $channel = $msg['channel'] ?? $msg['metadata_channel_id'] ?? $msg['session_id'] ?? 'N/A';
        
        echo "  ID: {$msg['id']} | Event ID: {$msg['event_id']}\n";
        echo "  Created: {$msg['created_at']} | Type: {$msg['event_type']}\n";
        echo "  From: {$from} | To: {$to}\n";
        echo "  Channel: {$channel} | Tenant: " . ($msg['tenant_id'] ?? 'NULL') . "\n";
        echo "  Thread ID: " . ($msg['thread_id'] ?? 'N/A') . "\n";
        echo "\n";
    }
    echo "\n";
}

// Verifica se há diferenças no formato
echo "=== ANÁLISE DE FORMATO ===\n\n";

$charlesMsgs = $byContact['Charles (554796164699)'] ?? [];
$servproMsgs = $byContact['ServPro (554796474223)'] ?? [];

if (!empty($charlesMsgs)) {
    $charlesSample = $charlesMsgs[0];
    $charlesFrom = $charlesSample['payload_from'] ?? $charlesSample['message_from'] ?? null;
    echo "Charles - Formato do 'from': " . ($charlesFrom ?? 'N/A') . "\n";
}

if (!empty($servproMsgs)) {
    $servproSample = $servproMsgs[0];
    $servproFrom = $servproSample['payload_from'] ?? $servproSample['message_from'] ?? null;
    echo "ServPro - Formato do 'from': " . ($servproFrom ?? 'N/A') . "\n";
} else {
    echo "⚠️  ServPro - NENHUMA MENSAGEM ENCONTRADA nos últimos 2 horas!\n";
    echo "   Isso indica que o webhook NÃO está chegando para o ServPro.\n";
    echo "   Possíveis causas:\n";
    echo "   1. Gateway não está enviando webhook para este número\n";
    echo "   2. Webhook está sendo bloqueado/rejeitado antes de chegar ao controller\n";
    echo "   3. Formato do número no gateway é diferente\n";
}

echo "\n=== VERIFICAÇÃO DE LOGS DO SERVIDOR ===\n\n";
echo "Verifique os logs do servidor (error_log) procurando por:\n";
echo "- '[WHATSAPP INBOUND RAW]' para ver se webhooks estão chegando\n";
echo "- Número '554796474223' ou '4223' nos logs\n";
echo "- Erros relacionados a validação de secret ou JSON\n";

echo "\n=== FIM DA ANÁLISE ===\n";

