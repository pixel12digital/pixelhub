<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== VERIFICAÇÃO DE AMBOS OS CANAIS ===\n\n";
echo "Buscando mensagens 'teste1827' enviadas às 18:35\n\n";

// 1. Verificar eventos com "teste1827" nos dois canais
echo "1. EVENTOS COM 'teste1827' (últimas 2 horas):\n";
echo str_repeat("=", 80) . "\n";
$stmt1 = $pdo->query("
    SELECT 
        id,
        event_id,
        event_type,
        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
        tenant_id,
        status,
        payload,
        created_at
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    AND (
        payload LIKE '%teste1827%'
        OR payload LIKE '%teste1827_imobsites%'
        OR payload LIKE '%teste1827_pixel%'
    )
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC
");

$events = $stmt1->fetchAll(PDO::FETCH_ASSOC);

echo "Total encontrado: " . count($events) . " eventos\n\n";

$channelsFound = [];
foreach ($events as $e) {
    $payload = json_decode($e['payload'], true);
    $body = $payload['body'] 
        ?? $payload['message']['body'] 
        ?? $payload['message']['content'] 
        ?? 'N/A';
    
    $channelId = $e['channel_id'] ?? 'NULL';
    $channelsFound[$channelId] = ($channelsFound[$channelId] ?? 0) + 1;
    
    echo sprintf("✅ ID: %4d | Channel: %-20s | Status: %-10s | Mensagem: %s | Criado: %s\n",
        $e['id'],
        $channelId,
        $e['status'],
        substr($body, 0, 50),
        $e['created_at']
    );
}

echo "\nResumo por canal:\n";
foreach ($channelsFound as $channel => $count) {
    echo "  - $channel: $count evento(s)\n";
}

// 2. Verificar conversations correspondentes
echo "\n2. CONVERSATIONS CORRESPONDENTES:\n";
echo str_repeat("=", 80) . "\n";

// Buscar conversations que foram atualizadas/criadas no mesmo horário
$stmt2 = $pdo->query("
    SELECT 
        c.id,
        c.channel_id,
        c.channel_account_id,
        c.contact_external_id,
        c.contact_name,
        c.message_count,
        c.last_message_at,
        c.updated_at,
        c.created_at
    FROM conversations c
    WHERE c.tenant_id = 2
    AND (
        c.channel_id = 'ImobSites'
        OR c.channel_id = 'Pixel12 Digital'
    )
    AND c.updated_at >= '2026-01-15 18:30:00'
    ORDER BY c.updated_at DESC
");

$conversations = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Total encontrado: " . count($conversations) . " conversations\n\n";

foreach ($conversations as $c) {
    echo sprintf("✅ ID: %3d | Channel: %-20s | Contact: %s | Messages: %2d | Última msg: %s\n",
        $c['id'],
        $c['channel_id'] ?? 'NULL',
        $c['contact_external_id'] ?? 'NULL',
        $c['message_count'],
        $c['last_message_at']
    );
}

// 3. Verificar mapeamento @lid
echo "\n3. MAPEAMENTO @lid → phone_number:\n";
echo str_repeat("=", 80) . "\n";

$stmt3 = $pdo->query("
    SELECT 
        business_id,
        phone_number,
        tenant_id,
        created_at
    FROM whatsapp_business_ids
    ORDER BY id DESC
    LIMIT 10
");

$mappings = $stmt3->fetchAll(PDO::FETCH_ASSOC);

echo "Total de mapeamentos: " . count($mappings) . "\n\n";

if (count($mappings) > 0) {
    foreach ($mappings as $m) {
        echo sprintf("  @lid: %s → Phone: %s (Tenant: %s)\n",
            $m['business_id'],
            $m['phone_number'] ?? 'NULL',
            $m['tenant_id'] ?? 'NULL'
        );
    }
} else {
    echo "⚠️  Nenhum mapeamento encontrado na tabela whatsapp_business_ids\n";
}

// 4. Verificar inconsistências
echo "\n4. VERIFICAÇÃO DE INCONSISTÊNCIAS:\n";
echo str_repeat("=", 80) . "\n";

// Verificar se há eventos sem conversation correspondente
$stmt4 = $pdo->query("
    SELECT 
        ce.id as event_id,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS event_channel_id,
        ce.tenant_id as event_tenant_id,
        ce.status as event_status,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) AS from_raw,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) AS from_message,
        c.id as conversation_id,
        c.channel_id as conv_channel_id,
        c.contact_external_id as conv_contact
    FROM communication_events ce
    LEFT JOIN conversations c ON (
        c.tenant_id = ce.tenant_id 
        AND (
            c.contact_external_id = REPLACE(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')), '@c.us', ''), '@s.whatsapp.net', '')
            OR c.contact_external_id = REPLACE(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')), '@c.us', ''), '@s.whatsapp.net', '')
        )
    )
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND (
        ce.payload LIKE '%teste1827%'
    )
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY ce.id DESC
");

$inconsistencies = $stmt4->fetchAll(PDO::FETCH_ASSOC);

echo "Análise de correspondência evento ↔ conversation:\n\n";

foreach ($inconsistencies as $inc) {
    $hasMatch = !empty($inc['conversation_id']);
    $channelMatch = ($inc['event_channel_id'] === $inc['conv_channel_id']);
    
    $status = $hasMatch ? '✅' : '❌';
    $channelStatus = $channelMatch ? '✅' : '⚠️';
    
    echo sprintf("%s Event ID: %4d | Channel Event: %-20s | Status: %s\n",
        $status,
        $inc['event_id'],
        $inc['event_channel_id'] ?? 'NULL',
        $inc['event_status']
    );
    
    if ($hasMatch) {
        echo sprintf("   %s Conversation ID: %3d | Channel Conv: %-20s | Contact: %s\n",
            $channelStatus,
            $inc['conversation_id'],
            $inc['conv_channel_id'] ?? 'NULL',
            $inc['conv_contact'] ?? 'NULL'
        );
        
        if (!$channelMatch) {
            echo "   ⚠️  INCONSISTÊNCIA: channel_id do evento não bate com channel_id da conversation!\n";
        }
    } else {
        echo "   ❌ Nenhuma conversation encontrada para este evento\n";
        echo "   From: " . ($inc['from_raw'] ?? $inc['from_message'] ?? 'NULL') . "\n";
    }
    echo "\n";
}

// 5. Resumo final
echo "\n5. RESUMO FINAL:\n";
echo str_repeat("=", 80) . "\n";

$stmt5 = $pdo->query("
    SELECT 
        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    GROUP BY channel_id
    ORDER BY total DESC
");

$summary = $stmt5->fetchAll(PDO::FETCH_ASSOC);

echo "Eventos por canal (últimas 2 horas):\n";
foreach ($summary as $s) {
    echo sprintf("  %-20s: Total: %2d | Processed: %2d | Failed: %2d\n",
        $s['channel_id'] ?? 'NULL',
        $s['total'],
        $s['processed'],
        $s['failed']
    );
}

$stmt6 = $pdo->query("
    SELECT 
        channel_id,
        COUNT(*) as total
    FROM conversations
    WHERE tenant_id = 2
    AND updated_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    GROUP BY channel_id
    ORDER BY total DESC
");

$convSummary = $stmt6->fetchAll(PDO::FETCH_ASSOC);

echo "\nConversations atualizadas por canal (últimas 2 horas):\n";
foreach ($convSummary as $s) {
    echo sprintf("  %-20s: %2d conversation(s)\n",
        $s['channel_id'] ?? 'NULL',
        $s['total']
    );
}

echo "\n";

