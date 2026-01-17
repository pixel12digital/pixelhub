<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== VERIFICAÇÃO: INBOUND-TEST-010 ===\n\n";

$searchTerm = "INBOUND-TEST-010";

// 1. Buscar mensagem exata
echo "1) BUSCANDO MENSAGEM 'INBOUND-TEST-010':\n";
echo str_repeat("=", 100) . "\n";

$sql1 = "SELECT id, created_at, event_type, status,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.session_id')) AS session_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS payload_event,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) AS raw_body,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS message_from,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.to')) AS message_to,
  error_message
FROM communication_events
WHERE (payload LIKE ? 
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) LIKE ?
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) LIKE ?)
  AND (JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) LIKE '%pixel12%' 
    OR JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.session_id')) LIKE '%pixel12%')
ORDER BY created_at DESC
LIMIT 5";

$stmt1 = $pdo->prepare($sql1);
$likeTerm = "%{$searchTerm}%";
$stmt1->execute([$likeTerm, $likeTerm, $likeTerm]);
$events = $stmt1->fetchAll(PDO::FETCH_ASSOC);

if (count($events) > 0) {
    echo "✅ Encontrada(s) " . count($events) . " mensagem(ns):\n\n";
    foreach ($events as $e) {
        $icon = $e['status'] === 'processed' ? '✅' : ($e['status'] === 'failed' ? '❌' : '⚠️');
        $text = $e['message_text'] ?: $e['raw_body'] ?: 'SEM TEXTO';
        
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
        echo sprintf("   From: %s | Text: %s\n",
            substr($e['message_from'] ?: 'NULL', 0, 30),
            substr($text, 0, 80)
        );
        if ($e['error_message']) {
            echo sprintf("   ⚠️  Erro: %s\n", substr($e['error_message'], 0, 100));
        }
        echo "\n";
    }
} else {
    echo "❌ Mensagem 'INBOUND-TEST-010' NÃO ENCONTRADA no banco de dados\n\n";
}

// 2. Verificar mensagens mais recentes (últimos 5 minutos)
echo "\n2) MENSAGENS RECENTES (últimos 5 minutos):\n";
echo str_repeat("=", 100) . "\n";

$sql2 = "SELECT id, created_at, status,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS payload_event,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text
FROM communication_events
WHERE (JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) LIKE '%pixel12%' 
    OR JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.session_id')) LIKE '%pixel12%')
  AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
ORDER BY created_at DESC
LIMIT 10";

$stmt2 = $pdo->query($sql2);
$recent = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (count($recent) > 0) {
    echo "📨 Mensagens recentes:\n\n";
    foreach ($recent as $r) {
        $icon = $r['status'] === 'processed' ? '✅' : ($r['status'] === 'failed' ? '❌' : '⚠️');
        $text = $r['message_text'] ?: 'SEM TEXTO';
        echo sprintf("%s %s | %s | Text: %s\n",
            $icon,
            $r['created_at'],
            $r['status'],
            substr($text, 0, 60)
        );
    }
} else {
    echo "❌ Nenhuma mensagem encontrada nos últimos 5 minutos\n";
}

// 3. Resumo final
echo "\n" . str_repeat("=", 100) . "\n";
echo "RESUMO:\n";
echo str_repeat("=", 100) . "\n";
echo sprintf("  Mensagem 'INBOUND-TEST-010' encontrada: %s\n", count($events) > 0 ? "✅ SIM" : "❌ NÃO");
echo sprintf("  Mensagens recentes (5 min): %d\n", count($recent));

if (count($events) === 0) {
    echo "\n⚠️  MENSAGEM AINDA NÃO FOI PROCESSADA!\n";
    echo "   Aguarde alguns segundos e execute novamente, ou verifique:\n";
    echo "   - Logs do webhook (HUB_WEBHOOK_IN)\n";
    echo "   - Se o gateway enviou o webhook\n";
}

echo "\n";

