<?php
// Simula o ambiente do framework
define('BASE_PATH', __DIR__);

// Inicia sessão
session_start();

// Simula usuário logado
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';
$_SESSION['is_internal'] = true;

// Inclui as classes necessárias
require_once BASE_PATH . '/src/Core/Auth.php';
require_once BASE_PATH . '/src/Config/Database.php';
require_once BASE_PATH . '/src/Controllers/BaseController.php';

try {
    // Testa autenticação
    Auth::requireInternal();
    echo "Auth: OK\n";
    
    // Testa conexão DB
    $db = Database::getConnection();
    echo "DB: OK\n";
    
    $id = 2;
    
    // Busca dados do agenda item
    $stmt = $db->prepare("
        SELECT id, title, item_date, time_start, time_end, notes, opportunity_id, lead_id, related_type
        FROM agenda_manual_items
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        echo "Follow-up não encontrado\n";
        exit;
    }
    
    echo "Item encontrado: " . $item['title'] . "\n";
    
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
        $msgStmt->execute([$id]);
        $msg = $msgStmt->fetch();
        
        if ($msg) {
            $scheduledMessage = $msg['message_text'];
            $messageStatus = $msg['status'];
            $sentAt = $msg['sent_at'];
            echo "Mensagem agendada encontrada\n";
        } else {
            echo "Nenhuma mensagem agendada\n";
        }
    } catch (PDOException $e) {
        echo "Erro PDO: " . $e->getMessage() . "\n";
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
    
    echo "JSON: " . json_encode([
        'success' => true,
        'followup' => $followup,
    ]) . "\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
}
