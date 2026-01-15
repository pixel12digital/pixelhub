<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== VERIFICAÇÃO DE MENSAGENS NOVAS ===\n\n";
echo "Data/Hora atual: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Eventos inbound muito recentes (últimos 30 minutos)
echo "1. EVENTOS INBOUND RECENTES (últimos 30 minutos):\n";
echo str_repeat("-", 80) . "\n";
$stmt1 = $pdo->query("
    SELECT 
        id,
        event_id,
        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
        tenant_id,
        status,
        error_message,
        created_at,
        TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS minutos_atras
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ORDER BY id DESC
    LIMIT 20
");

$events = $stmt1->fetchAll(PDO::FETCH_ASSOC);
echo "Total: " . count($events) . " eventos\n\n";

if (count($events) > 0) {
    foreach ($events as $e) {
        $statusIcon = $e['status'] === 'processed' ? '✅' : ($e['status'] === 'failed' ? '❌' : '⏳');
        echo sprintf("%s ID: %4d | Channel: %-20s | Tenant: %s | Status: %-10s | %s min atrás\n",
            $statusIcon,
            $e['id'],
            $e['channel_id'] ?? 'NULL',
            $e['tenant_id'] ?? 'NULL',
            $e['status'],
            $e['minutos_atras']
        );
        if ($e['error_message']) {
            echo "    ⚠️  Erro: " . $e['error_message'] . "\n";
        }
    }
} else {
    echo "Nenhum evento inbound encontrado nos últimos 30 minutos.\n";
}

// 2. Conversations atualizadas/criadas recentemente
echo "\n2. CONVERSATIONS ATUALIZADAS/CRIADAS (últimos 30 minutos):\n";
echo str_repeat("-", 80) . "\n";
$stmt2 = $pdo->query("
    SELECT 
        id,
        channel_id,
        channel_account_id,
        contact_external_id,
        contact_name,
        message_count,
        unread_count,
        status,
        last_message_at,
        updated_at,
        created_at,
        TIMESTAMPDIFF(MINUTE, updated_at, NOW()) AS atualizada_min_atras
    FROM conversations
    WHERE tenant_id = 2
    AND (updated_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE) 
         OR created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE))
    ORDER BY updated_at DESC
    LIMIT 20
");

$conversations = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "Total: " . count($conversations) . " conversations\n\n";

if (count($conversations) > 0) {
    foreach ($conversations as $c) {
        $isNew = strtotime($c['created_at']) >= strtotime('-30 minutes');
        $icon = $isNew ? '🆕' : '🔄';
        echo sprintf("%s ID: %3d | Channel: %-20s | Contact: %s | Msgs: %2d | Atualizada %s min atrás\n",
            $icon,
            $c['id'],
            $c['channel_id'] ?? 'NULL',
            substr($c['contact_external_id'] ?? 'NULL', 0, 20),
            $c['message_count'],
            $c['atualizada_min_atras']
        );
    }
} else {
    echo "Nenhuma conversation atualizada/criada nos últimos 30 minutos.\n";
}

// 3. Agrupamento por channel_id (só as recentes)
echo "\n3. AGRUPAMENTO POR CHANNEL_ID (conversations recentes):\n";
echo str_repeat("-", 80) . "\n";
$stmt3 = $pdo->query("
    SELECT 
        channel_id,
        COUNT(*) AS total,
        MAX(updated_at) AS ultima_atualizacao
    FROM conversations
    WHERE tenant_id = 2
    AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    GROUP BY channel_id
    ORDER BY total DESC
");

$grouped = $stmt3->fetchAll(PDO::FETCH_ASSOC);
foreach ($grouped as $g) {
    echo sprintf("  Channel: %-20s | Total: %2d | Última atualização: %s\n",
        $g['channel_id'] ?? 'NULL',
        $g['total'],
        $g['ultima_atualizacao']
    );
}

// 4. Verificar se há conversations com channel_id = 'ImobSites' criadas recentemente
echo "\n4. CONVERSATIONS DO CANAL 'ImobSites' (última hora):\n";
echo str_repeat("-", 80) . "\n";
$stmt4 = $pdo->query("
    SELECT 
        id,
        channel_id,
        contact_external_id,
        message_count,
        created_at,
        updated_at
    FROM conversations
    WHERE tenant_id = 2
    AND channel_id = 'ImobSites'
    AND (created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) 
         OR updated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR))
    ORDER BY updated_at DESC
");

$imobsites = $stmt4->fetchAll(PDO::FETCH_ASSOC);
if (count($imobsites) > 0) {
    echo "✅ ENCONTRADAS " . count($imobsites) . " conversations do canal 'ImobSites'!\n\n";
    foreach ($imobsites as $i) {
        echo sprintf("  ID: %3d | Contact: %s | Messages: %2d | Criada: %s\n",
            $i['id'],
            $i['contact_external_id'],
            $i['message_count'],
            $i['created_at']
        );
    }
} else {
    echo "⚠️  Nenhuma conversation do canal 'ImobSites' encontrada na última hora.\n";
}

echo "\n";

