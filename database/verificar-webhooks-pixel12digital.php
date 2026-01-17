<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== VERIFICAÇÃO: WEBHOOKS PIXEL12DIGITAL ===\n\n";

// 1. Verificar eventos recentes da sessão pixel12digital (últimas 2 horas)
echo "1) EVENTOS RECENTES PIXEL12DIGITAL (últimas 2 horas):\n";
echo str_repeat("=", 100) . "\n";

$sql1 = "SELECT id, created_at, event_type, status, 
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.session_id')) AS session_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS payload_event,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text
FROM communication_events
WHERE (JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) LIKE '%pixel12%' 
    OR JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.session_id')) LIKE '%pixel12%')
  AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
ORDER BY created_at DESC
LIMIT 50";

$stmt1 = $pdo->query($sql1);
$events = $stmt1->fetchAll(PDO::FETCH_ASSOC);

if (count($events) > 0) {
    echo "✅ Encontrados " . count($events) . " evento(s):\n\n";
    foreach ($events as $e) {
        $icon = $e['status'] === 'processed' ? '✅' : ($e['status'] === 'failed' ? '❌' : '⚠️');
        $text = $e['message_text'] ?: 'SEM TEXTO';
        echo sprintf("%s ID: %5d | %s | %s | Event: %s | Text: %s\n",
            $icon,
            $e['id'],
            $e['created_at'],
            $e['status'],
            $e['payload_event'] ?: $e['event_type'] ?: 'NULL',
            substr($text, 0, 50)
        );
    }
} else {
    echo "❌ Nenhum evento encontrado nas últimas 2 horas\n\n";
}

// 2. Verificar webhooks recebidos hoje (por status)
echo "\n2) WEBHOOKS HOJE (por status):\n";
echo str_repeat("=", 100) . "\n";

$sql2 = "SELECT status, COUNT(*) as total
FROM communication_events
WHERE (JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) LIKE '%pixel12%' 
    OR JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.session_id')) LIKE '%pixel12%')
  AND created_at >= DATE(NOW())
GROUP BY status";

$stmt2 = $pdo->query($sql2);
$stats = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (count($stats) > 0) {
    echo "📊 Estatísticas de hoje:\n";
    foreach ($stats as $s) {
        echo sprintf("   %s: %d\n", $s['status'], $s['total']);
    }
} else {
    echo "❌ Nenhum evento encontrado hoje\n";
}

// 3. Verificar último evento recebido
echo "\n3) ÚLTIMO EVENTO RECEBIDO:\n";
echo str_repeat("=", 100) . "\n";

$sql3 = "SELECT id, created_at, event_type, status,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS payload_event,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text
FROM communication_events
WHERE (JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) LIKE '%pixel12%' 
    OR JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.session_id')) LIKE '%pixel12%')
ORDER BY created_at DESC
LIMIT 1";

$stmt3 = $pdo->query($sql3);
$latest = $stmt3->fetch(PDO::FETCH_ASSOC);

if ($latest) {
    echo sprintf("✅ Último evento: ID %d\n", $latest['id']);
    echo sprintf("   Data/Hora: %s\n", $latest['created_at']);
    echo sprintf("   Status: %s\n", $latest['status']);
    echo sprintf("   Event: %s\n", $latest['payload_event'] ?: $latest['event_type'] ?: 'NULL');
    echo sprintf("   Channel: %s\n", $latest['channel_id'] ?: 'NULL');
    echo sprintf("   Text: %s\n", substr($latest['message_text'] ?: 'SEM TEXTO', 0, 80));
} else {
    echo "❌ Nenhum evento encontrado para pixel12digital\n";
}

// 4. Verificar eventos de tipo 'message' nas últimas 24 horas
echo "\n4) EVENTOS TIPO 'message' (últimas 24h):\n";
echo str_repeat("=", 100) . "\n";

$sql4 = "SELECT COUNT(*) as total
FROM communication_events
WHERE (JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) LIKE '%pixel12%' 
    OR JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.session_id')) LIKE '%pixel12%')
  AND (JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) = 'message' 
    OR event_type LIKE '%message%')
  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";

$stmt4 = $pdo->query($sql4);
$messageCount = $stmt4->fetch(PDO::FETCH_ASSOC);

echo sprintf("📨 Total de mensagens nas últimas 24h: %d\n", $messageCount['total']);

// 5. Resumo final
echo "\n" . str_repeat("=", 100) . "\n";
echo "RESUMO:\n";
echo str_repeat("=", 100) . "\n";
echo sprintf("  Eventos nas últimas 2h: %d\n", count($events));
echo sprintf("  Mensagens nas últimas 24h: %d\n", $messageCount['total']);

if (count($events) === 0) {
    echo "\n⚠️  PROBLEMA IDENTIFICADO:\n";
    echo "   Nenhum webhook está sendo processado para pixel12digital!\n";
    echo "   O gateway está enviando (status 200), mas não está chegando no banco.\n";
    echo "   Possíveis causas:\n";
    echo "   - Webhook está falhando silenciosamente no servidor\n";
    echo "   - Erro no processamento do payload\n";
    echo "   - Problema na rota /api/whatsapp/webhook\n";
}

echo "\n";

