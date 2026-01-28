<?php
/**
 * Diagnóstico de conversa específica
 * Uso: php database/diagnostico-conversa-especifica.php [conversation_id]
 * Ou acesse: /database/diagnostico-conversa-especifica.php?conv=8
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

header('Content-Type: text/plain; charset=utf-8');

// Pega conversation_id da URL ou argumento
$conversationId = $_GET['conv'] ?? $argv[1] ?? 8;

echo "=== DIAGNÓSTICO DA CONVERSA {$conversationId} ===\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 80) . "\n\n";

try {
    $db = DB::getConnection();
    
    // 1. Busca a conversa
    $stmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conv = $stmt->fetch();
    
    if (!$conv) {
        echo "❌ Conversa {$conversationId} não encontrada!\n";
        exit;
    }
    
    echo "1. DADOS DA CONVERSA:\n";
    echo str_repeat("-", 80) . "\n";
    echo "  ID:                  {$conv['id']}\n";
    echo "  conversation_key:    {$conv['conversation_key']}\n";
    echo "  contact_external_id: {$conv['contact_external_id']}\n";
    echo "  tenant_id:           {$conv['tenant_id']}\n";
    echo "  channel_id:          " . ($conv['channel_id'] ?? 'NULL') . "\n";
    echo "  remote_key:          " . ($conv['remote_key'] ?? 'NULL') . "\n";
    
    // 2. Busca outras conversas com o mesmo número
    $contactNumber = $conv['contact_external_id'];
    echo "\n2. CONVERSAS COM MESMO NÚMERO ({$contactNumber}):\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->prepare("SELECT * FROM conversations WHERE contact_external_id = ? OR contact_external_id LIKE ?");
    // Busca também variações do número (com/sem 9º dígito)
    $pattern = '%' . substr(preg_replace('/\D/', '', $contactNumber), -8) . '%';
    $stmt->execute([$contactNumber, $pattern]);
    $convs = $stmt->fetchAll();
    
    echo "Encontradas: " . count($convs) . " conversa(s)\n\n";
    foreach ($convs as $c) {
        $marker = $c['id'] == $conversationId ? ' <-- ATUAL' : '';
        echo "  ID: {$c['id']}{$marker}\n";
        echo "    conversation_key: {$c['conversation_key']}\n";
        echo "    contact_external_id: {$c['contact_external_id']}\n";
        echo "    tenant_id: {$c['tenant_id']}\n";
        echo "\n";
    }
    
    // 3. Busca eventos COM conversation_id = conversationId
    echo "\n3. EVENTOS COM conversation_id = {$conversationId}:\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->prepare("
        SELECT 
            event_id, event_type, created_at,
            JSON_EXTRACT(payload, '$.type') as msg_type,
            JSON_EXTRACT(payload, '$.from') as from_field,
            JSON_EXTRACT(payload, '$.to') as to_field
        FROM communication_events
        WHERE conversation_id = ?
        ORDER BY created_at DESC
        LIMIT 15
    ");
    $stmt->execute([$conversationId]);
    $events = $stmt->fetchAll();
    
    echo "Encontrados: " . count($events) . " evento(s)\n\n";
    foreach ($events as $e) {
        $direction = strpos($e['event_type'], 'inbound') !== false ? 'IN' : 'OUT';
        echo "  [{$direction}] {$e['event_id']} | {$e['msg_type']} | from={$e['from_field']} to={$e['to_field']} | {$e['created_at']}\n";
    }
    
    // 4. Busca eventos PELO NÚMERO (como a query faz)
    $normalized = preg_replace('/\D/', '', $contactNumber);
    echo "\n4. EVENTOS PELO NÚMERO (normalizado: {$normalized}):\n";
    echo str_repeat("-", 80) . "\n";
    
    // Adiciona variação com 9º dígito
    $patterns = [];
    $patterns[] = "%{$normalized}%";
    if (strlen($normalized) === 12) {
        $with9 = substr($normalized, 0, 4) . '9' . substr($normalized, 4);
        $patterns[] = "%{$with9}%";
        echo "Padrões de busca: {$normalized}, {$with9}\n\n";
    } elseif (strlen($normalized) === 13) {
        $without9 = substr($normalized, 0, 4) . substr($normalized, 5);
        $patterns[] = "%{$without9}%";
        echo "Padrões de busca: {$normalized}, {$without9}\n\n";
    }
    
    $placeholders = implode(' OR ', array_fill(0, count($patterns), "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) LIKE ?"));
    $placeholders .= ' OR ' . implode(' OR ', array_fill(0, count($patterns), "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) LIKE ?"));
    
    $stmt = $db->prepare("
        SELECT 
            event_id, event_type, conversation_id, created_at,
            JSON_EXTRACT(payload, '$.type') as msg_type,
            JSON_EXTRACT(payload, '$.from') as from_field,
            JSON_EXTRACT(payload, '$.to') as to_field
        FROM communication_events
        WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        AND ($placeholders)
        ORDER BY created_at DESC
        LIMIT 15
    ");
    $params = array_merge($patterns, $patterns);
    $stmt->execute($params);
    $events = $stmt->fetchAll();
    
    echo "Encontrados: " . count($events) . " evento(s)\n\n";
    foreach ($events as $e) {
        $direction = strpos($e['event_type'], 'inbound') !== false ? 'IN' : 'OUT';
        $convMark = $e['conversation_id'] == $conversationId ? '✅' : ($e['conversation_id'] ? "⚠️ conv={$e['conversation_id']}" : '❌');
        echo "  [{$direction}] {$e['event_id']} | conv_id={$e['conversation_id']} {$convMark} | {$e['msg_type']} | {$e['created_at']}\n";
    }
    
    // 5. Query de diagnóstico: O que a função getWhatsAppMessagesFromConversation retornaria?
    echo "\n5. SIMULAÇÃO DA QUERY DE MENSAGENS:\n";
    echo str_repeat("-", 80) . "\n";
    
    $sessionId = $conv['channel_id'] ?? '';
    echo "channel_id (session): " . ($sessionId ?: 'VAZIO') . "\n";
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM communication_events ce
        WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        AND ce.conversation_id = ?
    ");
    $stmt->execute([$conversationId]);
    $byConvId = $stmt->fetch()['total'];
    
    echo "Eventos encontrados por conversation_id: {$byConvId}\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
