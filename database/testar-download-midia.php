<?php
/**
 * Testar download de mídia específica
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
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;

Env::load();

$db = DB::getConnection();

echo "=== TESTE DE DOWNLOAD DE MÍDIA ===\n\n";

// Buscar uma mídia recente que não tem arquivo
$stmt = $db->query("
    SELECT 
        ce.id,
        ce.event_id,
        ce.tenant_id,
        cm.id AS media_id,
        cm.media_id AS cm_media_id,
        cm.stored_path,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS channel_id,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.mediaUrl')) AS payload_media_url,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.mediaUrl')) AS payload_mediaUrl,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.media_id')) AS metadata_media_id,
        ce.created_at
    FROM communication_events ce
    INNER JOIN communication_media cm ON cm.event_id = ce.event_id
    WHERE ce.source_system = 'wpp_gateway'
        AND cm.stored_path IS NOT NULL
        AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    ORDER BY ce.created_at DESC
    LIMIT 1
");
$media = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$media) {
    echo "❌ Nenhuma mídia recente encontrada para testar.\n";
    exit(1);
}

echo "Mídia encontrada:\n";
echo "  Event ID: {$media['event_id']}\n";
echo "  Media ID (cm.media_id): {$media['cm_media_id']}\n";
echo "  Channel ID: {$media['channel_id']}\n";
echo "  Stored Path: {$media['stored_path']}\n";
echo "  Payload mediaUrl: {$media['payload_media_url']}\n";
echo "  Payload mediaUrl (alt): {$media['payload_mediaUrl']}\n";
echo "  Metadata media_id: {$media['metadata_media_id']}\n";
echo "\n";

// Verificar se arquivo existe
$absolutePath = __DIR__ . '/../storage/' . ltrim($media['stored_path'], '/');
echo "Verificando arquivo:\n";
echo "  Caminho absoluto: {$absolutePath}\n";
echo "  Arquivo existe: " . (file_exists($absolutePath) ? '✅ SIM' : '❌ NÃO') . "\n";
echo "\n";

// Tentar identificar mediaId correto
$mediaId = $media['cm_media_id'] 
    ?: $media['payload_media_url'] 
    ?: $media['payload_mediaUrl'] 
    ?: $media['metadata_media_id'];

if (!$mediaId) {
    echo "❌ Não foi possível identificar mediaId para download.\n";
    exit(1);
}

$channelId = $media['channel_id'];
if (!$channelId) {
    echo "❌ Channel ID não encontrado.\n";
    exit(1);
}

echo "Tentando download:\n";
echo "  Channel ID: {$channelId}\n";
echo "  Media ID: {$mediaId}\n";
echo "\n";

try {
    $client = new WhatsAppGatewayClient();
    $result = $client->downloadMedia($channelId, $mediaId);
    
    if ($result['success']) {
        $dataSize = strlen($result['data']);
        echo "✅ Download bem-sucedido!\n";
        echo "  Tamanho dos dados: {$dataSize} bytes\n";
        echo "  MIME Type: {$result['mime_type']}\n";
        
        // Tentar salvar manualmente
        $testDir = __DIR__ . '/../storage/test-download';
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }
        
        $testFile = $testDir . '/test-' . time() . '.ogg';
        if (file_put_contents($testFile, $result['data']) !== false) {
            $savedSize = filesize($testFile);
            echo "✅ Arquivo salvo com sucesso!\n";
            echo "  Caminho: {$testFile}\n";
            echo "  Tamanho salvo: {$savedSize} bytes\n";
            
            if (file_exists($testFile)) {
                echo "  Arquivo existe após salvar: ✅ SIM\n";
            } else {
                echo "  Arquivo existe após salvar: ❌ NÃO (PROBLEMA!)\n";
            }
        } else {
            echo "❌ Falha ao salvar arquivo de teste.\n";
        }
    } else {
        echo "❌ Download falhou:\n";
        echo "  Erro: {$result['error']}\n";
    }
} catch (\Exception $e) {
    echo "❌ Exception durante download:\n";
    echo "  Mensagem: {$e->getMessage()}\n";
    echo "  Arquivo: {$e->getFile()}:{$e->getLine()}\n";
}

echo "\n=== FIM DO TESTE ===\n";






