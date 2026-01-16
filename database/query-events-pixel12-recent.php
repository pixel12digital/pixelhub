<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== EVENTOS MAIS RECENTES: Pixel12 Digital ===\n\n";

$sql = "SELECT id, 
  JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
  status, 
  error_message, 
  created_at
FROM communication_events
WHERE source_system = 'wpp_gateway'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) IN ('pixel12digital','Pixel12 Digital')
ORDER BY id DESC
LIMIT 15";

$stmt = $pdo->query($sql);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($events) > 0) {
    echo "Total encontrado: " . count($events) . " eventos\n\n";
    echo str_repeat("=", 120) . "\n";
    echo sprintf("%-8s | %-25s | %-12s | %-70s | %-19s\n", 
        "ID", "CHANNEL_ID", "STATUS", "ERROR_MESSAGE", "CREATED_AT");
    echo str_repeat("-", 120) . "\n";
    
    foreach ($events as $e) {
        $icon = $e['status'] === 'processed' ? '✅' : ($e['status'] === 'queued' ? '⏳' : '❌');
        $error = $e['error_message'] ? substr($e['error_message'], 0, 67) : '(sem erro)';
        
        echo sprintf("%-8s | %-25s | %-12s | %-70s | %-19s\n",
            $icon . ' ' . $e['id'],
            $e['channel_id'] ?: 'NULL',
            $e['status'],
            $error,
            $e['created_at']
        );
    }
    
    echo str_repeat("=", 120) . "\n\n";
    
    // Estatísticas
    $processed = count(array_filter($events, fn($e) => $e['status'] === 'processed'));
    $failed = count(array_filter($events, fn($e) => $e['status'] === 'failed'));
    $queued = count(array_filter($events, fn($e) => $e['status'] === 'queued'));
    
    echo "Resumo:\n";
    echo "  ✅ Processed: $processed\n";
    echo "  ❌ Failed: $failed\n";
    echo "  ⏳ Queued: $queued\n";
    
    if (count($events) > 0) {
        $latest = $events[0];
        echo "\nÚltimo evento: ID {$latest['id']} | {$latest['created_at']} | Status: {$latest['status']}\n";
        if ($latest['error_message']) {
            echo "Erro: {$latest['error_message']}\n";
        }
    }
} else {
    echo "❌ Nenhum evento encontrado para os canais 'pixel12digital' ou 'Pixel12 Digital'.\n";
}

echo "\n";

