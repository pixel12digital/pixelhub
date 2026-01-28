<?php
/**
 * Diagnóstico de áudios outbound em produção
 * Acesse: /database/diagnostico-audio-producao.php
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DE ÁUDIOS OUTBOUND ===\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 80) . "\n\n";

try {
    $db = DB::getConnection();
    echo "✅ Conexão com banco OK\n\n";
    
    // 1. Eventos de áudio outbound recentes
    echo "1. ÚLTIMOS 10 EVENTOS DE ÁUDIO OUTBOUND:\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->query("
        SELECT 
            ce.event_id,
            ce.event_type,
            ce.conversation_id,
            ce.tenant_id,
            ce.created_at,
            JSON_EXTRACT(ce.payload, '$.type') as msg_type,
            JSON_EXTRACT(ce.payload, '$.to') as to_number,
            JSON_EXTRACT(ce.metadata, '$.sent_by_name') as sent_by
        FROM communication_events ce
        WHERE ce.event_type = 'whatsapp.outbound.message'
        ORDER BY ce.created_at DESC
        LIMIT 10
    ");
    $events = $stmt->fetchAll();
    
    echo "Encontrados: " . count($events) . " eventos\n\n";
    
    foreach ($events as $i => $event) {
        $hasConvId = !empty($event['conversation_id']) ? '✅' : '❌';
        echo "Evento " . ($i + 1) . ":\n";
        echo "  event_id:        {$event['event_id']}\n";
        echo "  conversation_id: " . ($event['conversation_id'] ?: 'NULL') . " {$hasConvId}\n";
        echo "  to:              {$event['to_number']}\n";
        echo "  type:            {$event['msg_type']}\n";
        echo "  sent_by:         {$event['sent_by']}\n";
        echo "  created_at:      {$event['created_at']}\n";
        
        // Verifica mídia
        $mediaStmt = $db->prepare("SELECT * FROM communication_media WHERE event_id = ?");
        $mediaStmt->execute([$event['event_id']]);
        $media = $mediaStmt->fetch();
        
        if ($media) {
            echo "  mídia:           ✅ SIM (id={$media['id']}, path={$media['stored_path']})\n";
        } else {
            echo "  mídia:           ❌ NÃO\n";
        }
        echo "\n";
    }
    
    // 2. Estatísticas de conversation_id
    echo "\n2. ESTATÍSTICAS DE CONVERSATION_ID EM EVENTOS OUTBOUND:\n";
    echo str_repeat("-", 80) . "\n";
    
    $statsStmt = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN conversation_id IS NOT NULL THEN 1 ELSE 0 END) as com_conv_id,
            SUM(CASE WHEN conversation_id IS NULL THEN 1 ELSE 0 END) as sem_conv_id
        FROM communication_events
        WHERE event_type = 'whatsapp.outbound.message'
    ");
    $stats = $statsStmt->fetch();
    
    echo "Total eventos outbound:        {$stats['total']}\n";
    echo "  - COM conversation_id:       {$stats['com_conv_id']}\n";
    echo "  - SEM conversation_id (NULL): {$stats['sem_conv_id']}\n";
    
    // 3. Conversas duplicadas
    echo "\n3. POSSÍVEIS CONVERSAS DUPLICADAS (mesmo número, múltiplas conversas):\n";
    echo str_repeat("-", 80) . "\n";
    
    $dupStmt = $db->query("
        SELECT 
            contact_external_id,
            COUNT(*) as qtd_conversas,
            GROUP_CONCAT(id ORDER BY id) as ids,
            GROUP_CONCAT(conversation_key ORDER BY id SEPARATOR ' | ') as keys
        FROM conversations
        WHERE channel_type = 'whatsapp'
        GROUP BY contact_external_id
        HAVING COUNT(*) > 1
        ORDER BY COUNT(*) DESC
        LIMIT 10
    ");
    $dups = $dupStmt->fetchAll();
    
    if (empty($dups)) {
        echo "✅ Nenhuma conversa duplicada encontrada\n";
    } else {
        echo "⚠️ Encontradas " . count($dups) . " duplicações:\n\n";
        foreach ($dups as $dup) {
            echo "Contato: {$dup['contact_external_id']}\n";
            echo "  Qtd conversas: {$dup['qtd_conversas']}\n";
            echo "  IDs: {$dup['ids']}\n";
            echo "  Keys: {$dup['keys']}\n\n";
        }
    }
    
    // 4. Verifica se eventos outbound recentes têm conversa correspondente
    echo "\n4. VERIFICAÇÃO CRUZADA: EVENTOS vs CONVERSAS:\n";
    echo str_repeat("-", 80) . "\n";
    
    $crossStmt = $db->query("
        SELECT 
            ce.event_id,
            ce.conversation_id,
            ce.created_at,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_number,
            c.id as conv_id_found,
            c.contact_external_id
        FROM communication_events ce
        LEFT JOIN conversations c ON ce.conversation_id = c.id
        WHERE ce.event_type = 'whatsapp.outbound.message'
        AND ce.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY ce.created_at DESC
        LIMIT 5
    ");
    $crossResults = $crossStmt->fetchAll();
    
    foreach ($crossResults as $cr) {
        $convStatus = $cr['conv_id_found'] ? '✅' : '❌';
        echo "Event: {$cr['event_id']}\n";
        echo "  to: {$cr['to_number']}\n";
        echo "  conversation_id no evento: " . ($cr['conversation_id'] ?: 'NULL') . "\n";
        echo "  Conversa encontrada: {$convStatus} " . ($cr['conv_id_found'] ? "(id={$cr['conv_id_found']}, contact={$cr['contact_external_id']})" : "") . "\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
