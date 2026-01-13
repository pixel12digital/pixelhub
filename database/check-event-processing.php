<?php
/**
 * Verificação 1: Status de processamento do evento
 * 
 * Verifica se o evento foi apenas inserido ou também processado
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../');
$db = DB::getConnection();

// Busca o evento mais recente do ServPro
$stmt = $db->prepare("
    SELECT ce.event_id
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    AND (
        ce.payload LIKE '%554796474223%'
        OR ce.payload LIKE '%4796474223%'
        OR ce.payload LIKE '%TESTE SERVPRO%'
    )
    ORDER BY ce.created_at DESC
    LIMIT 1
");
$stmt->execute();
$latest = $stmt->fetch(PDO::FETCH_ASSOC);
$eventId = $latest['event_id'] ?? '006bb2b4-d536-40e3-89ee-061679d3d068'; // Fallback para evento anterior

echo "=== VERIFICAÇÃO 1: Status de Processamento do Evento ===\n\n";

$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.status,
        ce.processed_at,
        ce.retry_count,
        ce.error_message,
        ce.created_at,
        ce.updated_at
    FROM communication_events ce
    WHERE ce.event_id = ?
");

$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "❌ Evento não encontrado: {$eventId}\n";
    exit(1);
}

echo "📋 STATUS DO EVENTO:\n";
echo "   event_id: {$event['event_id']}\n";
echo "   event_type: {$event['event_type']}\n";
echo "   status: {$event['status']}\n";
echo "   processed_at: " . ($event['processed_at'] ?: 'NULL') . "\n";
echo "   retry_count: {$event['retry_count']}\n";
echo "   error_message: " . ($event['error_message'] ?: 'NULL') . "\n";
echo "   created_at: {$event['created_at']}\n";
echo "   updated_at: {$event['updated_at']}\n\n";

// Interpretação
if ($event['status'] === 'queued') {
    echo "⚠️  Evento ainda está em 'queued' - não foi processado pelo pipeline\n";
} elseif ($event['status'] === 'processing') {
    echo "⚠️  Evento está em 'processing' - pode estar travado\n";
} elseif ($event['status'] === 'processed') {
    echo "✅ Evento foi processado (status: processed)\n";
} elseif ($event['status'] === 'failed') {
    echo "❌ Evento falhou no processamento\n";
    echo "   Erro: " . ($event['error_message'] ?: 'Sem mensagem de erro') . "\n";
}

echo "\n";

