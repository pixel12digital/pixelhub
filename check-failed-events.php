<?php
require 'src/Core/DB.php';
require 'src/Core/Env.php';

\PixelHub\Core\Env::load();
$db = \PixelHub\Core\DB::getConnection();

// Busca eventos com erro ou status failed
echo "=== Eventos com Status 'failed' (últimas 24h) ===\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        correlation_id,
        event_type,
        status,
        error_message,
        created_at,
        JSON_EXTRACT(payload, '$.message.id') as message_id
    FROM communication_events 
    WHERE status = 'failed'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at DESC
    LIMIT 20
");

$stmt->execute();
$failed = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($failed) {
    echo "Total: " . count($failed) . "\n\n";
    foreach ($failed as $f) {
        echo sprintf(
            "[%s] event_id: %s | error: %s\n",
            $f['created_at'],
            substr($f['event_id'], 0, 8) . '...',
            substr($f['error_message'] ?: 'Sem mensagem', 0, 100)
        );
    }
} else {
    echo "Nenhum evento com status 'failed' encontrado\n";
}

// Verifica se há eventos duplicados (mesma idempotency_key)
echo "\n=== Verificando Duplicatas (últimas 24h) ===\n";
$stmt2 = $db->prepare("
    SELECT 
        idempotency_key,
        COUNT(*) as count,
        GROUP_CONCAT(event_id ORDER BY created_at DESC SEPARATOR ', ') as event_ids,
        MIN(created_at) as first_created,
        MAX(created_at) as last_created
    FROM communication_events 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY idempotency_key
    HAVING count > 1
    ORDER BY last_created DESC
    LIMIT 10
");

$stmt2->execute();
$duplicates = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if ($duplicates) {
    echo "Encontradas " . count($duplicates) . " idempotency_keys duplicadas:\n";
    foreach ($duplicates as $d) {
        echo sprintf(
            "idempotency_key: %s | count: %d | first: %s | last: %s\n",
            substr($d['idempotency_key'], 0, 50) . '...',
            $d['count'],
            $d['first_created'],
            $d['last_created']
        );
    }
} else {
    echo "Nenhuma duplicata encontrada\n";
}

// Verifica eventos recentes com correlation_id do teste
echo "\n=== Buscando por correlation_id do teste ===\n";
$testCorrelationId = '9858a507-cc4c-4632-8f92-462535eab504';
$stmt3 = $db->prepare("
    SELECT 
        id,
        event_id,
        correlation_id,
        event_type,
        status,
        created_at
    FROM communication_events 
    WHERE correlation_id = ?
    ORDER BY created_at DESC
");

$stmt3->execute([$testCorrelationId]);
$correlated = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if ($correlated) {
    echo "Encontrados " . count($correlated) . " eventos com esse correlation_id:\n";
    foreach ($correlated as $c) {
        echo sprintf(
            "[%s] event_id: %s | event_type: %s | status: %s\n",
            $c['created_at'],
            $c['event_id'],
            $c['event_type'],
            $c['status']
        );
    }
} else {
    echo "❌ Nenhum evento encontrado com correlation_id: $testCorrelationId\n";
}

