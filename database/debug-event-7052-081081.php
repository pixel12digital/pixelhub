<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== DEBUG: Evento 7052 (mensagem 081081) ===\n\n";

// Buscar payload completo do evento
$stmt = $pdo->prepare("
    SELECT 
        id,
        event_type,
        status,
        created_at,
        payload,
        metadata
    FROM communication_events
    WHERE id IN (7052, 7048)
    ORDER BY id DESC
");

$stmt->execute();
$events = $stmt->fetchAll();

foreach ($events as $event) {
    echo "Evento ID: {$event['id']}\n";
    echo "Tipo: {$event['event_type']}\n";
    echo "Status: {$event['status']}\n";
    echo "Created: {$event['created_at']}\n";
    echo "\n";
    
    // Decodifica payload
    $payload = json_decode($event['payload'], true);
    $metadata = json_decode($event['metadata'] ?? '{}', true);
    
    echo "METADATA:\n";
    echo json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    echo "PAYLOAD (primeiros 2000 caracteres):\n";
    echo substr($event['payload'], 0, 2000) . "\n\n";
    
    echo "PAYLOAD (estrutura):\n";
    if (is_array($payload)) {
        echo "Keys: " . implode(', ', array_keys($payload)) . "\n\n";
        
        // Tenta extrair from/to de diferentes caminhos
        $fromPaths = [
            'from' => $payload['from'] ?? null,
            'to' => $payload['to'] ?? null,
            'message.from' => $payload['message']['from'] ?? null,
            'message.to' => $payload['message']['to'] ?? null,
            'data.from' => $payload['data']['from'] ?? null,
            'data.to' => $payload['data']['to'] ?? null,
            'raw.payload.from' => $payload['raw']['payload']['from'] ?? null,
            'raw.payload.to' => $payload['raw']['payload']['to'] ?? null,
            'raw.from' => $payload['raw']['from'] ?? null,
            'raw.to' => $payload['raw']['to'] ?? null,
        ];
        
        echo "FROM/TO em diferentes caminhos:\n";
        foreach ($fromPaths as $path => $value) {
            if ($value !== null) {
                echo "  {$path}: {$value}\n";
            }
        }
        
        // Tenta extrair body/text
        $bodyPaths = [
            'body' => $payload['body'] ?? null,
            'text' => $payload['text'] ?? null,
            'message.body' => $payload['message']['body'] ?? null,
            'message.text' => $payload['message']['text'] ?? null,
            'data.body' => $payload['data']['body'] ?? null,
            'data.text' => $payload['data']['text'] ?? null,
            'raw.payload.body' => $payload['raw']['payload']['body'] ?? null,
            'raw.payload.text' => $payload['raw']['payload']['text'] ?? null,
        ];
        
        echo "\nBODY/TEXT em diferentes caminhos:\n";
        foreach ($bodyPaths as $path => $value) {
            if ($value !== null) {
                echo "  {$path}: " . substr((string)$value, 0, 100) . "\n";
            }
        }
        
        // Verifica se tem "081081" no payload
        if (strpos($event['payload'], '081081') !== false) {
            echo "\n✅ TEXTO '081081' ENCONTRADO no payload!\n";
            $pos = strpos($event['payload'], '081081');
            echo "Posição: {$pos}\n";
            echo "Contexto (100 chars antes/depois):\n";
            echo substr($event['payload'], max(0, $pos - 100), 200) . "\n";
        }
    }
    
    echo str_repeat("=", 80) . "\n\n";
}

echo "\n";

