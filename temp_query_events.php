<?php
require_once 'config/database.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
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
    
    echo "=== Eventos WhatsApp dos últimos 7 dias ===\n";
    echo "data\tchannel\tevent_type\teventos\tprimeiro\tultimo\n";
    echo str_repeat("-", 100) . "\n";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['data']}\t{$row['channel_account_id']}\t{$row['event_type']}\t{$row['eventos']}\t{$row['primeiro']}\t{$row['ultimo']}\n";
    }
    
    echo "\n=== Últimos eventos de desconexão ===\n";
    
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
            AND (event_type LIKE '%connection%' OR event_type LIKE '%disconnect%' OR event_type LIKE '%unpaired%')
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
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
