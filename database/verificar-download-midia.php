<?php
/**
 * Verificar processo de download/salvamento de mídia
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

$db = DB::getConnection();

echo "=== VERIFICAÇÃO DE DOWNLOAD/SALVAMENTO DE MÍDIA ===\n\n";

// 1. Verificar eventos com mídia que não têm arquivo salvo
echo "1. Eventos com mídia sem arquivo salvo:\n";
$stmt = $db->query("
    SELECT 
        ce.id AS event_id,
        ce.event_id AS event_uuid,
        ce.event_type,
        ce.created_at,
        cm.id AS media_id,
        cm.stored_path,
        cm.file_name,
        cm.mime_type,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.mediaUrl')) AS payload_media_url,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.mediaUrl')) AS payload_mediaUrl,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.media_id')) AS metadata_media_id
    FROM communication_events ce
    INNER JOIN communication_media cm ON cm.event_id = ce.event_id
    WHERE ce.source_system = 'wpp_gateway'
        AND cm.stored_path IS NOT NULL
        AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$medias = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($medias)) {
    echo "❌ Nenhum evento com mídia encontrado.\n\n";
} else {
    $missingCount = 0;
    foreach ($medias as $media) {
        $storedPath = $media['stored_path'];
        $absolutePath = __DIR__ . '/../storage/' . ltrim($storedPath, '/');
        $exists = file_exists($absolutePath);
        
        if (!$exists) {
            $missingCount++;
            echo "  ❌ Event ID: {$media['event_uuid']} | Media ID: {$media['media_id']}\n";
            echo "    Path: {$storedPath}\n";
            echo "    Absoluto: {$absolutePath}\n";
            echo "    Media URL no payload: {$media['payload_media_url']} ou {$media['payload_mediaUrl']}\n";
            echo "    Media ID no metadata: {$media['metadata_media_id']}\n";
            echo "    Criado: {$media['created_at']}\n\n";
        }
    }
    
    if ($missingCount === 0) {
        echo "✅ Todos os arquivos de mídia existem.\n\n";
    } else {
        echo "⚠️  {$missingCount} de " . count($medias) . " arquivos de mídia não existem no servidor.\n\n";
    }
}

// 2. Verificar eventos com mídia que não têm registro em communication_media
echo "2. Eventos com mídia no payload mas sem registro em communication_media:\n";
$stmt = $db->query("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.mediaUrl')) AS media_url,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')) AS message_type,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.has_media')) AS has_media
    FROM communication_events ce
    WHERE ce.source_system = 'wpp_gateway'
        AND (
            JSON_EXTRACT(ce.payload, '$.message.mediaUrl') IS NOT NULL
            OR JSON_EXTRACT(ce.payload, '$.mediaUrl') IS NOT NULL
            OR JSON_EXTRACT(ce.metadata, '$.has_media') = '1'
        )
        AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
        AND NOT EXISTS (
            SELECT 1 FROM communication_media cm 
            WHERE cm.event_id = ce.event_id
        )
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$unprocessed = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($unprocessed)) {
    echo "✅ Todos os eventos com mídia têm registro em communication_media.\n\n";
} else {
    echo "⚠️  " . count($unprocessed) . " eventos com mídia não têm registro em communication_media:\n";
    foreach ($unprocessed as $event) {
        echo "  • Event ID: {$event['event_id']} | Tipo: {$event['event_type']}\n";
        echo "    Media URL: {$event['media_url']}\n";
        echo "    Message Type: {$event['message_type']}\n";
        echo "    Criado: {$event['created_at']}\n\n";
    }
}

// 3. Verificar diretório storage/whatsapp-media
echo "3. Verificação do diretório storage:\n";
$storageDir = __DIR__ . '/../storage';
$whatsappMediaDir = $storageDir . '/whatsapp-media';

if (!is_dir($storageDir)) {
    echo "❌ Diretório storage/ não existe.\n\n";
} else {
    echo "✅ Diretório storage/ existe.\n";
    
    if (!is_dir($whatsappMediaDir)) {
        echo "❌ Diretório storage/whatsapp-media/ não existe. Tentando criar...\n";
        if (mkdir($whatsappMediaDir, 0755, true)) {
            echo "✅ Diretório criado com sucesso.\n\n";
        } else {
            echo "❌ Falha ao criar diretório.\n\n";
        }
    } else {
        echo "✅ Diretório storage/whatsapp-media/ existe.\n";
        
        // Testa escrita
        $testFile = $whatsappMediaDir . '/.test-write';
        if (file_put_contents($testFile, 'test') !== false) {
            echo "✅ Diretório tem permissão de escrita.\n";
            unlink($testFile);
        } else {
            echo "❌ Diretório NÃO tem permissão de escrita.\n";
        }
    }
}

echo "\n=== FIM DA VERIFICAÇÃO ===\n";







