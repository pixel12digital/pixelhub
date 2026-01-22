<?php
/**
 * Script para testar salvamento de áudio
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/Storage.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Core\Storage;

Env::load();

$db = DB::getConnection();

echo "=== TESTANDO SALVAMENTO DE ÁUDIO ===\n\n";

$eventId = '02025624-a245-4b9d-9fa9-384b2841fc6c';

// Busca o evento
$stmt = $db->prepare("SELECT * FROM communication_events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "❌ Evento não encontrado!\n";
    exit(1);
}

$payload = json_decode($event['payload'], true);
$text = $payload['text'] ?? $payload['message']['text'] ?? null;

if (!$text || strlen($text) < 100) {
    echo "❌ Texto não encontrado ou muito curto!\n";
    exit(1);
}

echo "Texto encontrado: " . strlen($text) . " caracteres\n";

// Limpa e decodifica base64
$textCleaned = preg_replace('/\s+/', '', $text);
$decoded = base64_decode($textCleaned, true);

if ($decoded === false) {
    echo "❌ Falha ao decodificar base64!\n";
    exit(1);
}

echo "Base64 decodificado: " . strlen($decoded) . " bytes\n";

// Verifica se é OGG
if (substr($decoded, 0, 4) === 'OggS') {
    echo "✅ É áudio OGG!\n";
} else {
    echo "⚠️  Não parece ser OGG (primeiros 4 bytes: " . bin2hex(substr($decoded, 0, 4)) . ")\n";
}

// Tenta salvar manualmente
$tenantId = $event['tenant_id'] ?? null;
$subDir = date('Y/m/d', strtotime($event['created_at'] ?? 'now'));
$baseDir = __DIR__ . '/../storage/whatsapp-media';
if ($tenantId) {
    $baseDir .= '/tenant-' . $tenantId;
}
if ($subDir) {
    $baseDir .= '/' . trim($subDir, '/');
}

echo "\nDiretório: {$baseDir}\n";

// Cria diretório
Storage::ensureDirExists($baseDir);

if (!is_dir($baseDir)) {
    echo "❌ Falha ao criar diretório!\n";
    exit(1);
}

echo "✅ Diretório criado/existe!\n";

// Tenta salvar arquivo
$fileName = 'test-' . bin2hex(random_bytes(8)) . '.ogg';
$fullPath = $baseDir . DIRECTORY_SEPARATOR . $fileName;

echo "Salvando arquivo: {$fullPath}\n";

$bytesWritten = file_put_contents($fullPath, $decoded);

if ($bytesWritten === false) {
    echo "❌ Falha ao salvar arquivo!\n";
    echo "   Verifique permissões do diretório: {$baseDir}\n";
} else {
    echo "✅ Arquivo salvo com sucesso!\n";
    echo "   Bytes escritos: {$bytesWritten}\n";
    echo "   Tamanho real: " . filesize($fullPath) . " bytes\n";
    
    // Limpa arquivo de teste
    unlink($fullPath);
    echo "   Arquivo de teste removido\n";
}

echo "\n✅ Teste concluído!\n";

