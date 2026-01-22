<?php
/**
 * Script para localizar mídia recebida de 5511965221349
 */

require_once __DIR__ . '/../public/index.php';

use PixelHub\Core\DB;

$phone = '5511965221349';
$normalizedPhone = preg_replace('/[^0-9]/', '', $phone);

echo "=== Buscando Mídia do Número: {$phone} ===\n\n";

$db = DB::getConnection();

// Busca eventos desse número
echo "1. Buscando eventos do número...\n";

// Tenta diferentes formatos do número
$phoneVariations = [
    $phone,
    $normalizedPhone,
    $phone . '@c.us',
    $phone . '@s.whatsapp.net',
    substr($normalizedPhone, 2), // Sem DDI
    '+55' . substr($normalizedPhone, 2), // Com + e DDI
];

$foundEvents = [];

foreach ($phoneVariations as $phoneVar) {
    // Busca eventos onde o número está no payload
    $stmt = $db->prepare("
        SELECT 
            ce.id,
            ce.event_id,
            ce.event_type,
            ce.created_at,
            ce.payload,
            ce.metadata,
            ce.tenant_id,
            cm.id as media_id,
            cm.media_type,
            cm.mime_type,
            cm.stored_path,
            cm.file_name,
            cm.file_size,
            cm.created_at as media_created_at
        FROM communication_events ce
        LEFT JOIN communication_media cm ON ce.event_id = cm.event_id
        WHERE ce.event_type = 'whatsapp.inbound.message'
        AND (
            JSON_EXTRACT(ce.payload, '$.from') = ?
            OR JSON_EXTRACT(ce.payload, '$.message.from') = ?
            OR JSON_EXTRACT(ce.payload, '$.from') LIKE ?
            OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE ?
            OR ce.payload LIKE ?
        )
        ORDER BY ce.created_at DESC
        LIMIT 20
    ");
    
    $searchPattern = '%' . $phoneVar . '%';
    $stmt->execute([$phoneVar, $phoneVar, $searchPattern, $searchPattern, $searchPattern]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($events)) {
        $foundEvents = array_merge($foundEvents, $events);
    }
}

// Remove duplicados por event_id
$uniqueEvents = [];
foreach ($foundEvents as $event) {
    $uniqueEvents[$event['event_id']] = $event;
}

$foundEvents = array_values($uniqueEvents);

if (empty($foundEvents)) {
    echo "   ❌ Nenhum evento encontrado para o número {$phone}\n\n";
    echo "   Tentativas de busca:\n";
    foreach ($phoneVariations as $var) {
        echo "     - {$var}\n";
    }
    exit(0);
}

echo "   ✅ Encontrados " . count($foundEvents) . " eventos\n\n";

// Analisa cada evento procurando mídia
$mediaEvents = [];

foreach ($foundEvents as $event) {
    $payload = json_decode($event['payload'], true);
    if (!$payload) continue;
    
    // Verifica se tem mídia
    $type = $payload['type'] 
        ?? $payload['message']['type'] 
        ?? $payload['message']['message']['type'] 
        ?? 'text';
    
    $mediaTypes = ['audio', 'ptt', 'voice', 'image', 'video', 'document', 'sticker'];
    
    if (in_array(strtolower($type), $mediaTypes)) {
        $mediaEvents[] = $event;
    }
}

echo "2. Eventos com mídia encontrados: " . count($mediaEvents) . "\n\n";

if (empty($mediaEvents)) {
    echo "   ⚠️  Nenhum evento com mídia encontrado\n";
    echo "   📋 Últimos eventos encontrados:\n\n";
    
    foreach (array_slice($foundEvents, 0, 5) as $event) {
        $payload = json_decode($event['payload'], true);
        $type = $payload['type'] ?? $payload['message']['type'] ?? 'text';
        $from = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
        
        echo "   - Event ID: {$event['event_id']}\n";
        echo "     Data: {$event['created_at']}\n";
        echo "     Tipo: {$type}\n";
        echo "     From: {$from}\n";
        echo "     Mídia processada: " . ($event['media_id'] ? '✅ Sim' : '❌ Não') . "\n\n";
    }
} else {
    foreach ($mediaEvents as $i => $event) {
        $payload = json_decode($event['payload'], true);
        $type = $payload['type'] ?? $payload['message']['type'] ?? 'text';
        $from = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
        
        echo "   📎 Evento " . ($i + 1) . ":\n";
        echo "      - Event ID: {$event['event_id']}\n";
        echo "      - Data: {$event['created_at']}\n";
        echo "      - Tipo de mídia: {$type}\n";
        echo "      - From: {$from}\n";
        echo "      - Tenant ID: " . ($event['tenant_id'] ?? 'N/A') . "\n";
        
        // Verifica processamento
        if ($event['media_id']) {
            echo "      - ✅ Mídia processada:\n";
            echo "        * ID: {$event['media_id']}\n";
            echo "        * Tipo: {$event['media_type']}\n";
            echo "        * MIME: {$event['mime_type']}\n";
            echo "        * Arquivo: {$event['stored_path']}\n";
            echo "        * Nome: {$event['file_name']}\n";
            echo "        * Tamanho: " . ($event['file_size'] ? number_format($event['file_size'] / 1024, 2) . ' KB' : 'N/A') . "\n";
            echo "        * Processado em: {$event['media_created_at']}\n";
        } else {
            echo "      - ❌ Mídia NÃO foi processada ainda\n";
            
            // Tenta processar agora
            echo "      - 🔄 Tentando processar agora...\n";
            try {
                $result = \PixelHub\Services\WhatsAppMediaService::processMediaFromEvent($event);
                if ($result) {
                    echo "        ✅ Mídia processada com sucesso!\n";
                    echo "        * Caminho: {$result['stored_path']}\n";
                    echo "        * URL: {$result['url']}\n";
                } else {
                    echo "        ❌ Falha ao processar mídia\n";
                }
            } catch (\Exception $e) {
                echo "        ❌ Erro: " . $e->getMessage() . "\n";
            }
        }
        
        // Mostra estrutura do payload para debug
        echo "\n      📋 Estrutura do Payload:\n";
        echo "        - Keys: " . implode(', ', array_keys($payload)) . "\n";
        
        // Tenta encontrar mediaId
        $possibleMediaIds = [
            'mediaId' => $payload['mediaId'] ?? null,
            'media_id' => $payload['media_id'] ?? null,
            'mediaUrl' => $payload['mediaUrl'] ?? null,
            'id' => $payload['id'] ?? null,
            'message.mediaId' => $payload['message']['mediaId'] ?? null,
            'message.mediaUrl' => $payload['message']['mediaUrl'] ?? null,
            'message.id' => $payload['message']['id'] ?? null,
            'message.key.id' => $payload['message']['key']['id'] ?? null,
        ];
        
        echo "        - MediaId encontrado em:\n";
        foreach ($possibleMediaIds as $path => $value) {
            if ($value) {
                echo "          ✅ {$path}: {$value}\n";
            }
        }
        
        echo "\n   " . str_repeat("━", 70) . "\n\n";
    }
}

// Busca na tabela de mídias diretamente via conversa
echo "3. Buscando via conversas...\n";

$stmt = $db->prepare("
    SELECT 
        c.id as conversation_id,
        c.contact_external_id,
        ce.event_id,
        ce.created_at,
        cm.*
    FROM conversations c
    INNER JOIN communication_events ce ON (
        JSON_EXTRACT(ce.payload, '$.from') = c.contact_external_id
        OR JSON_EXTRACT(ce.payload, '$.message.from') = c.contact_external_id
    )
    LEFT JOIN communication_media cm ON ce.event_id = cm.event_id
    WHERE c.contact_external_id LIKE ?
    AND ce.event_type = 'whatsapp.inbound.message'
    AND cm.id IS NOT NULL
    ORDER BY ce.created_at DESC
    LIMIT 5
");

$searchPattern = '%' . $normalizedPhone . '%';
$stmt->execute([$searchPattern]);
$conversationMedia = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($conversationMedia)) {
    echo "   ✅ Encontradas " . count($conversationMedia) . " mídias via conversas\n";
    foreach ($conversationMedia as $media) {
        echo "   - Conversa: {$media['conversation_id']}\n";
        echo "     Mídia: {$media['media_type']} ({$media['mime_type']})\n";
        echo "     Arquivo: {$media['stored_path']}\n";
        echo "     URL: " . pixelhub_url('/communication-hub/media?path=' . urlencode($media['stored_path'])) . "\n\n";
    }
}

echo "\n=== Fim da Busca ===\n";







