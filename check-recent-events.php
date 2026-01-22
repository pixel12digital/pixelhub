<?php
require 'src/Core/DB.php';
require 'src/Core/Env.php';

\PixelHub\Core\Env::load();
$db = \PixelHub\Core\DB::getConnection();

// Busca eventos recentes (últimos 10 minutos)
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        correlation_id,
        event_type,
        source_system,
        tenant_id,
        status,
        error_message,
        created_at,
        JSON_EXTRACT(payload, '$.message.id') as message_id,
        JSON_EXTRACT(payload, '$.message.from') as message_from,
        JSON_EXTRACT(payload, '$.session.id') as session_id
    FROM communication_events 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ORDER BY created_at DESC 
    LIMIT 20
");

$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Eventos dos Últimos 10 Minutos ===\n";
echo "Total encontrados: " . count($results) . "\n\n";

foreach ($results as $event) {
    echo sprintf(
        "ID: %d | event_id: %s | event_type: %s | status: %s | created_at: %s\n",
        $event['id'],
        $event['event_id'],
        $event['event_type'],
        $event['status'],
        $event['created_at']
    );
    if ($event['error_message']) {
        echo "  ERRO: " . $event['error_message'] . "\n";
    }
    echo "\n";
}

