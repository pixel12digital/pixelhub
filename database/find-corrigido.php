<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
use PixelHub\Core\DB;
use PixelHub\Core\Env;
Env::load(__DIR__ . '/../.env');

$db = DB::getConnection();

echo "=== BUSCA POR 'Corrigido' NO PAYLOAD ===\n";
$stmt = $db->query("SELECT id, event_type, created_at, conversation_id, tenant_id FROM communication_events WHERE payload LIKE '%Corrigido%' ORDER BY created_at DESC LIMIT 10");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($results)) {
    echo "Nenhum evento com 'Corrigido' encontrado.\n";
} else {
    foreach ($results as $r) {
        print_r($r);
    }
}

echo "\n=== EVENTOS COM TEXTO (não imagem) PARA ROBSON DAS 10:20-10:25 ===\n";
$stmt = $db->query("
    SELECT id, event_type, created_at, conversation_id, 
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) as msg_text,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) as raw_body
    FROM communication_events 
    WHERE created_at >= '2026-01-29 10:20:00' AND created_at <= '2026-01-29 10:25:00'
    AND payload LIKE '%9988%'
    ORDER BY created_at ASC
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $textPreview = substr($row['msg_text'] ?? '', 0, 50);
    $bodyPreview = substr($row['raw_body'] ?? '', 0, 50);
    echo "ID: {$row['id']} | {$row['event_type']} | {$row['created_at']}\n";
    echo "  text: {$textPreview}...\n";
    echo "  body: {$bodyPreview}...\n";
}
