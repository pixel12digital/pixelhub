<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/src/Config/Database.php';

// Simula uma requisição ao endpoint
$_GET['id'] = 2; // ID do follow-up

try {
    $db = Database::getConnection();
    
    // Busca dados do agenda item
    $stmt = $db->prepare("
        SELECT id, title, item_date, time_start, time_end, notes, opportunity_id, lead_id, related_type
        FROM agenda_manual_items
        WHERE id = ?
    ");
    $stmt->execute([$_GET['id']]);
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
        $msgStmt = $db->prepare("
            SELECT message_text, status, sent_at
            FROM scheduled_messages
            WHERE agenda_item_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $msgStmt->execute([$_GET['id']]);
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
