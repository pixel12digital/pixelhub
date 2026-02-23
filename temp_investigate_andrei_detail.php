<?php
// Carrega o ambiente
define('ROOT_PATH', __DIR__ . '/');
require_once ROOT_PATH . 'src/Core/Env.php';

PixelHub\Core\Env::load();

// Pega configurações do banco
$config = require ROOT_PATH . 'config/database.php';

try {
    $db = new PDO("mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}", $config['username'], $config['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

echo "=== INVESTIGAÇÃO DETALHADA: ANDREI LIMA ===\n\n";

// Buscar os eventos específicos encontrados
$eventIds = [190811, 190810, 190809];

foreach ($eventIds as $eventId) {
    $stmt = $db->prepare("
        SELECT 
            id,
            event_id,
            idempotency_key,
            event_type,
            source_system,
            tenant_id,
            trace_id,
            correlation_id,
            payload,
            metadata,
            status,
            processed_at,
            error_message,
            retry_count,
            created_at,
            updated_at
        FROM communication_events
        WHERE id = :id
    ");
    
    $stmt->execute(['id' => $eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($event) {
        echo "=== EVENTO ID: {$event['id']} ===\n";
        echo "Event ID: {$event['event_id']}\n";
        echo "Idempotency Key: {$event['idempotency_key']}\n";
        echo "Event Type: {$event['event_type']}\n";
        echo "Source: {$event['source_system']}\n";
        echo "Tenant ID: {$event['tenant_id']}\n";
        echo "Status: {$event['status']}\n";
        echo "Processed At: " . ($event['processed_at'] ?? 'NULL') . "\n";
        echo "Error Message: " . ($event['error_message'] ?? 'NULL') . "\n";
        echo "Retry Count: {$event['retry_count']}\n";
        echo "Created: {$event['created_at']}\n";
        echo "Updated: {$event['updated_at']}\n\n";
        
        echo "--- PAYLOAD ---\n";
        $payload = json_decode($event['payload'], true);
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        
        echo "--- METADATA ---\n";
        $metadata = json_decode($event['metadata'], true);
        echo json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        
        echo str_repeat("=", 80) . "\n\n";
    }
}

// Verificar se há eventos queued não processados
echo "\n=== EVENTOS QUEUED NÃO PROCESSADOS (últimas 72h) ===\n";

$stmt = $db->query("
    SELECT 
        id,
        event_type,
        tenant_id,
        status,
        error_message,
        retry_count,
        created_at,
        TIMESTAMPDIFF(HOUR, created_at, NOW()) as hours_ago
    FROM communication_events
    WHERE status = 'queued'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR)
    ORDER BY created_at DESC
    LIMIT 50
");

$queuedEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($queuedEvents) > 0) {
    echo "🔴 ATENÇÃO: " . count($queuedEvents) . " eventos QUEUED não processados:\n\n";
    
    foreach ($queuedEvents as $evt) {
        echo sprintf(
            "ID: %d | Type: %s | Tenant: %d | Retry: %d | Criado há %d horas | Error: %s\n",
            $evt['id'],
            $evt['event_type'],
            $evt['tenant_id'],
            $evt['retry_count'],
            $evt['hours_ago'],
            $evt['error_message'] ?? 'NULL'
        );
    }
} else {
    echo "✅ Nenhum evento queued pendente.\n";
}

// Verificar se há worker processando eventos
echo "\n\n=== ANÁLISE DO PROBLEMA ===\n";

$stmt = $db->query("
    SELECT 
        status,
        COUNT(*) as count,
        MIN(created_at) as oldest,
        MAX(created_at) as newest
    FROM communication_events
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR)
    GROUP BY status
    ORDER BY count DESC
");

$statusStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Distribuição de status dos eventos (últimas 72h):\n";
foreach ($statusStats as $stat) {
    echo sprintf(
        "  %s: %d eventos (mais antigo: %s, mais recente: %s)\n",
        strtoupper($stat['status']),
        $stat['count'],
        $stat['oldest'],
        $stat['newest']
    );
}

echo "\n🔍 DIAGNÓSTICO:\n";
if (count($queuedEvents) > 0) {
    echo "❌ Há eventos QUEUED não processados!\n";
    echo "   Possíveis causas:\n";
    echo "   1. Worker assíncrono não está rodando\n";
    echo "   2. Eventos com tenant_id=0 não estão sendo processados (resolução de tenant falhou)\n";
    echo "   3. Erro no processamento que não foi registrado\n";
}
