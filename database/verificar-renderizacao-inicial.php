<?php
/**
 * Script para verificar se a mídia está sendo incluída na renderização inicial
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simula o que a view recebe
$messages = [
    [
        'id' => 'fe23f980-c24b-4f8a-b378-99b4a1c2a2cc',
        'direction' => 'inbound',
        'content' => '',
        'timestamp' => '2026-01-16 05:35:23',
        'media' => [
            'id' => 1,
            'event_id' => 'fe23f980-c24b-4f8a-b378-99b4a1c2a2cc',
            'media_type' => 'audio',
            'mime_type' => 'audio/ogg',
            'stored_path' => 'whatsapp-media/2026/01/16/f6528d90b33fe0db1a41f275ab9c8346.ogg',
            'file_name' => 'f6528d90b33fe0db1a41f275ab9c8346.ogg',
            'file_size' => 65976,
            'url' => '/communication-hub/media?path=whatsapp-media%2F2026%2F01%2F16%2Ff6528d90b33fe0db1a41f275ab9c8346.ogg'
        ]
    ]
];

echo "========================================\n";
echo "TESTE DE RENDERIZAÇÃO DA VIEW\n";
echo "========================================\n\n";

foreach ($messages as $msg) {
    echo "Mensagem ID: {$msg['id']}\n";
    echo "  - Content vazio: " . (empty($msg['content']) ? 'Sim ✅' : 'Não ❌') . "\n";
    echo "  - Media presente: " . (!empty($msg['media']) ? 'Sim ✅' : 'Não ❌') . "\n";
    
    if (!empty($msg['media'])) {
        echo "  - Media URL presente: " . (!empty($msg['media']['url']) ? 'Sim ✅' : 'Não ❌') . "\n";
        echo "  - Media URL: " . ($msg['media']['url'] ?? 'N/A') . "\n";
        echo "  - Media Type: " . ($msg['media']['media_type'] ?? 'N/A') . "\n";
        echo "  - MIME Type: " . ($msg['media']['mime_type'] ?? 'N/A') . "\n";
        
        // Simula a condição da view
        $hasMedia = !empty($msg['media']) && !empty($msg['media']['url']);
        echo "  - Condição view (!empty(media) && !empty(media.url)): " . ($hasMedia ? 'TRUE ✅' : 'FALSE ❌') . "\n";
        
        if ($hasMedia) {
            $media = $msg['media'];
            $mediaType = strtolower($media['media_type'] ?? 'unknown');
            $mimeType = strtolower($media['mime_type'] ?? '');
            
            echo "  - Media Type (lowercase): {$mediaType}\n";
            echo "  - MIME Type (lowercase): {$mimeType}\n";
            
            $isAudio = (strpos($mimeType, 'audio/') === 0 || in_array($mediaType, ['audio', 'voice']));
            echo "  - É áudio? " . ($isAudio ? 'SIM ✅' : 'NÃO ❌') . "\n";
            
            if ($isAudio) {
                echo "  - ✅ DEVE RENDERIZAR PLAYER DE ÁUDIO\n";
            }
        }
    }
    
    echo "\n";
}

echo "========================================\n";

