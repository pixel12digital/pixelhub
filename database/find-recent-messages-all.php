<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== BUSCA AMPLA: TODAS AS MENSAGENS RECENTES ===\n\n";

// Buscar todas as mensagens de qualquer canal após 19:27
echo "1) TODAS AS MENSAGENS (qualquer canal, após 19:27):\n";
echo str_repeat("=", 100) . "\n";

$sql1 = "SELECT id, created_at, status, error_message,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS payload_event,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) AS raw_body,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.session.id')) AS session_id
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= '2026-01-15 19:27:00'
  AND (JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) = 'message' OR event_type LIKE '%message%')
ORDER BY created_at DESC
LIMIT 20";

$stmt1 = $pdo->query($sql1);
$allMessages = $stmt1->fetchAll(PDO::FETCH_ASSOC);

if (count($allMessages) > 0) {
    echo "Total: " . count($allMessages) . " mensagens\n\n";
    
    $found = false;
    foreach ($allMessages as $m) {
        $text = $m['message_text'] ?: $m['raw_body'] ?: 'SEM TEXTO';
        $isTarget = strpos(strtolower($text), 'nova_mensagem_1927') !== false || 
                    strpos(strtolower($text), '1927') !== false;
        
        $icon = $m['status'] === 'processed' ? '✅' : '❌';
        if ($isTarget) {
            $found = true;
            echo sprintf("🎯 %s ID: %5d | %s | Status: %-10s | Channel: %-20s | Session: %s\n",
                $icon,
                $m['id'],
                $m['created_at'],
                $m['status'],
                $m['channel_id'] ?: 'NULL',
                $m['session_id'] ?: 'NULL'
            );
            echo sprintf("   Text: %s\n", substr($text, 0, 80));
            if ($m['error_message']) {
                echo sprintf("   Erro: %s\n", substr($m['error_message'], 0, 60));
            }
            echo "\n";
        } else {
            echo sprintf("   %s ID: %5d | %s | Status: %-10s | Channel: %-20s | Text: %s\n",
                $icon,
                $m['id'],
                $m['created_at'],
                $m['status'],
                $m['channel_id'] ?: 'NULL',
                substr($text, 0, 50)
            );
        }
    }
    
    if (!$found) {
        echo "\n⚠️  Mensagem 'nova_mensagem_1927' não encontrada nas mensagens recentes.\n";
    }
} else {
    echo "❌ Nenhuma mensagem encontrada após 19:27.\n";
}

// Buscar eventos com text contendo "nova_mensagem" ou "1927"
echo "\n\n2) BUSCA POR TEXTO: 'nova_mensagem' ou '1927' (qualquer período hoje):\n";
echo str_repeat("=", 100) . "\n";

$sql2 = "SELECT id, created_at, status,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) AS raw_body
FROM communication_events
WHERE source_system='wpp_gateway'
  AND DATE(created_at) = CURDATE()
  AND (
    payload LIKE '%nova_mensagem%'
    OR payload LIKE '%1927%'
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) LIKE '%nova_mensagem%'
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) LIKE '%1927%'
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) LIKE '%nova_mensagem%'
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) LIKE '%1927%'
  )
ORDER BY created_at DESC
LIMIT 10";

$stmt2 = $pdo->query($sql2);
$textMatches = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (count($textMatches) > 0) {
    echo "Total encontrado: " . count($textMatches) . " eventos\n\n";
    foreach ($textMatches as $tm) {
        $icon = $tm['status'] === 'processed' ? '✅' : '❌';
        $text = $tm['message_text'] ?: $tm['raw_body'] ?: 'SEM TEXTO';
        
        echo sprintf("%s ID: %5d | %s | Status: %-10s | Channel: %-20s | Text: %s\n",
            $icon,
            $tm['id'],
            $tm['created_at'],
            $tm['status'],
            $tm['channel_id'] ?: 'NULL',
            substr($text, 0, 70)
        );
    }
} else {
    echo "❌ Nenhum evento com 'nova_mensagem' ou '1927' encontrado hoje.\n";
}

// Verificar se há eventos do Pixel12 Digital com status diferente
echo "\n\n3) EVENTOS DO PIXEL12 DIGITAL (qualquer tipo, após 19:27):\n";
echo str_repeat("=", 100) . "\n";

$sql3 = "SELECT id, created_at, event_type, status, error_message,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS payload_event
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= '2026-01-15 19:27:00'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) = 'Pixel12 Digital'
ORDER BY created_at DESC
LIMIT 10";

$stmt3 = $pdo->query($sql3);
$pixel12Events = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (count($pixel12Events) > 0) {
    echo "Total: " . count($pixel12Events) . " eventos\n\n";
    foreach ($pixel12Events as $pe) {
        $icon = $pe['status'] === 'processed' ? '✅' : ($pe['status'] === 'queued' ? '⏳' : '❌');
        echo sprintf("%s ID: %5d | %s | Type: %s | Status: %-10s\n",
            $icon,
            $pe['id'],
            $pe['created_at'],
            substr($pe['payload_event'] ?: $pe['event_type'], 0, 25),
            $pe['status']
        );
    }
} else {
    echo "❌ Nenhum evento do Pixel12 Digital encontrado após 19:27.\n";
    echo "\n⚠️  CONCLUSÃO:\n";
    echo "   O canal Pixel12 Digital não está recebendo eventos após 19:27.\n";
    echo "   Possíveis causas:\n";
    echo "   1. Problema de conexão do WPPConnect com o Pixel12 Digital\n";
    echo "   2. Gateway-wrapper não está enviando eventos desse canal\n";
    echo "   3. Session do Pixel12 Digital desconectada ou com problema\n";
    echo "   4. Mensagem foi enviada mas não chegou ao Hub (problema no wrapper)\n";
}

echo "\n";


