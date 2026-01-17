<?php
/**
 * Verifica se evento de teste manual foi gravado
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

\PixelHub\Core\Env::load();
$db = \PixelHub\Core\DB::getConnection();

$eventId = 'b073cddf-0ec2-471a-81b4-01e36b5aa888'; // Event ID retornado pelo webhook

echo "=== VERIFICANDO EVENTO DE TESTE MANUAL ===\n\n";
echo "Event ID: {$eventId}\n\n";

// Busca evento por event_id
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.status,
        ce.tenant_id,
        ce.error_message,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
        ce.payload
    FROM communication_events ce
    WHERE ce.event_id = ?
    LIMIT 1
");

$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if ($event) {
    echo "✅ EVENTO ENCONTRADO NO BANCO!\n\n";
    echo "ID: {$event['id']}\n";
    echo "Event ID: {$event['event_id']}\n";
    echo "Event Type: {$event['event_type']}\n";
    echo "Created At: {$event['created_at']}\n";
    echo "Status: {$event['status']}\n";
    echo "Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
    echo "Channel ID: " . ($event['meta_channel'] ?: 'NULL') . "\n";
    if ($event['error_message']) {
        echo "Error Message: {$event['error_message']}\n";
    }
    echo "\n";
    
    $payload = json_decode($event['payload'], true);
    if ($payload && isset($payload['message']['text'])) {
        echo "Payload text: {$payload['message']['text']}\n";
    }
} else {
    echo "❌ EVENTO NÃO ENCONTRADO NO BANCO!\n\n";
    echo "   Webhook retornou event_id={$eventId}\n";
    echo "   Mas evento não foi gravado na tabela communication_events\n\n";
    echo "   🔴 PROBLEMA CRÍTICO: EventIngestionService pode estar lançando exceção silenciosa\n";
    echo "   ou evento está sendo descartado em algum ponto\n\n";
    
    // Busca eventos recentes para contexto
    $stmt = $db->query("
        SELECT id, event_id, event_type, created_at, status
        FROM communication_events
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($recent)) {
        echo "   Eventos criados nos últimos 5 minutos:\n";
        foreach ($recent as $e) {
            echo "      [{$e['created_at']}] ID={$e['id']} | event_id={$e['event_id']} | type={$e['event_type']} | status={$e['status']}\n";
        }
    } else {
        echo "   ⚠️  Nenhum evento criado nos últimos 5 minutos\n";
    }
}

echo "\n";

