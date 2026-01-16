<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== EVENTOS MAIS RECENTES (últimos 5 minutos) ===\n\n";

// Buscar eventos muito recentes para ver se o novo código está rodando
$sql = "SELECT id, created_at, event_type, status, error_message,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS payload_event,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id
FROM communication_events
WHERE source_system='wpp_gateway'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
ORDER BY id DESC
LIMIT 30";

$stmt = $pdo->query($sql);
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($recent) . " eventos\n\n";

foreach ($recent as $r) {
    $isTechnical = in_array($r['payload_event'], ['connection.update', 'status-find', 'onpresencechanged', 'onack', 'onstatechanged'], true);
    $isMessage = $r['payload_event'] === 'message' || $r['payload_event'] === 'onmessage' || strpos($r['event_type'], 'message') !== false;
    
    $icon = '  ';
    if ($isTechnical && $r['status'] === 'processed') {
        $icon = '✅'; // Esperado: técnicos devem ser processed
    } elseif ($isTechnical && $r['status'] === 'failed') {
        $icon = '❌'; // Problema: técnicos não devem falhar
    } elseif ($isMessage && $r['status'] === 'processed') {
        $icon = '✅'; // Esperado: mensagens processadas
    } elseif ($isMessage && $r['status'] === 'failed') {
        $icon = '⚠️'; // Problema: mensagem falhou
    }
    
    echo sprintf("%s ID: %5d | %s | Status: %-10s | Event: %s | Channel: %s | Erro: %s\n",
        $icon,
        $r['id'],
        $r['created_at'],
        $r['status'],
        substr($r['payload_event'] ?: $r['event_type'], 0, 20),
        $r['channel_id'] ?: 'NULL',
        substr($r['error_message'] ?: 'OK', 0, 40)
    );
}

// Verificar eventos com "teste1921" (do print)
echo "\n\n=== EVENTOS COM 'teste1921' ===\n";
$sql2 = "SELECT id, created_at, event_type, status, error_message,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS payload_event,
  JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS message_text
FROM communication_events
WHERE source_system='wpp_gateway'
  AND (
    payload LIKE '%teste1921%'
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) LIKE '%teste1921%'
  )
ORDER BY id DESC
LIMIT 10";

$stmt2 = $pdo->query($sql2);
$testEvents = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (count($testEvents) > 0) {
    echo "Total: " . count($testEvents) . " eventos de teste\n\n";
    foreach ($testEvents as $te) {
        $icon = $te['status'] === 'processed' ? '✅' : '❌';
        echo sprintf("%s ID: %5d | Status: %-10s | Channel: %s | Text: %s | Erro: %s\n",
            $icon,
            $te['id'],
            $te['status'],
            $te['channel_id'] ?: 'NULL',
            substr($te['message_text'] ?: 'NULL', 0, 30),
            substr($te['error_message'] ?: 'OK', 0, 40)
        );
    }
} else {
    echo "Nenhum evento com 'teste1921' encontrado.\n";
}

echo "\n";
