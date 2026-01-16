<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== TODAS AS MENSAGENS DO PIXEL12 DIGITAL HOJE ===\n\n";

$sql = "SELECT id, created_at, status, 
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) AS raw_body
FROM communication_events
WHERE source_system='wpp_gateway'
  AND DATE(created_at) = CURDATE()
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) = 'Pixel12 Digital'
  AND (JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) = 'message' OR event_type LIKE '%message%')
  AND status = 'processed'
ORDER BY created_at DESC
LIMIT 15";

$stmt = $pdo->query($sql);
$msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($msgs) . " mensagens processadas hoje\n\n";

$foundTest = false;
foreach ($msgs as $m) {
    $text = $m['message_text'] ?: $m['raw_body'] ?: 'SEM TEXTO';
    
    if (strpos(strtolower($text), 'pixel') !== false || strpos(strtolower($text), 'teste') !== false) {
        $foundTest = true;
        echo sprintf("✅ ID: %5d | %s | Text: %s\n", $m['id'], $m['created_at'], substr($text, 0, 60));
    } else {
        echo sprintf("   ID: %5d | %s | Text: %s\n", $m['id'], $m['created_at'], substr($text, 0, 60));
    }
}

if (!$foundTest) {
    echo "\n⚠️  Nenhuma mensagem com 'teste' ou 'pixel' encontrada nas mensagens processadas hoje.\n";
    echo "   Isso sugere que a mensagem 'teste1921_pixel' pode ter sido processada antes ou não chegou ao Hub.\n";
}

echo "\n";


