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
$eventId = 'ed4e9725-9516-4524-a7e5-9f84bc04515c';
$correctTenantId = 25;
$conversationId = 2; // Conversa com thread_key correto

echo "=== CORREÇÃO FINAL: Mensagem Teste-17:14 ===\n\n";

// 1. Verifica payload
$stmt = $db->prepare("SELECT payload FROM communication_events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);
$payload = json_decode($event['payload'], true);

echo "1. Payload atual:\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Se payload tem text como string JSON, desaninha
$needsFix = false;
if (isset($payload['text']) && is_string($payload['text'])) {
    $inner = json_decode($payload['text'], true);
    if ($inner && is_array($inner) && isset($inner['from'])) {
        $needsFix = true;
        $payload = $inner;
        echo "2. Payload desaninhado:\n";
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }
}

$from = $payload['from'] ?? '';
$to = $payload['to'] ?? '';
$text = $payload['text'] ?? '';

echo "3. Dados extraídos:\n";
echo "   From: {$from}\n";
echo "   To: {$to}\n";
echo "   Text: {$text}\n\n";

// 4. Aplica correções
try {
    $db->beginTransaction();
    
    // Corrige payload se necessário
    if ($needsFix) {
        $fixedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $db->prepare("UPDATE communication_events SET payload = ? WHERE event_id = ?");
        $stmt->execute([$fixedPayload, $eventId]);
        echo "   ✓ Payload corrigido\n";
    }
    
    // Garante tenant_id correto
    $stmt = $db->prepare("UPDATE communication_events SET tenant_id = ? WHERE event_id = ?");
    $stmt->execute([$correctTenantId, $eventId]);
    echo "   ✓ Tenant_id atualizado para {$correctTenantId}\n";
    
    // Atualiza conversa ID 2
    $stmt = $db->prepare("
        UPDATE conversations 
        SET tenant_id = ?,
            status = 'active',
            last_message_at = (SELECT created_at FROM communication_events WHERE event_id = ?),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$correctTenantId, $eventId, $conversationId]);
    echo "   ✓ Conversa ID {$conversationId} atualizada:\n";
    echo "     - tenant_id: → {$correctTenantId}\n";
    echo "     - status: archived → active\n";
    echo "     - last_message_at atualizado\n";
    
    $db->commit();
    echo "\n✓ Todas as correções aplicadas!\n";
    
} catch (\Exception $e) {
    $db->rollBack();
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nRecarregue a página do painel de comunicação.\n";

