<?php
// temp_clear_billing_history_12.php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

try {
    $tenantId = 12; // JOSENILSON ALVES FIGUEIREDO LTDA | ZENCOM
    
    // Deleta notificações
    $stmt = $db->prepare("DELETE FROM billing_notifications WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $deleted = $stmt->rowCount();
    
    // Reseta última verificação
    $stmt = $db->prepare("UPDATE tenants SET billing_last_check_at = NULL WHERE id = ?");
    $stmt->execute([$tenantId]);
    
    echo "✅ Histórico limpo com sucesso!\n";
    echo "📊 Registros removidos: {$deleted}\n";
    echo "🔄 Última verificação resetada\n\n";
    
    // Verifica resultado
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM billing_notifications 
        WHERE tenant_id = ?
    ");
    $stmt->execute([$tenantId]);
    $remaining = $stmt->fetch()['count'];
    
    echo "📋 Notificações restantes: {$remaining}\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
