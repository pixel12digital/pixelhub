<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== BUSCA COMPLETA DAS MENSAGENS DE TESTE ===\n\n";

// Buscar todas as mensagens de teste enviadas hoje após 19:00
echo "1) TODAS AS MENSAGENS COM 'teste1921' (após 19:00):\n";
echo str_repeat("=", 100) . "\n";

$sql1 = "SELECT id, created_at, event_type, status, error_message,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS payload_event,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS message_from,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) AS raw_body
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= '2026-01-15 19:00:00'
  AND event_type LIKE '%message%'
  AND (
    payload LIKE '%teste1921%'
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) LIKE '%teste1921%'
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) LIKE '%teste1921%'
  )
ORDER BY created_at DESC";

$stmt1 = $pdo->query($sql1);
$testMessages = $stmt1->fetchAll(PDO::FETCH_ASSOC);

if (count($testMessages) > 0) {
    echo "Total encontrado: " . count($testMessages) . "\n\n";
    
    $imobsites = false;
    $pixel12 = false;
    
    foreach ($testMessages as $tm) {
        $icon = $tm['status'] === 'processed' ? '✅' : '❌';
        $text = $tm['message_text'] ?: $tm['raw_body'] ?: 'NULL';
        
        if (strpos($text, 'imobsites') !== false) {
            $imobsites = true;
        }
        if (strpos($text, 'pixel') !== false) {
            $pixel12 = true;
        }
        
        echo sprintf("%s ID: %5d | %s | Status: %-10s | Channel: %-20s | Text: %s\n",
            $icon,
            $tm['id'],
            $tm['created_at'],
            $tm['status'],
            $tm['channel_id'] ?: 'NULL',
            substr($text, 0, 50)
        );
    }
    
    echo "\n✅ Resumo:\n";
    echo sprintf("  ImobSites encontrada: %s\n", $imobsites ? '✅ SIM' : '❌ NÃO');
    echo sprintf("  Pixel12 Digital encontrada: %s\n", $pixel12 ? '✅ SIM' : '❌ NÃO');
} else {
    echo "❌ Nenhuma mensagem com 'teste1921' encontrada.\n";
}

// Buscar todas as mensagens processadas nos dois canais após 19:00 (última tentativa)
echo "\n\n2) TODAS AS MENSAGENS PROCESSADAS (ImobSites e Pixel12 Digital após 19:00):\n";
echo str_repeat("=", 100) . "\n";

$sql2 = "SELECT id, created_at, event_type, status, error_message,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS payload_event,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) AS raw_body
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= '2026-01-15 19:00:00'
  AND event_type LIKE '%message%'
  AND status = 'processed'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) IN ('ImobSites', 'Pixel12 Digital')
ORDER BY created_at DESC
LIMIT 20";

$stmt2 = $pdo->query($sql2);
$allMessages = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (count($allMessages) > 0) {
    echo "Total encontrado: " . count($allMessages) . "\n\n";
    
    $imobsitesCount = 0;
    $pixel12Count = 0;
    
    foreach ($allMessages as $am) {
        $channel = $am['channel_id'] ?: 'NULL';
        $text = $am['message_text'] ?: $am['raw_body'] ?: 'NULL';
        
        if ($channel === 'ImobSites') $imobsitesCount++;
        if ($channel === 'Pixel12 Digital') $pixel12Count++;
        
        echo sprintf("✅ ID: %5d | %s | Channel: %-20s | Text: %s\n",
            $am['id'],
            $am['created_at'],
            $channel,
            substr($text, 0, 50)
        );
    }
    
    echo "\n✅ Resumo por canal:\n";
    echo sprintf("  ImobSites: %d mensagens processadas\n", $imobsitesCount);
    echo sprintf("  Pixel12 Digital: %d mensagens processadas\n", $pixel12Count);
} else {
    echo "❌ Nenhuma mensagem processada encontrada.\n";
}

// Buscar conversations atualizadas para ver se ambas foram processadas
echo "\n\n3) CONVERSATIONS ATUALIZADAS (após 19:00):\n";
echo str_repeat("=", 100) . "\n";

$sql3 = "SELECT id, channel_id, contact_external_id, updated_at, created_at, message_count
FROM conversations
WHERE tenant_id = 2
  AND updated_at >= '2026-01-15 19:00:00'
ORDER BY updated_at DESC";

$stmt3 = $pdo->query($sql3);
$convs = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (count($convs) > 0) {
    echo "Total: " . count($convs) . " conversations\n\n";
    
    $imobsitesConvs = [];
    $pixel12Convs = [];
    
    foreach ($convs as $c) {
        $channel = $c['channel_id'] ?: 'NULL';
        $isNew = strtotime($c['updated_at']) >= strtotime('2026-01-15 19:20:00');
        $icon = $isNew ? '🆕' : '🔄';
        
        echo sprintf("%s ID: %3d | Channel: %-20s | Contact: %s | Messages: %2d | Updated: %s\n",
            $icon,
            $c['id'],
            $channel,
            $c['contact_external_id'] ?: 'NULL',
            $c['message_count'],
            $c['updated_at']
        );
        
        if ($channel === 'ImobSites') {
            $imobsitesConvs[] = $c;
        }
        if ($channel === 'Pixel12 Digital') {
            $pixel12Convs[] = $c;
        }
    }
    
    echo "\n✅ Resumo:\n";
    echo sprintf("  ImobSites: %d conversation(s) atualizada(s)\n", count($imobsitesConvs));
    echo sprintf("  Pixel12 Digital: %d conversation(s) atualizada(s)\n", count($pixel12Convs));
} else {
    echo "⚠️  Nenhuma conversation atualizada após 19:00.\n";
}

// Busca final: eventos com texto que contenha "pixel" ou "imobsites" após 19:00
echo "\n\n4) BUSCA ALTERNATIVA - Texto contendo 'pixel' ou 'imobsites' (após 19:00):\n";
echo str_repeat("=", 100) . "\n";

$sql4 = "SELECT id, created_at, status,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) AS raw_body,
  LEFT(payload, 300) AS payload_head
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= '2026-01-15 19:00:00'
  AND event_type LIKE '%message%'
  AND (
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) LIKE '%pixel%'
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) LIKE '%imobsites%'
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) LIKE '%pixel%'
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) LIKE '%imobsites%'
    OR payload LIKE '%pixel%'
    OR payload LIKE '%imobsites%'
  )
ORDER BY created_at DESC
LIMIT 10";

$stmt4 = $pdo->query($sql4);
$alternate = $stmt4->fetchAll(PDO::FETCH_ASSOC);

if (count($alternate) > 0) {
    echo "Total encontrado: " . count($alternate) . "\n\n";
    foreach ($alternate as $alt) {
        $icon = $alt['status'] === 'processed' ? '✅' : '❌';
        $text = $alt['message_text'] ?: $alt['raw_body'] ?: 'TEXT NOT FOUND';
        
        echo sprintf("%s ID: %5d | %s | Status: %-10s | Channel: %-20s | Text: %s\n",
            $icon,
            $alt['id'],
            $alt['created_at'],
            $alt['status'],
            $alt['channel_id'] ?: 'NULL',
            substr($text, 0, 60)
        );
    }
} else {
    echo "❌ Nenhuma mensagem com 'pixel' ou 'imobsites' encontrada.\n";
}

echo "\n" . str_repeat("=", 100) . "\n";
echo "BUSCA CONCLUÍDA\n";
echo str_repeat("=", 100) . "\n";


