<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== VERIFICANDO EVENTO 190866 (ÁUDIO DO LUIZ CARLOS) ===\n\n";

$stmt = $db->prepare("
    SELECT id, event_id, event_type, tenant_id, source_system, created_at, payload
    FROM communication_events 
    WHERE id = 190866
");
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "✗ Evento 190866 não encontrado!\n";
    exit(1);
}

echo "Event ID: {$event['event_id']}\n";
echo "Tipo: {$event['event_type']}\n";
echo "Tenant: " . ($event['tenant_id'] ?: 'NULL') . "\n";
echo "Source: {$event['source_system']}\n";
echo "Criado: {$event['created_at']}\n\n";

$payload = json_decode($event['payload'], true);

echo "=== ESTRUTURA DO PAYLOAD ===\n";
echo "Keys principais: " . implode(', ', array_keys($payload)) . "\n\n";

// Verifica se tem mensagem
if (isset($payload['message'])) {
    echo "✓ Tem campo 'message'\n";
    echo "Message keys: " . implode(', ', array_keys($payload['message'])) . "\n";
    
    if (isset($payload['message']['type'])) {
        echo "Message type: {$payload['message']['type']}\n";
    }
    
    // Verifica se é áudio
    if (isset($payload['message']['type']) && $payload['message']['type'] === 'ptt') {
        echo "\n✓ É ÁUDIO (ptt - push-to-talk)\n";
        
        if (isset($payload['message']['mediaUrl'])) {
            echo "✓ Tem mediaUrl: {$payload['message']['mediaUrl']}\n";
        } else {
            echo "✗ NÃO tem mediaUrl\n";
        }
        
        if (isset($payload['message']['mimetype'])) {
            echo "✓ Mimetype: {$payload['message']['mimetype']}\n";
        }
        
        if (isset($payload['message']['duration'])) {
            echo "✓ Duração: {$payload['message']['duration']} segundos\n";
        }
        
        if (isset($payload['message']['size'])) {
            echo "✓ Tamanho: {$payload['message']['size']} bytes\n";
        }
        
        // Verifica se foi enfileirado para processamento
        echo "\n=== VERIFICANDO FILA DE PROCESSAMENTO DE MÍDIA ===\n";
        $queueStmt = $db->prepare("
            SELECT id, event_id, media_type, status, error_message, created_at, processed_at
            FROM media_process_queue
            WHERE event_id = ?
            ORDER BY id DESC
        ");
        $queueStmt->execute([$event['event_id']]);
        $queueItems = $queueStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($queueItems) > 0) {
            foreach ($queueItems as $item) {
                echo "Queue ID: {$item['id']}\n";
                echo "  Status: {$item['status']}\n";
                echo "  Media Type: {$item['media_type']}\n";
                echo "  Criado: {$item['created_at']}\n";
                echo "  Processado: " . ($item['processed_at'] ?: 'NULL') . "\n";
                if ($item['error_message']) {
                    echo "  Erro: {$item['error_message']}\n";
                }
                echo "\n";
            }
        } else {
            echo "✗ NÃO foi enfileirado para processamento de mídia!\n";
        }
    } else {
        echo "\n✗ NÃO é áudio (type: " . ($payload['message']['type'] ?? 'NULL') . ")\n";
    }
} else {
    echo "✗ NÃO tem campo 'message'\n";
}

// Mostra payload completo (primeiros 2000 caracteres)
echo "\n=== PAYLOAD COMPLETO (preview) ===\n";
echo substr(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 0, 2000) . "\n...\n";
