<?php
/**
 * Auditoria completa do sistema de cobrança automática
 * Verifica clientes, regras, fila e identifica problemas
 */

// ─── Bootstrap ──────────────────────────────────────────────────
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
if (!class_exists('PixelHub\Core\Env')) {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $base_dir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) require $file;
    });
}

use PixelHub\Core\Env;
use PixelHub\Database\Database;

Env::load(__DIR__ . '/../.env');

$db = Database::getInstance()->getConnection();

echo "=== AUDITORIA DO SISTEMA DE COBRANÇA AUTOMÁTICA ===\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n\n";

// 1. CLIENTES COM COBRANÇA AUTOMÁTICA ATIVA
echo "1. CLIENTES COM COBRANÇA AUTOMÁTICA ATIVA\n";
echo str_repeat("=", 80) . "\n";

$stmt = $db->query("
    SELECT 
        t.id,
        t.nome_fantasia,
        t.billing_auto_send,
        t.billing_channel_id,
        t.is_billing_test,
        tmc.channel_account_id as whatsapp_session,
        COUNT(DISTINCT i.id) as total_invoices,
        COUNT(DISTINCT CASE WHEN i.status = 'pending' THEN i.id END) as pending_invoices,
        COUNT(DISTINCT CASE WHEN i.status = 'overdue' THEN i.id END) as overdue_invoices
    FROM tenants t
    LEFT JOIN tenant_message_channels tmc ON tmc.id = t.billing_channel_id
    LEFT JOIN invoices i ON i.tenant_id = t.id
    WHERE t.billing_auto_send = 1
    GROUP BY t.id
    ORDER BY t.id
");

$activeClients = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($activeClients)) {
    echo "❌ NENHUM cliente com cobrança automática ativa!\n\n";
} else {
    foreach ($activeClients as $client) {
        echo sprintf(
            "Cliente #%d: %s\n",
            $client['id'],
            $client['nome_fantasia']
        );
        echo sprintf(
            "  - Canal WhatsApp: %s\n",
            $client['whatsapp_session'] ?? '❌ NÃO CONFIGURADO'
        );
        echo sprintf(
            "  - Modo teste: %s\n",
            $client['is_billing_test'] ? '✅ SIM' : '❌ NÃO'
        );
        echo sprintf(
            "  - Faturas: %d total | %d pendentes | %d vencidas\n",
            $client['total_invoices'],
            $client['pending_invoices'],
            $client['overdue_invoices']
        );
        echo "\n";
    }
}

// 2. REGRAS DE DISPARO CONFIGURADAS
echo "\n2. REGRAS DE DISPARO CONFIGURADAS\n";
echo str_repeat("=", 80) . "\n";

$stmt = $db->query("
    SELECT 
        id,
        rule_name,
        trigger_type,
        days_offset,
        is_active,
        priority
    FROM billing_dispatch_rules
    ORDER BY priority ASC
");

$rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rules as $rule) {
    echo sprintf(
        "[%s] %s (offset: %+d dias, prioridade: %d)\n",
        $rule['is_active'] ? '✅' : '❌',
        $rule['rule_name'],
        $rule['days_offset'],
        $rule['priority']
    );
}

// 3. ANÁLISE DA FILA DE ENVIOS (ÚLTIMOS 7 DIAS)
echo "\n\n3. FILA DE ENVIOS (ÚLTIMOS 7 DIAS)\n";
echo str_repeat("=", 80) . "\n";

$stmt = $db->query("
    SELECT 
        DATE(scheduled_at) as data,
        status,
        COUNT(*) as total
    FROM billing_dispatch_queue
    WHERE scheduled_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(scheduled_at), status
    ORDER BY data DESC, status
");

$queueStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($queueStats)) {
    echo "❌ NENHUM registro na fila nos últimos 7 dias!\n";
} else {
    $currentDate = null;
    foreach ($queueStats as $stat) {
        if ($currentDate !== $stat['data']) {
            $currentDate = $stat['data'];
            echo "\n" . $currentDate . ":\n";
        }
        echo sprintf("  - %s: %d\n", strtoupper($stat['status']), $stat['total']);
    }
}

// 4. ANÁLISE ESPECÍFICA DO CLIENTE ID 14
echo "\n\n4. ANÁLISE DETALHADA - CLIENTE ID 14\n";
echo str_repeat("=", 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        t.id,
        t.nome_fantasia,
        t.billing_auto_send,
        t.billing_channel_id,
        t.is_billing_test,
        tmc.channel_account_id as whatsapp_session
    FROM tenants t
    LEFT JOIN tenant_message_channels tmc ON tmc.id = t.billing_channel_id
    WHERE t.id = 14
");
$stmt->execute();
$client14 = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client14) {
    echo "❌ Cliente ID 14 não encontrado!\n";
} else {
    echo "Cliente: " . $client14['nome_fantasia'] . "\n";
    echo "Cobrança automática: " . ($client14['billing_auto_send'] ? '✅ ATIVA' : '❌ INATIVA') . "\n";
    echo "Canal WhatsApp: " . ($client14['whatsapp_session'] ?? '❌ NÃO CONFIGURADO') . "\n";
    echo "Modo teste: " . ($client14['is_billing_test'] ? '✅ SIM' : '❌ NÃO') . "\n\n";

    // Faturas do cliente 14
    echo "FATURAS:\n";
    $stmt = $db->prepare("
        SELECT 
            id,
            asaas_id,
            status,
            due_date,
            value,
            DATEDIFF(due_date, CURDATE()) as days_until_due
        FROM invoices
        WHERE tenant_id = 14
        ORDER BY due_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($invoices)) {
        echo "  ❌ Nenhuma fatura encontrada\n";
    } else {
        foreach ($invoices as $inv) {
            echo sprintf(
                "  Fatura #%d (%s) - Venc: %s (%+d dias) - R$ %.2f - Status: %s\n",
                $inv['id'],
                $inv['asaas_id'],
                $inv['due_date'],
                $inv['days_until_due'],
                $inv['value'],
                strtoupper($inv['status'])
            );
        }
    }

    // Envios na fila para cliente 14
    echo "\n\nENVIOS NA FILA (ÚLTIMOS 30 DIAS):\n";
    $stmt = $db->prepare("
        SELECT 
            bdq.id,
            bdq.scheduled_at,
            bdq.status,
            bdq.retry_count,
            bdq.last_error,
            bdr.rule_name,
            i.asaas_id,
            i.due_date,
            i.status as invoice_status
        FROM billing_dispatch_queue bdq
        JOIN invoices i ON i.id = bdq.invoice_id
        LEFT JOIN billing_dispatch_rules bdr ON bdr.id = bdq.dispatch_rule_id
        WHERE i.tenant_id = 14
        AND bdq.scheduled_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY bdq.scheduled_at DESC
    ");
    $stmt->execute();
    $queueItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($queueItems)) {
        echo "  ❌ NENHUM envio na fila para este cliente!\n";
        echo "  ⚠️  PROBLEMA IDENTIFICADO: Faturas não estão sendo enfileiradas!\n";
    } else {
        foreach ($queueItems as $item) {
            echo sprintf(
                "  [%s] Fatura %s (venc: %s) - Regra: %s - Agendado: %s - Status: %s\n",
                $item['status'] === 'sent' ? '✅' : ($item['status'] === 'failed' ? '❌' : '⏳'),
                $item['asaas_id'],
                $item['due_date'],
                $item['rule_name'] ?? 'N/A',
                $item['scheduled_at'],
                strtoupper($item['status'])
            );
            if ($item['last_error']) {
                echo "    Erro: " . $item['last_error'] . "\n";
            }
        }
    }

    // Notificações enviadas
    echo "\n\nNOTIFICAÇÕES ENVIADAS (ÚLTIMOS 30 DIAS):\n";
    $stmt = $db->prepare("
        SELECT 
            bn.id,
            bn.sent_at,
            bn.triggered_by,
            bn.gateway_message_id,
            bdr.rule_name,
            i.asaas_id,
            i.due_date
        FROM billing_notifications bn
        JOIN invoices i ON i.id = bn.invoice_id
        LEFT JOIN billing_dispatch_rules bdr ON bdr.id = bn.dispatch_rule_id
        WHERE i.tenant_id = 14
        AND bn.sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAYS)
        ORDER BY bn.sent_at DESC
    ");
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($notifications)) {
        echo "  ❌ NENHUMA notificação enviada nos últimos 30 dias!\n";
    } else {
        foreach ($notifications as $notif) {
            echo sprintf(
                "  [%s] Fatura %s (venc: %s) - Regra: %s - Enviado por: %s\n",
                $notif['gateway_message_id'] ? '✅' : '❌',
                $notif['asaas_id'],
                $notif['due_date'],
                $notif['rule_name'] ?? 'N/A',
                $notif['triggered_by']
            );
        }
    }
}

// 5. VERIFICAÇÃO DOS CRONS
echo "\n\n5. VERIFICAÇÃO DE LOGS DOS CRONS\n";
echo str_repeat("=", 80) . "\n";

$logFiles = [
    'billing_dispatch.log' => 'Planejador (billing_auto_dispatch.php)',
    'billing_worker.log' => 'Worker (billing_queue_worker.php)'
];

foreach ($logFiles as $logFile => $description) {
    $logPath = __DIR__ . '/../logs/' . $logFile;
    echo "\n" . $description . ":\n";
    
    if (!file_exists($logPath)) {
        echo "  ❌ Arquivo de log não encontrado: $logPath\n";
        echo "  ⚠️  PROBLEMA: Cron pode não estar rodando!\n";
    } else {
        $lastModified = filemtime($logPath);
        $hoursSinceModified = (time() - $lastModified) / 3600;
        
        echo sprintf(
            "  Última modificação: %s (%.1f horas atrás)\n",
            date('Y-m-d H:i:s', $lastModified),
            $hoursSinceModified
        );
        
        if ($hoursSinceModified > 24) {
            echo "  ⚠️  ALERTA: Log não atualizado há mais de 24 horas!\n";
        }
        
        // Últimas 10 linhas
        $lines = file($logPath);
        $lastLines = array_slice($lines, -10);
        echo "  Últimas linhas:\n";
        foreach ($lastLines as $line) {
            echo "    " . trim($line) . "\n";
        }
    }
}

// 6. DIAGNÓSTICO FINAL
echo "\n\n6. DIAGNÓSTICO E PROBLEMAS IDENTIFICADOS\n";
echo str_repeat("=", 80) . "\n";

$problems = [];

// Verifica se há clientes ativos
if (empty($activeClients)) {
    $problems[] = "❌ CRÍTICO: Nenhum cliente com cobrança automática ativa";
}

// Verifica clientes sem canal configurado
foreach ($activeClients as $client) {
    if (!$client['whatsapp_session']) {
        $problems[] = sprintf(
            "❌ Cliente #%d (%s) sem canal WhatsApp configurado",
            $client['id'],
            $client['nome_fantasia']
        );
    }
}

// Verifica se há fila vazia nos últimos 7 dias
if (empty($queueStats)) {
    $problems[] = "❌ CRÍTICO: Nenhum item enfileirado nos últimos 7 dias - Planejador pode não estar rodando";
}

// Verifica logs dos crons
foreach ($logFiles as $logFile => $description) {
    $logPath = __DIR__ . '/../logs/' . $logFile;
    if (!file_exists($logPath)) {
        $problems[] = "❌ CRÍTICO: Log do cron não encontrado ($description)";
    } else {
        $hoursSinceModified = (time() - filemtime($logPath)) / 3600;
        if ($hoursSinceModified > 24) {
            $problems[] = sprintf(
                "⚠️  ALERTA: Log do cron desatualizado há %.1f horas ($description)",
                $hoursSinceModified
            );
        }
    }
}

// Verifica cliente 14 específico
if ($client14 && $client14['billing_auto_send'] && empty($queueItems)) {
    $problems[] = "❌ Cliente #14 tem cobrança ativa mas NENHUM item na fila - Planejador não está enfileirando";
}

if (empty($problems)) {
    echo "✅ Nenhum problema crítico identificado!\n";
} else {
    echo "PROBLEMAS ENCONTRADOS:\n\n";
    foreach ($problems as $i => $problem) {
        echo ($i + 1) . ". " . $problem . "\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "Auditoria concluída!\n";
