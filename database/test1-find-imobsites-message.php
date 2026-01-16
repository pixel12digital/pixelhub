<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== TESTE 1: Buscar evento onmessage ImobSites por texto ===\n\n";

$searchText = 'TESTE_IMOBSITES_001';

echo "Buscando texto: '{$searchText}'\n\n";

// Ajustando query para a estrutura real da tabela communication_events
$sql = "SELECT 
  id,
  created_at,
  tenant_id,
  JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
  event_type,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS `from`,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.from')) AS raw_from,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.author')) AS author,
  LEFT(payload, 300) AS payload_preview,
  status,
  error_message
FROM communication_events
WHERE source_system = 'wpp_gateway'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) = 'ImobSites'
  AND (
    payload LIKE ?
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) LIKE ?
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) LIKE ?
  )
ORDER BY id DESC
LIMIT 10";

$pattern = '%' . $searchText . '%';

$stmt = $pdo->prepare($sql);
$stmt->execute([$pattern, $pattern, $pattern]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($events) > 0) {
    echo "✅ " . count($events) . " evento(s) encontrado(s)!\n\n";
    echo str_repeat("=", 150) . "\n";
    echo sprintf("%-8s | %-19s | %-10s | %-25s | %-30s | %-40s | %-12s | %-200s\n",
        "ID", "CREATED_AT", "TENANT_ID", "CHANNEL_ID", "EVENT_TYPE", "FROM", "STATUS", "PAYLOAD_PREVIEW");
    echo str_repeat("-", 150) . "\n";
    
    foreach ($events as $e) {
        $from = $e['from'] ?: $e['raw_from'] ?: $e['author'] ?: 'NULL';
        $eventType = $e['event_type'];
        $status = $e['status'];
        $payloadPreview = substr($e['payload_preview'], 0, 195);
        
        $icon = $status === 'processed' ? '✅' : ($status === 'failed' ? '❌' : '⏳');
        $isMessage = (strpos(strtolower($eventType), 'message') !== false || 
                      strpos(strtolower($e['payload_preview']), 'onmessage') !== false ||
                      strpos(strtolower($e['payload_preview']), '"event":"message"') !== false) ? '📨' : '';
        
        echo sprintf("%-8s | %-19s | %-10s | %-25s | %-30s | %-40s | %-12s | %-200s\n",
            $icon . ' ' . $isMessage . ' ' . $e['id'],
            $e['created_at'],
            $e['tenant_id'] ?: 'NULL',
            $e['channel_id'] ?: 'NULL',
            substr($eventType, 0, 28),
            substr($from, 0, 38),
            $status,
            $payloadPreview
        );
    }
    
    echo str_repeat("=", 150) . "\n\n";
    
    // Análise
    $messageEvents = array_filter($events, function($e) {
        $eventType = strtolower($e['event_type']);
        $payload = strtolower($e['payload_preview']);
        return (strpos($eventType, 'message') !== false || 
                strpos($payload, 'onmessage') !== false ||
                strpos($payload, '"event":"message"') !== false);
    });
    
    if (count($messageEvents) > 0) {
        echo "✅ CONCLUSÃO: O Hub RECEBEU o evento onmessage!\n";
        echo "   - " . count($messageEvents) . " evento(s) de mensagem encontrado(s)\n";
        echo "   - Todos processados: " . (count(array_filter($messageEvents, fn($e) => $e['status'] === 'processed')) > 0 ? 'SIM' : 'NÃO') . "\n\n";
        
        foreach ($messageEvents as $me) {
            echo "   📨 Evento ID {$me['id']}:\n";
            echo "      - Status: {$me['status']}\n";
            echo "      - Event Type: {$me['event_type']}\n";
            echo "      - Created At: {$me['created_at']}\n";
            if ($me['error_message']) {
                echo "      - Erro: {$me['error_message']}\n";
            }
        }
    } else {
        echo "⚠️  ATENÇÃO: Eventos encontrados, mas NÃO são eventos de mensagem (onmessage).\n";
        echo "   - Pode ser evento técnico (connection.update, etc)\n";
        echo "   - Verifique o event_type e payload acima\n";
    }
} else {
    echo "❌ Nenhum evento encontrado com o texto '{$searchText}'.\n\n";
    echo "⚠️  CONCLUSÃO: A mensagem NÃO chegou no Hub (ou não contém esse texto).\n";
    echo "   Possíveis causas:\n";
    echo "   1. Mensagem não foi enviada\n";
    echo "   2. Gateway-wrapper não entregou ao Hub\n";
    echo "   3. Erro na transformação do payload\n";
    echo "   4. Texto diferente do esperado\n\n";
    
    // Buscar eventos recentes do ImobSites para comparação
    echo "Para referência, buscando últimos eventos do ImobSites...\n\n";
    
    $refSql = "SELECT id, created_at, event_type, status,
      LEFT(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')), 60) AS text_preview
    FROM communication_events
    WHERE source_system = 'wpp_gateway'
      AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) = 'ImobSites'
    ORDER BY id DESC
    LIMIT 5";
    
    $refStmt = $pdo->query($refSql);
    $refEvents = $refStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($refEvents) > 0) {
        echo "Últimos 5 eventos do ImobSites:\n";
        foreach ($refEvents as $re) {
            echo "  ID {$re['id']} | {$re['created_at']} | {$re['event_type']} | {$re['status']} | Text: {$re['text_preview']}\n";
        }
    }
}

echo "\n";

