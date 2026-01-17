<?php
/**
 * Script para forçar salvamento do áudio recebido às 16:38
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
use PixelHub\Core\Storage;

Env::load();

$db = DB::getConnection();

echo "=== FORÇAR SALVAMENTO ÁUDIO 16:38 ===\n\n";

$eventId = 'e352a48f-140d-47cf-9223-88f158e17c3d';

// Busca evento
$stmt = $db->prepare("SELECT * FROM communication_events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "❌ Evento não encontrado\n";
    exit(1);
}

$payload = json_decode($event['payload'], true);
$text = $payload['text'] ?? $payload['message']['text'] ?? null;

if (!$text || strlen($text) < 100) {
    echo "❌ Campo text não contém áudio\n";
    exit(1);
}

// Decodifica base64
$audioData = base64_decode($text, true);
if (!$audioData || substr($audioData, 0, 4) !== 'OggS') {
    echo "❌ Dados não são um áudio OGG válido\n";
    exit(1);
}

echo "✅ Áudio decodificado: " . strlen($audioData) . " bytes\n\n";

// Define caminho
$tenantId = $event['tenant_id'] ?? 2;
$subDir = '2026/01/17';
$mediaDir = __DIR__ . '/../storage/whatsapp-media/tenant-' . $tenantId . '/' . $subDir;
$fileName = 'fcf97711c504639b0b4d7dc052245a1c.ogg'; // Mesmo nome do banco
$fullPath = $mediaDir . DIRECTORY_SEPARATOR . $fileName;

echo "1. Criando diretório...\n";
if (!is_dir($mediaDir)) {
    if (!mkdir($mediaDir, 0755, true)) {
        echo "   ❌ Falha ao criar diretório: {$mediaDir}\n";
        exit(1);
    }
    echo "   ✅ Diretório criado: {$mediaDir}\n";
} else {
    echo "   ✅ Diretório já existe\n";
}

echo "\n2. Salvando arquivo...\n";
if (file_put_contents($fullPath, $audioData) === false) {
    echo "   ❌ Falha ao salvar arquivo\n";
    $lastError = error_get_last();
    echo "   Erro: " . ($lastError['message'] ?? 'desconhecido') . "\n";
    exit(1);
}

echo "   ✅ Arquivo salvo: {$fullPath}\n";
echo "   - Tamanho: " . filesize($fullPath) . " bytes\n";

// Atualiza banco para garantir que stored_path está correto
echo "\n3. Verificando registro no banco...\n";
$storedPath = 'whatsapp-media/tenant-' . $tenantId . '/' . $subDir . '/' . $fileName;

$stmt = $db->prepare("SELECT * FROM communication_media WHERE event_id = ?");
$stmt->execute([$eventId]);
$media = $stmt->fetch(PDO::FETCH_ASSOC);

if ($media) {
    if ($media['stored_path'] !== $storedPath) {
        echo "   ⚠️  Atualizando stored_path no banco...\n";
        $stmt = $db->prepare("UPDATE communication_media SET stored_path = ?, file_size = ? WHERE event_id = ?");
        $stmt->execute([$storedPath, filesize($fullPath), $eventId]);
        echo "   ✅ stored_path atualizado\n";
    } else {
        echo "   ✅ stored_path já está correto\n";
    }
} else {
    echo "   ⚠️  Registro não encontrado, criando...\n";
    $stmt = $db->prepare("
        INSERT INTO communication_media 
        (event_id, media_id, media_type, mime_type, stored_path, file_name, file_size, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $eventId,
        $eventId, // media_id usa event_id como fallback
        'audio',
        'audio/ogg',
        $storedPath,
        $fileName,
        filesize($fullPath)
    ]);
    echo "   ✅ Registro criado\n";
}

echo "\n=== ÁUDIO SALVO COM SUCESSO ===\n";
echo "URL: http://localhost/painel.pixel12digital/communication-hub/media?path=" . urlencode($storedPath) . "\n";

