<?php
require 'src/Core/DB.php';
require 'src/Core/Env.php';

\PixelHub\Core\Env::load();
$db = \PixelHub\Core\DB::getConnection();

// Busca eventos mais recentes (últimos 2 horas)
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
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC 
    LIMIT 30
");

$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Eventos das Últimas 2 Horas ===\n";
echo "Total encontrados: " . count($results) . "\n";
echo "Hora atual do servidor: " . date('Y-m-d H:i:s') . "\n\n";

foreach ($results as $event) {
    echo sprintf(
        "[%s] ID: %d | event_id: %s | event_type: %s | status: %s | correlation_id: %s\n",
        $event['created_at'],
        $event['id'],
        substr($event['event_id'], 0, 8) . '...',
        $event['event_type'],
        $event['status'],
        $event['correlation_id'] ? substr($event['correlation_id'], 0, 8) . '...' : 'NULL'
    );
}

// Verifica se há eventos com erro
$stmt2 = $db->prepare("
    SELECT COUNT(*) as total_errors
    FROM communication_events 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    AND status = 'failed'
    AND error_message IS NOT NULL
");
$stmt2->execute();
$errors = $stmt2->fetch();

echo "\n=== Eventos com Erro (últimas 2h) ===\n";
echo "Total: " . $errors['total_errors'] . "\n";

