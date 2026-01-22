<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== VERIFICAÇÃO CANAL PIXEL12 DIGITAL ===\n\n";

// Buscar TODOS os eventos recentes do Pixel12 Digital
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
    AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) = 'Pixel12 Digital'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY id DESC
    LIMIT 20
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos Pixel12 Digital (últimas 2 horas): " . count($events) . "\n\n";

if (count($events) > 0) {
    foreach ($events as $e) {
        $payload = json_decode($e['payload'], true);
        $body = $payload['body'] 
            ?? $payload['message']['body'] 
            ?? $payload['message']['content'] 
            ?? 'N/A';
        
        $from = $payload['from'] 
            ?? $payload['message']['from'] 
            ?? 'N/A';
        
        echo sprintf("✅ ID: %4d | Status: %-10s | From: %s | Mensagem: %s | Criado: %s (%s min atrás)\n",
            $e['id'],
            $e['status'],
            substr($from, 0, 30),
            substr($body, 0, 40),
            $e['created_at'],
            $e['minutos_atras']
        );
    }
} else {
    echo "⚠️  NENHUM evento encontrado para o canal 'Pixel12 Digital' nas últimas 2 horas!\n";
}

// Verificar se há mensagens com "teste1827_pixel" ou "pixel" no conteúdo
echo "\n=== BUSCANDO MENSAGENS COM 'pixel' OU 'teste1827' ===\n\n";
$stmt2 = $pdo->query("
    SELECT 
        id,
        event_id,
        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
        payload,
        created_at
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    AND (
        payload LIKE '%teste1827_pixel%'
        OR payload LIKE '%pixel%'
        OR payload LIKE '%teste1827%'
    )
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY id DESC
");

$allTestMessages = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Total encontrado: " . count($allTestMessages) . "\n\n";

foreach ($allTestMessages as $msg) {
    $payload = json_decode($msg['payload'], true);
    $body = $payload['body'] 
        ?? $payload['message']['body'] 
        ?? $payload['message']['content'] 
        ?? 'N/A';
    
    echo sprintf("Channel: %-20s | Mensagem: %s | Criado: %s\n",
        $msg['channel_id'] ?? 'NULL',
        substr($body, 0, 50),
        $msg['created_at']
    );
}

// Verificar mapeamento do @lid que está sendo usado
echo "\n=== VERIFICANDO MAPEAMENTO @lid ===\n\n";
echo "Buscando mapeamento para: 208989199560861@lid (Charles Dietrich)\n";

$stmt3 = $pdo->prepare("
    SELECT * FROM whatsapp_business_ids 
    WHERE business_id LIKE '%208989199560861%'
    OR phone_number LIKE '%208989199560861%'
");

$stmt3->execute();
$mapping = $stmt3->fetch(PDO::FETCH_ASSOC);

if ($mapping) {
    echo "✅ Mapeamento encontrado:\n";
    echo json_encode($mapping, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "❌ Nenhum mapeamento encontrado para 208989199560861@lid\n";
    echo "   Isso explica por que as mensagens não estão criando conversations!\n";
}

echo "\n";

