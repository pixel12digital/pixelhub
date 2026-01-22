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

echo "=== Payload Completo do Evento Teste-17-40 ===\n\n";

$stmt = $db->prepare("SELECT payload, tenant_id, created_at FROM communication_events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Evento não encontrado!\n");
}

$payload = json_decode($event['payload'], true);

echo "Payload completo (JSON formatado):\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

echo "Campos extraídos:\n";
// Estrutura aninhada: message.from, message.to, session.id
$from = $payload['message']['from'] ?? $payload['from'] ?? '';
$to = $payload['message']['to'] ?? $payload['to'] ?? '';
$channelId = $payload['session']['id'] ?? $payload['channel_id'] ?? null;
$text = $payload['message']['text'] ?? $payload['text'] ?? 'N/A';

echo "  from: {$from}\n";
echo "  to: {$to}\n";
echo "  channel_id: " . ($channelId ?? 'N/A') . "\n";
echo "  text: {$text}\n";
echo "  timestamp: " . ($payload['message']['timestamp'] ?? $payload['timestamp'] ?? 'N/A') . "\n";
echo "\n";

echo "Tentativas de thread_key:\n";

if ($from && $channelId) {
    $fromClean = str_replace(['@c.us', '@s.whatsapp.net', '@lid'], '', $from);
    $threadKey1 = "wpp_gateway:{$channelId}:tel:{$fromClean}";
    echo "  1. wpp_gateway:{$channelId}:tel:{$fromClean}\n";
    
    // Verifica se existe
    $stmt2 = $db->prepare("SELECT id, thread_key, tenant_id, status FROM conversations WHERE thread_key = ?");
    $stmt2->execute([$threadKey1]);
    $conv1 = $stmt2->fetch(PDO::FETCH_ASSOC);
    if ($conv1) {
        echo "     ✓ ENCONTRADA! ID: {$conv1['id']}, Tenant: " . ($conv1['tenant_id'] ?? 'NULL') . ", Status: {$conv1['status']}\n";
    } else {
        echo "     ✗ Não encontrada\n";
    }
}

// Tenta com lid
if (strpos($from, '@lid') !== false) {
    $fromLid = str_replace('@lid', '', $from);
    if ($channelId) {
        $threadKey2 = "wpp_gateway:{$channelId}:lid:{$fromLid}";
        echo "  2. wpp_gateway:{$channelId}:lid:{$fromLid}\n";
        
        $stmt3 = $db->prepare("SELECT id, thread_key, tenant_id, status FROM conversations WHERE thread_key = ?");
        $stmt3->execute([$threadKey2]);
        $conv2 = $stmt3->fetch(PDO::FETCH_ASSOC);
        if ($conv2) {
            echo "     ✓ ENCONTRADA! ID: {$conv2['id']}, Tenant: " . ($conv2['tenant_id'] ?? 'NULL') . ", Status: {$conv2['status']}\n";
        } else {
            echo "     ✗ Não encontrada\n";
        }
    }
}

// Busca todas as conversas que podem estar relacionadas
echo "\n--- Buscando conversas relacionadas por número ---\n";
$fromClean = str_replace(['@c.us', '@s.whatsapp.net', '@lid'], '', $from);
if ($fromClean) {
    $stmt4 = $db->prepare("
        SELECT id, thread_key, tenant_id, status, contact_external_id, contact_name
        FROM conversations 
        WHERE contact_external_id LIKE ?
           OR contact_external_id = ?
           OR thread_key LIKE ?
        LIMIT 10
    ");
    $pattern = "%{$fromClean}%";
    $stmt4->execute([$pattern, $from, $pattern]);
    $related = $stmt4->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($related)) {
        echo "Encontradas " . count($related) . " conversa(s) relacionada(s):\n";
        foreach ($related as $rel) {
            echo "  - ID: {$rel['id']}\n";
            echo "    Thread Key: {$rel['thread_key']}\n";
            echo "    Tenant: " . ($rel['tenant_id'] ?? 'NULL') . "\n";
            echo "    Status: {$rel['status']}\n";
            echo "    Contact: " . ($rel['contact_name'] ?? $rel['contact_external_id'] ?? 'N/A') . "\n";
            echo "\n";
        }
    } else {
        echo "Nenhuma conversa relacionada encontrada.\n";
    }
}

