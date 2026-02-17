<?php
$pdo = new PDO('mysql:host=r225us.hmservers.net;port=3306;dbname=pixel12digital_pixelhub;charset=utf8mb4', 'pixel12digital_pixelhub', 'Los@ngo#081081');

echo "=== Verificando se tabela webhook_raw_logs existe ===\n";
$stmt = $pdo->query("SHOW TABLES LIKE 'webhook_raw_logs'");
if ($stmt->rowCount() > 0) {
    echo "Tabela webhook_raw_logs encontrada\n";
    
    echo "\n=== Webhooks recebidos (últimas 48h) ===\n";
    $stmt = $pdo->prepare("
        SELECT 
            created_at,
            session_id,
            event_type,
            status,
            error_message
        FROM webhook_raw_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    
    echo "created_at\tsession_id\tevent_type\tstatus\terror_message\n";
    echo str_repeat("-", 120) . "\n";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['created_at']}\t{$row['session_id']}\t{$row['event_type']}\t{$row['status']}\t{$row['error_message']}\n";
    }
    
    echo "\n=== Contagem de webhooks por tipo (últimos 7 dias) ===\n";
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as data,
            session_id,
            event_type,
            COUNT(*) as total
        FROM webhook_raw_logs 
        WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
        GROUP BY DATE(created_at), session_id, event_type
        ORDER BY data DESC, total DESC
        LIMIT 50
    ");
    $stmt->execute();
    
    echo "data\tsession_id\tevent_type\ttotal\n";
    echo str_repeat("-", 80) . "\n";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['data']}\t{$row['session_id']}\t{$row['event_type']}\t{$row['total']}\n";
    }
    
} else {
    echo "Tabela webhook_raw_logs não encontrada\n";
    
    echo "\n=== Verificando outras tabelas de webhook/log ===\n";
    $stmt = $pdo->query("SHOW TABLES LIKE '%webhook%'");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "Tabela encontrada: {$row[0]}\n";
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE '%log%'");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "Tabela encontrada: {$row[0]}\n";
    }
}
