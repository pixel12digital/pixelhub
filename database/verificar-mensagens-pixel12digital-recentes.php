<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== MENSAGENS RECENTES - SESSÃO PIXEL12DIGITAL ===\n\n";

// Buscar últimas 50 mensagens da sessão pixel12digital
echo "1) ÚLTIMAS 50 MENSAGENS DA SESSÃO PIXEL12DIGITAL:\n";
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
WHERE (JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) LIKE '%pixel12%' 
    OR JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) LIKE '%Pixel12%'
    OR JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.session_id')) LIKE '%pixel12%'
    OR JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.session_id')) LIKE '%Pixel12%')
  AND (JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) = 'message'
    OR event_type LIKE '%message%')
ORDER BY created_at DESC
LIMIT 50";

$stmt1 = $pdo->query($sql1);
$events = $stmt1->fetchAll(PDO::FETCH_ASSOC);

if (count($events) > 0) {
    echo "✅ Encontrados " . count($events) . " evento(s) de mensagem:\n\n";
    foreach ($events as $e) {
        $text = $e['message_text'] ?: $e['raw_body'] ?: 'SEM TEXTO';
        $icon = $e['status'] === 'processed' ? '✅' : ($e['status'] === 'failed' ? '❌' : '⚠️');
        
        echo sprintf("%s ID: %5d | %s | Status: %-10s\n",
            $icon,
            $e['id'],
            $e['created_at'],
            $e['status']
        );
        echo sprintf("   Channel: %s | Session: %s\n",
            $e['channel_id'] ?: 'NULL',
            $e['session_id'] ?: 'NULL'
        );
        echo sprintf("   From: %s | Text: %s\n\n", 
            substr($e['message_from'] ?: 'NULL', 0, 25),
            substr($text, 0, 80)
        );
        
        // Destacar se contém "ONMESSAGE" ou "03"
        if (stripos($text, 'ONMESSAGE') !== false || stripos($text, '-03') !== false) {
            echo "   ⚠️  ATENÇÃO: Esta mensagem contém padrões relacionados!\n\n";
        }
    }
} else {
    echo "❌ Nenhum evento de mensagem encontrado para pixel12digital\n\n";
}

// Buscar qualquer texto que contenha "03" nas últimas 100 mensagens
echo "\n2) MENSAGENS COM '03' OU 'ONMESSAGE' (últimas 100):\n";
echo str_repeat("=", 100) . "\n";

$sql2 = "SELECT id, created_at, event_type, status,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.session_id')) AS session_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) AS raw_body
FROM communication_events
WHERE (payload LIKE '%03%' OR payload LIKE '%ONMESSAGE%' OR payload LIKE '%onmessage%')
  AND (JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) LIKE '%pixel12%'
    OR JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.session_id')) LIKE '%pixel12%')
ORDER BY created_at DESC
LIMIT 100";

$stmt2 = $pdo->query($sql2);
$related = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (count($related) > 0) {
    echo "✅ Encontrados " . count($related) . " evento(s) com padrões relacionados:\n\n";
    foreach ($related as $r) {
        $text = $r['message_text'] ?: $r['raw_body'] ?: 'SEM TEXTO';
        echo sprintf("   ID: %5d | %s | Channel: %s\n",
            $r['id'],
            $r['created_at'],
            $r['channel_id'] ?: 'NULL'
        );
        echo sprintf("   Text: %s\n\n", substr($text, 0, 100));
    }
} else {
    echo "❌ Nenhum evento relacionado encontrado\n\n";
}

echo "\n" . str_repeat("=", 100) . "\n";
echo "CONCLUSÃO:\n";
echo str_repeat("=", 100) . "\n";
echo sprintf("  Total de mensagens pixel12digital: %d\n", count($events));
echo sprintf("  Total com padrões '03' ou 'ONMESSAGE': %d\n", count($related));

if (count($events) === 0) {
    echo "\n⚠️  NENHUMA MENSAGEM ENCONTRADA para a sessão pixel12digital!\n";
    echo "   Isso pode indicar:\n";
    echo "   - A sessão não está recebendo mensagens\n";
    echo "   - As mensagens estão sendo processadas com outro nome/canal\n";
    echo "   - As mensagens estão apenas em logs de arquivo\n";
}

echo "\n";

