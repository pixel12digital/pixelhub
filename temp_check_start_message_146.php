<?php
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

$db = DB::getConnection();

echo "=== Verificando Mensagem de Start do Tenant 146 ===\n\n";

$stmt = $db->prepare("
    SELECT id, tenant_id, status, message_type, total_amount, overdue_count, pending_count, 
           message_text, channel, created_at, sent_at
    FROM billing_start_messages 
    WHERE tenant_id = 146
");
$stmt->execute();
$startMessage = $stmt->fetch(PDO::FETCH_ASSOC);

if ($startMessage) {
    echo "✅ MENSAGEM DE START ENCONTRADA!\n\n";
    echo "ID: " . $startMessage['id'] . "\n";
    echo "Status: " . $startMessage['status'] . "\n";
    echo "Tipo: " . $startMessage['message_type'] . "\n";
    echo "Total: R$ " . number_format($startMessage['total_amount'], 2, ',', '.') . "\n";
    echo "Vencidas: " . $startMessage['overdue_count'] . "\n";
    echo "A vencer: " . $startMessage['pending_count'] . "\n";
    echo "Canal: " . $startMessage['channel'] . "\n";
    echo "Criada em: " . $startMessage['created_at'] . "\n";
    echo "Enviada em: " . ($startMessage['sent_at'] ?? 'NÃO ENVIADA') . "\n\n";
    
    echo "=== MENSAGEM ===\n";
    echo $startMessage['message_text'] . "\n\n";
    
    if ($startMessage['status'] === 'pending') {
        echo "⚠️ AÇÃO NECESSÁRIA: Mensagem está PENDENTE de aprovação!\n";
        echo "Você pode abrir o modal manualmente acessando:\n";
        echo "https://hub.pixel12digital.com.br/tenants/view?id=146&tab=financial&start_generated=1&start_id=" . $startMessage['id'] . "\n";
    } elseif ($startMessage['status'] === 'sent') {
        echo "✅ Mensagem já foi ENVIADA!\n";
    } elseif ($startMessage['status'] === 'cancelled') {
        echo "❌ Mensagem foi CANCELADA!\n";
    }
} else {
    echo "❌ NENHUMA MENSAGEM DE START ENCONTRADA!\n";
    echo "Isso é estranho, pois billing_started_at está preenchido...\n";
}
