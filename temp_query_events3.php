<?php
// Conexão direta ao banco remoto para análise
$host = 'r225us.hmservers.net';
$port = '3306';
$database = 'pixel12digital_pixelhub';
$username = 'pixel12digital_pixelhub';
$password = 'Los@ngo#081081';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
    
    echo "=== Eventos WhatsApp dos últimos 7 dias ===\n";
    
    // Eventos dos últimos 7 dias por sessão
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as data,
            channel_type,
            channel_account_id,
            event_type,
            COUNT(*) as eventos,
            MIN(created_at) as primeiro,
            MAX(created_at) as ultimo
        FROM communication_events 
        WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
            AND channel_type = 'whatsapp'
        GROUP BY DATE(created_at), channel_type, channel_account_id, event_type
        ORDER BY data DESC, eventos DESC
        LIMIT 50
    ");
    $stmt->execute();
    
    echo "data\tchannel\tevent_type\teventos\tprimeiro\tultimo\n";
    echo str_repeat("-", 100) . "\n";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['data']}\t{$row['channel_account_id']}\t{$row['event_type']}\t{$row['eventos']}\t{$row['primeiro']}\t{$row['ultimo']}\n";
    }
    
    echo "\n=== Últimos eventos de conexão ===\n";
    
    // Buscar eventos de connection.update ou desconexão
    $stmt2 = $pdo->prepare("
        SELECT 
            channel_account_id,
            event_type,
            event_data,
            created_at
        FROM communication_events 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR)
            AND channel_type = 'whatsapp'
            AND (event_type LIKE '%connection%' OR event_type LIKE '%disconnect%' OR event_type LIKE '%unpaired%' OR event_type LIKE '%qr%')
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt2->execute();
    
    while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        $data = json_decode($row['event_data'], true);
        $status = $data['status'] ?? 'N/A';
        $reason = $data['reason'] ?? 'N/A';
        echo "{$row['created_at']}\t{$row['channel_account_id']}\t{$row['event_type']}\tstatus={$status}\treason={$reason}\n";
    }
    
    echo "\n=== Padrão de mensagens por hora (últimas 48h) ===\n";
    
    // Verificar padrão de mensagens por hora
    $stmt3 = $pdo->prepare("
        SELECT 
            channel_account_id,
            DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hora,
            SUM(CASE WHEN event_type = 'message.received' THEN 1 ELSE 0 END) as mensagens_recebidas,
            SUM(CASE WHEN event_type = 'message.sent' THEN 1 ELSE 0 END) as mensagens_enviadas,
            COUNT(*) as total_eventos
        FROM communication_events 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
            AND channel_type = 'whatsapp'
        GROUP BY channel_account_id, DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')
        ORDER BY hora DESC, channel_account_id
        LIMIT 100
    ");
    $stmt3->execute();
    
    echo "hora\tchannel\trecebidas\tenviadas\ttotal\n";
    echo str_repeat("-", 80) . "\n";
    
    while ($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['hora']}\t{$row['channel_account_id']}\t{$row['mensagens_recebidas']}\t{$row['mensagens_enviadas']}\t{$row['total_eventos']}\n";
    }
    
    echo "\n=== Sessões mais recentes e status ===\n";
    
    // Verificar status mais recente das sessões
    $stmt4 = $pdo->prepare("
        SELECT 
            channel_account_id,
            event_type,
            event_data,
            created_at
        FROM communication_events 
        WHERE channel_type = 'whatsapp'
            AND (event_type LIKE '%connection%' OR event_type LIKE '%status%' OR event_type LIKE '%qr%')
        ORDER BY channel_account_id, created_at DESC
    ");
    $stmt4->execute();
    
    $sessions = [];
    while ($row = $stmt4->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($sessions[$row['channel_account_id']])) {
            $sessions[$row['channel_account_id']] = $row;
        }
    }
    
    foreach ($sessions as $channel => $lastEvent) {
        $data = json_decode($lastEvent['event_data'], true);
        $status = $data['status'] ?? 'N/A';
        echo "{$channel}\t{$lastEvent['event_type']}\t{$status}\t{$lastEvent['created_at']}\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
