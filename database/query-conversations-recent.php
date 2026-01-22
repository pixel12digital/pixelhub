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
    
    // Query 1: Conversations recentes
    echo "=== CONVERSATIONS RECENTES ===\n\n";
    $sql1 = "SELECT
  id,
  tenant_id,
  channel_id,
  channel_account_id,
  contact_external_id,
  contact_name,
  status,
  message_count,
  unread_count,
  last_message_at,
  created_at
FROM conversations
WHERE tenant_id = 2
ORDER BY id DESC
LIMIT 10";
    
    $stmt1 = $pdo->prepare($sql1);
    $stmt1->execute();
    $results1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total: " . count($results1) . " conversas\n\n";
    echo json_encode($results1, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    
    // Query 2: Agrupamento por channel_id
    echo "\n=== AGRUPAMENTO POR CHANNEL_ID ===\n\n";
    $sql2 = "SELECT
  channel_id,
  COUNT(*) AS total
FROM conversations
WHERE tenant_id = 2
GROUP BY channel_id
ORDER BY total DESC";
    
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute();
    $results2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    
    // Query 3: Eventos inbound recentes
    echo "\n=== EVENTOS INBOUND RECENTES ===\n\n";
    $sql3 = "SELECT
  id,
  event_id,
  JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
  tenant_id,
  status,
  error_message,
  created_at
FROM communication_events
WHERE event_type = 'whatsapp.inbound.message'
ORDER BY id DESC
LIMIT 10";
    
    $stmt3 = $pdo->prepare($sql3);
    $stmt3->execute();
    $results3 = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total: " . count($results3) . " eventos\n\n";
    echo json_encode($results3, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}

