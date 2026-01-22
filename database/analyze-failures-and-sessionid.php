<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== ANÁLISE DE FALHAS E SESSIONID ===\n\n";

// 1) Top 20 erros mais comuns do Pixel12 Digital
echo "1) TOP 20 ERROS MAIS COMUNS (Pixel12 Digital - últimas 24h):\n";
echo str_repeat("=", 100) . "\n";

$sql1 = "SELECT
  COALESCE(error_message, 'NO_MESSAGE') AS error_message,
  COUNT(*) AS total
FROM communication_events
WHERE source_system = 'wpp_gateway'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) = 'Pixel12 Digital'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
  AND status = 'failed'
GROUP BY error_message
ORDER BY total DESC
LIMIT 20";

$stmt1 = $pdo->query($sql1);
$errors = $stmt1->fetchAll(PDO::FETCH_ASSOC);

echo "Total de erros únicos: " . count($errors) . "\n\n";

foreach ($errors as $err) {
    echo sprintf("  [%3d vezes] Message: %s\n",
        $err['total'],
        substr($err['error_message'], 0, 150)
    );
}

// 2) Amostras reais dos últimos failures
echo "\n\n2) AMOSTRAS REAIS DOS ÚLTIMOS FAILURES (últimos 30):\n";
echo str_repeat("=", 100) . "\n";

$sql2 = "SELECT id, created_at, status,
       LEFT(COALESCE(error_message,''), 180) AS error_message,
       JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.raw_event_type')) AS eventType,
       JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id
FROM communication_events
WHERE source_system = 'wpp_gateway'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) = 'Pixel12 Digital'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
  AND status = 'failed'
ORDER BY id DESC
LIMIT 30";

$stmt2 = $pdo->query($sql2);
$failures = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($failures) . " eventos falhados\n\n";

foreach ($failures as $f) {
    echo sprintf("ID: %4d | Criado: %s | Message: %s\n",
        $f['id'],
        $f['created_at'],
        substr($f['error_message'] ?: 'NULL', 0, 120)
    );
}

// 3) Verificar sessionId no payload
echo "\n\n3) VERIFICAÇÃO: sessionId NO PAYLOAD (últimos 10 eventos Pixel12 Digital):\n";
echo str_repeat("=", 100) . "\n";

$sql3 = "SELECT id, status,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.sessionId')) AS p_sessionId,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.session'))   AS p_session,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.session_id')) AS p_session_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.eventType')) AS p_eventType,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.data.sessionId')) AS p_data_sessionId,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.data.session'))   AS p_data_session,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.channel')) AS p_channel,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.channelId')) AS p_channelId,
  JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS metadata_channel_id
FROM communication_events
WHERE source_system='wpp_gateway'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id'))='Pixel12 Digital'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY id DESC
LIMIT 10";

$stmt3 = $pdo->query($sql3);
$sessionChecks = $stmt3->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($sessionChecks) . " eventos\n\n";

foreach ($sessionChecks as $sc) {
    echo sprintf("ID: %4d | Status: %-10s\n", $sc['id'], $sc['status']);
    echo "  payload.sessionId: " . ($sc['p_sessionId'] ?? 'NULL') . "\n";
    echo "  payload.session: " . ($sc['p_session'] ?? 'NULL') . "\n";
    echo "  payload.session_id: " . ($sc['p_session_id'] ?? 'NULL') . "\n";
    echo "  payload.data.sessionId: " . ($sc['p_data_sessionId'] ?? 'NULL') . "\n";
    echo "  payload.data.session: " . ($sc['p_data_session'] ?? 'NULL') . "\n";
    echo "  payload.channel: " . ($sc['p_channel'] ?? 'NULL') . "\n";
    echo "  payload.channelId: " . ($sc['p_channelId'] ?? 'NULL') . "\n";
    echo "  metadata.channel_id: " . ($sc['metadata_channel_id'] ?? 'NULL') . "\n";
    echo "\n";
}

// 4) Verificar eventos de teste recentes do ImobSites
echo "\n\n4) EVENTOS RECENTES COM 'teste_imobsites' (última 1h):\n";
echo str_repeat("=", 100) . "\n";

$sql4 = "SELECT id, created_at, status,
       JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
       LEFT(COALESCE(error_message,''),160) AS error_message,
       JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.body')) AS message_body
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
  AND (
      payload LIKE '%teste_imobsites%' 
      OR payload LIKE '%teste%imobsites%'
      OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.body')) LIKE '%teste%imobsites%'
  )
ORDER BY id DESC
LIMIT 20";

$stmt4 = $pdo->query($sql4);
$testEvents = $stmt4->fetchAll(PDO::FETCH_ASSOC);

if (count($testEvents) > 0) {
    echo "Total: " . count($testEvents) . " eventos\n\n";
    foreach ($testEvents as $te) {
        echo sprintf("ID: %4d | Status: %-10s | Channel: %-20s | Mensagem: %s | Erro: %s\n",
            $te['id'],
            $te['status'],
            $te['channel_id'] ?? 'NULL',
            substr($te['message_body'] ?? 'NULL', 0, 40),
            substr($te['error_message'] ?: 'OK', 0, 40)
        );
    }
} else {
    echo "Nenhum evento de teste encontrado na última hora.\n";
}

// 5) Verificar conversations recentes
echo "\n\n5) CONVERSATIONS RECENTES (últimas 20):\n";
echo str_repeat("=", 100) . "\n";

$sql5 = "SELECT id, tenant_id, channel_id, contact_external_id, message_count, updated_at, created_at
FROM conversations
WHERE tenant_id = 2
ORDER BY updated_at DESC
LIMIT 20";

$stmt5 = $pdo->query($sql5);
$convs = $stmt5->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($convs) . " conversations\n\n";

foreach ($convs as $c) {
    $isNew = strtotime($c['updated_at']) >= strtotime('-1 hour');
    $icon = $isNew ? '🆕' : '🔄';
    echo sprintf("%s ID: %3d | Channel: %-20s | Contact: %s | Messages: %2d | Updated: %s\n",
        $icon,
        $c['id'],
        $c['channel_id'] ?? 'NULL',
        $c['contact_external_id'] ?? 'NULL',
        $c['message_count'],
        $c['updated_at']
    );
}

echo "\n";

