<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== VALIDAÇÃO: ImobSites ===\n\n";

// (A) Eventos ImobSites
echo "(A) EVENTOS IMOBSITES:\n";
echo str_repeat("=", 120) . "\n";

$sqlA = "SELECT id,
  JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
  status,
  error_message,
  created_at
FROM communication_events
WHERE source_system = 'wpp_gateway'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) IN ('ImobSites','imobsites')
ORDER BY id DESC
LIMIT 15";

$stmtA = $pdo->query($sqlA);
$events = $stmtA->fetchAll(PDO::FETCH_ASSOC);

if (count($events) > 0) {
    echo "Total encontrado: " . count($events) . " eventos\n\n";
    echo sprintf("%-8s | %-25s | %-12s | %-50s | %-19s\n",
        "ID", "CHANNEL_ID", "STATUS", "ERROR_MESSAGE", "CREATED_AT");
    echo str_repeat("-", 120) . "\n";
    
    foreach ($events as $e) {
        $icon = $e['status'] === 'processed' ? '✅' : ($e['status'] === 'queued' ? '⏳' : '❌');
        $error = $e['error_message'] ? substr($e['error_message'], 0, 48) : '(sem erro)';
        
        echo sprintf("%-8s | %-25s | %-12s | %-50s | %-19s\n",
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
    
    echo "Resumo (A):\n";
    echo "  ✅ Processed: $processed\n";
    echo "  ❌ Failed: $failed\n";
    echo "  ⏳ Queued: $queued\n";
    
    if (count($events) > 0) {
        $latest = $events[0];
        echo "\nÚltimo evento: ID {$latest['id']} | {$latest['created_at']} | Status: {$latest['status']}\n";
    }
} else {
    echo "❌ Nenhum evento encontrado para os canais 'ImobSites' ou 'imobsites'.\n";
}

echo "\n\n";

// (B) Conversations ImobSites
echo "(B) CONVERSATIONS IMOBSITES:\n";
echo str_repeat("=", 120) . "\n";

$sqlB = "SELECT id,
  channel_id,
  contact_external_id,
  updated_at
FROM conversations
WHERE channel_id IN ('ImobSites','imobsites')
ORDER BY updated_at DESC
LIMIT 10";

$stmtB = $pdo->query($sqlB);
$conversations = $stmtB->fetchAll(PDO::FETCH_ASSOC);

if (count($conversations) > 0) {
    echo "Total encontrado: " . count($conversations) . " conversations\n\n";
    echo sprintf("%-8s | %-25s | %-40s | %-19s\n",
        "ID", "CHANNEL_ID", "CONTACT_EXTERNAL_ID", "UPDATED_AT");
    echo str_repeat("-", 120) . "\n";
    
    foreach ($conversations as $c) {
        $contact = $c['contact_external_id'] ? substr($c['contact_external_id'], 0, 38) : 'NULL';
        
        echo sprintf("%-8s | %-25s | %-40s | %-19s\n",
            $c['id'],
            $c['channel_id'] ?: 'NULL',
            $contact,
            $c['updated_at']
        );
    }
    
    echo str_repeat("=", 120) . "\n\n";
    
    if (count($conversations) > 0) {
        $latest = $conversations[0];
        echo "Última conversation atualizada: ID {$latest['id']} | {$latest['updated_at']}\n";
        
        // Contar conversations únicas
        $uniqueContacts = array_unique(array_column($conversations, 'contact_external_id'));
        echo "Contatos únicos: " . count($uniqueContacts) . "\n";
    }
} else {
    echo "❌ Nenhuma conversation encontrada para os canais 'ImobSites' ou 'imobsites'.\n";
}

echo "\n";

