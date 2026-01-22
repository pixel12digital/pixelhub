<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== BUSCA: PIXEL12 DIGITAL (19:15 - 19:25) ===\n\n";

$sql = "SELECT id, created_at, status, error_message,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS payload_event,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) AS raw_body
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= '2026-01-15 19:15:00'
  AND created_at <= '2026-01-15 19:25:00'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) = 'Pixel12 Digital'
  AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) = 'message'
ORDER BY created_at DESC";

$stmt = $pdo->query($sql);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total encontrado: " . count($events) . " eventos de mensagem\n\n";

if (count($events) > 0) {
    foreach ($events as $e) {
        $icon = $e['status'] === 'processed' ? '✅' : '❌';
        $text = $e['message_text'] ?: $e['raw_body'] ?: 'SEM TEXTO';
        
        echo sprintf("%s ID: %5d | %s | Status: %-10s | Text: %s | Erro: %s\n",
            $icon,
            $e['id'],
            $e['created_at'],
            $e['status'],
            substr($text, 0, 60),
            substr($e['error_message'] ?: 'OK', 0, 40)
        );
        
        if (strpos(strtolower($text), 'pixel') !== false || strpos(strtolower($text), 'teste1921') !== false) {
            echo "   ^^^^^^ MENSAGEM DE TESTE ENCONTRADA!\n";
        }
    }
} else {
    echo "❌ Nenhuma mensagem do Pixel12 Digital encontrada nesse período.\n";
    echo "\n📌 Conclusão:\n";
    echo "   A mensagem 'teste1921_pixel' que aparece no print pode:\n";
    echo "   1. Ter sido processada ANTES de 19:15\n";
    echo "   2. Não ter chegado ao Hub (problema no gateway-wrapper/WPPConnect)\n";
    echo "   3. Estar com channel_id diferente ou NULL\n";
    echo "   4. Ter sido processada localmente no cliente mas não persistida no banco\n";
}

echo "\n";


