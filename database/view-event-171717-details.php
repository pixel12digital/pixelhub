<?php

/**
 * Script para visualizar detalhes completos do evento que contém "171717"
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

// Carrega .env
Env::load();

echo "=== DETALHES DO EVENTO QUE CONTÉM '171717' ===\n\n";

$db = DB::getConnection();

// Busca o evento com ID 15 (encontrado anteriormente)
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        source_system,
        tenant_id,
        trace_id,
        correlation_id,
        payload,
        metadata,
        status,
        created_at
    FROM communication_events
    WHERE id = 15
       OR payload LIKE '%171717%'
    ORDER BY id DESC
    LIMIT 5
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($events as $event) {
    echo "Event ID: " . $event['id'] . "\n";
    echo "Event UUID: " . ($event['event_id'] ?? 'NULL') . "\n";
    echo "Event Type: " . ($event['event_type'] ?? 'NULL') . "\n";
    echo "Source System: " . ($event['source_system'] ?? 'NULL') . "\n";
    echo "Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n";
    echo "Created At: " . ($event['created_at'] ?? 'NULL') . "\n";
    echo "\n";
    
    // Decodifica payload
    $payload = json_decode($event['payload'], true);
    if ($payload) {
        echo "=== PAYLOAD COMPLETO ===\n";
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        echo "\n";
        
        // Extrai informações específicas
        echo "=== INFORMAÇÕES EXTRAÍDAS ===\n";
        
        // Informações de mensagem
        $from = $payload['from'] 
            ?? $payload['message']['from'] 
            ?? $payload['data']['from'] 
            ?? $payload['raw']['payload']['from'] 
            ?? null;
        $to = $payload['to'] 
            ?? $payload['message']['to'] 
            ?? $payload['data']['to'] 
            ?? $payload['raw']['payload']['to'] 
            ?? null;
        $text = $payload['text'] 
            ?? $payload['message']['text'] 
            ?? $payload['data']['text'] 
            ?? $payload['raw']['payload']['body'] 
            ?? null;
        
        // Informações de encaminhamento
        $forwardedFrom = $payload['message']['forwardedFrom'] 
            ?? $payload['forwardedFrom'] 
            ?? $payload['data']['forwardedFrom'] 
            ?? $payload['raw']['payload']['forwardedFrom'] 
            ?? null;
        
        $isForwarded = $payload['message']['isForwarded'] 
            ?? $payload['isForwarded'] 
            ?? $payload['data']['isForwarded'] 
            ?? $payload['raw']['payload']['forwardingScore'] 
            ?? null;
        
        // Channel/Session
        $channelId = $payload['channel'] 
            ?? $payload['channelId'] 
            ?? $payload['session']['id'] 
            ?? $payload['session']['session'] 
            ?? $payload['metadata']['channel_id'] 
            ?? null;
        
        echo "DE (From): " . ($from ?? 'NULL') . "\n";
        echo "PARA (To): " . ($to ?? 'NULL') . "\n";
        echo "TEXTO: " . ($text ?? 'NULL') . "\n";
        echo "Channel ID: " . ($channelId ?? 'NULL') . "\n";
        
        if ($forwardedFrom) {
            echo "🔄 ENCAMINHADA DE: " . $forwardedFrom . "\n";
        }
        
        if ($isForwarded !== null) {
            echo "É Encaminhada: " . ($isForwarded ? 'SIM' : 'NÃO') . "\n";
        }
        
        // Verifica se é mensagem encaminhada de outra forma
        if (isset($payload['message']['key']['fromMe']) && !$payload['message']['key']['fromMe']) {
            echo "Tipo: Mensagem recebida (inbound)\n";
        }
        
        echo "\n";
    }
    
    // Decodifica metadata
    if (!empty($event['metadata'])) {
        $metadata = json_decode($event['metadata'], true);
        if ($metadata) {
            echo "=== METADATA ===\n";
            echo json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            echo "\n";
        }
    }
    
    // Tenta encontrar a conversa relacionada
    echo "=== BUSCANDO CONVERSA RELACIONADA ===\n";
    if ($from) {
        // Remove @lid ou @c.us do número para busca
        $contactId = preg_replace('/@.*$/', '', $from);
        
        $convStmt = $db->prepare("
            SELECT * FROM conversations 
            WHERE contact_external_id LIKE ?
               OR conversation_key LIKE ?
               OR channel_id = ?
            ORDER BY last_message_at DESC
            LIMIT 5
        ");
        $searchTerm = '%' . $contactId . '%';
        $convStmt->execute([$searchTerm, $searchTerm, $channelId ?? '']);
        $conversations = $convStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($conversations) > 0) {
            echo "✅ Encontradas " . count($conversations) . " conversas relacionadas:\n";
            foreach ($conversations as $conv) {
                echo "  - ID: " . $conv['id'] . ", Key: " . ($conv['conversation_key'] ?? 'NULL') . 
                     ", Contact: " . ($conv['contact_external_id'] ?? 'NULL') . 
                     ", Tenant: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
            }
        } else {
            echo "❌ Nenhuma conversa relacionada encontrada.\n";
        }
    }
    
    echo "\n" . str_repeat("=", 80) . "\n\n";
}

// Verifica se há conversas com tenant_id NULL ou que deveriam estar associadas
echo "=== VERIFICANDO CONVERSAS SEM TENANT ===\n";
$stmt = $db->prepare("
    SELECT * FROM conversations 
    WHERE tenant_id IS NULL
      AND channel_type = 'whatsapp'
      AND last_message_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY last_message_at DESC
    LIMIT 10
");
$stmt->execute();
$conversationsNoTenant = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($conversationsNoTenant) > 0) {
    echo "✅ Encontradas " . count($conversationsNoTenant) . " conversas sem tenant (últimos 7 dias):\n";
    foreach ($conversationsNoTenant as $conv) {
        echo "  - ID: " . $conv['id'] . ", Contact: " . ($conv['contact_external_id'] ?? 'NULL') . 
             ", Channel ID: " . ($conv['channel_id'] ?? 'NULL') . 
             ", Last Message: " . ($conv['last_message_at'] ?? 'NULL') . "\n";
    }
} else {
    echo "❌ Nenhuma conversa sem tenant encontrada.\n";
}

echo "\n=== FIM ===\n";

