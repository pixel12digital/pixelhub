<?php

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
$eventId = '30129e36-ac2f-4b65-b99f-c00cd2d155b4';

echo "=== Verificação Thread Key para Teste-17-40 ===\n\n";

// Busca o evento
$stmt = $db->prepare("SELECT payload, tenant_id FROM communication_events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Evento não encontrado!\n");
}

$payload = json_decode($event['payload'], true);
$from = $payload['from'] ?? '';
$channelId = $payload['channel_id'] ?? 'N/A';

// Remove sufixos do from
$fromClean = str_replace(['@c.us', '@s.whatsapp.net', '@lid'], '', $from);

// Calcula thread_key esperado
$threadKey = 'wpp_gateway:' . $channelId . ':tel:' . $fromClean;

echo "From original: {$from}\n";
echo "From limpo: {$fromClean}\n";
echo "Channel ID: {$channelId}\n";
echo "Thread Key esperado: {$threadKey}\n\n";

// Busca conversa com este thread_key
$stmt2 = $db->prepare("
    SELECT 
        id, 
        thread_key, 
        tenant_id, 
        status, 
        contact_name,
        contact_external_id,
        last_message_at
    FROM conversations 
    WHERE thread_key = ?
");
$stmt2->execute([$threadKey]);
$conv = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($conv) {
    echo "✓ Conversa encontrada:\n";
    echo "  ID: {$conv['id']}\n";
    echo "  Thread Key: {$conv['thread_key']}\n";
    echo "  Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
    echo "  Status: {$conv['status']}\n";
    echo "  Contact Name: " . ($conv['contact_name'] ?? 'N/A') . "\n";
    echo "  Contact External ID: " . ($conv['contact_external_id'] ?? 'N/A') . "\n";
    echo "  Last Message At: " . ($conv['last_message_at'] ?? 'N/A') . "\n";
    
    if ($conv['tenant_id'] != $event['tenant_id']) {
        echo "\n⚠ ATENÇÃO: Tenant ID da conversa ({$conv['tenant_id']}) não corresponde ao do evento ({$event['tenant_id']})!\n";
    }
    
    if ($conv['status'] !== 'active') {
        echo "\n⚠ ATENÇÃO: Status da conversa é '{$conv['status']}', não 'active'!\n";
        echo "  A interface filtra apenas conversas com status 'active'.\n";
    }
} else {
    echo "✗ Nenhuma conversa encontrada com este thread_key!\n";
    echo "\nIsso explica por que a mensagem não aparece na interface.\n";
    echo "A mensagem existe no banco, mas não há conversa vinculada.\n";
}

// Verifica se há conversas similares (com o mesmo from mas thread_key diferente)
echo "\n--- Buscando conversas com o mesmo contact_external_id ---\n";
$stmt3 = $db->prepare("
    SELECT 
        id, 
        thread_key, 
        tenant_id, 
        status, 
        contact_name,
        contact_external_id
    FROM conversations 
    WHERE contact_external_id LIKE ?
       OR contact_external_id = ?
    LIMIT 5
");
$fromPattern = "%{$fromClean}%";
$stmt3->execute([$fromPattern, $from]);
$similarConvs = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (!empty($similarConvs)) {
    echo "Encontradas " . count($similarConvs) . " conversa(s) similar(es):\n";
    foreach ($similarConvs as $sim) {
        echo "  - ID: {$sim['id']}, Thread Key: {$sim['thread_key']}, Status: {$sim['status']}, Tenant: " . ($sim['tenant_id'] ?? 'NULL') . "\n";
    }
} else {
    echo "Nenhuma conversa similar encontrada.\n";
}

