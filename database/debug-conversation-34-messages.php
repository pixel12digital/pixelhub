<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== DEBUG: Mensagens para Conversation 34 ===\n\n";

// Buscar conversation 34
$conv = $pdo->query("SELECT id, channel_id, contact_external_id, tenant_id, updated_at FROM conversations WHERE id = 34")->fetch(PDO::FETCH_ASSOC);

if (!$conv) {
    echo "❌ Conversation 34 não encontrada\n";
    exit;
}

echo "Conversation 34:\n";
echo "  Contact External ID: {$conv['contact_external_id']}\n";
echo "  Channel ID: {$conv['channel_id']}\n";
echo "  Última atualização: {$conv['updated_at']}\n\n";

// Buscar TODOS os eventos recentes do ImobSites para ver estrutura
echo "Eventos recentes do ImobSites (últimos 20):\n";
echo str_repeat("=", 140) . "\n";

$sql = "SELECT 
  id,
  created_at,
  status,
  JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS msg_from,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.from')) AS raw_from,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.author')) AS author,
  LEFT(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')), 50) AS text_preview,
  LEFT(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')), 50) AS raw_body_preview
FROM communication_events
WHERE source_system = 'wpp_gateway'
  AND tenant_id = 2
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) = 'ImobSites'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
ORDER BY id DESC
LIMIT 20";

$stmt = $pdo->query($sql);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($events) > 0) {
    echo sprintf("%-8s | %-19s | %-12s | %-40s | %-40s | %-50s\n",
        "ID", "CREATED_AT", "STATUS", "MSG_FROM", "RAW_FROM", "TEXT");
    echo str_repeat("-", 140) . "\n";
    
    foreach ($events as $e) {
        $from = $e['msg_from'] ?: $e['raw_from'] ?: 'NULL';
        $text = $e['text_preview'] ?: $e['raw_body_preview'] ?: '(sem texto)';
        
        echo sprintf("%-8s | %-19s | %-12s | %-40s | %-40s | %-50s\n",
            $e['id'],
            $e['created_at'],
            $e['status'],
            substr($from, 0, 38),
            substr($e['raw_from'] ?: 'NULL', 0, 38),
            substr($text, 0, 48)
        );
    }
    
    echo "\n";
    
    // Verificar se algum evento corresponde ao contact_external_id
    $contactId = $conv['contact_external_id'];
    $matches = [];
    
    foreach ($events as $e) {
        $from = $e['msg_from'] ?: $e['raw_from'] ?: '';
        // Verificar se contém o contact_id (com ou sem prefixo)
        if (strpos($from, $contactId) !== false || 
            strpos(preg_replace('/[^0-9]/', '', $from), $contactId) !== false ||
            strpos($contactId, preg_replace('/[^0-9]/', '', $from)) !== false) {
            $matches[] = $e;
        }
    }
    
    if (count($matches) > 0) {
        echo "✅ Encontrados " . count($matches) . " eventos correspondentes ao contact {$contactId}:\n\n";
        foreach ($matches as $m) {
            echo "  ID: {$m['id']} | {$m['created_at']} | From: " . substr($m['msg_from'] ?: $m['raw_from'] ?: 'NULL', 0, 50) . "\n";
        }
    } else {
        echo "⚠️  Nenhum evento encontrado que corresponda diretamente ao contact_external_id '{$contactId}'.\n";
        echo "   Isso pode indicar que:\n";
        echo "   - O número está em formato diferente no payload (ex: com @c.us, @lid, etc)\n";
        echo "   - Os eventos não chegaram ainda para este contato\n";
        echo "   - A conversation foi criada mas não há mensagens processadas ainda\n";
    }
} else {
    echo "❌ Nenhum evento encontrado do ImobSites nas últimas 2 horas.\n";
}

echo "\n";

