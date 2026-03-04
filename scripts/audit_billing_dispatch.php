<?php

/**
 * Script de Auditoria de Cobranças Automáticas
 * 
 * Objetivo: Verificar quais cobranças deveriam ter sido enviadas hoje
 * baseado nas regras de disparo e confrontar com o Asaas.
 * 
 * Data base: 04/03/2026
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
echo "║  AUDITORIA DE COBRANÇAS AUTOMÁTICAS - " . date('d/m/Y H:i:s') . "  ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

$db = DB::getConnection();

// Data de referência para auditoria
$auditDate = new DateTime('2026-03-04');
echo "📅 Data de auditoria: " . $auditDate->format('d/m/Y') . "\n\n";

// Busca regras ativas
echo "═══════════════════════════════════════════════════════════════════\n";
echo "📋 REGRAS DE DISPARO ATIVAS\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$stmt = $db->query("
    SELECT id, name, stage, days_offset, is_enabled, template_key
    FROM billing_dispatch_rules
    WHERE is_enabled = 1
    ORDER BY days_offset ASC
");
$rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$datesForAsaas = [];

foreach ($rules as $rule) {
    $offset = (int) $rule['days_offset'];
    
    // Calcula a data de vencimento que deveria disparar hoje
    $dueDate = clone $auditDate;
    $dueDate->modify(($offset > 0 ? '-' : '+') . abs($offset) . ' days');
    
    echo "┌─────────────────────────────────────────────────────────────────┐\n";
    echo "│ Regra: " . str_pad($rule['name'], 58) . "│\n";
    echo "├─────────────────────────────────────────────────────────────────┤\n";
    echo "│ Stage: " . str_pad($rule['stage'], 58) . "│\n";
    echo "│ Offset: " . str_pad($offset . ' dias', 57) . "│\n";
    echo "│ Template: " . str_pad($rule['template_key'], 55) . "│\n";
    echo "├─────────────────────────────────────────────────────────────────┤\n";
    echo "│ 📌 VERIFICAR NO ASAAS:                                          │\n";
    echo "│    Data de vencimento: " . str_pad($dueDate->format('d/m/Y'), 41) . "│\n";
    echo "│    Filtro: dueDate=" . str_pad($dueDate->format('Y-m-d'), 44) . "│\n";
    echo "└─────────────────────────────────────────────────────────────────┘\n\n";
    
    $datesForAsaas[] = [
        'rule' => $rule['name'],
        'stage' => $rule['stage'],
        'offset' => $offset,
        'due_date' => $dueDate->format('Y-m-d'),
        'due_date_br' => $dueDate->format('d/m/Y'),
        'template' => $rule['template_key']
    ];
}

// Verifica configuração do tenant Charles Dietrich (id=25)
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "⚙️  CONFIGURAÇÃO DO CLIENTE (Charles Dietrich - ID 25)\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$stmt = $db->prepare("
    SELECT 
        id, name, billing_auto_send, billing_auto_channel, is_billing_test,
        asaas_customer_id
    FROM tenants
    WHERE id = 25
");
$stmt->execute();
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if ($tenant) {
    echo "Cliente: {$tenant['name']}\n";
    echo "Envio automático: " . ($tenant['billing_auto_send'] ? '✅ ATIVADO' : '❌ DESATIVADO') . "\n";
    echo "Canal: {$tenant['billing_auto_channel']}\n";
    echo "Modo teste: " . ($tenant['is_billing_test'] ? '🧪 SIM' : '❌ NÃO') . "\n";
    echo "Asaas Customer ID: " . ($tenant['asaas_customer_id'] ? $tenant['asaas_customer_id'] : '❌ NÃO CONFIGURADO') . "\n\n";
    
    if (!$tenant['billing_auto_send']) {
        echo "⚠️  ATENÇÃO: Envio automático está DESATIVADO!\n";
        echo "   Nenhuma cobrança será enviada automaticamente.\n\n";
    }
    
    if (!$tenant['is_billing_test']) {
        echo "⚠️  ATENÇÃO: Modo teste está DESATIVADO!\n";
        echo "   Por segurança, apenas clientes com is_billing_test=1 recebem envios.\n\n";
    }
} else {
    echo "❌ Cliente não encontrado!\n\n";
}

// Verifica fila de hoje
echo "═══════════════════════════════════════════════════════════════════\n";
echo "📦 FILA DE ENVIO (billing_dispatch_queue) - HOJE\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$stmt = $db->query("
    SELECT 
        bdq.id, bdq.tenant_id, bdq.status, bdq.scheduled_at, bdq.sent_at,
        bdq.attempts, bdq.error_message, bdq.invoice_ids,
        bdr.name as rule_name, bdr.stage,
        t.name as tenant_name
    FROM billing_dispatch_queue bdq
    LEFT JOIN billing_dispatch_rules bdr ON bdq.dispatch_rule_id = bdr.id
    LEFT JOIN tenants t ON bdq.tenant_id = t.id
    WHERE DATE(bdq.created_at) = CURDATE()
    ORDER BY bdq.scheduled_at ASC
");
$queueJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($queueJobs)) {
    echo "❌ Nenhum job na fila para hoje!\n\n";
    echo "💡 Isso significa que:\n";
    echo "   1. O scheduler (billing_auto_dispatch.php) não rodou hoje, OU\n";
    echo "   2. Não há cobranças elegíveis para envio, OU\n";
    echo "   3. O cliente não tem billing_auto_send=1\n\n";
} else {
    echo "Total de jobs: " . count($queueJobs) . "\n\n";
    
    foreach ($queueJobs as $job) {
        $invoiceIds = json_decode($job['invoice_ids'], true);
        $statusIcon = match($job['status']) {
            'queued' => '⏳',
            'processing' => '🔄',
            'sent' => '✅',
            'failed' => '❌',
            default => '❓'
        };
        
        echo "┌─────────────────────────────────────────────────────────────────┐\n";
        echo "│ Job #{$job['id']} - {$statusIcon} " . strtoupper($job['status']) . str_repeat(' ', 50 - strlen($job['status'])) . "│\n";
        echo "├─────────────────────────────────────────────────────────────────┤\n";
        echo "│ Cliente: " . str_pad($job['tenant_name'] ?? 'N/A', 56) . "│\n";
        echo "│ Regra: " . str_pad($job['rule_name'] ?? 'Manual', 58) . "│\n";
        echo "│ Stage: " . str_pad($job['stage'] ?? 'N/A', 58) . "│\n";
        echo "│ Faturas: " . str_pad(implode(', ', $invoiceIds), 56) . "│\n";
        echo "│ Agendado: " . str_pad($job['scheduled_at'], 55) . "│\n";
        
        if ($job['sent_at']) {
            echo "│ Enviado: " . str_pad($job['sent_at'], 56) . "│\n";
        }
        
        if ($job['error_message']) {
            echo "│ Erro: " . str_pad(substr($job['error_message'], 0, 60), 61) . "│\n";
        }
        
        echo "│ Tentativas: " . str_pad($job['attempts'] . '/3', 53) . "│\n";
        echo "└─────────────────────────────────────────────────────────────────┘\n\n";
    }
}

// Verifica notificações enviadas hoje
echo "═══════════════════════════════════════════════════════════════════\n";
echo "📨 NOTIFICAÇÕES ENVIADAS (billing_notifications) - HOJE\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$stmt = $db->query("
    SELECT 
        bn.id, bn.tenant_id, bn.invoice_id, bn.channel, bn.sent_at,
        bn.template, bn.status,
        t.name as tenant_name
    FROM billing_notifications bn
    LEFT JOIN tenants t ON bn.tenant_id = t.id
    WHERE DATE(bn.sent_at) = CURDATE()
    ORDER BY bn.sent_at DESC
");
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($notifications)) {
    echo "❌ Nenhuma notificação enviada hoje!\n\n";
} else {
    echo "Total de notificações: " . count($notifications) . "\n\n";
    
    foreach ($notifications as $notif) {
        echo "┌─────────────────────────────────────────────────────────────────┐\n";
        echo "│ Notificação #{$notif['id']}                                      │\n";
        echo "├─────────────────────────────────────────────────────────────────┤\n";
        echo "│ Cliente: " . str_pad($notif['tenant_name'] ?? 'N/A', 56) . "│\n";
        echo "│ Invoice ID: " . str_pad($notif['invoice_id'] ?? 'N/A', 53) . "│\n";
        echo "│ Template: " . str_pad($notif['template'] ?? 'N/A', 55) . "│\n";
        echo "│ Canal: " . str_pad($notif['channel'], 58) . "│\n";
        echo "│ Status: " . str_pad($notif['status'], 57) . "│\n";
        echo "│ Enviado: " . str_pad($notif['sent_at'] ?? 'N/A', 56) . "│\n";
        echo "└─────────────────────────────────────────────────────────────────┘\n\n";
    }
}

// Resumo para verificação no Asaas
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "🔍 RESUMO: DATAS PARA VERIFICAR NO ASAAS\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

echo "Para auditar se há cobranças que deveriam ter sido enviadas,\n";
echo "verifique no Asaas as faturas com as seguintes datas de vencimento:\n\n";

foreach ($datesForAsaas as $item) {
    $arrow = $item['offset'] < 0 ? '⬅️' : ($item['offset'] > 0 ? '➡️' : '🎯');
    echo "{$arrow} {$item['rule']}\n";
    echo "   Vencimento: {$item['due_date_br']} (filtro API: dueDate={$item['due_date']})\n";
    echo "   Stage: {$item['stage']} | Template: {$item['template']}\n\n";
}

echo "═══════════════════════════════════════════════════════════════════\n";
echo "📊 CHECKLIST DE AUDITORIA\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$checklist = [
    "1. Verificar se billing_auto_dispatch.php rodou hoje às 08:00",
    "2. Verificar se há faturas no Asaas com as datas acima",
    "3. Verificar se o cliente tem billing_auto_send=1",
    "4. Verificar se o cliente tem is_billing_test=1 (obrigatório)",
    "5. Verificar se há jobs na billing_dispatch_queue para hoje",
    "6. Verificar se billing_queue_worker.php está rodando (cron 5/5min)",
    "7. Verificar logs: logs/billing_dispatch.log e logs/billing_worker.log",
    "8. Verificar se o gateway WhatsApp está online (wpp.pixel12digital.com.br:8443)"
];

foreach ($checklist as $item) {
    echo "☐ {$item}\n";
}

echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "🔧 COMANDOS ÚTEIS\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

echo "# Rodar scheduler manualmente (enfileira cobranças):\n";
echo "php scripts/billing_auto_dispatch.php\n\n";

echo "# Rodar worker manualmente (processa fila):\n";
echo "php scripts/billing_queue_worker.php\n\n";

echo "# Ver logs do scheduler:\n";
echo "tail -f logs/billing_dispatch.log\n\n";

echo "# Ver logs do worker:\n";
echo "tail -f logs/billing_worker.log\n\n";

echo "# Verificar crons ativos:\n";
echo "crontab -l | grep billing\n\n";

echo "\n✅ Auditoria concluída!\n\n";
