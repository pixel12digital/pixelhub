<?php
/**
 * Script para reprocessar áudio recebido às 16:38 e corrigir arquivo faltante
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

echo "=== REPROCESSAR ÁUDIO 16:38 ===\n\n";

$eventId = 'e352a48f-140d-47cf-9223-88f158e17c3d';

// Busca evento completo
echo "1. Buscando evento...\n";
$stmt = $db->prepare("
    SELECT * FROM communication_events 
    WHERE event_id = ?
    LIMIT 1
");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "   ❌ Evento não encontrado: {$eventId}\n";
    exit(1);
}

echo "   ✅ Evento encontrado\n";
echo "      - Data: {$event['created_at']}\n";
echo "      - Tipo: {$event['event_type']}\n";
echo "      - Tenant ID: {$event['tenant_id']}\n\n";

// Busca mídia existente
echo "2. Verificando mídia existente...\n";
$stmt = $db->prepare("
    SELECT * FROM communication_media 
    WHERE event_id = ?
    LIMIT 1
");
$stmt->execute([$eventId]);
$media = $stmt->fetch(PDO::FETCH_ASSOC);

if ($media) {
    echo "   ✅ Mídia encontrada no banco:\n";
    echo "      - ID: {$media['id']}\n";
    echo "      - Tipo: {$media['media_type']}\n";
    echo "      - Path: {$media['stored_path']}\n";
    echo "      - File: {$media['file_name']}\n\n";
    
    // Verifica se arquivo existe
    $absolutePath = __DIR__ . '/../storage/' . ltrim($media['stored_path'], '/');
    if (file_exists($absolutePath)) {
        echo "      ✅ Arquivo EXISTE em: {$absolutePath}\n";
        echo "      - Tamanho: " . filesize($absolutePath) . " bytes\n\n";
    } else {
        echo "      ❌ Arquivo NÃO EXISTE em: {$absolutePath}\n\n";
    }
} else {
    echo "   ❌ Mídia NÃO encontrada no banco\n\n";
}

// Mostra payload do evento
echo "3. Analisando payload...\n";
$payload = json_decode($event['payload'], true);
if (!$payload) {
    echo "   ❌ Payload inválido\n";
    exit(1);
}

echo "   ✅ Payload válido\n";
$type = $payload['type'] ?? $payload['message']['type'] ?? 'unknown';
echo "   - Tipo de mensagem: {$type}\n";

// Verifica se tem dados de áudio em base64 no campo text
$text = $payload['text'] ?? $payload['message']['text'] ?? $payload['body'] ?? null;
$hasBase64Audio = false;

if ($text && strlen($text) > 100) {
    // Tenta decodificar base64
    $decoded = @base64_decode($text, true);
    if ($decoded && substr($decoded, 0, 4) === 'OggS') {
        $hasBase64Audio = true;
        echo "   ✅ Áudio em base64 detectado no campo text\n";
        echo "   - Tamanho decodificado: " . strlen($decoded) . " bytes\n";
        
        // Tenta reprocessar
        echo "\n4. Tentando reprocessar áudio...\n";
        try {
            $eventArray = [
                'event_id' => $event['event_id'],
                'tenant_id' => $event['tenant_id'],
                'created_at' => $event['created_at'],
                'payload' => $event['payload']
            ];
            
            $result = \PixelHub\Services\WhatsAppMediaService::processMediaFromEvent($eventArray);
            
            if ($result) {
                echo "   ✅ Áudio reprocessado com sucesso!\n";
                echo "      - Path: {$result['stored_path']}\n";
                echo "      - URL: {$result['url']}\n";
                
                // Verifica se arquivo foi criado
                $newAbsolutePath = __DIR__ . '/../storage/' . ltrim($result['stored_path'], '/');
                if (file_exists($newAbsolutePath)) {
                    echo "      ✅ Arquivo criado: {$newAbsolutePath}\n";
                    echo "      - Tamanho: " . filesize($newAbsolutePath) . " bytes\n";
                } else {
                    echo "      ⚠️  Arquivo NÃO foi criado em: {$newAbsolutePath}\n";
                }
            } else {
                echo "   ❌ Falha ao reprocessar áudio\n";
            }
        } catch (\Exception $e) {
            echo "   ❌ Erro ao reprocessar: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   ❌ Não há áudio em base64 no campo text\n";
    }
} else {
    echo "   ❌ Campo text não contém dados de áudio (tamanho: " . ($text ? strlen($text) : 0) . ")\n";
}

// Verifica se há mediaId para baixar
$mediaId = $payload['mediaId'] 
    ?? $payload['media_id'] 
    ?? $payload['message']['mediaId'] 
    ?? $payload['id']
    ?? null;

if ($mediaId) {
    echo "\n5. MediaId encontrado: {$mediaId}\n";
    echo "   ⚠️  Áudio pode precisar ser baixado via gateway\n";
}

echo "\n=== Fim do Reprocessamento ===\n";

