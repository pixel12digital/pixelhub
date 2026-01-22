<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== BUSCA: nova_mensagem_1927 (Pixel12 Digital às 19:28) ===\n\n";

// Buscar mensagem específica
$sql = "SELECT id, created_at, event_type, status, error_message,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS payload_event,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) AS raw_body,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS message_from,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.to')) AS message_to
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= '2026-01-15 19:27:00'
  AND created_at <= '2026-01-15 19:30:00'
  AND (
    payload LIKE '%nova_mensagem_1927%'
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) LIKE '%nova_mensagem_1927%'
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) LIKE '%nova_mensagem_1927%'
  )
ORDER BY created_at DESC
LIMIT 5";

$stmt = $pdo->query($sql);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($events) > 0) {
    echo "✅ Mensagem encontrada: " . count($events) . " evento(s)\n\n";
    
    foreach ($events as $e) {
        $icon = $e['status'] === 'processed' ? '✅' : '❌';
        $text = $e['message_text'] ?: $e['raw_body'] ?: 'SEM TEXTO';
        
        echo sprintf("%s ID: %5d | %s | Status: %-10s\n", $icon, $e['id'], $e['created_at'], $e['status']);
        echo sprintf("   Channel: %s\n", $e['channel_id'] ?: 'NULL');
        echo sprintf("   Text: %s\n", substr($text, 0, 80));
        echo sprintf("   From: %s\n", substr($e['message_from'] ?: 'NULL', 0, 50));
        echo sprintf("   To: %s\n", substr($e['message_to'] ?: 'NULL', 0, 50));
        if ($e['error_message']) {
            echo sprintf("   Erro: %s\n", substr($e['error_message'], 0, 60));
        }
        echo "\n";
        
        // Se foi processada, verificar se criou/atualizou conversation
        if ($e['status'] === 'processed') {
            echo "   ✅ Evento processado com sucesso!\n";
            
            // Buscar conversation atualizada após esse evento
            $contact = preg_replace('/@.*$/', '', $e['message_from'] ?: '');
            $contact = preg_replace('/[^0-9]/', '', $contact);
            
            if ($contact && strlen($contact) >= 10) {
                $sql2 = "SELECT id, channel_id, contact_external_id, updated_at, message_count
                FROM conversations
                WHERE tenant_id = 2
                  AND channel_id = ?
                  AND contact_external_id LIKE ?
                  AND updated_at >= ?
                ORDER BY updated_at DESC
                LIMIT 1";
                
                $stmt2 = $pdo->prepare($sql2);
                $stmt2->execute([$e['channel_id'], '%' . substr($contact, -10) . '%', $e['created_at']]);
                $conv = $stmt2->fetch(PDO::FETCH_ASSOC);
                
                if ($conv) {
                    echo sprintf("   ✅ Conversation atualizada: ID %d | Messages: %d | Updated: %s\n",
                        $conv['id'],
                        $conv['message_count'],
                        $conv['updated_at']
                    );
                } else {
                    echo "   ⚠️  Conversation não encontrada para esse contato\n";
                }
            }
        } else {
            echo "   ❌ Evento falhou: " . ($e['error_message'] ?: 'sem mensagem de erro') . "\n";
        }
    }
} else {
    echo "❌ Mensagem 'nova_mensagem_1927' não encontrada no banco.\n\n";
    
    // Buscar todas as mensagens do Pixel12 Digital nesse período
    echo "Buscando todas as mensagens do Pixel12 Digital (19:27-19:30)...\n\n";
    
    $sql2 = "SELECT id, created_at, status, error_message,
      JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
      JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text
    FROM communication_events
    WHERE source_system='wpp_gateway'
      AND created_at >= '2026-01-15 19:27:00'
      AND created_at <= '2026-01-15 19:30:00'
      AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) = 'Pixel12 Digital'
      AND (JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) = 'message' OR event_type LIKE '%message%')
    ORDER BY created_at DESC
    LIMIT 10";
    
    $stmt2 = $pdo->query($sql2);
    $allMessages = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($allMessages) > 0) {
        echo "Total: " . count($allMessages) . " mensagens encontradas\n\n";
        foreach ($allMessages as $m) {
            $icon = $m['status'] === 'processed' ? '✅' : '❌';
            echo sprintf("%s ID: %5d | %s | Status: %s | Text: %s\n",
                $icon,
                $m['id'],
                $m['created_at'],
                $m['status'],
                substr($m['message_text'] ?: 'SEM TEXTO', 0, 60)
            );
        }
    } else {
        echo "⚠️  Nenhuma mensagem do Pixel12 Digital encontrada nesse período.\n";
        echo "   A mensagem pode não ter chegado ao Hub ou está com outro channel_id.\n";
    }
}

echo "\n";


