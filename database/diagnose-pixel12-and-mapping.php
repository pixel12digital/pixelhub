<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== DIAGNÓSTICO: Pixel12 Digital + Mapeamento @lid ===\n\n";

// A) Últimos 50 eventos do provider (independente do canal)
echo "A) ÚLTIMOS 50 EVENTOS DO PROVIDER 'wpp_gateway':\n";
echo str_repeat("=", 100) . "\n";

$sqlA = "SELECT 
    id, 
    created_at, 
    status,
    JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
    JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.sessionId')) AS sessionId,
    JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.raw_event_type')) AS raw_event_type,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) AS msg_from,
    LEFT(payload, 200) AS payload_head
FROM communication_events
WHERE source_system = 'wpp_gateway'
  AND event_type LIKE 'whatsapp.%'
ORDER BY id DESC
LIMIT 50";

$stmtA = $pdo->query($sqlA);
$eventsA = $stmtA->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($eventsA) . " eventos\n\n";

$channelsFound = [];
$sessionIdsFound = [];

foreach ($eventsA as $e) {
    $channelId = $e['channel_id'] ?? 'NULL';
    $sessionId = $e['sessionId'] ?? 'NULL';
    
    $channelsFound[$channelId] = ($channelsFound[$channelId] ?? 0) + 1;
    if ($sessionId !== 'NULL') {
        $sessionIdsFound[$sessionId] = ($sessionIdsFound[$sessionId] ?? 0) + 1;
    }
    
    echo sprintf("ID: %4d | Criado: %s | Status: %-10s | Channel: %-25s | SessionId: %-25s | From: %s\n",
        $e['id'],
        $e['created_at'],
        $e['status'],
        $channelId,
        substr($sessionId, 0, 25),
        substr($e['msg_from'] ?? 'NULL', 0, 30)
    );
}

echo "\n📊 RESUMO POR CHANNEL_ID:\n";
foreach ($channelsFound as $channel => $count) {
    echo "  - $channel: $count evento(s)\n";
}

echo "\n📊 SESSION IDs ENCONTRADOS:\n";
foreach ($sessionIdsFound as $sessionId => $count) {
    echo "  - $sessionId: $count evento(s)\n";
}

// B) Últimos eventos explicitamente do canal Pixel12 Digital (últimas 24h)
echo "\n\nB) EVENTOS DO CANAL 'Pixel12 Digital' (últimas 24h):\n";
echo str_repeat("=", 100) . "\n";

$sqlB = "SELECT 
    id, 
    created_at, 
    status,
    JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
    JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.sessionId')) AS sessionId,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) AS msg_from,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.body')) AS text
FROM communication_events
WHERE source_system = 'wpp_gateway'
  AND event_type LIKE 'whatsapp.%'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) = 'Pixel12 Digital'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY id DESC";

$stmtB = $pdo->query($sqlB);
$eventsB = $stmtB->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($eventsB) . " eventos\n\n";

if (count($eventsB) > 0) {
    foreach ($eventsB as $e) {
        echo sprintf("ID: %4d | Criado: %s | Status: %-10s | SessionId: %s | Text: %s\n",
            $e['id'],
            $e['created_at'],
            $e['status'],
            $e['sessionId'] ?? 'NULL',
            substr($e['text'] ?? 'NULL', 0, 50)
        );
    }
} else {
    echo "⚠️  NENHUM evento encontrado com channel_id = 'Pixel12 Digital'\n";
    echo "    Verificando variações do nome...\n\n";
    
    // Verificar variações do nome
    $sqlB2 = "SELECT DISTINCT 
        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id
    FROM communication_events
    WHERE source_system = 'wpp_gateway'
      AND event_type LIKE 'whatsapp.%'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
      AND (
          JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) LIKE '%pixel%'
          OR JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) LIKE '%Pixel%'
          OR JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) LIKE '%digital%'
      )";
    
    $stmtB2 = $pdo->query($sqlB2);
    $variations = $stmtB2->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($variations) > 0) {
        echo "Variações encontradas:\n";
        foreach ($variations as $v) {
            echo "  - '" . ($v['channel_id'] ?? 'NULL') . "'\n";
        }
    } else {
        echo "Nenhuma variação encontrada com 'pixel' ou 'digital'\n";
    }
}

// C) Verificar estrutura da tabela whatsapp_business_ids
echo "\n\nC) ESTRUTURA DA TABELA whatsapp_business_ids:\n";
echo str_repeat("=", 100) . "\n";

$sqlC = "SHOW COLUMNS FROM whatsapp_business_ids";
$stmtC = $pdo->query($sqlC);
$columns = $stmtC->fetchAll(PDO::FETCH_ASSOC);

echo "Colunas da tabela:\n";
foreach ($columns as $col) {
    echo sprintf("  - %s (%s)\n", $col['Field'], $col['Type']);
}

// D) Verificar se existe mapeamento para 208989199560861@lid
echo "\n\nD) MAPEAMENTO PARA 208989199560861@lid:\n";
echo str_repeat("=", 100) . "\n";

$sqlD = "SELECT * FROM whatsapp_business_ids 
WHERE business_id LIKE '%208989199560861%'
LIMIT 10";

$stmtD = $pdo->query($sqlD);
$mapping = $stmtD->fetchAll(PDO::FETCH_ASSOC);

if (count($mapping) > 0) {
    echo "✅ Mapeamento encontrado:\n";
    foreach ($mapping as $m) {
        echo json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
} else {
    echo "❌ Nenhum mapeamento encontrado para 208989199560861@lid\n";
}

// E) Buscar o phone_number do payload do evento mais recente do Charles Dietrich
echo "\n\nE) EXTRAINDO PHONE DO PAYLOAD (evento mais recente do Charles):\n";
echo str_repeat("=", 100) . "\n";

$sqlE = "SELECT 
    id,
    payload,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) AS from_raw,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS from_message,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) AS to_raw,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.to')) AS to_message
FROM communication_events
WHERE event_type = 'whatsapp.inbound.message'
  AND (
      payload LIKE '%208989199560861%'
      OR payload LIKE '%Charles Dietrich%'
  )
ORDER BY id DESC
LIMIT 1";

$stmtE = $pdo->query($sqlE);
$eventCharles = $stmtE->fetch(PDO::FETCH_ASSOC);

if ($eventCharles) {
    echo "Evento encontrado (ID: " . $eventCharles['id'] . ")\n\n";
    echo "From (raw): " . ($eventCharles['from_raw'] ?? 'NULL') . "\n";
    echo "From (message): " . ($eventCharles['from_message'] ?? 'NULL') . "\n";
    echo "To (raw): " . ($eventCharles['to_raw'] ?? 'NULL') . "\n";
    echo "To (message): " . ($eventCharles['to_message'] ?? 'NULL') . "\n";
    
    // Tentar extrair número do "to" (número do destino geralmente é o número real)
    $toNumber = $eventCharles['to_message'] ?? $eventCharles['to_raw'] ?? null;
    if ($toNumber) {
        // Remove @c.us, @s.whatsapp.net, etc
        $cleanNumber = preg_replace('/@.*$/', '', $toNumber);
        // Remove caracteres não numéricos
        $cleanNumber = preg_replace('/[^0-9]/', '', $cleanNumber);
        
        echo "\n💡 Sugestão de phone_number para mapeamento: $cleanNumber\n";
        echo "   (extraído do campo 'to' do evento - número do destino)\n";
    }
} else {
    echo "Nenhum evento encontrado para extrair phone_number\n";
}

echo "\n";

