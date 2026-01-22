<?php
/**
 * Script para corrigir message_count das conversas
 * Remove contagem de notificações de sistema do WhatsApp Business
 * 
 * Este script recalcula o message_count baseado apenas em mensagens com conteúdo real
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== CORRIGINDO MESSAGE_COUNT DAS CONVERSAS ===\n\n";

// Função para verificar se mensagem tem conteúdo (mesma lógica do ConversationService)
function hasMessageContent($event, $db): bool
{
    $payload = json_decode($event['payload'], true);
    
    // Extrai conteúdo de texto
    $text = $payload['text'] 
        ?? $payload['body'] 
        ?? $payload['message']['text'] ?? null
        ?? $payload['message']['body'] ?? null;
    
    // Se tem texto não vazio, é mensagem válida
    if (!empty(trim($text ?? ''))) {
        return true;
    }
    
    // Verifica se é notificação de sistema do WhatsApp Business
    $rawPayload = $payload['raw']['payload'] ?? [];
    $type = $rawPayload['type'] ?? $payload['type'] ?? $payload['message']['type'] ?? null;
    $subtype = $rawPayload['subtype'] ?? null;
    
    // Notificações de sistema do WhatsApp Business
    if ($type === 'notification_template' || !empty($subtype)) {
        // Verifica se tem mídia processada
        $eventId = $event['event_id'] ?? null;
        if ($eventId) {
            try {
                $mediaStmt = $db->prepare("SELECT id FROM communication_media WHERE event_id = ? LIMIT 1");
                $mediaStmt->execute([$eventId]);
                if ($mediaStmt->fetch()) {
                    return true; // Tem mídia, conta como mensagem válida
                }
            } catch (\Exception $e) {
                // Se der erro, assume que não tem
            }
        }
        return false; // É notificação de sistema sem conteúdo nem mídia
    }
    
    // Verifica se tem mídia processada
    $eventId = $event['event_id'] ?? null;
    if ($eventId) {
        try {
            $mediaStmt = $db->prepare("SELECT id FROM communication_media WHERE event_id = ? LIMIT 1");
            $mediaStmt->execute([$eventId]);
            if ($mediaStmt->fetch()) {
                return true; // Tem mídia, conta como mensagem válida
            }
        } catch (\Exception $e) {
            // Se der erro, assume que não tem
        }
    }
    
    // Verifica se tem tipo de mídia no payload
    $mediaTypes = ['image', 'video', 'audio', 'document', 'sticker', 'ptt'];
    if (in_array($type, $mediaTypes)) {
        return true; // Tem tipo de mídia, conta como mensagem válida
    }
    
    return false; // Sem conteúdo, sem mídia
}

// Busca todas as conversas WhatsApp
$stmt = $db->query("
    SELECT id, contact_external_id, contact_name, message_count
    FROM conversations
    WHERE channel_type = 'whatsapp'
    ORDER BY id
");
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de conversas encontradas: " . count($conversations) . "\n\n";

$totalFixed = 0;
$totalReduced = 0;

foreach ($conversations as $conv) {
    $conversationId = $conv['id'];
    $contactExternalId = $conv['contact_external_id'];
    $currentCount = (int) $conv['message_count'];
    
    // Busca channel_id da conversa para filtrar corretamente
    $convStmt = $db->prepare("SELECT channel_id FROM conversations WHERE id = ?");
    $convStmt->execute([$conversationId]);
    $channelId = $convStmt->fetchColumn();
    
    // Busca todos os eventos dessa conversa (usando os mesmos filtros do CommunicationHubController)
    $pattern = "%{$contactExternalId}%";
    $where = ["ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')"];
    $params = [];
    
    // Filtro por contato
    $contactConditions = [];
    for ($i = 0; $i < 4; $i++) {
        $contactConditions[] = "(
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
        )";
        $params[] = $pattern;
        $params[] = $pattern;
        $params[] = $pattern;
        $params[] = $pattern;
    }
    $where[] = "(" . implode(" OR ", $contactConditions) . ")";
    
    // Filtro por channel_id se disponível
    if (!empty($channelId)) {
        $where[] = "(
            JSON_EXTRACT(ce.metadata, '$.channel_id') = ?
            OR JSON_EXTRACT(ce.payload, '$.session.id') = ?
            OR JSON_EXTRACT(ce.payload, '$.sessionId') = ?
            OR JSON_EXTRACT(ce.payload, '$.channelId') = ?
        )";
        $params[] = $channelId;
        $params[] = $channelId;
        $params[] = $channelId;
        $params[] = $channelId;
    }
    
    $whereClause = "WHERE " . implode(" AND ", $where);
    
    $stmt = $db->prepare("
        SELECT 
            ce.event_id,
            ce.payload
        FROM communication_events ce
        {$whereClause}
    ");
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Conta apenas mensagens com conteúdo
    $validCount = 0;
    foreach ($events as $event) {
        if (hasMessageContent($event, $db)) {
            $validCount++;
        }
    }
    
    // Se o contador está diferente, corrige
    if ($validCount != $currentCount) {
        $diff = $currentCount - $validCount;
        $totalReduced += $diff;
        
        $updateStmt = $db->prepare("UPDATE conversations SET message_count = ? WHERE id = ?");
        $updateStmt->execute([$validCount, $conversationId]);
        
        echo "✅ Conversa #{$conversationId} ({$conv['contact_name']}): {$currentCount} → {$validCount} (redução de {$diff})\n";
        $totalFixed++;
    }
}

echo "\n=== RESUMO ===\n";
echo "Conversas corrigidas: {$totalFixed}\n";
echo "Total de mensagens removidas da contagem: {$totalReduced}\n";
echo "\n✅ Correção concluída!\n";

