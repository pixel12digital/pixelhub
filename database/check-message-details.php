<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== DETALHES DAS MENSAGENS RECENTES ===\n\n";

// Buscar eventos inbound recentes com payload
$stmt = $pdo->query("
    SELECT 
        id,
        event_id,
        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
        tenant_id,
        status,
        payload,
        created_at,
        TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS minutos_atras
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY id DESC
    LIMIT 20
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($events) . " eventos\n\n";

foreach ($events as $index => $e) {
    $payload = json_decode($e['payload'], true);
    
    echo str_repeat("=", 80) . "\n";
    echo "MENSAGEM #" . ($index + 1) . "\n";
    echo str_repeat("-", 80) . "\n";
    echo "ID Evento: " . $e['id'] . "\n";
    echo "Event ID: " . $e['event_id'] . "\n";
    echo "Channel ID: " . ($e['channel_id'] ?? 'NULL') . "\n";
    echo "Tenant ID: " . ($e['tenant_id'] ?? 'NULL') . "\n";
    echo "Status: " . $e['status'] . "\n";
    echo "Criado há: " . $e['minutos_atras'] . " minutos\n";
    echo "Data/Hora: " . $e['created_at'] . "\n";
    echo "\n";
    
    // Extrair informações da mensagem
    $from = $payload['from'] 
        ?? $payload['message']['from'] 
        ?? $payload['data']['from'] 
        ?? $payload['raw']['payload']['from'] 
        ?? 'N/A';
    
    $to = $payload['to'] 
        ?? $payload['message']['to'] 
        ?? $payload['data']['to'] 
        ?? 'N/A';
    
    $body = $payload['body'] 
        ?? $payload['message']['body'] 
        ?? $payload['message']['content'] 
        ?? $payload['data']['body'] 
        ?? $payload['raw']['payload']['body'] 
        ?? null;
    
    $messageType = $payload['type'] 
        ?? $payload['message']['type'] 
        ?? $payload['data']['type'] 
        ?? 'unknown';
    
    $notifyName = $payload['notifyName'] 
        ?? $payload['message']['notifyName'] 
        ?? $payload['raw']['payload']['notifyName'] 
        ?? null;
    
    echo "DE: " . $from . "\n";
    echo "PARA: " . $to . "\n";
    echo "TIPO: " . $messageType . "\n";
    
    if ($notifyName) {
        echo "NOME: " . $notifyName . "\n";
    }
    
    if ($body) {
        echo "MENSAGEM:\n";
        echo "  " . substr($body, 0, 200) . (strlen($body) > 200 ? '...' : '') . "\n";
    } else {
        echo "MENSAGEM: (sem texto - pode ser mídia, áudio, etc.)\n";
    }
    
    // Se for mídia, mostrar informações
    if (isset($payload['message']['mediaType']) || isset($payload['message']['mimetype'])) {
        $mediaType = $payload['message']['mediaType'] ?? $payload['message']['mimetype'] ?? 'unknown';
        echo "MÍDIA: " . $mediaType . "\n";
    }
    
    // Mostrar captions se houver
    if (isset($payload['message']['caption'])) {
        echo "LEGENDA: " . substr($payload['message']['caption'], 0, 100) . "\n";
    }
    
    echo "\n";
}

echo "\n";

