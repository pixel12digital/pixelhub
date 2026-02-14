<?php
// Diagnóstico temporário - verificar webhooks e eventos recentes
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/DB.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__);
$db = DB::getConnection();

echo "=== DIAGNÓSTICO DE WEBHOOKS E EVENTOS ===\n\n";

// 1. Últimos webhooks recebidos
echo "--- 1. webhook_raw_logs (últimos 10) ---\n";
try {
    $stmt = $db->query("SELECT id, event_type, created_at, processed FROM webhook_raw_logs ORDER BY id DESC LIMIT 10");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo sprintf("  id=%d event=%s created=%s processed=%s\n", $r['id'], $r['event_type'], $r['created_at'], $r['processed']);
    }
    $countStmt = $db->query("SELECT COUNT(*) as total, MAX(created_at) as last_at FROM webhook_raw_logs");
    $c = $countStmt->fetch(PDO::FETCH_ASSOC);
    echo sprintf("  TOTAL: %d | ÚLTIMO: %s\n", $c['total'], $c['last_at'] ?? 'NENHUM');
} catch (Exception $e) {
    echo "  ERRO: " . $e->getMessage() . "\n";
}

echo "\n--- 2. webhook_raw_logs após 2025-02-12 ---\n";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM webhook_raw_logs WHERE created_at > '2025-02-12 23:59:59'");
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    echo sprintf("  Webhooks após 12/02: %d\n", $c['total']);
    
    $stmt2 = $db->query("SELECT COUNT(*) as total FROM webhook_raw_logs WHERE created_at > '2026-02-12 23:59:59'");
    $c2 = $stmt2->fetch(PDO::FETCH_ASSOC);
    echo sprintf("  Webhooks após 12/02/2026: %d\n", $c2['total']);
} catch (Exception $e) {
    echo "  ERRO: " . $e->getMessage() . "\n";
}

// 3. Últimos eventos de comunicação
echo "\n--- 3. communication_events (últimos 10) ---\n";
try {
    $stmt = $db->query("SELECT event_id, event_type, tenant_id, created_at FROM communication_events ORDER BY id DESC LIMIT 10");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo sprintf("  event_id=%s type=%s tenant=%s created=%s\n", substr($r['event_id'],0,12), $r['event_type'], $r['tenant_id'] ?? 'NULL', $r['created_at']);
    }
    $countStmt = $db->query("SELECT COUNT(*) as total, MAX(created_at) as last_at FROM communication_events");
    $c = $countStmt->fetch(PDO::FETCH_ASSOC);
    echo sprintf("  TOTAL: %d | ÚLTIMO: %s\n", $c['total'], $c['last_at'] ?? 'NENHUM');
} catch (Exception $e) {
    echo "  ERRO: " . $e->getMessage() . "\n";
}

// 4. Eventos após 12/02
echo "\n--- 4. communication_events após 12/02/2026 ---\n";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM communication_events WHERE created_at > '2026-02-12 23:59:59'");
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    echo sprintf("  Eventos após 12/02/2026: %d\n", $c['total']);
} catch (Exception $e) {
    echo "  ERRO: " . $e->getMessage() . "\n";
}

// 5. Conversas recentes
echo "\n--- 5. conversations (últimas 10 por last_message_at) ---\n";
try {
    $stmt = $db->query("SELECT id, contact_name, contact_external_id, channel_id, tenant_id, last_message_at, message_count FROM conversations ORDER BY last_message_at DESC LIMIT 10");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo sprintf("  id=%d name=%s phone=%s channel=%s tenant=%s last_msg=%s msgs=%d\n", 
            $r['id'], $r['contact_name'] ?? 'NULL', $r['contact_external_id'] ?? 'NULL', 
            $r['channel_id'] ?? 'NULL', $r['tenant_id'] ?? 'NULL', $r['last_message_at'] ?? 'NULL', $r['message_count']);
    }
} catch (Exception $e) {
    echo "  ERRO: " . $e->getMessage() . "\n";
}

// 6. Configuração do webhook
echo "\n--- 6. Configuração ---\n";
echo "  WPP_GATEWAY_BASE_URL: " . Env::get('WPP_GATEWAY_BASE_URL', 'NÃO DEFINIDO') . "\n";
echo "  PIXELHUB_WHATSAPP_WEBHOOK_URL: " . Env::get('PIXELHUB_WHATSAPP_WEBHOOK_URL', 'NÃO DEFINIDO') . "\n";
echo "  PIXELHUB_WHATSAPP_WEBHOOK_SECRET: " . (Env::get('PIXELHUB_WHATSAPP_WEBHOOK_SECRET') ? 'DEFINIDO' : 'NÃO DEFINIDO') . "\n";

echo "\n=== FIM DIAGNÓSTICO ===\n";
