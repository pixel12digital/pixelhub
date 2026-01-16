<?php
/**
 * Script para verificar se há mídia no banco remoto recebida do número 5511965221349
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carrega autoload sem passar pelo index.php
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
use PDO;

// Carrega .env
Env::load();

$phone = '5511965221349';
$normalizedPhone = preg_replace('/[^0-9]/', '', $phone);

echo "========================================\n";
echo "VERIFICAÇÃO DE MÍDIA NO BANCO REMOTO\n";
echo "Número: {$phone}\n";
echo "========================================\n\n";

try {
    $db = DB::getConnection();
    $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
    echo "✅ Conexão estabelecida\n";
    echo "   Database: {$dbName}\n\n";
} catch (Exception $e) {
    echo "❌ Erro ao conectar: " . $e->getMessage() . "\n";
    exit(1);
}

// 1. Busca eventos do número
echo "1. Buscando eventos do número {$phone}...\n";

$sql = "SELECT 
    ce.id,
    ce.event_id,
    ce.event_type,
    ce.created_at,
    ce.tenant_id,
    ce.payload,
    JSON_EXTRACT(ce.payload, '$.from') as from_num,
    JSON_EXTRACT(ce.payload, '$.message.from') as from_num2,
    JSON_EXTRACT(ce.payload, '$.type') as msg_type,
    JSON_EXTRACT(ce.payload, '$.message.type') as msg_type2,
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
AND ce.payload LIKE ?
ORDER BY ce.created_at DESC
LIMIT 50";

$stmt = $db->prepare($sql);
$stmt->execute(["%{$normalizedPhone}%"]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ Nenhum evento encontrado\n\n";
} else {
    echo "   ✅ Encontrados " . count($events) . " eventos\n\n";
}

// 2. Analisa eventos com mídia
echo "2. Analisando eventos com mídia...\n";

$mediaEvents = [];
$processedMedia = [];
$unprocessedMedia = [];

foreach ($events as $event) {
    $payload = json_decode($event['payload'] ?? '{}', true);
    if (!$payload) continue;
    
    $type = $payload['type'] 
        ?? $payload['message']['type'] 
        ?? $payload['message']['message']['type'] 
        ?? 'text';
    
    $mediaTypes = ['audio', 'ptt', 'voice', 'image', 'video', 'document', 'sticker'];
    
    if (in_array(strtolower($type), $mediaTypes)) {
        $mediaEvents[] = $event;
        
        if ($event['media_id'] && !empty($event['stored_path'])) {
            $processedMedia[] = $event;
        } else {
            $unprocessedMedia[] = $event;
        }
    }
}

echo "   📊 Estatísticas:\n";
echo "      - Total de eventos com mídia: " . count($mediaEvents) . "\n";
echo "      - Mídias processadas: " . count($processedMedia) . "\n";
echo "      - Mídias não processadas: " . count($unprocessedMedia) . "\n\n";

// 3. Mostra mídias processadas
if (!empty($processedMedia)) {
    echo "3. ✅ MÍDIAS PROCESSADAS ENCONTRADAS:\n\n";
    
    foreach ($processedMedia as $i => $event) {
        $from = trim($event['from_num'], '"') ?: trim($event['from_num2'], '"') ?: 'N/A';
        $type = trim($event['msg_type'], '"') ?: trim($event['msg_type2'], '"') ?: 'unknown';
        
        echo "   📎 Mídia " . ($i + 1) . ":\n";
        echo "      - Event ID: {$event['event_id']}\n";
        echo "      - Data: {$event['created_at']}\n";
        echo "      - Tipo: {$type}\n";
        echo "      - From: {$from}\n";
        echo "      - Tenant ID: " . ($event['tenant_id'] ?? 'N/A') . "\n";
        echo "      - Media ID (DB): {$event['media_id']}\n";
        echo "      - Tipo de mídia: {$event['media_type']}\n";
        echo "      - MIME: {$event['mime_type']}\n";
        echo "      - Arquivo: {$event['stored_path']}\n";
        echo "      - Nome: {$event['file_name']}\n";
        echo "      - Tamanho: " . ($event['file_size'] ? number_format($event['file_size'] / 1024, 2) . ' KB' : 'N/A') . "\n";
        echo "      - Processado em: {$event['media_created_at']}\n";
        
        // Verifica se o arquivo existe fisicamente
        if ($event['stored_path']) {
            $fullPath = __DIR__ . '/../storage/' . $event['stored_path'];
            if (file_exists($fullPath)) {
                echo "      - ✅ Arquivo existe fisicamente\n";
            } else {
                echo "      - ⚠️  Arquivo NÃO encontrado em: {$fullPath}\n";
            }
        }
        
        echo "\n";
    }
} else {
    echo "3. ❌ Nenhuma mídia processada encontrada\n\n";
}

// 4. Mostra mídias não processadas
if (!empty($unprocessedMedia)) {
    echo "4. ⚠️  Mídias não processadas:\n\n";
    
    foreach (array_slice($unprocessedMedia, 0, 5) as $i => $event) {
        $payload = json_decode($event['payload'] ?? '{}', true);
        $type = $payload['type'] ?? $payload['message']['type'] ?? 'unknown';
        $from = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
        
        echo "   - Mídia " . ($i + 1) . ":\n";
        echo "     Event ID: {$event['event_id']}\n";
        echo "     Data: {$event['created_at']}\n";
        echo "     Tipo: {$type}\n";
        echo "     From: {$from}\n\n";
    }
    
    if (count($unprocessedMedia) > 5) {
        echo "   ... e mais " . (count($unprocessedMedia) - 5) . " mídias não processadas\n\n";
    }
}

// 5. Busca direta na tabela communication_media
echo "5. Busca direta na tabela communication_media...\n";

$sql2 = "SELECT 
    cm.*,
    ce.created_at as event_created_at,
    ce.tenant_id,
    JSON_EXTRACT(ce.payload, '$.from') as from_num
FROM communication_media cm
INNER JOIN communication_events ce ON cm.event_id = ce.event_id
WHERE ce.event_type = 'whatsapp.inbound.message'
AND (
    JSON_EXTRACT(ce.payload, '$.from') LIKE ?
    OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE ?
    OR ce.payload LIKE ?
)
ORDER BY cm.created_at DESC
LIMIT 20";

$stmt2 = $db->prepare($sql2);
$searchPattern = '%' . $normalizedPhone . '%';
$stmt2->execute([$searchPattern, $searchPattern, $searchPattern]);
$directMedia = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (!empty($directMedia)) {
    echo "   ✅ Encontradas " . count($directMedia) . " mídias diretamente na tabela\n\n";
    
    foreach (array_slice($directMedia, 0, 5) as $media) {
        $from = trim($media['from_num'], '"') ?: 'N/A';
        echo "   - Media ID: {$media['id']}\n";
        echo "     Event ID: {$media['event_id']}\n";
        echo "     Tipo: {$media['media_type']}\n";
        echo "     From: {$from}\n";
        echo "     Arquivo: {$media['stored_path']}\n";
        echo "     Data: {$media['event_created_at']}\n\n";
    }
} else {
    echo "   ❌ Nenhuma mídia encontrada diretamente na tabela\n\n";
}

echo "========================================\n";
echo "RESUMO FINAL\n";
echo "========================================\n";
echo "Total de eventos encontrados: " . count($events) . "\n";
echo "Eventos com mídia: " . count($mediaEvents) . "\n";
echo "Mídias processadas: " . count($processedMedia) . "\n";
echo "Mídias não processadas: " . count($unprocessedMedia) . "\n";
echo "Mídias na tabela communication_media: " . count($directMedia) . "\n";
echo "========================================\n";

