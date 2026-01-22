<?php
/**
 * Script para verificar o áudio do Ponto Do Golfe
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== VERIFICANDO ÁUDIO: PONTO DO GOLFE ===\n\n";

// Busca o evento do áudio (20/01 11:08)
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.payload,
        ce.metadata
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= '2026-01-20 11:08:00'
    AND ce.created_at <= '2026-01-20 11:08:10'
    AND (
        JSON_EXTRACT(ce.metadata, '$.channel_id') = 'pixel12digital'
        OR JSON_EXTRACT(ce.payload, '$.session.id') = 'pixel12digital'
    )
    LIMIT 1
");
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "❌ Evento não encontrado!\n";
    exit(1);
}

echo "Evento encontrado:\n";
echo "  ID: {$event['event_id']}\n";
echo "  Created: {$event['created_at']}\n\n";

// Verifica se há mídia processada
$stmt = $db->prepare("
    SELECT * FROM communication_media
    WHERE event_id = ?
");
$stmt->execute([$event['event_id']]);
$media = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$media) {
    echo "❌ Mídia não encontrada no banco!\n";
    echo "   Isso significa que a mídia não foi processada ainda.\n\n";
    
    // Verifica o payload
    $payload = json_decode($event['payload'], true);
    $text = $payload['text'] ?? $payload['message']['text'] ?? null;
    
    if ($text && strlen($text) > 100) {
        echo "⚠️  Payload contém texto longo (possível base64):\n";
        echo "   Tamanho: " . strlen($text) . " caracteres\n";
        echo "   Primeiros 100 chars: " . substr($text, 0, 100) . "...\n";
        
        // Verifica se é base64
        $textCleaned = preg_replace('/\s+/', '', $text);
        $decoded = base64_decode($textCleaned, true);
        if ($decoded !== false) {
            echo "   ✅ É base64 válido!\n";
            echo "   Tamanho decodificado: " . strlen($decoded) . " bytes\n";
            
            // Verifica se é OGG
            if (substr($decoded, 0, 4) === 'OggS') {
                echo "   ✅ É áudio OGG!\n";
                echo "   ⚠️  Mas não foi processado e salvo ainda.\n";
            }
        }
    }
} else {
    echo "✅ Mídia encontrada no banco:\n";
    echo "  ID: {$media['id']}\n";
    echo "  Media Type: {$media['media_type']}\n";
    echo "  MIME Type: {$media['mime_type']}\n";
    echo "  Stored Path: {$media['stored_path']}\n";
    echo "  File Size: {$media['file_size']} bytes\n";
    
    // Verifica se arquivo existe
    $absolutePath = __DIR__ . '/../storage/' . $media['stored_path'];
    if (file_exists($absolutePath)) {
        echo "  ✅ Arquivo existe no disco!\n";
        echo "  Caminho absoluto: {$absolutePath}\n";
        echo "  Tamanho real: " . filesize($absolutePath) . " bytes\n";
    } else {
        echo "  ❌ Arquivo NÃO existe no disco!\n";
        echo "  Caminho esperado: {$absolutePath}\n";
    }
    
    // Gera URL
    $mediaUrl = '/communication-hub/media?path=' . urlencode($media['stored_path']);
    echo "  URL gerada: {$mediaUrl}\n";
}

echo "\n✅ Verificação concluída!\n";

