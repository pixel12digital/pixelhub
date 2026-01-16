<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== MENSAGENS: Conversation ID 34 (ImobSites) ===\n\n";

// Primeiro, buscar informações da conversation 34
$convSql = "SELECT id, channel_id, contact_external_id, tenant_id 
FROM conversations 
WHERE id = 34";
$convStmt = $pdo->query($convSql);
$conversation = $convStmt->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    echo "❌ Conversation ID 34 não encontrada.\n";
    exit;
}

echo "Conversation 34:\n";
echo "  Channel ID: {$conversation['channel_id']}\n";
echo "  Contact External ID: {$conversation['contact_external_id']}\n";
echo "  Tenant ID: {$conversation['tenant_id']}\n\n";

echo "⚠️  Nota: A tabela 'messages' ainda não foi criada.\n";
echo "As mensagens estão armazenadas em 'communication_events'.\n\n";

// Buscar eventos relacionados à conversation 34
// Busca por tenant_id, channel_id e contact_external_id
$contactId = $conversation['contact_external_id'];
$tenantId = $conversation['tenant_id'];
$channelId = $conversation['channel_id'];

$sql = "SELECT 
  id,
  event_type,
  status,
  JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS payload_event,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) AS raw_body,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS message_from,
  created_at
FROM communication_events
WHERE source_system = 'wpp_gateway'
  AND tenant_id = ?
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) = ?
  AND (
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) LIKE ?
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) LIKE ?
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.from')) LIKE ?
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.from')) LIKE ?
  )
  AND (
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) = 'message'
    OR event_type LIKE '%message%'
  )
  AND status = 'processed'
ORDER BY id DESC
LIMIT 10";

$contactPattern1 = '%' . $contactId . '%';
$contactPattern2 = '%' . substr($contactId, -10) . '%'; // últimos 10 dígitos

$stmt = $pdo->prepare($sql);
$stmt->execute([$tenantId, $channelId, $contactPattern1, $contactPattern2, $contactPattern1, $contactPattern2]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($messages) > 0) {
    echo "Total encontrado: " . count($messages) . " mensagens relacionadas\n\n";
    echo str_repeat("=", 130) . "\n";
    echo sprintf("%-8s | %-25s | %-12s | %-50s | %-19s\n",
        "ID", "CHANNEL_ID", "STATUS", "MESSAGE_TEXT", "CREATED_AT");
    echo str_repeat("-", 130) . "\n";
    
    foreach ($messages as $m) {
        $text = $m['message_text'] ?: $m['raw_body'] ?: '(sem texto)';
        $icon = $m['status'] === 'processed' ? '✅' : '❌';
        
        echo sprintf("%-8s | %-25s | %-12s | %-50s | %-19s\n",
            $icon . ' ' . $m['id'],
            $m['channel_id'] ?: 'NULL',
            $m['status'],
            substr($text, 0, 48),
            $m['created_at']
        );
    }
    
    echo str_repeat("=", 130) . "\n\n";
    
    if (count($messages) > 0) {
        $latest = $messages[0];
        echo "Última mensagem: ID {$latest['id']} | {$latest['created_at']}\n";
        if ($latest['message_text'] || $latest['raw_body']) {
            $text = $latest['message_text'] ?: $latest['raw_body'];
            echo "Texto: " . substr($text, 0, 100) . "\n";
        }
    }
} else {
    echo "❌ Nenhuma mensagem encontrada relacionada à conversation 34.\n";
    echo "\n⚠️  Isso indica que:\n";
    echo "   - Os eventos foram processados, mas não há mensagens associadas ao contato {$contactId}\n";
    echo "   - Ou os eventos ainda não chegaram para este contato\n";
    echo "   - Ou o contact_external_id não corresponde ao 'from' nos payloads\n";
}

echo "\n";
