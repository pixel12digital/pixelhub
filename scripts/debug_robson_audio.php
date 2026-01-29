<?php
/**
 * Debug: Verificar áudios da conversa do Robson
 */

// Bootstrap
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== DEBUG ÁUDIOS ROBSON (CONVERSA #8) ===\n\n";

// 1. Buscar conversa whatsapp_8
$stmt = $db->query("
    SELECT id, contact_name, contact_external_id, conversation_key, tenant_id
    FROM conversations 
    WHERE id = 8 OR conversation_key LIKE '%whatsapp_8'
    LIMIT 5
");
$convs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "1. Conversas encontradas:\n";
foreach ($convs as $c) {
    echo "   ID: {$c['id']} | Nome: {$c['contact_name']} | Key: {$c['conversation_key']}\n";
}

if (empty($convs)) {
    echo "   NENHUMA CONVERSA ENCONTRADA!\n";
    exit;
}

$convId = $convs[0]['id'];
echo "\n2. Buscando eventos com mídia da conversa #{$convId}...\n\n";

// 2. Buscar eventos com mídia
$stmt2 = $db->prepare("
    SELECT 
        id, 
        event_type, 
        payload,
        created_at
    FROM communication_events 
    WHERE conversation_id = ?
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt2->execute([$convId]);
$events = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "   Total eventos: " . count($events) . "\n\n";

// Verificar tabela communication_media para esta conversa
echo "3. Verificando mídias na tabela communication_media...\n\n";

$eventIds = array_column($events, 'id');
if (!empty($eventIds)) {
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    $mediaStmt = $db->prepare("
        SELECT * FROM communication_media 
        WHERE event_id IN ({$placeholders})
        ORDER BY created_at DESC
    ");
    $mediaStmt->execute($eventIds);
    $mediaRecords = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Registros de mídia encontrados: " . count($mediaRecords) . "\n\n";
    
    foreach ($mediaRecords as $m) {
        echo "   [MEDIA] event_id: {$m['event_id']}\n";
        echo "      Type: {$m['media_type']} | MIME: {$m['mime_type']}\n";
        echo "      Path: " . ($m['stored_path'] ?: 'NULL') . "\n";
        echo "      File: " . ($m['file_name'] ?: 'NULL') . " | Size: " . ($m['file_size'] ?: 'NULL') . "\n";
        
        // Verificar se arquivo existe
        if (!empty($m['stored_path'])) {
            $fullPath = __DIR__ . '/../storage/' . $m['stored_path'];
            $exists = file_exists($fullPath);
            echo "      Arquivo existe: " . ($exists ? 'SIM' : 'NÃO') . " ({$fullPath})\n";
        }
        echo "\n";
    }
} else {
    echo "   Nenhum evento para verificar.\n\n";
}

// Mostrar eventos com possível mídia
echo "4. Eventos com conteúdo base64 (possível mídia)...\n\n";

foreach ($events as $e) {
    $payload = json_decode($e['payload'], true);
    $isInbound = strpos($e['event_type'], 'inbound') !== false;
    
    // Verifica se tem base64 no text
    $text = $payload['text'] ?? $payload['message']['text'] ?? '';
    $hasBase64 = strlen($text) > 100 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $text);
    
    if ($hasBase64) {
        $textCleaned = preg_replace('/\s+/', '', $text);
        $decoded = base64_decode($textCleaned, true);
        $isOgg = $decoded !== false && substr($decoded, 0, 4) === 'OggS';
        $isJpeg = substr($textCleaned, 0, 4) === '/9j/';
        
        echo "   [{$e['created_at']}] ID: {$e['id']} | " . ($isInbound ? 'RECEBIDO' : 'ENVIADO') . "\n";
        echo "      Base64 detectado: " . strlen($text) . " chars\n";
        echo "      É OGG (áudio): " . ($isOgg ? 'SIM' : 'NÃO') . "\n";
        echo "      É JPEG (imagem): " . ($isJpeg ? 'SIM' : 'NÃO') . "\n";
        echo "\n";
    }
}
echo "\n=== FIM ===\n";
