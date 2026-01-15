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
    
    echo "=== VERIFICAÇÃO DA CORREÇÃO ===\n\n";
    echo "Data/Hora atual: " . date('Y-m-d H:i:s') . "\n\n";
    
    // 1. Agrupamento de conversations por channel_id
    echo "1. CONVERSATIONS POR CHANNEL_ID:\n";
    echo str_repeat("-", 60) . "\n";
    $sql1 = "SELECT
  channel_id,
  COUNT(*) AS total,
  MAX(created_at) AS ultima_criacao
FROM conversations
WHERE tenant_id = 2
GROUP BY channel_id
ORDER BY total DESC";
    
    $stmt1 = $pdo->prepare($sql1);
    $stmt1->execute();
    $results1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results1 as $row) {
        echo sprintf("  channel_id: %-20s | Total: %2d | Última: %s\n", 
            $row['channel_id'] ?? 'NULL', 
            $row['total'], 
            $row['ultima_criacao']
        );
    }
    
    // 2. Eventos inbound recentes com channel_id = 'ImobSites'
    echo "\n2. EVENTOS RECENTES DO CANAL 'ImobSites' (últimas 2 horas):\n";
    echo str_repeat("-", 60) . "\n";
    $sql2 = "SELECT
  id,
  event_id,
  JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
  tenant_id,
  status,
  created_at
FROM communication_events
WHERE event_type = 'whatsapp.inbound.message'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) = 'ImobSites'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
ORDER BY id DESC
LIMIT 10";
    
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute();
    $results2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total de eventos encontrados: " . count($results2) . "\n";
    foreach ($results2 as $row) {
        echo sprintf("  ID: %4d | Status: %-10s | Tenant: %s | Criado: %s\n",
            $row['id'],
            $row['status'] ?? 'NULL',
            $row['tenant_id'] ?? 'NULL',
            $row['created_at']
        );
    }
    
    // 3. Verificar se há conversations criadas após as 18:00 de hoje com channel_id = 'ImobSites'
    echo "\n3. CONVERSATIONS DO CANAL 'ImobSites' (criadas hoje após 18:00):\n";
    echo str_repeat("-", 60) . "\n";
    $sql3 = "SELECT
  id,
  channel_id,
  channel_account_id,
  contact_external_id,
  contact_name,
  message_count,
  created_at
FROM conversations
WHERE tenant_id = 2
  AND channel_id = 'ImobSites'
  AND created_at >= '2026-01-15 18:00:00'
ORDER BY id DESC";
    
    $stmt3 = $pdo->prepare($sql3);
    $stmt3->execute();
    $results3 = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($results3) > 0) {
        echo "✅ ENCONTRADAS " . count($results3) . " conversations com channel_id = 'ImobSites'!\n";
        foreach ($results3 as $row) {
            echo sprintf("  ID: %3d | Contact: %s | Messages: %2d | Criada: %s\n",
                $row['id'],
                $row['contact_external_id'],
                $row['message_count'],
                $row['created_at']
            );
        }
    } else {
        echo "⚠️  Nenhuma conversation encontrada com channel_id = 'ImobSites' criada após 18:00\n";
        echo "    Isso pode indicar que:\n";
        echo "    - Nenhuma nova mensagem chegou após o deploy\n";
        echo "    - Ou a correção ainda não foi aplicada no servidor\n";
    }
    
    // 4. Comparação: eventos ImobSites vs conversations ImobSites
    echo "\n4. COMPARAÇÃO: Eventos vs Conversations (ImobSites):\n";
    echo str_repeat("-", 60) . "\n";
    
    $sql4a = "SELECT COUNT(*) as total FROM communication_events 
              WHERE event_type = 'whatsapp.inbound.message' 
              AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) = 'ImobSites'
              AND tenant_id = 2
              AND status = 'processed'";
    $stmt4a = $pdo->query($sql4a);
    $eventsCount = $stmt4a->fetch()['total'];
    
    $sql4b = "SELECT COUNT(*) as total FROM conversations 
              WHERE tenant_id = 2 
              AND channel_id = 'ImobSites'";
    $stmt4b = $pdo->query($sql4b);
    $convsCount = $stmt4b->fetch()['total'];
    
    echo sprintf("  Eventos processados (ImobSites): %d\n", $eventsCount);
    echo sprintf("  Conversations criadas (ImobSites): %d\n", $convsCount);
    
    if ($convsCount > 0) {
        echo "  ✅ CORREÇÃO FUNCIONANDO! Há conversations com channel_id = 'ImobSites'\n";
    } else {
        echo "  ⚠️  Ainda não há conversations com channel_id = 'ImobSites'\n";
        echo "      (pode ser que as conversations antigas usem channel_account_id mas não channel_id)\n";
    }
    
    echo "\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}

