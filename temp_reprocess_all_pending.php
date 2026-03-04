<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Services\EventIngestionService;

echo "=== REPROCESSAMENTO EM LOTE DE WEBHOOKS PENDENTES ===\n\n";

$db = DB::getConnection();

// Buscar todos os webhooks não processados do tipo message (exceto tipos filtrados)
$stmt = $db->query("
    SELECT id, received_at, event_type, payload_json
    FROM webhook_raw_logs
    WHERE processed = 0
      AND event_type = 'message'
      AND received_at >= '2026-03-04 12:00:00'
    ORDER BY received_at ASC
");
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($webhooks);
echo "Total de webhooks pendentes: {$total}\n\n";

if ($total === 0) {
    echo "✓ Nenhum webhook pendente para processar\n";
    exit;
}

$processed = 0;
$skipped = 0;
$errors = 0;

foreach ($webhooks as $webhook) {
    $payload = json_decode($webhook['payload_json'], true);
    
    if (!$payload) {
        echo "⚠️  [{$webhook['id']}] JSON inválido - SKIP\n";
        $skipped++;
        continue;
    }
    
    // Verificar se é tipo filtrado (e2e_notification, ciphertext, etc.)
    $messageType = $payload['raw']['payload']['type'] ?? $payload['type'] ?? null;
    $skipTypes = ['ciphertext', 'notification_template', 'e2e_notification', 'protocol', 'revoked'];
    
    if (in_array($messageType, $skipTypes, true)) {
        echo "⚠️  [{$webhook['id']}] Tipo '{$messageType}' filtrado - SKIP\n";
        
        // Marcar como processado (foi intencionalmente ignorado)
        $updateStmt = $db->prepare("
            UPDATE webhook_raw_logs 
            SET processed = 1, error_message = ?
            WHERE id = ?
        ");
        $updateStmt->execute(["Filtered: {$messageType}", $webhook['id']]);
        
        $skipped++;
        continue;
    }
    
    // Processar webhook
    try {
        $from = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
        $sessionId = $payload['session']['id'] ?? 'N/A';
        
        echo "[{$webhook['id']}] {$webhook['received_at']} | From: {$from} | Session: {$sessionId}...";
        
        $eventId = EventIngestionService::ingest([
            'event_type' => 'whatsapp.inbound.message',
            'source_system' => 'wpp_gateway',
            'payload' => $payload,
            'tenant_id' => null,
            'process_media_sync' => false,
            'metadata' => [
                'channel_id' => $sessionId,
                'raw_event_type' => 'message',
                'bulk_reprocess' => true
            ]
        ]);
        
        if ($eventId) {
            // Marcar como processado
            $updateStmt = $db->prepare("
                UPDATE webhook_raw_logs 
                SET processed = 1, event_id = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$eventId, $webhook['id']]);
            
            echo " ✓ OK (Event: {$eventId})\n";
            $processed++;
        } else {
            echo " ❌ FALHOU (EventIngestionService retornou NULL)\n";
            $errors++;
        }
        
    } catch (\Throwable $e) {
        echo " ❌ ERRO: {$e->getMessage()}\n";
        
        // Registrar erro
        $updateStmt = $db->prepare("
            UPDATE webhook_raw_logs 
            SET error_message = ?
            WHERE id = ?
        ");
        $updateStmt->execute([substr($e->getMessage(), 0, 500), $webhook['id']]);
        
        $errors++;
    }
    
    // Pequeno delay para não sobrecarregar
    usleep(100000); // 100ms
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "RESUMO:\n";
echo "  Total: {$total}\n";
echo "  ✓ Processados: {$processed}\n";
echo "  ⚠️  Filtrados/Ignorados: {$skipped}\n";
echo "  ❌ Erros: {$errors}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Verificar se a mensagem do Luiz foi processada
echo "\nVerificando se Luiz (16981404507) foi processado...\n";
$luizStmt = $db->query("
    SELECT c.id, c.contact_external_id, c.last_message_at
    FROM conversations c
    WHERE c.contact_external_id LIKE '%16981404507%'
    ORDER BY c.last_message_at DESC
    LIMIT 1
");
$luizConv = $luizStmt->fetch(PDO::FETCH_ASSOC);

if ($luizConv) {
    echo "✓ ENCONTRADO! Conversa ID: {$luizConv['id']}\n";
    echo "  Contact: {$luizConv['contact_external_id']}\n";
    echo "  Última mensagem: {$luizConv['last_message_at']}\n";
} else {
    echo "❌ Luiz ainda não encontrado nas conversas\n";
}

echo "\n=== FIM DO REPROCESSAMENTO ===\n";
