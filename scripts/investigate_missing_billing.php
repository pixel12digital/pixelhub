<?php

/**
 * Script de Investigação: Por que cobranças de 07/03 e 25/02 não foram enviadas?
 * 
 * Objetivo: Identificar exatamente qual filtro está bloqueando as cobranças
 */

// ─── Bootstrap ──────────────────────────────────────────────────
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
if (!class_exists('PixelHub\Core\Env')) {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require_once $file;
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  INVESTIGAÇÃO: COBRANÇAS NÃO ENVIADAS                            ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

$db = DB::getConnection();

// Datas das cobranças que existem no Asaas
$targetDates = [
    '2026-03-07' => 'Lembrete pré-vencimento (3 dias antes)',
    '2026-02-25' => 'Cobrança +7 dias (venceu em 25/02)'
];

echo "📅 Data de hoje: " . date('Y-m-d') . " (" . date('d/m/Y') . ")\n\n";

foreach ($targetDates as $dueDate => $description) {
    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "🔍 INVESTIGANDO: {$description}\n";
    echo "   Data de vencimento: {$dueDate}\n";
    echo "═══════════════════════════════════════════════════════════════════\n\n";

    // 1. Verifica se a cobrança existe na tabela billing_invoices
    echo "1️⃣ Verificando se existe em billing_invoices...\n";
    $stmt = $db->prepare("
        SELECT 
            bi.id, bi.tenant_id, bi.asaas_payment_id, bi.status, bi.amount, bi.due_date,
            bi.created_at,
            t.name as tenant_name, t.billing_auto_send, t.billing_auto_channel, 
            t.is_billing_test, t.asaas_customer_id
        FROM billing_invoices bi
        JOIN tenants t ON t.id = bi.tenant_id
        WHERE bi.due_date = ?
        ORDER BY bi.id
    ");
    $stmt->execute([$dueDate]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($invoices)) {
        echo "   ❌ NÃO ENCONTRADA em billing_invoices!\n";
        echo "   💡 CAUSA: A cobrança não foi sincronizada do Asaas.\n";
        echo "   🔧 SOLUÇÃO: Executar sync do Asaas (AsaasBillingService::syncAllCustomersAndInvoices)\n\n";
        continue;
    }

    echo "   ✅ Encontrada(s): " . count($invoices) . " cobrança(s)\n\n";

    foreach ($invoices as $inv) {
        echo "   ┌─────────────────────────────────────────────────────────────┐\n";
        echo "   │ Invoice ID: " . str_pad($inv['id'], 49) . "│\n";
        echo "   │ Asaas Payment ID: " . str_pad($inv['asaas_payment_id'] ?? 'N/A', 43) . "│\n";
        echo "   │ Cliente: " . str_pad($inv['tenant_name'], 52) . "│\n";
        echo "   │ Tenant ID: " . str_pad($inv['tenant_id'], 50) . "│\n";
        echo "   │ Valor: R$ " . str_pad(number_format($inv['amount'], 2, ',', '.'), 49) . "│\n";
        echo "   │ Status: " . str_pad($inv['status'], 53) . "│\n";
        echo "   │ Vencimento: " . str_pad($inv['due_date'], 49) . "│\n";
        echo "   │ Criada em: " . str_pad($inv['created_at'], 50) . "│\n";
        echo "   └─────────────────────────────────────────────────────────────┘\n\n";

        // 2. Verifica configuração do tenant
        echo "2️⃣ Verificando configuração do tenant...\n";
        $checks = [
            'billing_auto_send' => (int) $inv['billing_auto_send'] === 1,
            'is_billing_test' => (int) $inv['is_billing_test'] === 1,
            'asaas_customer_id' => !empty($inv['asaas_customer_id']),
            'billing_auto_channel' => !empty($inv['billing_auto_channel']),
        ];

        foreach ($checks as $field => $passed) {
            $icon = $passed ? '✅' : '❌';
            $value = $inv[$field] ?? 'NULL';
            echo "   {$icon} {$field}: {$value}\n";
            
            if (!$passed) {
                echo "      💡 BLOQUEIO: Este campo está impedindo o envio!\n";
            }
        }
        echo "\n";

        // 3. Verifica status da fatura
        echo "3️⃣ Verificando status da fatura...\n";
        $validStatuses = ['pending', 'overdue'];
        $statusOk = in_array($inv['status'], $validStatuses);
        $icon = $statusOk ? '✅' : '❌';
        echo "   {$icon} Status: {$inv['status']} (válidos: " . implode(', ', $validStatuses) . ")\n";
        if (!$statusOk) {
            echo "      💡 BLOQUEIO: Status não permite envio automático!\n";
        }
        echo "\n";

        // 4. Verifica regra aplicável
        echo "4️⃣ Verificando regra aplicável para hoje (" . date('Y-m-d') . ")...\n";
        $daysDiff = (new DateTime($inv['due_date']))->diff(new DateTime())->days;
        $isOverdue = new DateTime($inv['due_date']) < new DateTime();
        $daysDiffSigned = $isOverdue ? $daysDiff : -$daysDiff;
        
        echo "   📊 Diferença de dias: {$daysDiffSigned} (vencimento " . ($isOverdue ? 'passou' : 'futuro') . ")\n";
        
        $stmt = $db->prepare("
            SELECT id, name, days_offset, is_enabled, stage
            FROM billing_dispatch_rules
            WHERE days_offset = ? AND is_enabled = 1
        ");
        $stmt->execute([$daysDiffSigned]);
        $matchingRule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($matchingRule) {
            echo "   ✅ Regra encontrada: {$matchingRule['name']} (ID {$matchingRule['id']})\n";
            echo "      Stage: {$matchingRule['stage']}\n";
        } else {
            echo "   ❌ Nenhuma regra ativa para offset {$daysDiffSigned}\n";
            echo "      💡 BLOQUEIO: Não há regra configurada para esta diferença de dias!\n";
            
            // Mostra regras disponíveis
            $allRules = $db->query("SELECT id, name, days_offset, is_enabled FROM billing_dispatch_rules ORDER BY days_offset")->fetchAll(PDO::FETCH_ASSOC);
            echo "      📋 Regras disponíveis:\n";
            foreach ($allRules as $r) {
                $status = $r['is_enabled'] ? '✅' : '❌';
                echo "         {$status} Offset {$r['days_offset']}: {$r['name']}\n";
            }
        }
        echo "\n";

        // 5. Verifica histórico de envios
        echo "5️⃣ Verificando histórico de envios...\n";
        $stmt = $db->prepare("
            SELECT id, channel, template, status, sent_at, created_at
            FROM billing_notifications
            WHERE invoice_id = ?
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$inv['id']]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($notifications)) {
            echo "   ℹ️  Nenhum envio anterior registrado\n";
        } else {
            echo "   📨 Envios anteriores: " . count($notifications) . "\n";
            foreach ($notifications as $notif) {
                echo "      • ID {$notif['id']}: {$notif['channel']} | {$notif['template']} | {$notif['status']} | " . ($notif['sent_at'] ?? 'não enviado') . "\n";
            }
        }
        echo "\n";

        // 6. Verifica fila de hoje
        echo "6️⃣ Verificando fila de hoje...\n";
        $stmt = $db->prepare("
            SELECT bdq.id, bdq.status, bdq.scheduled_at, bdq.sent_at, bdq.error_message,
                   bdr.name as rule_name
            FROM billing_dispatch_queue bdq
            LEFT JOIN billing_dispatch_rules bdr ON bdq.dispatch_rule_id = bdr.id
            WHERE bdq.tenant_id = ?
              AND DATE(bdq.created_at) = CURDATE()
              AND JSON_CONTAINS(bdq.invoice_ids, ?)
        ");
        $stmt->execute([$inv['tenant_id'], json_encode((int)$inv['id'])]);
        $queueJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($queueJobs)) {
            echo "   ❌ Não está na fila de hoje\n";
            echo "      💡 BLOQUEIO: Scheduler não enfileirou esta cobrança!\n";
        } else {
            echo "   ✅ Encontrado na fila: " . count($queueJobs) . " job(s)\n";
            foreach ($queueJobs as $job) {
                echo "      • Job #{$job['id']}: {$job['status']} | Regra: {$job['rule_name']} | Agendado: {$job['scheduled_at']}\n";
                if ($job['error_message']) {
                    echo "        ⚠️  Erro: {$job['error_message']}\n";
                }
            }
        }
        echo "\n";

        // RESUMO DO DIAGNÓSTICO
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "📋 RESUMO DO DIAGNÓSTICO\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";
        
        $blockers = [];
        
        if ((int) $inv['billing_auto_send'] !== 1) {
            $blockers[] = "❌ billing_auto_send = 0 (envio automático desativado)";
        }
        if ((int) $inv['is_billing_test'] !== 1) {
            $blockers[] = "❌ is_billing_test = 0 (não é tenant de teste)";
        }
        if (empty($inv['asaas_customer_id'])) {
            $blockers[] = "❌ asaas_customer_id vazio (cliente não sincronizado)";
        }
        if (!in_array($inv['status'], $validStatuses)) {
            $blockers[] = "❌ Status '{$inv['status']}' não permite envio";
        }
        if (!$matchingRule) {
            $blockers[] = "❌ Nenhuma regra ativa para offset {$daysDiffSigned}";
        }
        
        if (empty($blockers)) {
            echo "✅ NENHUM BLOQUEIO IDENTIFICADO!\n";
            echo "   A cobrança deveria ter sido enfileirada.\n";
            echo "   Possíveis causas:\n";
            echo "   • Scheduler não rodou hoje\n";
            echo "   • Erro na lógica de seleção do scheduler\n";
            echo "   • Filtro anti-spam bloqueou (já enviado recentemente)\n\n";
        } else {
            echo "🚫 BLOQUEIOS IDENTIFICADOS:\n\n";
            foreach ($blockers as $blocker) {
                echo "   {$blocker}\n";
            }
            echo "\n";
        }
        
        echo "═══════════════════════════════════════════════════════════════\n\n";
    }
}

echo "\n✅ Investigação concluída!\n\n";

// ═══════════════════════════════════════════════════════════════════
// SEÇÃO 2: INVESTIGAÇÃO DE DUPLICATAS (SO OBRAS / qualquer tenant)
// ═══════════════════════════════════════════════════════════════════

echo "\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  SEÇÃO 2: DIAGNÓSTICO DE MENSAGENS DUPLICADAS                    ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

// 1. Verifica colunas da billing_dispatch_queue
echo "🔬 1. Estrutura da tabela billing_dispatch_queue:\n";
$cols = $db->query("DESCRIBE billing_dispatch_queue")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($cols, 'Field');
$expected = ['trigger_source', 'triggered_by_user_id', 'is_forced', 'force_reason', 'sent_at', 'idempotency_key'];
foreach ($expected as $col) {
    $exists = in_array($col, $colNames);
    echo "   " . ($exists ? '✅' : '❌ AUSENTE') . " $col\n";
}
echo "\n";

// 2. Jobs duplicados: mesma regra + mesmo tenant + mesmo dia, mais de 1 entry
echo "🔬 2. Jobs duplicados no billing_dispatch_queue (últimos 7 dias):\n";
$stmt = $db->query("
    SELECT tenant_id, dispatch_rule_id, DATE(created_at) as dia,
           COUNT(*) as total,
           GROUP_CONCAT(id ORDER BY id) as job_ids,
           GROUP_CONCAT(status ORDER BY id) as statuses
    FROM billing_dispatch_queue
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY tenant_id, dispatch_rule_id, DATE(created_at)
    HAVING COUNT(*) > 1
    ORDER BY dia DESC
");
$dups = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($dups)) {
    echo "   ✅ Nenhum job duplicado encontrado nos últimos 7 dias.\n\n";
} else {
    echo "   ⚠️  " . count($dups) . " grupo(s) de jobs duplicados:\n\n";
    foreach ($dups as $dup) {
        $tenantName = $db->query("SELECT name FROM tenants WHERE id = {$dup['tenant_id']}")->fetchColumn();
        $ruleName   = $dup['dispatch_rule_id']
            ? $db->query("SELECT name FROM billing_dispatch_rules WHERE id = {$dup['dispatch_rule_id']}")->fetchColumn()
            : 'N/A';
        echo "   ┌─ Tenant: {$tenantName} (id={$dup['tenant_id']})\n";
        echo "   │  Regra: {$ruleName} (id={$dup['dispatch_rule_id']})\n";
        echo "   │  Dia: {$dup['dia']} | Total jobs: {$dup['total']}\n";
        echo "   │  Job IDs: {$dup['job_ids']}\n";
        echo "   └─ Statuses: {$dup['statuses']}\n\n";
    }
}

// 3. Histórico completo SO OBRAS
echo "🔬 3. Histórico SO OBRAS - billing_dispatch_queue (últimos 14 dias):\n";
$stmt = $db->prepare("
    SELECT t.name, bdq.id, bdq.status, bdq.dispatch_rule_id, bdq.invoice_ids,
           bdq.attempts, bdq.max_attempts, bdq.scheduled_at, bdq.sent_at,
           bdq.created_at, bdq.error_message,
           bdr.name as rule_name
    FROM billing_dispatch_queue bdq
    JOIN tenants t ON t.id = bdq.tenant_id
    LEFT JOIN billing_dispatch_rules bdr ON bdr.id = bdq.dispatch_rule_id
    WHERE t.name LIKE '%SO OBRAS%'
       OR t.name LIKE '%SOOBRASEPC%'
    ORDER BY bdq.created_at DESC
    LIMIT 20
");
$stmt->execute();
$soObrasJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($soObrasJobs)) {
    echo "   ℹ️  Nenhum registro encontrado para SO OBRAS.\n\n";
} else {
    foreach ($soObrasJobs as $j) {
        echo "   Job #{$j['id']} | {$j['status']} | Regra: {$j['rule_name']} | Tentativas: {$j['attempts']}/{$j['max_attempts']}\n";
        echo "   Agendado: {$j['scheduled_at']} | Criado: {$j['created_at']} | Sent_at: " . ($j['sent_at'] ?? 'NULL') . "\n";
        echo "   Faturas: {$j['invoice_ids']}\n";
        if ($j['error_message']) {
            echo "   ⚠️  Erro: {$j['error_message']}\n";
        }
        echo "\n";
    }
}

// 4. billing_dispatch_log SO OBRAS
echo "🔬 4. billing_dispatch_log SO OBRAS (últimos 14 dias):\n";
$stmt = $db->prepare("
    SELECT bdl.id, bdl.invoice_id, bdl.channel, bdl.template_key,
           bdl.sent_at, bdl.trigger_source, bdl.status
    FROM billing_dispatch_log bdl
    JOIN billing_invoices bi ON bi.id = bdl.invoice_id
    JOIN tenants t ON t.id = bi.tenant_id
    WHERE t.name LIKE '%SO OBRAS%'
       OR t.name LIKE '%SOOBRASEPC%'
    ORDER BY bdl.sent_at DESC
    LIMIT 20
");
$stmt->execute();
$soObrasLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($soObrasLogs)) {
    echo "   ℹ️  Nenhum registro no billing_dispatch_log.\n\n";
} else {
    foreach ($soObrasLogs as $l) {
        echo "   Log #{$l['id']} | invoice #{$l['invoice_id']} | {$l['template_key']} | {$l['status']} | {$l['sent_at']}\n";
    }
    echo "\n";
}

echo "\n✅ Diagnóstico de duplicatas concluído!\n\n";
