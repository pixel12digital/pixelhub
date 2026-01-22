<?php

/**
 * Script para verificar o payload completo das mensagens vazias do Aguiar
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== VERIFICANDO PAYLOAD DAS MENSAGENS VAZIAS DO AGUIAR ===\n\n";

$contactPhone = '556993245042';
$channelId = 'pixel12digital';

// IDs das mensagens vazias
$emptyEventIds = [
    'ab74b93d-c909-4a64-906b-6dc6a527aa0f',
    '688c5e25-e4d8-42ce-829e-d26554d52c73',
    'fa8397c8-99bf-459c-bb17-d3686e0b5b3f',
    'a3c7b114-1ad0-455d-96ea-f7ef5dc698ae',
    '78583f0b-fcff-4f57-92aa-0347da7722b4',
    '1431b7ee-7d3d-45ed-88f2-7bd595536c38',
    '8ed29221-b97d-4d3c-8834-6e826d55b485',
    '927f2793-487e-4728-8913-74916aeb258a',
    '747e9e54-7bcf-4386-97ec-eb7780feabb3',
    '6e13df0b-8736-4d69-8b8a-1048cc0e2c45',
    '73135c79-cda6-464a-957f-81d160ccce61',
    '6216acf7-2e0a-4bc0-9329-acda1c936d93',
    'b11f24ad-305f-4af5-8e6f-9a5b6e35ff11',
    'd05c2a44-d480-4742-8c68-c8d5a4dcfb83'
];

$placeholders = str_repeat('?,', count($emptyEventIds) - 1) . '?';

$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.payload,
        ce.metadata
    FROM communication_events ce
    WHERE ce.event_id IN ({$placeholders})
    ORDER BY ce.created_at ASC
");
$stmt->execute($emptyEventIds);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Analisando " . count($events) . " eventos vazios:\n\n";

foreach ($events as $idx => $event) {
    $payload = json_decode($event['payload'], true);
    $metadata = json_decode($event['metadata'] ?? '{}', true);
    
    echo "Evento #" . ($idx + 1) . ":\n";
    echo "  ID: {$event['event_id']}\n";
    echo "  Created: {$event['created_at']}\n";
    echo "  Tipo: {$event['event_type']}\n";
    echo "\n";
    echo "  Payload (chaves principais):\n";
    if (is_array($payload)) {
        foreach (array_keys($payload) as $key) {
            $value = $payload[$key];
            if (is_array($value)) {
                echo "    - {$key}: [array com " . count($value) . " itens]\n";
                if (isset($value['type'])) {
                    echo "      → type: {$value['type']}\n";
                }
                if (isset($value['mimetype'])) {
                    echo "      → mimetype: {$value['mimetype']}\n";
                }
                if (isset($value['caption'])) {
                    echo "      → caption: {$value['caption']}\n";
                }
            } else {
                $preview = is_string($value) ? mb_substr($value, 0, 100) : json_encode($value);
                echo "    - {$key}: {$preview}\n";
            }
        }
    }
    echo "\n";
    echo "  Verificando campos específicos:\n";
    echo "    - from: " . ($payload['from'] ?? $payload['message']['from'] ?? 'NULL') . "\n";
    echo "    - to: " . ($payload['to'] ?? $payload['message']['to'] ?? 'NULL') . "\n";
    echo "    - text: " . (isset($payload['text']) ? ($payload['text'] ?: '[VAZIO]') : (isset($payload['message']['text']) ? ($payload['message']['text'] ?: '[VAZIO]') : 'NULL')) . "\n";
    echo "    - body: " . (isset($payload['body']) ? ($payload['body'] ?: '[VAZIO]') : (isset($payload['message']['body']) ? ($payload['message']['body'] ?: '[VAZIO]') : 'NULL')) . "\n";
    echo "    - type: " . ($payload['type'] ?? $payload['message']['type'] ?? 'NULL') . "\n";
    echo "    - mimetype: " . ($payload['mimetype'] ?? $payload['message']['mimetype'] ?? 'NULL') . "\n";
    echo "    - caption: " . ($payload['caption'] ?? $payload['message']['caption'] ?? 'NULL') . "\n";
    
    // Verifica se há dados de mídia
    if (isset($payload['mediaUrl']) || isset($payload['message']['mediaUrl']) || 
        isset($payload['media']) || isset($payload['message']['media'])) {
        echo "    - ⚠️  TEM DADOS DE MÍDIA MAS NÃO ESTÁ SENDO PROCESSADO!\n";
    }
    
    echo "\n";
    echo "  Payload completo (JSON):\n";
    echo "  " . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    echo "\n" . str_repeat("-", 80) . "\n\n";
}

echo "✅ Análise concluída!\n";

