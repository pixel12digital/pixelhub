<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== BUSCA: MENSAGEM DO PIXEL12 DIGITAL ===\n\n";

// A conversation ID 34 foi atualizada às 19:23:41
// Vamos buscar todos os eventos do Pixel12 Digital próximos a esse horário
echo "1) EVENTOS DO PIXEL12 DIGITAL (19:20 - 19:25):\n";
echo str_repeat("=", 100) . "\n";

$sql1 = "SELECT id, created_at, event_type, status, error_message,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS payload_event,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) AS raw_body,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS message_from,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.to')) AS message_to
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= '2026-01-15 19:20:00'
  AND created_at <= '2026-01-15 19:25:00'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) = 'Pixel12 Digital'
ORDER BY created_at DESC";

$stmt1 = $pdo->query($sql1);
$events = $stmt1->fetchAll(PDO::FETCH_ASSOC);

if (count($events) > 0) {
    echo "Total encontrado: " . count($events) . "\n\n";
    
    $messages = [];
    $technical = [];
    
    foreach ($events as $e) {
        $isMessage = $e['payload_event'] === 'message' || strpos($e['event_type'], 'message') !== false;
        
        if ($isMessage) {
            $messages[] = $e;
            $icon = $e['status'] === 'processed' ? '✅' : '❌';
            $text = $e['message_text'] ?: $e['raw_body'] ?: 'SEM TEXTO';
            
            echo sprintf("%s ID: %5d | %s | Status: %-10s | From: %s | Text: %s\n",
                $icon,
                $e['id'],
                $e['created_at'],
                $e['status'],
                substr($e['message_from'] ?: 'NULL', 0, 25),
                substr($text, 0, 60)
            );
        } else {
            $technical[] = $e;
        }
    }
    
    echo "\n✅ Resumo:\n";
    echo sprintf("  Eventos de mensagem: %d\n", count($messages));
    echo sprintf("  Eventos técnicos: %d\n", count($technical));
    
    if (count($messages) === 0) {
        echo "\n⚠️  Nenhuma mensagem encontrada nesse período.\n";
        echo "   A conversation pode ter sido atualizada por um evento de status ou ack.\n";
    }
} else {
    echo "❌ Nenhum evento do Pixel12 Digital encontrado nesse período.\n";
}

// Verificar o payload completo de eventos próximos da atualização da conversation
echo "\n\n2) PAYLOAD COMPLETO - Evento mais recente do Pixel12 Digital (message):\n";
echo str_repeat("=", 100) . "\n";

$sql2 = "SELECT id, created_at, payload
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= '2026-01-15 19:20:00'
  AND created_at <= '2026-01-15 19:30:00'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) = 'Pixel12 Digital'
  AND (JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) = 'message' OR event_type LIKE '%message%')
ORDER BY created_at DESC
LIMIT 1";

$stmt2 = $pdo->query($sql2);
$latest = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($latest) {
    echo "Event ID: " . $latest['id'] . "\n";
    echo "Criado: " . $latest['created_at'] . "\n\n";
    
    $payload = json_decode($latest['payload'], true);
    
    echo "Estrutura do payload:\n";
    echo "  event: " . ($payload['event'] ?? 'NULL') . "\n";
    echo "  message.text: " . ($payload['message']['text'] ?? 'NULL') . "\n";
    echo "  message.from: " . ($payload['message']['from'] ?? 'NULL') . "\n";
    echo "  message.to: " . ($payload['message']['to'] ?? 'NULL') . "\n";
    echo "  raw.payload.body: " . ($payload['raw']['payload']['body'] ?? 'NULL') . "\n";
    echo "  raw.payload.content: " . ($payload['raw']['payload']['content'] ?? 'NULL') . "\n";
    
    if (isset($payload['raw']['payload'])) {
        echo "\n  Keys em raw.payload: " . implode(', ', array_keys($payload['raw']['payload'])) . "\n";
    }
} else {
    echo "❌ Nenhum evento de mensagem encontrado.\n";
}

// Verificar conversations atualizadas recentemente do Pixel12 Digital
echo "\n\n3) CONVERSATION DO PIXEL12 DIGITAL (ID 34):\n";
echo str_repeat("=", 100) . "\n";

$sql3 = "SELECT id, channel_id, contact_external_id, message_count, last_message_at, updated_at, created_at
FROM conversations
WHERE id = 34";

$stmt3 = $pdo->query($sql3);
$conv = $stmt3->fetch(PDO::FETCH_ASSOC);

if ($conv) {
    echo "Conversation ID: " . $conv['id'] . "\n";
    echo "Channel: " . $conv['channel_id'] . "\n";
    echo "Contact: " . $conv['contact_external_id'] . "\n";
    echo "Message Count: " . $conv['message_count'] . "\n";
    echo "Last Message At: " . ($conv['last_message_at'] ?: 'NULL') . "\n";
    echo "Updated At: " . $conv['updated_at'] . "\n";
    echo "Created At: " . $conv['created_at'] . "\n";
    
    // Se last_message_at está próximo do updated_at, significa que foi atualizada por uma mensagem
    if ($conv['last_message_at'] && abs(strtotime($conv['last_message_at']) - strtotime($conv['updated_at'])) < 60) {
        echo "\n✅ Conversation foi atualizada por uma mensagem recente (last_message_at próximo de updated_at).\n";
        echo "   Buscando evento próximo a esse horário...\n";
        
        // Buscar eventos próximos ao last_message_at
        $sql4 = "SELECT id, created_at, status,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) AS raw_body
        FROM communication_events
        WHERE source_system='wpp_gateway'
          AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) = 'Pixel12 Digital'
          AND created_at >= DATE_SUB(?, INTERVAL 2 MINUTE)
          AND created_at <= DATE_ADD(?, INTERVAL 2 MINUTE)
          AND (JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) = 'message' OR event_type LIKE '%message%')
        ORDER BY ABS(TIMESTAMPDIFF(SECOND, created_at, ?))
        LIMIT 3";
        
        $stmt4 = $pdo->prepare($sql4);
        $stmt4->execute([$conv['last_message_at'], $conv['last_message_at'], $conv['last_message_at']]);
        $nearby = $stmt4->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($nearby) > 0) {
            echo "\n   Eventos próximos encontrados:\n";
            foreach ($nearby as $n) {
                $text = $n['message_text'] ?: $n['raw_body'] ?: 'SEM TEXTO';
                echo sprintf("     ID: %5d | %s | Text: %s\n", $n['id'], $n['created_at'], substr($text, 0, 60));
            }
        }
    }
} else {
    echo "❌ Conversation não encontrada.\n";
}

echo "\n";


