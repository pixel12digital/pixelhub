<?php
require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'], $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== Eventos outbound de hoje - últimas 20 mensagens ===\n";
$stmt = $pdo->query("
    SELECT
        ce.id,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) AS numero_enviado,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) AS texto,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS canal,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.message_id')) AS whapi_msg_id,
        c.contact_name,
        c.contact_external_id
    FROM communication_events ce
    LEFT JOIN conversations c ON c.id = (
        SELECT conv.id FROM conversations conv
        WHERE conv.contact_external_id LIKE CONCAT('%', REGEXP_REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')), '[^0-9]', ''), '%')
        LIMIT 1
    )
    WHERE ce.event_type = 'whatsapp.outbound.message'
      AND ce.source_system = 'pixelhub_operator'
      AND DATE(ce.created_at) = CURDATE()
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  [{$r['created_at']}] to={$r['numero_enviado']} | canal={$r['canal']} | contato={$r['contact_name']} | msg_id={$r['whapi_msg_id']}\n";
    if ($r['texto']) echo "    texto=" . substr($r['texto'], 0, 80) . "\n";
}

echo "\n=== Conversa Studio Di Capelli (busca por 90994 ou 9994 ou 9249) ===\n";
$stmt2 = $pdo->query("
    SELECT id, contact_name, contact_external_id, channel_id, provider_type, created_at, updated_at
    FROM conversations
    WHERE contact_name LIKE '%Capelli%'
       OR contact_external_id LIKE '%90994%'
       OR contact_external_id LIKE '%9994%'
       OR contact_external_id LIKE '%9249%'
    ORDER BY updated_at DESC
    LIMIT 10
");
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  conv_id={$r['id']} | name={$r['contact_name']} | ext_id={$r['contact_external_id']} | updated={$r['updated_at']}\n";
}
