<?php
// Script para limpar dados de notificações do banco de dados
$pdo = new PDO('mysql:host=r225us.hmservers.net;port=3306;dbname=pixel12digital_pixelhub;charset=utf8mb4', 'pixel12digital_pixelhub', 'Los@ngo#081081');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Limpeza de Dados de Notificações ===\n\n";

// 1. Contar registros antes da limpeza
echo "1. Contando registros antes da limpeza:\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM whatsapp_generic_logs");
$countGeneric = $stmt->fetchColumn();
echo "   - whatsapp_generic_logs: {$countGeneric} registros\n";

// 2. Limpar whatsapp_generic_logs
echo "\n2. Limpando whatsapp_generic_logs...\n";
$pdo->exec("DELETE FROM whatsapp_generic_logs");
echo "   ✅ Todos os registros de whatsapp_generic_logs foram excluídos\n";

// 3. Verificar limpeza
echo "\n3. Verificando limpeza:\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM whatsapp_generic_logs");
$countGenericAfter = $stmt->fetchColumn();
echo "   - whatsapp_generic_logs: {$countGenericAfter} registros (deve ser 0)\n";

echo "\n=== Limpeza concluída! ===\n";
echo "Total de registros excluídos: {$countGeneric}\n";
