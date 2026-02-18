<?php
$host = 'localhost';
$dbname = 'pixelhub';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $id = 2;
    
    // Busca dados do agenda item
    $stmt = $pdo->prepare("
        SELECT id, title, item_date, time_start, time_end, notes, opportunity_id, lead_id, related_type
        FROM agenda_manual_items
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Follow-up não encontrado']);
        exit;
    }
    
    // Verifica se há mensagem agendada
    $scheduledMessage = null;
    $messageStatus = null;
    $sentAt = null;
    
    try {
        $msgStmt = $pdo->prepare("
            SELECT message_text, status, sent_at
            FROM scheduled_messages
            WHERE agenda_item_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $msgStmt->execute([$id]);
        $msg = $msgStmt->fetch();
        
        if ($msg) {
            $scheduledMessage = $msg['message_text'];
            $messageStatus = $msg['status'];
            $sentAt = $msg['sent_at'];
        }
    } catch (PDOException $e) {
        echo "Erro PDO: " . $e->getMessage() . PHP_EOL;
    } catch (Exception $e) {
        echo "Erro Exception: " . $e->getMessage() . PHP_EOL;
    }
    
    $followup = [
        'id' => $item['id'],
        'title' => $item['title'],
        'item_date' => $item['item_date'],
        'time_start' => $item['time_start'],
        'time_end' => $item['time_end'],
        'notes' => $item['notes'],
        'opportunity_id' => $item['opportunity_id'],
        'lead_id' => $item['lead_id'],
        'related_type' => $item['related_type'],
        'scheduled_message' => $scheduledMessage,
        'status' => $messageStatus,
        'sent_at' => $sentAt,
    ];
    
    echo json_encode([
        'success' => true,
        'followup' => $followup,
    ]);
    
} catch (Exception $e) {
    echo "Erro geral: " . $e->getMessage() . PHP_EOL;
}
