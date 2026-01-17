<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== BUSCA: PIXEL12-ONMESSAGE-03 ===\n\n";

$searchTerm = "PIXEL12-ONMESSAGE-03";

// 1. Buscar em communication_events (payload JSON)
echo "1) BUSCANDO EM communication_events (payload):\n";
echo str_repeat("=", 100) . "\n";

$sql1 = "SELECT id, created_at, event_type, status, 
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.session_id')) AS session_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS payload_event,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) AS raw_body,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS message_from,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.to')) AS message_to
FROM communication_events
WHERE payload LIKE ?
   OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) LIKE ?
   OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) LIKE ?
ORDER BY created_at DESC
LIMIT 20";

$stmt1 = $pdo->prepare($sql1);
$likeTerm = "%{$searchTerm}%";
$stmt1->execute([$likeTerm, $likeTerm, $likeTerm]);
$events = $stmt1->fetchAll(PDO::FETCH_ASSOC);

if (count($events) > 0) {
    echo "✅ Encontrados " . count($events) . " evento(s):\n\n";
    foreach ($events as $e) {
        $text = $e['message_text'] ?: $e['raw_body'] ?: 'SEM TEXTO';
        echo sprintf("   ID: %5d | %s | Status: %-10s | Channel: %s | Session: %s\n",
            $e['id'],
            $e['created_at'],
            $e['status'],
            $e['channel_id'] ?: 'NULL',
            $e['session_id'] ?: 'NULL'
        );
        echo sprintf("   From: %s | Text: %s\n\n", 
            $e['message_from'] ?: 'NULL',
            substr($text, 0, 100)
        );
    }
} else {
    echo "❌ Nenhum evento encontrado em communication_events\n\n";
}

// 2. Buscar em chat_messages (campo content - estrutura diferente)
echo "\n2) BUSCANDO EM chat_messages (content):\n";
echo str_repeat("=", 100) . "\n";

$sql2 = "SELECT id, created_at, thread_id, role, content
FROM chat_messages
WHERE content LIKE ?
ORDER BY created_at DESC
LIMIT 20";

$stmt2 = $pdo->prepare($sql2);
$stmt2->execute([$likeTerm]);
$messages = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (count($messages) > 0) {
    echo "✅ Encontradas " . count($messages) . " mensagem(ns):\n\n";
    foreach ($messages as $m) {
        echo sprintf("   ID: %5d | %s | Thread: %s | Role: %s\n",
            $m['id'],
            $m['created_at'],
            $m['thread_id'],
            $m['role']
        );
        echo sprintf("   Text: %s\n\n", substr($m['content'] ?: 'SEM TEXTO', 0, 100));
    }
} else {
    echo "❌ Nenhuma mensagem encontrada em chat_messages\n\n";
}

// 3. Buscar variações (pixel12digital, pixel12 digital, etc.)
echo "\n3) BUSCANDO VARIAÇÕES (pixel12digital + ONMESSAGE-03):\n";
echo str_repeat("=", 100) . "\n";

$sql3 = "SELECT id, created_at, event_type, status,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.session_id')) AS session_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) AS raw_body
FROM communication_events
WHERE (JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) LIKE '%pixel12%' 
    OR JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.session_id')) LIKE '%pixel12%')
  AND (payload LIKE '%ONMESSAGE-03%' 
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) LIKE '%ONMESSAGE-03%'
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) LIKE '%ONMESSAGE-03%')
ORDER BY created_at DESC
LIMIT 20";

$stmt3 = $pdo->query($sql3);
$variations = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (count($variations) > 0) {
    echo "✅ Encontrados " . count($variations) . " evento(s) com variações:\n\n";
    foreach ($variations as $v) {
        $text = $v['message_text'] ?: $v['raw_body'] ?: 'SEM TEXTO';
        echo sprintf("   ID: %5d | %s | Channel: %s | Session: %s\n",
            $v['id'],
            $v['created_at'],
            $v['channel_id'] ?: 'NULL',
            $v['session_id'] ?: 'NULL'
        );
        echo sprintf("   Text: %s\n\n", substr($text, 0, 100));
    }
} else {
    echo "❌ Nenhuma variação encontrada\n\n";
}

// 4. Resumo final
echo "\n" . str_repeat("=", 100) . "\n";
echo "RESUMO:\n";
echo str_repeat("=", 100) . "\n";
echo sprintf("  communication_events: %d resultado(s)\n", count($events));
echo sprintf("  chat_messages: %d resultado(s)\n", count($messages));
echo sprintf("  Variações pixel12digital: %d resultado(s)\n", count($variations));

if (count($events) === 0 && count($messages) === 0 && count($variations) === 0) {
    echo "\n⚠️  MENSAGEM NÃO ENCONTRADA!\n";
    echo "   A mensagem 'PIXEL12-ONMESSAGE-03' não foi localizada no banco de dados.\n";
    echo "   Possibilidades:\n";
    echo "   - Mensagem ainda não foi recebida/processada\n";
    echo "   - Mensagem está em logs de arquivo (não no BD)\n";
    echo "   - Mensagem está em outra tabela/nome\n";
} else {
    echo "\n✅ MENSAGEM LOCALIZADA!\n";
}

echo "\n";

