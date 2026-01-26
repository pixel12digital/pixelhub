<?php

/**
 * Endpoint para verificar status do webhook
 * Acesse: /api/whatsapp/webhook-status
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../src/Core/DB.php';
require_once __DIR__ . '/../../../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

try {
    Env::load();
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao carregar configurações'], JSON_UNESCAPED_UNICODE);
    exit;
}

$response = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'webhook_url' => Env::get('PIXELHUB_WHATSAPP_WEBHOOK_URL', 'Não configurado'),
    'status' => 'online'
];

// Verifica eventos recentes
try {
    $db = DB::getConnection();
    
    // Último evento recebido
    $stmt = $db->query("
        SELECT 
            ce.event_id,
            ce.created_at,
            TIMESTAMPDIFF(MINUTE, ce.created_at, NOW()) as minutes_ago,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as text
        FROM communication_events ce
        WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        ORDER BY ce.created_at DESC
        LIMIT 1
    ");
    $lastEvent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastEvent) {
        $response['last_event'] = [
            'received_at' => $lastEvent['created_at'],
            'minutes_ago' => (int)$lastEvent['minutes_ago'],
            'text_preview' => substr($lastEvent['text'] ?: 'NULL', 0, 50)
        ];
        
        if ($lastEvent['minutes_ago'] > 5) {
            $response['status'] = 'warning';
            $response['message'] = 'Nenhum evento recebido há mais de 5 minutos';
        } else {
            $response['status'] = 'active';
            $response['message'] = 'Webhook está recebendo mensagens';
        }
    } else {
        $response['status'] = 'no_events';
        $response['message'] = 'Nenhum evento encontrado no banco de dados';
    }
    
    // Eventos das últimas 2 horas
    $stmt2 = $db->query("
        SELECT COUNT(*) as count
        FROM communication_events
        WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ");
    $recentCount = $stmt2->fetch(PDO::FETCH_ASSOC);
    $response['events_last_2_hours'] = (int)$recentCount['count'];
    
} catch (\Exception $e) {
    $response['database_error'] = $e->getMessage();
}

http_response_code(200);
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

