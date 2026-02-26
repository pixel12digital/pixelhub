<?php
// temp_reset_billing_start_12.php
// Reseta o estado de cobrança automática para permitir reenvio da mensagem de start
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

try {
    $tenantId = 12; // JOSENILSON ALVES FIGUEIREDO LTDA | ZENCOM
    
    echo "=== Resetando estado de cobrança automática ===\n\n";
    
    // 1. Cancela mensagens de start pendentes
    $stmt = $db->prepare("
        UPDATE billing_start_messages 
        SET status = 'cancelled' 
        WHERE tenant_id = ? AND status IN ('pending', 'approved')
    ");
    $stmt->execute([$tenantId]);
    $cancelledMessages = $stmt->rowCount();
    echo "1. Mensagens de start canceladas: {$cancelledMessages}\n";
    
    // 2. Reseta billing_started_at para permitir novo start
    $stmt = $db->prepare("
        UPDATE tenants 
        SET billing_started_at = NULL 
        WHERE id = ?
    ");
    $stmt->execute([$tenantId]);
    echo "2. billing_started_at resetado ✓\n";
    
    // 3. Limpa histórico de notificações
    $stmt = $db->prepare("DELETE FROM billing_notifications WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $deletedNotifications = $stmt->rowCount();
    echo "3. Notificações removidas: {$deletedNotifications}\n";
    
    // 4. Reseta última verificação
    $stmt = $db->prepare("UPDATE tenants SET billing_last_check_at = NULL WHERE id = ?");
    $stmt->execute([$tenantId]);
    echo "4. Última verificação resetada ✓\n\n";
    
    echo "✅ Estado resetado com sucesso!\n";
    echo "📧 Agora você pode ativar a cobrança automática novamente e enviar a mensagem de start.\n\n";
    
    // Verifica estado final
    $stmt = $db->prepare("
        SELECT 
            billing_auto_send,
            billing_auto_channel,
            billing_started_at,
            billing_last_check_at
        FROM tenants 
        WHERE id = ?
    ");
    $stmt->execute([$tenantId]);
    $tenant = $stmt->fetch();
    
    echo "Estado atual:\n";
    echo "- Cobrança automática: " . ($tenant['billing_auto_send'] ? 'ATIVADA' : 'DESATIVADA') . "\n";
    echo "- Canal: " . ($tenant['billing_auto_channel'] ?? 'não definido') . "\n";
    echo "- billing_started_at: " . ($tenant['billing_started_at'] ?? 'NULL (pronto para start)') . "\n";
    echo "- billing_last_check_at: " . ($tenant['billing_last_check_at'] ?? 'NULL') . "\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
