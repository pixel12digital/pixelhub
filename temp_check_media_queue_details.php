<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== VERIFICANDO PROCESSAMENTO DE MÍDIA DO ÁUDIO ===\n\n";

$eventId = '0c231352-997b-4e32-8596-5139d7ff04e8';

// Busca item da fila de mídia
$stmt = $db->prepare("
    SELECT id, event_id, status, attempts, error_message, 
           created_at, last_attempt_at
    FROM media_process_queue
    WHERE event_id = ?
");
$stmt->execute([$eventId]);
$queueItem = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$queueItem) {
    echo "✗ Item não encontrado na fila de mídia!\n";
    exit(1);
}

echo "Queue ID: {$queueItem['id']}\n";
echo "Event ID: {$queueItem['event_id']}\n";
echo "Status: {$queueItem['status']}\n";
echo "Attempts: {$queueItem['attempts']}\n";
echo "Error: " . ($queueItem['error_message'] ?: 'NULL') . "\n";
echo "Criado: {$queueItem['created_at']}\n";
echo "Última tentativa: " . ($queueItem['last_attempt_at'] ?: 'NULL') . "\n\n";

if ($queueItem['status'] === 'done') {
    echo "✓ Mídia processada com sucesso!\n\n";
    
    // Verifica se o evento foi atualizado com mediaUrl
    $stmt = $db->prepare("
        SELECT id, event_id, payload
        FROM communication_events
        WHERE event_id = ?
    ");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($event) {
        $payload = json_decode($event['payload'], true);
        $mediaUrl = $payload['message']['mediaUrl'] ?? null;
        
        if ($mediaUrl) {
            echo "✓ Evento atualizado com mediaUrl: {$mediaUrl}\n";
        } else {
            echo "✗ PROBLEMA: Evento NÃO foi atualizado com mediaUrl!\n";
            echo "  O processamento de mídia foi concluído mas o payload não foi atualizado.\n";
            echo "  Isso impede que o Inbox exiba o áudio.\n\n";
            
            // Vamos buscar o mediaUrl do payload raw
            echo "=== VERIFICANDO: Buscando mediaUrl do payload raw ===\n\n";
            
            $rawPayload = $payload['raw']['payload'] ?? [];
            $mediaUrl = $rawPayload['deprecatedMms3Url'] ?? $rawPayload['directPath'] ?? null;
            
            if ($mediaUrl) {
                echo "✓ Encontrado URL de mídia no payload raw: {$mediaUrl}\n";
                echo "⚠️ O sistema deveria ter processado e salvo este áudio localmente.\n";
                echo "⚠️ Verifique o WhatsAppMediaService para entender por que não salvou.\n";
            } else {
                echo "✗ Não foi possível encontrar URL de mídia no payload\n";
            }
        }
    }
} else {
    echo "⚠️ Status: {$queueItem['status']}\n";
    if ($queueItem['error_message']) {
        echo "Erro: {$queueItem['error_message']}\n";
    }
}

echo "\n✓ Verificação concluída!\n";
