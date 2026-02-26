<?php
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';
require_once __DIR__ . '/src/Services/AsaasBillingService.php';
require_once __DIR__ . '/src/Services/AISuggestReplyService.php';
require_once __DIR__ . '/src/Services/BillingStartService.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Services\BillingStartService;

Env::load();

$db = DB::getConnection();

echo "=== Regenerando Mensagem de Start do Tenant 146 ===\n\n";

// 1. Cancela mensagem antiga
echo "1. Cancelando mensagem antiga...\n";
$stmt = $db->prepare("UPDATE billing_start_messages SET status = 'cancelled' WHERE tenant_id = 146 AND status = 'pending'");
$stmt->execute();
echo "   ✓ Mensagem antiga cancelada\n\n";

// 2. Reseta billing_started_at
echo "2. Resetando billing_started_at...\n";
$stmt = $db->prepare("UPDATE tenants SET billing_started_at = NULL WHERE id = 146");
$stmt->execute();
echo "   ✓ billing_started_at resetado\n\n";

// 3. Gera nova mensagem com formato atualizado
echo "3. Gerando nova mensagem de start...\n";
$result = BillingStartService::generateStartMessage(146);

if ($result['success']) {
    echo "   ✓ Nova mensagem gerada com sucesso!\n\n";
    echo "=== RESULTADO ===\n";
    echo "ID da nova mensagem: " . $result['start_message_id'] . "\n";
    echo "Tipo: " . $result['message_type'] . "\n";
    echo "Total: R$ " . number_format($result['total_amount'], 2, ',', '.') . "\n";
    echo "Vencidas: " . $result['overdue_count'] . "\n";
    echo "A vencer: " . $result['pending_count'] . "\n\n";
    
    // Busca a mensagem gerada
    $stmt = $db->prepare("SELECT message_text FROM billing_start_messages WHERE id = ?");
    $stmt->execute([$result['start_message_id']]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "=== NOVA MENSAGEM (COM LINKS E PERSONALIZAÇÃO) ===\n";
    echo $message['message_text'] . "\n\n";
    
    echo "✅ Acesse este link para aprovar e enviar:\n";
    echo "https://hub.pixel12digital.com.br/tenants/view?id=146&tab=financial&start_generated=1&start_id=" . $result['start_message_id'] . "\n";
} else {
    echo "   ❌ Erro ao gerar mensagem: " . $result['message'] . "\n";
}
