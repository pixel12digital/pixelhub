<?php
// Script para verificar dados de notificações
$pdo = new PDO('mysql:host=r225us.hmservers.net;port=3306;dbname=pixel12digital_pixelhub;charset=utf8mb4', 'pixel12digital_pixelhub', 'Los@ngo#081081');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Verificação de Dados de Notificações ===\n\n";

// 1. whatsapp_generic_logs
echo "1. whatsapp_generic_logs:\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM whatsapp_generic_logs");
$count = $stmt->fetchColumn();
echo "   Total: {$count} registros\n";

if ($count > 0) {
    $stmt = $pdo->query("SELECT * FROM whatsapp_generic_logs LIMIT 5");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Primeiros 5 registros:\n";
    foreach ($records as $r) {
        echo "   - ID: {$r['id']}, Tenant: {$r['tenant_id']}, Sent: {$r['sent_at']}\n";
    }
}

// 2. billing_notifications
echo "\n2. billing_notifications:\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM billing_notifications");
$count = $stmt->fetchColumn();
echo "   Total: {$count} registros\n";

if ($count > 0) {
    $stmt = $pdo->query("SELECT * FROM billing_notifications LIMIT 5");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Primeiros 5 registros:\n";
    foreach ($records as $r) {
        echo "   - ID: {$r['id']}, Tenant: {$r['tenant_id']}, Template: {$r['template']}, Sent: {$r['sent_at']}\n";
    }
}

// 3. Verificar se há outras tabelas relacionadas
echo "\n3. Buscando outras tabelas com 'notification' no nome:\n";
$stmt = $pdo->query("SHOW TABLES LIKE '%notification%'");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
    $count = $stmt->fetchColumn();
    echo "   - {$table}: {$count} registros\n";
}

echo "\n=== Fim da verificação ===\n";
