<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== ANÁLISE: APENAS EVENTOS DE MENSAGEM ===\n\n";

// Filtrar apenas eventos de mensagem inbound
echo "1) EVENTOS DE MENSAGEM (whatsapp.inbound.message) do Pixel12 Digital:\n";
echo str_repeat("=", 100) . "\n";

$sql1 = "SELECT 
  id,
  event_type,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS payload_event,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  status,
  error_message,
  created_at
FROM communication_events
WHERE source_system='wpp_gateway'
  AND event_type = 'whatsapp.inbound.message'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id'))='Pixel12 Digital'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY id DESC
LIMIT 20";

$stmt1 = $pdo->query($sql1);
$messageEvents = $stmt1->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($messageEvents) . " eventos de mensagem\n\n";

$statusCounts = [];
foreach ($messageEvents as $me) {
    $status = $me['status'];
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    
    echo sprintf("ID: %4d | Status: %-10s | Payload Event: %s | Erro: %s\n",
        $me['id'],
        $me['status'],
        $me['payload_event'] ?? 'NULL',
        substr($me['error_message'] ?? 'OK', 0, 50)
    );
}

echo "\nResumo por status:\n";
foreach ($statusCounts as $status => $count) {
    echo "  - $status: $count evento(s)\n";
}

// 2) Verificar from_id apenas em eventos de mensagem
echo "\n\n2) FROM_ID NOS EVENTOS DE MENSAGEM (Pixel12 Digital):\n";
echo str_repeat("=", 100) . "\n";

$sql2 = "SELECT
  COALESCE(
    JSON_UNQUOTE(JSON_EXTRACT(payload,'$.message.from')),
    JSON_UNQUOTE(JSON_EXTRACT(payload,'$.from')),
    JSON_UNQUOTE(JSON_EXTRACT(payload,'$.data.from')),
    JSON_UNQUOTE(JSON_EXTRACT(payload,'$.message.key.remoteJid')),
    JSON_UNQUOTE(JSON_EXTRACT(payload,'$.message.key.participant')),
    JSON_UNQUOTE(JSON_EXTRACT(payload,'$.data.message.from')),
    'NO_FROM'
  ) AS from_id,
  COUNT(*) AS total,
  SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
  SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) AS processed_count
FROM communication_events
WHERE source_system='wpp_gateway'
  AND event_type = 'whatsapp.inbound.message'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id'))='Pixel12 Digital'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY from_id
ORDER BY total DESC
LIMIT 20";

$stmt2 = $pdo->query($sql2);
$fromIds = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Total de from_id únicos: " . count($fromIds) . "\n\n";

foreach ($fromIds as $fi) {
    $fromId = $fi['from_id'];
    $total = $fi['total'];
    $failed = $fi['failed_count'];
    $processed = $fi['processed_count'];
    
    $isLid = strpos($fromId, '@lid') !== false;
    $isJid = strpos($fromId, '@c.us') !== false || strpos($fromId, '@s.whatsapp.net') !== false;
    $isGroup = strpos($fromId, '@g.us') !== false;
    
    $type = $isLid ? '[@lid]' : ($isJid ? '[JID]' : ($isGroup ? '[GRUPO]' : '[OUTRO]'));
    
    $status = $failed > 0 ? '❌' : '✅';
    
    echo sprintf("%s [%3d total | %2d failed | %2d processed] %s %s\n",
        $status,
        $total,
        $failed,
        $processed,
        $type,
        $fromId
    );
    
    // Se for JID, sugerir número
    if ($isJid && $failed > 0) {
        $cleanNumber = preg_replace('/@.*$/', '', $fromId);
        $cleanNumber = preg_replace('/[^0-9]/', '', $cleanNumber);
        if (strlen($cleanNumber) >= 10) {
            echo "    💡 Phone sugerido: $cleanNumber\n";
        }
    }
}

// 3) Mostrar amostra de payload de evento de mensagem
echo "\n\n3) AMOSTRA: PAYLOAD DE EVENTO DE MENSAGEM:\n";
echo str_repeat("=", 100) . "\n";

$sql3 = "SELECT id, payload, status, error_message
FROM communication_events
WHERE source_system='wpp_gateway'
  AND event_type = 'whatsapp.inbound.message'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id'))='Pixel12 Digital'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY id DESC
LIMIT 3";

$stmt3 = $pdo->query($sql3);
$samples = $stmt3->fetchAll(PDO::FETCH_ASSOC);

foreach ($samples as $index => $sample) {
    echo "\nAmostra #" . ($index + 1) . " - ID: " . $sample['id'] . " | Status: " . $sample['status'] . "\n";
    echo str_repeat("-", 80) . "\n";
    
    $payload = json_decode($sample['payload'], true);
    
    // Extrair informações relevantes
    $from = $payload['message']['from'] 
        ?? $payload['from'] 
        ?? $payload['message']['key']['remoteJid']
        ?? $payload['message']['key']['participant']
        ?? 'NULL';
    
    $participant = $payload['message']['key']['participant'] ?? 'NULL';
    $remoteJid = $payload['message']['key']['remoteJid'] ?? 'NULL';
    
    echo "From: $from\n";
    echo "Participant: $participant\n";
    echo "RemoteJid: $remoteJid\n";
    
    if ($sample['error_message']) {
        echo "Erro: " . $sample['error_message'] . "\n";
    }
}

echo "\n";

