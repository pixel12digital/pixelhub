<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== EVENTOS FALHADOS DO PIXEL12 DIGITAL ===\n\n";

// Buscar alguns eventos falhados para ver o erro
$stmt = $pdo->query("
    SELECT 
        id,
        event_id,
        status,
        error_message,
        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
        created_at
    FROM communication_events
    WHERE source_system = 'wpp_gateway'
      AND event_type LIKE 'whatsapp.%'
      AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) = 'Pixel12 Digital'
      AND status = 'failed'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY id DESC
    LIMIT 10
");

$failed = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($failed) . " eventos falhados\n\n";

foreach ($failed as $f) {
    echo sprintf("ID: %4d | Error: %s\n",
        $f['id'],
        substr($f['error_message'] ?? 'NULL', 0, 100)
    );
}

// Verificar eventos processados do Pixel12 Digital
echo "\n\n=== EVENTOS PROCESSADOS DO PIXEL12 DIGITAL (últimas 2h) ===\n\n";
$stmt2 = $pdo->query("
    SELECT 
        id,
        event_id,
        status,
        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
        tenant_id,
        created_at
    FROM communication_events
    WHERE source_system = 'wpp_gateway'
      AND event_type LIKE 'whatsapp.%'
      AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) = 'Pixel12 Digital'
      AND status = 'processed'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY id DESC
    LIMIT 10
");

$processed = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($processed) . " eventos processados\n\n";

foreach ($processed as $p) {
    echo sprintf("✅ ID: %4d | Tenant: %s | Criado: %s\n",
        $p['id'],
        $p['tenant_id'] ?? 'NULL',
        $p['created_at']
    );
}

echo "\n";

