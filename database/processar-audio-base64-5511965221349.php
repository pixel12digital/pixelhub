<?php
/**
 * Script para processar e extrair áudio do evento existente do número 5511965221349
 * O áudio está codificado em base64 no campo "text" do payload
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

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
use PixelHub\Core\Storage;
use PixelHub\Services\WhatsAppMediaService;

Env::load();

$phone = '5511965221349';
$normalizedPhone = preg_replace('/[^0-9]/', '', $phone);

echo "========================================\n";
echo "PROCESSAMENTO DE ÁUDIO BASE64\n";
echo "Número: {$phone}\n";
echo "========================================\n\n";

$db = DB::getConnection();

// Busca o evento
$sql = "SELECT 
    ce.*
FROM communication_events ce
WHERE ce.event_type = 'whatsapp.inbound.message'
AND ce.payload LIKE ?
ORDER BY ce.created_at DESC
LIMIT 1";

$stmt = $db->prepare($sql);
$stmt->execute(["%{$normalizedPhone}%"]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "❌ Nenhum evento encontrado\n";
    exit(1);
}

echo "✅ Evento encontrado:\n";
echo "   - Event ID: {$event['event_id']}\n";
echo "   - Data: {$event['created_at']}\n\n";

// Verifica se já tem mídia processada
$mediaCheck = $db->prepare("SELECT * FROM communication_media WHERE event_id = ?");
$mediaCheck->execute([$event['event_id']]);
$existingMedia = $mediaCheck->fetch(PDO::FETCH_ASSOC);

if ($existingMedia && !empty($existingMedia['stored_path'])) {
    echo "⚠️  Mídia já processada:\n";
    echo "   - Media ID: {$existingMedia['id']}\n";
    echo "   - Caminho: {$existingMedia['stored_path']}\n";
    echo "   - Arquivo existe: " . (file_exists(__DIR__ . '/../storage/' . $existingMedia['stored_path']) ? 'Sim' : 'Não') . "\n";
    exit(0);
}

// Decodifica payload
$payload = json_decode($event['payload'], true);
if (!$payload) {
    echo "❌ Erro ao decodificar payload\n";
    exit(1);
}

// Verifica se tem texto com base64
$text = $payload['text'] ?? $payload['message']['text'] ?? null;

if (!$text) {
    echo "❌ Campo 'text' não encontrado no payload\n";
    exit(1);
}

// Verifica se é base64 (começa com T2dnUwAC que é "OggS" - header OGG)
$isBase64Audio = false;
$audioData = null;

// Tenta decodificar base64
if (strlen($text) > 100 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $text)) {
    // Remove espaços e quebras de linha
    $textCleaned = preg_replace('/\s+/', '', $text);
    
    // Tenta decodificar
    $decoded = base64_decode($textCleaned, true);
    
    if ($decoded !== false) {
        // Verifica se é OGG (formato de áudio do WhatsApp)
        if (substr($decoded, 0, 4) === 'OggS') {
            $isBase64Audio = true;
            $audioData = $decoded;
            echo "✅ Áudio OGG detectado em base64\n";
            echo "   - Tamanho decodificado: " . strlen($audioData) . " bytes\n";
        } else {
            echo "⚠️  Dados base64 decodificados, mas não é OGG\n";
            echo "   - Primeiros bytes: " . bin2hex(substr($decoded, 0, 8)) . "\n";
        }
    } else {
        echo "⚠️  Não é base64 válido\n";
    }
}

if (!$isBase64Audio || !$audioData) {
    echo "❌ Não foi possível extrair áudio do campo text\n";
    echo "   - Tamanho do text: " . strlen($text) . " caracteres\n";
    echo "   - Primeiros 100 caracteres: " . substr($text, 0, 100) . "...\n";
    exit(1);
}

// Salva o arquivo
$tenantId = $event['tenant_id'] ?? null;
$subDir = date('Y/m/d', strtotime($event['created_at']));
$mediaDir = __DIR__ . '/../storage/whatsapp-media';
if ($tenantId) {
    $mediaDir .= '/tenant-' . $tenantId;
}
$mediaDir .= '/' . $subDir;

Storage::ensureDirExists($mediaDir);

// Gera nome de arquivo único
$fileName = bin2hex(random_bytes(16)) . '.ogg';
$fullPath = $mediaDir . DIRECTORY_SEPARATOR . $fileName;
$storedPath = 'whatsapp-media/' . ($tenantId ? "tenant-{$tenantId}/" : '') . $subDir . '/' . $fileName;

// Salva arquivo
if (file_put_contents($fullPath, $audioData) === false) {
    echo "❌ Erro ao salvar arquivo: {$fullPath}\n";
    exit(1);
}

$fileSize = filesize($fullPath);
echo "✅ Arquivo salvo:\n";
echo "   - Caminho completo: {$fullPath}\n";
echo "   - Caminho relativo: {$storedPath}\n";
echo "   - Tamanho: " . number_format($fileSize / 1024, 2) . " KB\n\n";

// Salva registro no banco
try {
    // Verifica se já existe
    $checkStmt = $db->prepare("SELECT * FROM communication_media WHERE event_id = ?");
    $checkStmt->execute([$event['event_id']]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Atualiza
        $updateStmt = $db->prepare("
            UPDATE communication_media 
            SET media_id = ?, media_type = ?, mime_type = ?, 
                stored_path = ?, file_name = ?, file_size = ?,
                updated_at = NOW()
            WHERE event_id = ?
        ");
        $updateStmt->execute([
            $event['event_id'], // Usa event_id como media_id (fallback)
            'audio',
            'audio/ogg',
            $storedPath,
            $fileName,
            $fileSize,
            $event['event_id']
        ]);
        echo "✅ Registro atualizado no banco\n";
        echo "   - Media ID: {$existing['id']}\n";
    } else {
        // Insere novo
        $insertStmt = $db->prepare("
            INSERT INTO communication_media 
            (event_id, media_id, media_type, mime_type, stored_path, file_name, file_size, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $insertStmt->execute([
            $event['event_id'],
            $event['event_id'], // Usa event_id como media_id (fallback)
            'audio',
            'audio/ogg',
            $storedPath,
            $fileName,
            $fileSize
        ]);
        $mediaId = $db->lastInsertId();
        echo "✅ Registro criado no banco\n";
        echo "   - Media ID: {$mediaId}\n";
    }
    
    echo "\n✅ Processamento concluído com sucesso!\n";
    echo "   O áudio agora deve aparecer na thread.\n";
    
} catch (\Exception $e) {
    echo "❌ Erro ao salvar no banco: " . $e->getMessage() . "\n";
    exit(1);
}

