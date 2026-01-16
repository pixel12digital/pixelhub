<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== VALIDAÇÃO PÓS-DEPLOY (eventos após 19:20) ===\n\n";

// Query 1: Eventos técnicos após deploy
echo "1) EVENTOS TÉCNICOS (após 19:20):\n";
echo str_repeat("=", 100) . "\n";

$sql1 = "SELECT
  JSON_UNQUOTE(JSON_EXTRACT(payload,'$.event')) AS payload_event,
  status,
  COUNT(*) AS total
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= '2026-01-15 19:20:00'
  AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.event')) IN ('connection.update','status-find','onpresencechanged','onack','onstatechanged')
GROUP BY payload_event, status
ORDER BY payload_event, status";

$stmt1 = $pdo->query($sql1);
$technical = $stmt1->fetchAll(PDO::FETCH_ASSOC);

$techProcessed = 0;
$techFailed = 0;
foreach ($technical as $t) {
    $eventType = $t['payload_event'] ?: 'NULL';
    $status = $t['status'];
    $total = $t['total'];
    
    if ($status === 'processed') $techProcessed += $total;
    if ($status === 'failed') $techFailed += $total;
    
    $icon = $status === 'processed' ? '✅' : '❌';
    echo sprintf("  %s %-25s | %-12s | %4d eventos\n", $icon, $eventType, $status, $total);
}

echo sprintf("\n✅ Resumo: %d processed, %d failed\n", $techProcessed, $techFailed);
if ($techFailed === 0) {
    echo "✅ PERFEITO: Nenhum evento técnico falhando após deploy!\n";
} else {
    $failRate = round(($techFailed / ($techProcessed + $techFailed)) * 100, 1);
    echo sprintf("⚠️  Taxa de falha: %.1f%%\n", $failRate);
}

// Query 2: Mensagens de teste
echo "\n\n2) MENSAGENS DE TESTE (teste1921):\n";
echo str_repeat("=", 100) . "\n";

$sql2 = "SELECT id, created_at, status, error_message,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= '2026-01-15 19:20:00'
  AND (
    payload LIKE '%teste1921%'
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) LIKE '%teste1921%'
  )
ORDER BY id DESC";

$stmt2 = $pdo->query($sql2);
$testMessages = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (count($testMessages) > 0) {
    echo "Total: " . count($testMessages) . " mensagens de teste\n\n";
    foreach ($testMessages as $tm) {
        $icon = $tm['status'] === 'processed' ? '✅' : '❌';
        echo sprintf("  %s ID: %5d | Status: %-10s | Channel: %-20s | Text: %s\n",
            $icon,
            $tm['id'],
            $tm['status'],
            $tm['channel_id'] ?: 'NULL',
            substr($tm['message_text'] ?: 'NULL', 0, 40)
        );
    }
    
    $processed = 0;
    $failed = 0;
    $imobsites = false;
    $pixel12 = false;
    
    foreach ($testMessages as $tm) {
        if ($tm['status'] === 'processed') $processed++;
        if ($tm['status'] === 'failed') $failed++;
        if ($tm['channel_id'] === 'ImobSites') $imobsites = true;
        if ($tm['channel_id'] === 'Pixel12 Digital') $pixel12 = true;
    }
    
    echo "\n✅ Resumo:\n";
    echo sprintf("  Processadas: %d\n", $processed);
    echo sprintf("  Falhadas: %d\n", $failed);
    echo sprintf("  ImobSites: %s\n", $imobsites ? '✅' : '❌');
    echo sprintf("  Pixel12 Digital: %s\n", $pixel12 ? '✅' : '❌');
} else {
    echo "⚠️  Nenhuma mensagem de teste encontrada após 19:20.\n";
}

// Query 3: Conversations criadas/atualizadas após deploy
echo "\n\n3) CONVERSATIONS CRIADAS/ATUALIZADAS (após 19:20):\n";
echo str_repeat("=", 100) . "\n";

$sql3 = "SELECT id, channel_id, contact_external_id, updated_at, created_at
FROM conversations
WHERE tenant_id = 2
  AND updated_at >= '2026-01-15 19:20:00'
ORDER BY updated_at DESC";

$stmt3 = $pdo->query($sql3);
$convs = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (count($convs) > 0) {
    echo "Total: " . count($convs) . " conversations\n\n";
    foreach ($convs as $c) {
        echo sprintf("  ✅ ID: %3d | Channel: %-20s | Contact: %s | Updated: %s\n",
            $c['id'],
            $c['channel_id'] ?: 'NULL',
            $c['contact_external_id'] ?: 'NULL',
            $c['updated_at']
        );
    }
    
    $channels = [];
    foreach ($convs as $c) {
        $channel = $c['channel_id'] ?: 'NULL';
        if (!isset($channels[$channel])) {
            $channels[$channel] = 0;
        }
        $channels[$channel]++;
    }
    
    echo "\n✅ Resumo por canal:\n";
    foreach ($channels as $channel => $count) {
        echo sprintf("  %-25s: %d conversation(s)\n", $channel, $count);
    }
} else {
    echo "⚠️  Nenhuma conversation atualizada após 19:20.\n";
}

// Query 4: Saúde geral após deploy
echo "\n\n4) SAÚDE GERAL (após 19:20):\n";
echo str_repeat("=", 100) . "\n";

$sql4 = "SELECT
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  status,
  COUNT(*) AS total
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= '2026-01-15 19:20:00'
GROUP BY channel_id, status
ORDER BY channel_id, status";

$stmt4 = $pdo->query($sql4);
$health = $stmt4->fetchAll(PDO::FETCH_ASSOC);

$channelStats = [];
foreach ($health as $h) {
    $channel = $h['channel_id'] ?: 'NULL';
    $status = $h['status'];
    $total = $h['total'];
    
    if (!isset($channelStats[$channel])) {
        $channelStats[$channel] = ['processed' => 0, 'failed' => 0, 'queued' => 0];
    }
    $channelStats[$channel][$status] = $total;
    
    echo sprintf("  %-25s | %-12s | %4d eventos\n", $channel, $status, $total);
}

echo "\n✅ Resumo por canal:\n";
foreach ($channelStats as $channel => $stats) {
    $processed = $stats['processed'] ?? 0;
    $failed = $stats['failed'] ?? 0;
    $queued = $stats['queued'] ?? 0;
    $total = $processed + $failed + $queued;
    $failRate = $total > 0 ? round(($failed / $total) * 100, 1) : 0;
    
    $icon = $failRate < 5 ? '✅' : ($failRate < 20 ? '⚠️' : '❌');
    echo sprintf("  %s %-25s: %d processed, %d failed, %d queued (%.1f%% falha)\n",
        $icon, $channel, $processed, $failed, $queued, $failRate);
}

echo "\n" . str_repeat("=", 100) . "\n";
echo "VALIDAÇÃO PÓS-DEPLOY CONCLUÍDA\n";
echo str_repeat("=", 100) . "\n";


