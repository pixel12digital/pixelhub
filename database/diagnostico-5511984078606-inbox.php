<?php
/**
 * Diagnóstico: Por que conversa de 5511984078606 (Adriana) para pixel12digital não aparece no Inbox?
 *
 * Contexto: Mensagem recebida no WhatsApp às 07:14, de Adriana (5511984078606) para Pixel12 Digital.
 * O Inbox do Pixel Hub não mostra essa conversa.
 *
 * Execução: php database/diagnostico-5511984078606-inbox.php
 * No servidor: php database/diagnostico-5511984078606-inbox.php
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require_once $file;
    });
}

\PixelHub\Core\Env::load();
$db = \PixelHub\Core\DB::getConnection();

$numero = '5511984078606';
$padroes = ['%11984078606%', '%1984078606%', '%984078606%', '%5511984078606%', '%11984078606@%', '%5511984078606@%'];

echo "=== DIAGNÓSTICO: 5511984078606 (Adriana) → pixel12digital ===\n\n";

// 1. Eventos em communication_events
echo "1. EVENTOS (communication_events) para este número:\n";
$fromConditions = [];
$fromParams = ['2026-02-01 00:00:00']; // created_at >= ?
foreach ($padroes as $p) {
    $fromConditions[] = "(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ? 
     OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
     OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.data.from')) LIKE ?
     OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.from')) LIKE ?)";
    $fromParams = array_merge($fromParams, [$p, $p, $p, $p]);
}
$fromPlaceholders = implode(' OR ', $fromConditions);

$stmt = $db->prepare("
    SELECT ce.id, ce.event_id, ce.event_type, ce.tenant_id, ce.status, ce.created_at,
           JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as meta_channel_id,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.channel')) as payload_channel,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.session.id')) as session_id,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_field,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as msg_from,
           LEFT(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')), 80) as text_preview,
           ce.conversation_id
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= ?
    AND ({$fromPlaceholders})
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$stmt->execute($fromParams);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ NENHUM EVENTO encontrado!\n";
    echo "   → O webhook NÃO recebeu a mensagem OU o payload tem estrutura diferente.\n";
    echo "   → Verificar: logs [HUB_WEBHOOK_IN] no servidor, webhook_raw_logs no banco.\n\n";
} else {
    echo "   ✓ " . count($events) . " evento(s) encontrado(s):\n";
    foreach ($events as $e) {
        $ch = $e['meta_channel_id'] ?: $e['payload_channel'] ?: $e['session_id'] ?: 'NULL';
        echo "   - id={$e['id']} | event_id={$e['event_id']} | status={$e['status']} | tenant_id=" . ($e['tenant_id'] ?: 'NULL') . " | channel_id={$ch} | conv_id=" . ($e['conversation_id'] ?: 'NULL') . " | {$e['created_at']}\n";
    }
    echo "\n";
}

// 2. Conversas em conversations
echo "2. CONVERSAS (conversations) para este número:\n";
$placeholders = implode(' OR ', array_fill(0, count($padroes), 'c.contact_external_id LIKE ?'));
$stmt = $db->prepare("
    SELECT c.id, c.conversation_key, c.contact_external_id, c.contact_name, c.tenant_id, 
           c.channel_id, c.status, c.last_message_at, c.message_count, c.unread_count, c.created_at
    FROM conversations c
    WHERE c.channel_type = 'whatsapp' AND ({$placeholders})
    ORDER BY c.last_message_at DESC, c.created_at DESC
    LIMIT 10
");
$stmt->execute($padroes);
$convs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($convs)) {
    echo "   ❌ NENHUMA CONVERSA encontrada!\n";
    echo "   → Possíveis causas:\n";
    echo "     a) Evento nunca chegou (webhook não recebeu)\n";
    echo "     b) extractChannelInfo retornou NULL (payload sem from/to válido)\n";
    echo "     c) resolveConversation falhou e marcou evento como 'failed'\n";
    echo "     d) channel_id ausente no payload e fallback também falhou\n\n";
} else {
    echo "   ✓ " . count($convs) . " conversa(s) encontrada(s):\n";
    foreach ($convs as $c) {
        $visivel = in_array($c['status'] ?? '', ['new', 'open', 'pending']) || !in_array($c['status'] ?? '', ['closed', 'archived', 'ignored']);
        $vis = $visivel ? '✓ visível' : '✗ filtrada (status=' . ($c['status'] ?? 'NULL') . ')';
        echo "   - id={$c['id']} | contact={$c['contact_external_id']} | channel_id=" . ($c['channel_id'] ?: 'NULL') . " | status={$c['status']} | last_msg={$c['last_message_at']} | {$vis}\n";
    }
    echo "\n";
}

// 3. Canais pixel12digital em tenant_message_channels
echo "3. CANAIS pixel12digital (tenant_message_channels):\n";
$hasSessionId = false;
try {
    $cols = $db->query("SHOW COLUMNS FROM tenant_message_channels")->fetchAll(PDO::FETCH_COLUMN);
    $hasSessionId = in_array('session_id', $cols);
} catch (\Exception $e) {}
$sessionCol = $hasSessionId ? ', session_id' : '';
$sessionCond = $hasSessionId ? " OR LOWER(REPLACE(COALESCE(session_id,''), ' ', '')) LIKE '%pixel12%'" : '';
$stmt = $db->prepare("
    SELECT id, tenant_id, channel_id {$sessionCol}, is_enabled
    FROM tenant_message_channels
    WHERE provider = 'wpp_gateway'
    AND (LOWER(REPLACE(channel_id, ' ', '')) LIKE '%pixel12%'{$sessionCond})
    ORDER BY id
");
$stmt->execute();
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($channels)) {
    echo "   ❌ Nenhum canal pixel12 encontrado!\n\n";
} else {
    foreach ($channels as $ch) {
        $sId = isset($ch['session_id']) ? ($ch['session_id'] ?? 'NULL') : 'N/A';
        echo "   - id={$ch['id']} | tenant_id=" . ($ch['tenant_id'] ?: 'NULL') . " | channel_id={$ch['channel_id']} | session_id={$sId} | enabled=" . ($ch['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
    }
    echo "\n";
}

// 4. webhook_raw_logs (últimos webhooks recebidos)
echo "4. ÚLTIMOS WEBHOOKS (webhook_raw_logs) - últimos 5 eventos 'message':\n";
if (tableExists($db, 'webhook_raw_logs')) {
    $stmt = $db->query("
        SELECT id, event_type, payload_hash, created_at, processed
        FROM webhook_raw_logs
        WHERE event_type IN ('message', 'onmessage')
        ORDER BY id DESC
        LIMIT 5
    ");
    $logs = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    if (empty($logs)) {
        echo "   (tabela existe mas sem registros recentes)\n\n";
    } else {
        foreach ($logs as $l) {
            echo "   - id={$l['id']} | type={$l['event_type']} | hash={$l['payload_hash']} | {$l['created_at']}\n";
        }
        echo "\n";
    }
} else {
    echo "   (tabela webhook_raw_logs não existe)\n\n";
}

// 5. Simula query do Inbox (getWhatsAppThreadsFromConversations sem filtro session)
echo "5. SIMULAÇÃO: Query do Inbox (sem filtro session, status=active) - inclui 5511984078606?\n";
$stmt = $db->prepare("
    SELECT c.id, c.contact_external_id, c.channel_id, c.status, c.last_message_at
    FROM conversations c
    WHERE c.channel_type = 'whatsapp'
    AND c.status NOT IN ('closed', 'archived', 'ignored')
    AND (c.contact_external_id LIKE ? OR c.contact_external_id LIKE ?)
    ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
    LIMIT 5
");
$stmt->execute(['%5511984078606%', '%11984078606%']);
$inboxMatch = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($inboxMatch)) {
    echo "   ❌ NÃO aparece na query do Inbox (conversa não existe ou está closed/archived)\n\n";
} else {
    echo "   ✓ Aparece na query:\n";
    foreach ($inboxMatch as $m) {
        echo "   - id={$m['id']} | contact={$m['contact_external_id']} | channel_id=" . ($m['channel_id'] ?: 'NULL') . " | status={$m['status']}\n";
    }
    echo "\n";
}

echo "=== FIM DO DIAGNÓSTICO ===\n";
echo "\nPróximos passos conforme resultado:\n";
echo "- Se eventos existem mas conversa não: verificar logs [CONVERSATION UPSERT], [HUB_CONV_MATCH], [extractChannelInfo]\n";
echo "- Se eventos não existem: verificar VPS/Gateway (webhook enviado?), logs [HUB_WEBHOOK_IN]\n";
echo "- Se conversa existe mas não aparece: verificar filtro session_id na UI (Canal: Todos vs pixel12digital)\n";

// Helper (simplificado)
function tableExists($db, $table) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE " . $db->quote($table));
        return $stmt->rowCount() > 0;
    } catch (\Exception $e) {
        return false;
    }
}
