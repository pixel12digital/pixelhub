<?php
/**
 * INVESTIGAÇÃO: Por que mensagens não aparecem no Inbox
 * Data: 2026-03-11
 */

require_once 'vendor/autoload.php';
use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== INVESTIGAÇÃO: MENSAGENS WHATSAPP NÃO APARECEM NO INBOX ===\n\n";

// 1. Verificar se worker está rodando
echo "1. STATUS DO WORKER:\n";
$pidFile = 'storage/whatsapp_worker.pid';
if (file_exists($pidFile)) {
    $pid = file_get_contents($pidFile);
    echo "   Arquivo PID existe: $pid\n";
    // No Windows não temos posix_kill, então apenas verificamos se existe
} else {
    echo "   ✗ Worker NÃO está rodando (arquivo PID não existe)\n";
}

// 2. Verificar eventos na fila
echo "\n2. EVENTOS NA FILA (status = 'queued'):\n";
try {
    $pdo = DB::getConnection();
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM communication_events WHERE status = 'queued'");
    $result = $stmt->fetch();
    echo "   Total em fila: {$result['total']}\n";
    
    // Mostrar alguns exemplos
    $stmt = $pdo->query("SELECT id, event_type, created_at FROM communication_events WHERE status = 'queued' ORDER BY created_at DESC LIMIT 5");
    $events = $stmt->fetchAll();
    foreach ($events as $event) {
        echo "   - ID {$event['id']}: {$event['event_type']} ({$event['created_at']})\n";
    }
} catch (Exception $e) {
    echo "   Erro: " . $e->getMessage() . "\n";
}

// 3. Verificar eventos recentes com status diferente
echo "\n3. EVENTOS RECENTES POR STATUS:\n";
try {
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as total 
        FROM communication_events 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY status
    ");
    $results = $stmt->fetchAll();
    foreach ($results as $row) {
        echo "   {$row['status']}: {$row['total']}\n";
    }
} catch (Exception $e) {
    echo "   Erro: " . $e->getMessage() . "\n";
}

// 4. Verificar conversas recentes
echo "\n4. CONVERSAS RECENTES:\n";
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM conversations 
        WHERE last_message_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $result = $stmt->fetch();
    echo "   Total: {$result['total']}\n";
    
    // Mostrar algumas
    $stmt = $pdo->query("
        SELECT id, contact_name, contact_external_id, tenant_id, status, last_message_at
        FROM conversations 
        WHERE last_message_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY last_message_at DESC
        LIMIT 5
    ");
    $convs = $stmt->fetchAll();
    foreach ($convs as $conv) {
        echo "   - {$conv['contact_name']} ({$conv['contact_external_id']}) - Tenant: {$conv['tenant_id']}\n";
    }
} catch (Exception $e) {
    echo "   Erro: " . $e->getMessage() . "\n";
}

// 5. Verificar tenant do pixel12digital
echo "\n5. VERIFICANDO TENANT PIXEL12DIGITAL:\n";
try {
    $stmt = $pdo->prepare("SELECT id, slug, name FROM tenants WHERE slug = ?");
    $stmt->execute(['pixel12digital']);
    $tenant = $stmt->fetch();
    if ($tenant) {
        echo "   ID: {$tenant['id']}\n";
        echo "   Nome: {$tenant['name']}\n";
        
        // Verificar eventos deste tenant
        $stmt2 = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM communication_events 
            WHERE tenant_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt2->execute([$tenant['id']]);
        $events = $stmt2->fetch();
        echo "   Eventos (24h): {$events['total']}\n";
        
        // Verificar conversas deste tenant
        $stmt3 = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM conversations 
            WHERE tenant_id = ? 
            AND last_message_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt3->execute([$tenant['id']]);
        $convs = $stmt3->fetch();
        echo "   Conversas (24h): {$convs['total']}\n";
    } else {
        echo "   ✗ Tenant não encontrado!\n";
    }
} catch (Exception $e) {
    echo "   Erro: " . $e->getMessage() . "\n";
}

// 6. Verificar webhooks não processados
echo "\n6. WEBHOOKS NÃO PROCESSADOS:\n";
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM webhook_raw_logs 
        WHERE processed = 0 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $result = $stmt->fetch();
    echo "   Total: {$result['total']}\n";
} catch (Exception $e) {
    echo "   Erro: " . $e->getMessage() . "\n";
}

echo "\n=== CONCLUSÃO PRELIMINAR ===\n";
echo "Baseado na investigação:\n\n";
echo "1. Webhooks estão chegando (vistos em webhook_raw_logs)\n";
echo "2. Communication events estão sendo criados\n";
echo "3. Mas o worker pode não estar rodando para processar a fila\n";
echo "4. Sem o worker, conversas não são criadas/atualizadas\n";
echo "5. Sem conversas, mensagens não aparecem no Inbox\n";
