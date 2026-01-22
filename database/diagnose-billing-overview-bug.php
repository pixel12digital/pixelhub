<?php
/**
 * Script de diagnóstico para investigar o bug da Central de Cobranças
 * 
 * Problema: Após clicar em "Salvar/Marcar como enviado", a Central de Cobranças
 * mostra valores incorretos (ex: 63 faturas vencidas para o cliente Carlos).
 * 
 * Este script investiga:
 * 1. Quantas faturas realmente existem para o tenant
 * 2. Quantas notificações existem
 * 3. Como a query do overview está contando
 * 4. Se há duplicação por causa do JOIN
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

// ID do cliente Carlos (do exemplo)
$tenantId = 44; // Ajuste conforme necessário

echo "=== DIAGNÓSTICO: BUG CENTRAL DE COBRANÇAS ===\n\n";

// 1. Busca dados do tenant
echo "1. DADOS DO TENANT:\n";
echo str_repeat("-", 60) . "\n";
$stmt = $db->prepare("SELECT id, name, cpf_cnpj, asaas_customer_id, status, is_archived, is_financial_only FROM tenants WHERE id = ?");
$stmt->execute([$tenantId]);
$tenant = $stmt->fetch();
if ($tenant) {
    echo "ID: {$tenant['id']}\n";
    echo "Nome: {$tenant['name']}\n";
    echo "CPF/CNPJ: " . ($tenant['cpf_cnpj'] ?? 'N/A') . "\n";
    echo "Asaas Customer ID: " . ($tenant['asaas_customer_id'] ?? 'N/A') . "\n";
    echo "Status: {$tenant['status']}\n";
    echo "Arquivado: " . ($tenant['is_archived'] ?? 0 ? 'Sim' : 'Não') . "\n";
    echo "Somente Financeiro: " . ($tenant['is_financial_only'] ?? 0 ? 'Sim' : 'Não') . "\n";
} else {
    echo "Tenant não encontrado!\n";
    exit(1);
}
echo "\n";

// 2. Conta faturas reais (query simples)
echo "2. FATURAS REAIS (query simples):\n";
echo str_repeat("-", 60) . "\n";
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'overdue' AND (is_deleted IS NULL OR is_deleted = 0) THEN 1 END) as overdue_count,
        SUM(CASE WHEN status = 'overdue' AND (is_deleted IS NULL OR is_deleted = 0) THEN amount ELSE 0 END) as overdue_total
    FROM billing_invoices
    WHERE tenant_id = ?
");
$stmt->execute([$tenantId]);
$realCounts = $stmt->fetch();
echo "Total de faturas: {$realCounts['total']}\n";
echo "Faturas vencidas: {$realCounts['overdue_count']}\n";
echo "Valor em atraso: R$ " . number_format($realCounts['overdue_total'] ?? 0, 2, ',', '.') . "\n";
echo "\n";

// 3. Conta notificações
echo "3. NOTIFICAÇÕES:\n";
echo str_repeat("-", 60) . "\n";
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM billing_notifications
    WHERE tenant_id = ? AND status = 'sent_manual'
");
$stmt->execute([$tenantId]);
$notifications = $stmt->fetch();
echo "Total de notificações (sent_manual): {$notifications['total']}\n";
echo "\n";

// 4. Simula a query do overview (COM o JOIN problemático)
echo "4. QUERY DO OVERVIEW (COM JOIN - PROBLEMÁTICA):\n";
echo str_repeat("-", 60) . "\n";
$sql = "
    SELECT 
        t.id as tenant_id,
        COUNT(CASE WHEN bi.status = 'overdue' AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) THEN 1 END) as qtd_invoices_overdue,
        COALESCE(SUM(CASE WHEN bi.status = 'overdue' AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) THEN bi.amount ELSE 0 END), 0) as total_overdue
    FROM tenants t
    LEFT JOIN billing_invoices bi ON t.id = bi.tenant_id
    LEFT JOIN billing_notifications bn ON t.id = bn.tenant_id AND bn.status = 'sent_manual'
    WHERE t.id = ?
    GROUP BY t.id
";
$stmt = $db->prepare($sql);
$stmt->execute([$tenantId]);
$overviewResult = $stmt->fetch();
echo "Qtd faturas vencidas (com JOIN): {$overviewResult['qtd_invoices_overdue']}\n";
echo "Valor em atraso (com JOIN): R$ " . number_format($overviewResult['total_overdue'], 2, ',', '.') . "\n";
echo "\n";

// 5. Mostra quantas linhas o JOIN está gerando
echo "5. ANÁLISE DO JOIN (quantas linhas são geradas):\n";
echo str_repeat("-", 60) . "\n";
$sql = "
    SELECT 
        COUNT(*) as total_rows,
        COUNT(DISTINCT bi.id) as distinct_invoices,
        COUNT(DISTINCT bn.id) as distinct_notifications
    FROM tenants t
    LEFT JOIN billing_invoices bi ON t.id = bi.tenant_id AND (bi.is_deleted IS NULL OR bi.is_deleted = 0)
    LEFT JOIN billing_notifications bn ON t.id = bn.tenant_id AND bn.status = 'sent_manual'
    WHERE t.id = ?
";
$stmt = $db->prepare($sql);
$stmt->execute([$tenantId]);
$joinAnalysis = $stmt->fetch();
echo "Total de linhas geradas pelo JOIN: {$joinAnalysis['total_rows']}\n";
echo "Faturas distintas: {$joinAnalysis['distinct_invoices']}\n";
echo "Notificações distintas: {$joinAnalysis['distinct_notifications']}\n";
echo "\n";

if ($joinAnalysis['total_rows'] > $joinAnalysis['distinct_invoices']) {
    echo "⚠️ PROBLEMA IDENTIFICADO: O JOIN está gerando mais linhas do que faturas!\n";
    echo "   Isso causa duplicação na contagem.\n";
    echo "   Razão: Cada notificação multiplica as linhas das faturas.\n";
}
echo "\n";

// 6. Query corrigida (usando subquery para notificações)
echo "6. QUERY CORRIGIDA (usando subquery):\n";
echo str_repeat("-", 60) . "\n";
$sql = "
    SELECT 
        t.id as tenant_id,
        COUNT(CASE WHEN bi.status = 'overdue' AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) THEN 1 END) as qtd_invoices_overdue,
        COALESCE(SUM(CASE WHEN bi.status = 'overdue' AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) THEN bi.amount ELSE 0 END), 0) as total_overdue,
        (SELECT MAX(sent_at) FROM billing_notifications WHERE tenant_id = t.id AND status = 'sent_manual') as last_notification_sent
    FROM tenants t
    LEFT JOIN billing_invoices bi ON t.id = bi.tenant_id
    WHERE t.id = ?
    GROUP BY t.id
";
$stmt = $db->prepare($sql);
$stmt->execute([$tenantId]);
$correctedResult = $stmt->fetch();
echo "Qtd faturas vencidas (corrigida): {$correctedResult['qtd_invoices_overdue']}\n";
echo "Valor em atraso (corrigida): R$ " . number_format($correctedResult['total_overdue'], 2, ',', '.') . "\n";
echo "\n";

// 7. Verifica se há faturas duplicadas por asaas_payment_id
echo "7. VERIFICAÇÃO DE DUPLICATAS:\n";
echo str_repeat("-", 60) . "\n";
$stmt = $db->prepare("
    SELECT asaas_payment_id, COUNT(*) as count
    FROM billing_invoices
    WHERE tenant_id = ?
    GROUP BY asaas_payment_id
    HAVING count > 1
");
$stmt->execute([$tenantId]);
$duplicates = $stmt->fetchAll();
if (empty($duplicates)) {
    echo "✓ Nenhuma fatura duplicada encontrada (por asaas_payment_id)\n";
} else {
    echo "⚠️ Faturas duplicadas encontradas:\n";
    foreach ($duplicates as $dup) {
        echo "   asaas_payment_id: {$dup['asaas_payment_id']} - {$dup['count']} registros\n";
    }
}
echo "\n";

// 8. Verifica outros tenants com mesmo CPF/CNPJ
if (!empty($tenant['cpf_cnpj'])) {
    echo "8. OUTROS TENANTS COM MESMO CPF/CNPJ:\n";
    echo str_repeat("-", 60) . "\n";
    $stmt = $db->prepare("
        SELECT id, name, asaas_customer_id, status, is_archived, is_financial_only
        FROM tenants
        WHERE (cpf_cnpj = ? OR document = ?) AND id != ?
    ");
    $stmt->execute([$tenant['cpf_cnpj'], $tenant['cpf_cnpj'], $tenantId]);
    $sameCpfTenants = $stmt->fetchAll();
    if (empty($sameCpfTenants)) {
        echo "✓ Nenhum outro tenant com mesmo CPF/CNPJ\n";
    } else {
        echo "⚠️ Outros tenants encontrados:\n";
        foreach ($sameCpfTenants as $other) {
            echo "   ID: {$other['id']}, Nome: {$other['name']}, Asaas: " . ($other['asaas_customer_id'] ?? 'N/A') . "\n";
        }
    }
    echo "\n";
}

echo "=== FIM DO DIAGNÓSTICO ===\n";

