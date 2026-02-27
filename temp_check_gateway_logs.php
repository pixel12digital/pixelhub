<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

// Vamos verificar se há webhook de confirmação (ACK) do gateway para a mensagem de cobrança
echo "=== VERIFICANDO WEBHOOK RAW LOGS (27/02/2026 08:50-09:00) ===\n\n";

$stmt = $db->prepare("
    SELECT 
        id,
        event_type,
        received_at,
        JSON_EXTRACT(payload_json, '$.to') as phone_to,
        JSON_EXTRACT(payload_json, '$.from') as phone_from,
        JSON_EXTRACT(payload_json, '$.id') as message_id,
        JSON_EXTRACT(payload_json, '$.ack') as ack_status,
        SUBSTRING(payload_json, 1, 200) as payload_preview
    FROM webhook_raw_logs
    WHERE DATE(received_at) = '2026-02-27'
      AND TIME(received_at) BETWEEN '08:50:00' AND '09:00:00'
    ORDER BY received_at DESC
    LIMIT 20
");
$stmt->execute();
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de webhooks encontrados: " . count($webhooks) . "\n\n";

foreach ($webhooks as $w) {
    echo "─────────────────────────────────────────\n";
    echo "ID: " . $w['id'] . "\n";
    echo "Tipo: " . $w['event_type'] . "\n";
    echo "Recebido em: " . $w['received_at'] . "\n";
    echo "Para: " . ($w['phone_to'] ?? 'NULL') . "\n";
    echo "De: " . ($w['phone_from'] ?? 'NULL') . "\n";
    echo "Message ID: " . ($w['message_id'] ?? 'NULL') . "\n";
    echo "ACK Status: " . ($w['ack_status'] ?? 'NULL') . "\n";
    echo "Payload (preview): " . substr($w['payload_preview'], 0, 150) . "...\n";
    echo "\n";
}

// Agora vamos verificar se o BillingSenderService registrou algum log
echo "\n=== VERIFICANDO LOGS DO BILLING SENDER ===\n\n";

$logFile = __DIR__ . '/logs/billing_dispatch.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    
    // Filtra linhas do dia 27/02/2026 entre 08:50 e 09:00
    $relevantLines = array_filter($lines, function($line) {
        return strpos($line, '2026-02-27') !== false 
            && (strpos($line, '08:5') !== false || strpos($line, '09:0') !== false);
    });
    
    echo "Linhas relevantes encontradas: " . count($relevantLines) . "\n\n";
    
    foreach ($relevantLines as $line) {
        echo $line . "\n";
    }
} else {
    echo "Arquivo de log não encontrado: {$logFile}\n";
}

// Verificar também o log principal do PixelHub
echo "\n=== VERIFICANDO LOGS PRINCIPAIS (pixelhub.log) ===\n\n";

$mainLogFile = __DIR__ . '/logs/pixelhub.log';
if (file_exists($mainLogFile)) {
    $logContent = file_get_contents($mainLogFile);
    $lines = explode("\n", $logContent);
    
    // Filtra linhas do dia 27/02/2026 entre 08:50 e 09:00 que mencionam "billing" ou "invoice" ou "Renato"
    $relevantLines = array_filter($lines, function($line) {
        $hasDate = strpos($line, '2026-02-27') !== false;
        $hasTime = strpos($line, '08:5') !== false || strpos($line, '09:0') !== false;
        $hasKeyword = stripos($line, 'billing') !== false 
                   || stripos($line, 'invoice') !== false 
                   || stripos($line, 'renato') !== false
                   || stripos($line, 'timeout') !== false;
        return $hasDate && $hasTime && $hasKeyword;
    });
    
    echo "Linhas relevantes encontradas: " . count($relevantLines) . "\n\n";
    
    $count = 0;
    foreach ($relevantLines as $line) {
        echo $line . "\n";
        $count++;
        if ($count >= 30) {
            echo "\n... (mostrando apenas as primeiras 30 linhas)\n";
            break;
        }
    }
} else {
    echo "Arquivo de log não encontrado: {$mainLogFile}\n";
}
