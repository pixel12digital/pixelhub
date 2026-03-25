<?php
require_once __DIR__ . '/src/Core/DB.php';
require_once __DIR__ . '/src/Core/Env.php';
\PixelHub\Core\Env::load();

$db = \PixelHub\Core\DB::getConnection();

echo "=== SDR CONVERSATIONS (all, last 20) ===\n";
$rows = $db->query("
    SELECT id, phone, establishment_name, stage, human_mode,
           last_inbound_at, last_ai_reply_at, reply_after,
           conversation_id, updated_at
    FROM sdr_conversations
    ORDER BY updated_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "ID:{$r['id']} | {$r['phone']} | {$r['establishment_name']}\n";
    echo "  stage={$r['stage']} human={$r['human_mode']} conv_id={$r['conversation_id']}\n";
    echo "  last_inbound_at={$r['last_inbound_at']} last_ai_reply_at={$r['last_ai_reply_at']}\n";
    echo "  reply_after={$r['reply_after']} updated_at={$r['updated_at']}\n";
}

echo "\n=== CONVERSATIONS MATCHING processInboundReplies QUERY ===\n";
$ready = $db->query("
    SELECT id, phone, establishment_name, stage, last_inbound_at, reply_after, conversation_id
    FROM sdr_conversations
    WHERE human_mode = 0
      AND stage NOT IN ('closed_win','closed_lost','opted_out')
      AND last_inbound_at IS NOT NULL
      AND (last_ai_reply_at IS NULL OR last_inbound_at > last_ai_reply_at)
      AND (reply_after IS NULL OR reply_after <= NOW())
")->fetchAll(PDO::FETCH_ASSOC);
echo count($ready) . " conversa(s) prontas para IA:\n";
foreach ($ready as $r) {
    echo "  ID:{$r['id']} {$r['phone']} | {$r['establishment_name']} | stage={$r['stage']} | conv_id={$r['conversation_id']}\n";
}

echo "\n=== communication_events para Ana Agnol (+554784746865) ===\n";
$evts = $db->query("
    SELECT event_id, event_type, source_system, conversation_id,
           JSON_UNQUOTE(JSON_EXTRACT(payload,'$.from')) AS frm,
           LEFT(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.body')), 60) AS body,
           created_at
    FROM communication_events
    WHERE JSON_EXTRACT(payload,'$.from') LIKE '%84746865%'
       OR JSON_EXTRACT(payload,'$.to') LIKE '%84746865%'
       OR (conversation_id IS NOT NULL AND conversation_id IN (
           SELECT id FROM conversations WHERE conversation_key LIKE '%84746865%'
       ))
    ORDER BY created_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($evts as $e) {
    echo "  conv:{$e['conversation_id']} | {$e['event_type']} | from:{$e['frm']} | {$e['body']} | {$e['created_at']}\n";
}

echo "\n=== SDR_AI_RESPONDER LOG (últimas 20 linhas) ===\n";
$logFile = __DIR__ . '/logs/sdr_ai_responder.log';
if (file_exists($logFile)) {
    $lines = array_slice(file($logFile), -20);
    echo implode('', $lines);
} else {
    echo "Log não encontrado: {$logFile}\n";
}

echo "\n=== SDR_WORKER LOG (últimas 10 linhas) ===\n";
$workerLog = __DIR__ . '/logs/sdr_worker.log';
if (file_exists($workerLog)) {
    $lines = array_slice(file($workerLog), -10);
    echo implode('', $lines);
} else {
    echo "Log não encontrado: {$workerLog}\n";
}
