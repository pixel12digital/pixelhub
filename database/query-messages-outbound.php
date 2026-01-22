<?php

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

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

try {
    $pdo = DB::getConnection();
    
    $sql = "SELECT
  id,
  event_id,
  event_type,
  JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
  tenant_id,
  status,
  error_message,
  created_at
FROM communication_events
WHERE event_type = 'whatsapp.outbound.message'
ORDER BY id DESC
LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== Mensagens Enviadas (Outbound) ===\n\n";
    echo "Total de registros encontrados: " . count($results) . "\n\n";
    
    if (count($results) === 0) {
        echo "Nenhuma mensagem enviada encontrada.\n";
        exit(0);
    }
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "RESULTADOS:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    foreach ($results as $index => $row) {
        echo "Registro #" . ($index + 1) . ":\n";
        echo "  id: " . ($row['id'] ?? 'NULL') . "\n";
        echo "  event_id: " . ($row['event_id'] ?? 'NULL') . "\n";
        echo "  event_type: " . ($row['event_type'] ?? 'NULL') . "\n";
        echo "  channel_id: " . ($row['channel_id'] ?? 'NULL') . "\n";
        echo "  tenant_id: " . ($row['tenant_id'] ?? 'NULL') . "\n";
        echo "  status: " . ($row['status'] ?? 'NULL') . "\n";
        echo "  error_message: " . ($row['error_message'] ?? 'NULL') . "\n";
        echo "  created_at: " . ($row['created_at'] ?? 'NULL') . "\n";
        echo "\n";
        echo "────────────────────────────────────────────────────────────────────────────────\n\n";
    }
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "RESULTADOS (JSON):\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}

