<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== VALIDAÇÃO DAS CORREÇÕES ===\n";
echo "Período: Últimos 60 minutos (eventos) e 24 horas (alguns)\n\n";

// Query 0: Verificar estrutura da tabela
echo "0) ESTRUTURA DA TABELA:\n";
echo str_repeat("=", 100) . "\n";
$checkColumns = $pdo->query("SHOW COLUMNS FROM communication_events LIKE '%conversation%'");
$columns = $checkColumns->fetchAll(PDO::FETCH_ASSOC);
if (count($columns) > 0) {
    echo "Colunas de conversation encontradas:\n";
    foreach ($columns as $col) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} else {
    echo "⚠️  Nenhuma coluna de conversation encontrada (query 5 será ignorada)\n";
}
$hasConversationId = count($columns) > 0;

// Query 1: Saúde geral por canal (últimos 60 min)
echo "\n\n1) SAÚDE GERAL POR CANAL (últimos 60 min):\n";
echo str_repeat("=", 100) . "\n";

$sql1 = "SELECT
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  status,
  COUNT(*) AS total
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)
GROUP BY channel_id, status
ORDER BY channel_id, status";

$stmt1 = $pdo->query($sql1);
$health = $stmt1->fetchAll(PDO::FETCH_ASSOC);

$totals = [];
foreach ($health as $h) {
    $channel = $h['channel_id'] ?: 'NULL';
    $status = $h['status'];
    $total = $h['total'];
    
    if (!isset($totals[$channel])) {
        $totals[$channel] = [];
    }
    $totals[$channel][$status] = $total;
    
    echo sprintf("  %-25s | %-12s | %4d eventos\n", $channel, $status, $total);
}

echo "\n✅ Resumo por canal:\n";
foreach ($totals as $channel => $statuses) {
    $failed = $statuses['failed'] ?? 0;
    $processed = $statuses['processed'] ?? 0;
    $total = array_sum($statuses);
    $failRate = $total > 0 ? round(($failed / $total) * 100, 1) : 0;
    
    $icon = $failRate < 5 ? '✅' : ($failRate < 20 ? '⚠️' : '❌');
    echo sprintf("  %s %-25s: %d processed, %d failed (%.1f%% falha)\n", 
        $icon, $channel, $processed, $failed, $failRate);
}

// Query 2: Eventos técnicos não devem mais falhar
echo "\n\n2) EVENTOS TÉCNICOS (últimas 24h):\n";
echo str_repeat("=", 100) . "\n";

$sql2 = "SELECT
  COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.raw_event_type')), JSON_UNQUOTE(JSON_EXTRACT(payload,'$.event'))) AS eventType,
  status,
  COUNT(*) AS total
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
  AND (
    JSON_UNQUOTE(JSON_EXTRACT(payload,'$.event')) IN ('connection.update','status-find','onpresencechanged','onack','onstatechanged')
    OR event_type LIKE '%connection%'
    OR event_type LIKE '%status%'
  )
GROUP BY eventType, status
ORDER BY total DESC";

$stmt2 = $pdo->query($sql2);
$technical = $stmt2->fetchAll(PDO::FETCH_ASSOC);

$techTotals = [];
foreach ($technical as $t) {
    $eventType = $t['eventType'] ?: 'NULL';
    $status = $t['status'];
    $total = $t['total'];
    
    if (!isset($techTotals[$eventType])) {
        $techTotals[$eventType] = [];
    }
    $techTotals[$eventType][$status] = $total;
    
    $icon = ($status === 'processed') ? '✅' : '❌';
    echo sprintf("  %s %-30s | %-12s | %4d eventos\n", $icon, substr($eventType, 0, 30), $status, $total);
}

echo "\n✅ Resumo eventos técnicos:\n";
$techFailed = 0;
$techProcessed = 0;
foreach ($techTotals as $eventType => $statuses) {
    $failed = $statuses['failed'] ?? 0;
    $processed = $statuses['processed'] ?? 0;
    $techFailed += $failed;
    $techProcessed += $processed;
}
$techTotal = $techFailed + $techProcessed;
$techFailRate = $techTotal > 0 ? round(($techFailed / $techTotal) * 100, 1) : 0;
echo sprintf("  Total: %d processed, %d failed (%.1f%% falha)\n", $techProcessed, $techFailed, $techFailRate);

// Query 3: Erros específicos
echo "\n\n3) ERROS ESPECÍFICOS (últimas 24h):\n";
echo str_repeat("=", 100) . "\n";

$sql3 = "SELECT
  COALESCE(error_message,'NO_ERROR') AS error_message,
  COUNT(*) AS total
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
  AND status='failed'
GROUP BY error_message
ORDER BY total DESC
LIMIT 20";

$stmt3 = $pdo->query($sql3);
$errors = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (count($errors) > 0) {
    echo "Total de erros únicos: " . count($errors) . "\n\n";
    foreach ($errors as $err) {
        $errorMsg = $err['error_message'];
        $total = $err['total'];
        
        $isOld = strpos($errorMsg, 'conversation_not_resolved') !== false && $total > 50;
        $icon = $isOld ? '⚠️' : '❌';
        
        echo sprintf("  %s [%4d vezes] %s\n", $icon, $total, substr($errorMsg, 0, 80));
    }
} else {
    echo "✅ Nenhum erro encontrado!\n";
}

// Query 4: Conversations criadas/atualizadas por canal
echo "\n\n4) CONVERSATIONS CRIADAS/ATUALIZADAS (últimos 60 min):\n";
echo str_repeat("=", 100) . "\n";

$sql4 = "SELECT channel_id, COUNT(*) AS total
FROM conversations
WHERE tenant_id = 2
  AND updated_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)
GROUP BY channel_id
ORDER BY total DESC";

$stmt4 = $pdo->query($sql4);
$convs = $stmt4->fetchAll(PDO::FETCH_ASSOC);

if (count($convs) > 0) {
    foreach ($convs as $c) {
        $channel = $c['channel_id'] ?: 'NULL';
        $total = $c['total'];
        $icon = $total > 0 ? '✅' : '❌';
        echo sprintf("  %s %-25s: %d conversation(s)\n", $icon, $channel, $total);
    }
    
    $imobsites = 0;
    $pixel12 = 0;
    foreach ($convs as $c) {
        if ($c['channel_id'] === 'ImobSites') $imobsites = $c['total'];
        if ($c['channel_id'] === 'Pixel12 Digital') $pixel12 = $c['total'];
    }
    
    echo "\n✅ Validação:\n";
    echo sprintf("  ImobSites: %s (%d conversations)\n", $imobsites > 0 ? '✅' : '❌', $imobsites);
    echo sprintf("  Pixel12 Digital: %s (%d conversations)\n", $pixel12 > 0 ? '✅' : '❌', $pixel12);
} else {
    echo "⚠️  Nenhuma conversation atualizada nos últimos 60 minutos.\n";
    echo "   Isso pode ser normal se não houve mensagens recentes.\n";
}

// Query 5: Eventos processed sem conversation (apenas se tabela tiver conversation_id)
if ($hasConversationId) {
    echo "\n\n5) EVENTOS PROCESSED SEM CONVERSATION (últimas 2h):\n";
    echo str_repeat("=", 100) . "\n";
    
    $sql5 = "SELECT id, created_at,
      JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
      COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.raw_event_type')), JSON_UNQUOTE(JSON_EXTRACT(payload,'$.event'))) AS eventType,
      status, error_message
    FROM communication_events
    WHERE source_system='wpp_gateway'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
      AND status='processed'
      AND conversation_id IS NULL
    ORDER BY id DESC
    LIMIT 50";
    
    $stmt5 = $pdo->query($sql5);
    $noConv = $stmt5->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($noConv) > 0) {
        $messageEvents = 0;
        $technicalEvents = 0;
        
        foreach ($noConv as $nc) {
            $eventType = $nc['eventType'] ?: 'NULL';
            $isMessage = strpos($eventType, 'message') !== false || $eventType === 'onmessage';
            
            if ($isMessage) {
                $messageEvents++;
                echo sprintf("  ❌ ID: %4d | Event: %s | Channel: %s | PROBLEMA: mensagem processed sem conversation\n",
                    $nc['id'],
                    substr($eventType, 0, 30),
                    $nc['channel_id'] ?: 'NULL'
                );
            } else {
                $technicalEvents++;
            }
        }
        
        echo "\n✅ Resumo:\n";
        echo sprintf("  Eventos técnicos (OK): %d\n", $technicalEvents);
        echo sprintf("  Eventos de mensagem (PROBLEMA): %d\n", $messageEvents);
        
        if ($messageEvents > 0) {
            echo "\n⚠️  ATENÇÃO: Existem mensagens marcadas como processed sem conversation!\n";
        }
    } else {
        echo "✅ Nenhum evento processed sem conversation encontrado.\n";
    }
} else {
    echo "\n\n5) EVENTOS PROCESSED SEM CONVERSATION:\n";
    echo str_repeat("=", 100) . "\n";
    echo "⚠️  Tabela não tem campo conversation_id - query ignorada.\n";
}

// Query 6: Grupos (@g.us)
echo "\n\n6) EVENTOS DE GRUPO (@g.us) - últimos 24h:\n";
echo str_repeat("=", 100) . "\n";

$sql6 = "SELECT id, created_at, status,
  LEFT(COALESCE(error_message,''),120) AS error_message,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.raw_event_type')), JSON_UNQUOTE(JSON_EXTRACT(payload,'$.event'))) AS eventType,
  JSON_UNQUOTE(JSON_EXTRACT(payload,'$.message.from')) AS message_from,
  JSON_UNQUOTE(JSON_EXTRACT(payload,'$.raw.payload.author')) AS author,
  JSON_UNQUOTE(JSON_EXTRACT(payload,'$.raw.payload.participant')) AS participant
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
  AND (
    payload LIKE '%@g.us%'
    OR JSON_UNQUOTE(JSON_EXTRACT(payload,'$.message.from')) LIKE '%@g.us%'
  )
ORDER BY id DESC
LIMIT 30";

$stmt6 = $pdo->query($sql6);
$groups = $stmt6->fetchAll(PDO::FETCH_ASSOC);

if (count($groups) > 0) {
    $groupProcessed = 0;
    $groupFailed = 0;
    $groupMissingParticipant = 0;
    
    foreach ($groups as $g) {
        $status = $g['status'];
        $hasParticipant = !empty($g['author']) || !empty($g['participant']);
        $error = $g['error_message'] ?: 'OK';
        
        if ($status === 'processed') {
            $groupProcessed++;
            $icon = '✅';
        } else {
            $groupFailed++;
            if (strpos($error, 'group_missing_participant') !== false || strpos($error, 'missing_participant') !== false) {
                $groupMissingParticipant++;
                $icon = '⚠️';
            } else {
                $icon = '❌';
            }
        }
        
        echo sprintf("  %s ID: %4d | Status: %-10s | Group: %s | Participant: %s | Error: %s\n",
            $icon,
            $g['id'],
            $status,
            substr($g['message_from'] ?: 'NULL', 0, 30),
            $hasParticipant ? 'SIM' : 'NÃO',
            substr($error, 0, 40)
        );
    }
    
    echo "\n✅ Resumo grupos:\n";
    echo sprintf("  Processados: %d\n", $groupProcessed);
    echo sprintf("  Falhados: %d (missing_participant: %d)\n", $groupFailed, $groupMissingParticipant);
    
    if ($groupFailed > 0 && $groupMissingParticipant === $groupFailed) {
        echo "  ✅ Todos os fails são por missing_participant (payload incompleto do gateway)\n";
    }
} else {
    echo "✅ Nenhum evento de grupo encontrado nas últimas 24h.\n";
}

echo "\n" . str_repeat("=", 100) . "\n";
echo "VALIDAÇÃO CONCLUÍDA\n";
echo str_repeat("=", 100) . "\n";

