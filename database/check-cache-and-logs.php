<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== CHECK: Cache e Eventos Recentes ===\n\n";

// 1) Cache
echo "1) CACHE wa_pnlid_cache:\n";
echo str_repeat("=", 120) . "\n";

$sql1 = "SELECT provider, session_id, pnlid, phone_e164, updated_at
FROM wa_pnlid_cache
ORDER BY updated_at DESC
LIMIT 10";

$stmt1 = $pdo->query($sql1);
$cache = $stmt1->fetchAll(PDO::FETCH_ASSOC);

if (count($cache) > 0) {
    echo "✅ " . count($cache) . " registro(s) no cache:\n\n";
    echo sprintf("%-15s | %-20s | %-20s | %-20s | %-19s\n",
        "PROVIDER", "SESSION_ID", "PNLID", "PHONE_E164", "UPDATED_AT");
    echo str_repeat("-", 120) . "\n";
    foreach ($cache as $c) {
        echo sprintf("%-15s | %-20s | %-20s | %-20s | %-19s\n",
            $c['provider'],
            $c['session_id'],
            substr($c['pnlid'], 0, 18),
            $c['phone_e164'],
            $c['updated_at']
        );
    }
} else {
    echo "❌ Cache vazio.\n";
}

// 2) Evento 6934 (mensagem 76023300)
echo "\n\n2) EVENTO 6934 (mensagem 76023300):\n";
echo str_repeat("=", 120) . "\n";

$sql2 = "SELECT 
  id,
  created_at,
  event_type,
  status,
  JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS from_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.to')) AS to_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS text
FROM communication_events
WHERE id = 6934";

$stmt2 = $pdo->query($sql2);
$event = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($event) {
    echo "✅ Evento encontrado:\n";
    echo "  ID: {$event['id']}\n";
    echo "  Created: {$event['created_at']}\n";
    echo "  Status: {$event['status']}\n";
    echo "  Channel ID: {$event['channel_id']}\n";
    echo "  From: {$event['from_id']}\n";
    echo "  To: {$event['to_id']}\n";
    echo "  Text: {$event['text']}\n";
    
    // Verificar se esse pnlid está no cache
    if ($event['from_id'] && preg_match('/^([0-9]+)@lid$/', $event['from_id'], $m)) {
        $pnLid = $m[1];
        echo "\n  Extraído pnLid: {$pnLid}\n";
        
        $sql3 = "SELECT * FROM wa_pnlid_cache 
                 WHERE pnlid = ? AND session_id = ?";
        $stmt3 = $pdo->prepare($sql3);
        $stmt3->execute([$pnLid, $event['channel_id']]);
        $cacheEntry = $stmt3->fetch(PDO::FETCH_ASSOC);
        
        if ($cacheEntry) {
            echo "  ✅ Cache ENCONTRADO: pnLid {$pnLid} → phone {$cacheEntry['phone_e164']}\n";
        } else {
            echo "  ❌ Cache NÃO ENCONTRADO para pnLid {$pnLid} e session {$event['channel_id']}\n";
        }
    }
} else {
    echo "❌ Evento 6934 não encontrado.\n";
}

// 3) Conversation 34
echo "\n\n3) CONVERSATION 34:\n";
echo str_repeat("=", 120) . "\n";

$sql4 = "SELECT id, channel_id, contact_external_id, tenant_id, updated_at, message_count
FROM conversations
WHERE id = 34";

$stmt4 = $pdo->query($sql4);
$conv = $stmt4->fetch(PDO::FETCH_ASSOC);

if ($conv) {
    echo "✅ Conversation encontrada:\n";
    echo "  ID: {$conv['id']}\n";
    echo "  Channel ID: {$conv['channel_id']}\n";
    echo "  Contact External ID: {$conv['contact_external_id']}\n";
    echo "  Updated: {$conv['updated_at']}\n";
    echo "  Message Count: {$conv['message_count']}\n";
    echo "\n  ⚠️  IMPORTANTE: channel_id usado como sessionId = '{$conv['channel_id']}'\n";
    echo "     (deve ser exatamente 'ImobSites' para a API funcionar)\n";
} else {
    echo "❌ Conversation 34 não encontrada.\n";
}

echo "\n";

