<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
use PixelHub\Core\DB;
use PixelHub\Core\Env;
Env::load(__DIR__ . '/../.env');
$pdo = DB::getConnection();

$busca = 'teste 1213';
echo "=== Buscando mensagem: '{$busca}' ===\n\n";

$sql = "SELECT id, event_id, conversation_id, event_type, 
               JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) as from_num,
               JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) as texto,
               JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) as body,
               created_at
        FROM communication_events 
        WHERE JSON_EXTRACT(payload, '$.message.text') LIKE ?
           OR JSON_EXTRACT(payload, '$.raw.payload.body') LIKE ?
           OR JSON_EXTRACT(payload, '$.text') LIKE ?
        ORDER BY created_at DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$pattern = "%{$busca}%";
$stmt->execute([$pattern, $pattern, $pattern]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($results)) {
    echo "NENHUMA mensagem encontrada com '{$busca}'\n";
} else {
    foreach ($results as $r) {
        echo "[{$r['created_at']}] ID={$r['id']} conv={$r['conversation_id']}\n";
        echo "  type: {$r['event_type']}\n";
        echo "  from: {$r['from_num']}\n";
        echo "  texto: {$r['texto']}\n";
        echo "  body: {$r['body']}\n\n";
    }
}
