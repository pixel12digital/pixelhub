<?php
// Script para limpar TODOS os dados de notificações do banco de dados
$pdo = new PDO('mysql:host=r225us.hmservers.net;port=3306;dbname=pixel12digital_pixelhub;charset=utf8mb4', 'pixel12digital_pixelhub', 'Los@ngo#081081');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Limpeza COMPLETA de Dados de Notificações ===\n\n";

// 1. Contar registros antes da limpeza
echo "1. Contando registros ANTES da limpeza:\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM whatsapp_generic_logs");
$countGeneric = $stmt->fetchColumn();
echo "   - whatsapp_generic_logs: {$countGeneric} registros\n";

$stmt = $pdo->query("SELECT COUNT(*) FROM billing_notifications");
$countBilling = $stmt->fetchColumn();
echo "   - billing_notifications: {$countBilling} registros\n";

$totalBefore = $countGeneric + $countBilling;
echo "   TOTAL: {$totalBefore} registros\n";

// 2. Limpar whatsapp_generic_logs
echo "\n2. Limpando whatsapp_generic_logs...\n";
$pdo->exec("DELETE FROM whatsapp_generic_logs");
echo "   ✅ {$countGeneric} registros excluídos de whatsapp_generic_logs\n";

// 3. Limpar billing_notifications
echo "\n3. Limpando billing_notifications...\n";
$pdo->exec("DELETE FROM billing_notifications");
echo "   ✅ {$countBilling} registros excluídos de billing_notifications\n";

// 4. Verificar limpeza
echo "\n4. Verificando limpeza (APÓS exclusão):\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM whatsapp_generic_logs");
$countGenericAfter = $stmt->fetchColumn();
echo "   - whatsapp_generic_logs: {$countGenericAfter} registros (deve ser 0)\n";

$stmt = $pdo->query("SELECT COUNT(*) FROM billing_notifications");
$countBillingAfter = $stmt->fetchColumn();
echo "   - billing_notifications: {$countBillingAfter} registros (deve ser 0)\n";

$totalAfter = $countGenericAfter + $countBillingAfter;

// 5. Resumo
echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ LIMPEZA CONCLUÍDA COM SUCESSO!\n";
echo str_repeat("=", 60) . "\n";
echo "Registros excluídos:\n";
echo "  - whatsapp_generic_logs: {$countGeneric}\n";
echo "  - billing_notifications: {$countBilling}\n";
echo "  TOTAL EXCLUÍDO: {$totalBefore} registros\n";
echo "\nRegistros restantes: {$totalAfter} (deve ser 0)\n";

if ($totalAfter === 0) {
    echo "\n✅ PERFEITO! Todos os dados de notificações foram removidos.\n";
} else {
    echo "\n⚠️  ATENÇÃO! Ainda restam {$totalAfter} registros.\n";
}
