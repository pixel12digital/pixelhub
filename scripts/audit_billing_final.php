<?php
/**
 * Auditoria COMPLETA do sistema de cobrança automática
 * Identifica problemas na lógica de envio
 */

$envFile = __DIR__ . '/../.env';
$envVars = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $envVars[trim($key)] = trim($value);
    }
}

$pdo = new PDO(
    "mysql:host={$envVars['DB_HOST']};dbname={$envVars['DB_NAME']};charset=utf8mb4",
    $envVars['DB_USER'],
    $envVars['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "╔═══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║         AUDITORIA DO SISTEMA DE COBRANÇA AUTOMÁTICA - PIXELHUB               ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════════╝\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n\n";

// 1. CLIENTES COM COBRANÇA AUTOMÁTICA ATIVA
echo "┌─────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 1. CLIENTES COM COBRANÇA AUTOMÁTICA ATIVA                                   │\n";
echo "└─────────────────────────────────────────────────────────────────────────────┘\n";

$stmt = $pdo->query("
    SELECT 
        t.id,
        t.nome_fantasia,
        t.billing_auto_send,
        t.billing_auto_channel,
        t.is_billing_test,
        COUNT(DISTINCT i.id) as total_invoices,
        COUNT(DISTINCT CASE WHEN i.status = 'pending' THEN i.id END) as pending_invoices,
        COUNT(DISTINCT CASE WHEN i.status = 'overdue' THEN i.id END) as overdue_invoices
    FROM tenants t
    LEFT JOIN invoices i ON i.tenant_id = t.id
    WHERE t.billing_auto_send = 1
    GROUP BY t.id
    ORDER BY t.id
");

$activeClients = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalClients = count($activeClients);

echo "Total de clientes ativos: $totalClients\n\n";

if (empty($activeClients)) {
    echo "❌ NENHUM cliente com cobrança automática ativa!\n\n";
} else {
    foreach ($activeClients as $client) {
        $icon = $client['is_billing_test'] ? '🧪' : '✅';
        echo sprintf(
            "%s Cliente #%d: %s\n",
            $icon,
            $client['id'],
            $client['nome_fantasia'] ?: '(sem nome)'
        );
        echo sprintf("   Canal: %s | Teste: %s | Faturas: %d (pendentes: %d, vencidas: %d)\n\n",
            $client['billing_auto_channel'] ?? '❌ NÃO CONFIG',
            $client['is_billing_test'] ? 'SIM' : 'NÃO',
            $client['total_invoices'],
            $client['pending_invoices'],
            $client['overdue_invoices']
        );
    }
}

// 2. REGRAS DE DISPARO
echo "\n┌─────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 2. REGRAS DE DISPARO CONFIGURADAS                                          │\n";
echo "└─────────────────────────────────────────────────────────────────────────────┘\n";

$stmt = $pdo->query("
    SELECT 
        id,
        name,
        stage,
        days_offset,
        is_enabled,
        channels,
        repeat_if_open,
        repeat_interval_days,
        max_repeats
    FROM billing_dispatch_rules
    ORDER BY days_offset ASC
");

$rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rules as $rule) {
    $icon = $rule['is_enabled'] ? '✅' : '❌';
    $channels = json_decode($rule['channels'], true);
    $channelStr = is_array($channels) ? implode(', ', $channels) : $rule['channels'];
    
    echo sprintf(
        "%s [%s] %s (offset: %+d dias)\n",
        $icon,
        $rule['stage'],
        $rule['name'],
        $rule['days_offset']
    );
    echo sprintf(
        "   Canais: %s | Repetir: %s",
        $channelStr,
        $rule['repeat_if_open'] ? "SIM (a cada {$rule['repeat_interval_days']} dias, máx {$rule['max_repeats']}x)" : 'NÃO'
    );
    echo "\n\n";
}

// 3. FILA DE ENVIOS (ÚLTIMOS 7 DIAS)
echo "\n┌─────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 3. FILA DE ENVIOS (ÚLTIMOS 7 DIAS)                                         │\n";
echo "└─────────────────────────────────────────────────────────────────────────────┘\n";

$stmt = $pdo->query("
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
    echo "⚠️  PROBLEMA CRÍTICO: O planejador (billing_auto_dispatch.php) NÃO está rodando!\n";
} else {
    $currentDate = null;
    foreach ($queueStats as $stat) {
        if ($currentDate !== $stat['data']) {
            $currentDate = $stat['data'];
            echo "\n📅 " . $currentDate . ":\n";
        }
        $statusIcon = [
            'queued' => '⏳',
            'processing' => '🔄',
            'sent' => '✅',
            'failed' => '❌'
        ][$stat['status']] ?? '❓';
        
        echo sprintf("   %s %s: %d\n", $statusIcon, strtoupper($stat['status']), $stat['total']);
    }
}

// 4. ANÁLISE DO CLIENTE ID 14
echo "\n\n┌─────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 4. ANÁLISE DETALHADA - CLIENTE ID 14                                       │\n";
echo "└─────────────────────────────────────────────────────────────────────────────┘\n";

$stmt = $pdo->prepare("
    SELECT 
        t.id,
        t.nome_fantasia,
        t.billing_auto_send,
        t.billing_auto_channel,
        t.is_billing_test,
        t.asaas_customer_id
    FROM tenants t
    WHERE t.id = 14
");
$stmt->execute();
$client14 = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client14) {
    echo "❌ Cliente ID 14 não encontrado!\n";
} else {
    echo "Cliente: " . ($client14['nome_fantasia'] ?: '(sem nome)') . "\n";
    echo "Cobrança automática: " . ($client14['billing_auto_send'] ? '✅ ATIVA' : '❌ INATIVA') . "\n";
    echo "Canal: " . ($client14['billing_auto_channel'] ?? '❌ NÃO CONFIGURADO') . "\n";
    echo "Modo teste: " . ($client14['is_billing_test'] ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "Asaas Customer ID: " . ($client14['asaas_customer_id'] ?? '❌ NÃO VINCULADO') . "\n\n";

    // Faturas do cliente 14
    echo "📄 FATURAS (últimas 15):\n";
    $stmt = $pdo->prepare("
        SELECT 
            id,
            asaas_id,
            status,
            due_date,
            value,
            DATEDIFF(due_date, CURDATE()) as days_until_due,
            DATEDIFF(CURDATE(), due_date) as days_overdue
        FROM invoices
        WHERE tenant_id = 14
        ORDER BY due_date DESC
        LIMIT 15
    ");
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($invoices)) {
        echo "   ❌ Nenhuma fatura encontrada\n";
    } else {
        foreach ($invoices as $inv) {
            $statusIcon = [
                'pending' => '⏳',
                'overdue' => '🔴',
                'paid' => '✅',
                'canceled' => '❌'
            ][$inv['status']] ?? '❓';
            
            $daysInfo = $inv['days_until_due'] >= 0 
                ? "vence em {$inv['days_until_due']} dias" 
                : "vencida há {$inv['days_overdue']} dias";
            
            echo sprintf(
                "   %s #%d (%s) - %s - R$ %.2f - %s - %s\n",
                $statusIcon,
                $inv['id'],
                $inv['asaas_id'],
                $inv['due_date'],
                $inv['value'],
                strtoupper($inv['status']),
                $daysInfo
            );
        }
    }

    // Envios na fila para cliente 14
    echo "\n\n📬 ENVIOS NA FILA (ÚLTIMOS 30 DIAS):\n";
    $stmt = $pdo->prepare("
        SELECT 
            bdq.id,
            bdq.scheduled_at,
            bdq.status,
            bdq.attempts,
            bdq.error_message,
            bdr.name as rule_name,
            bdr.stage,
            bdq.invoice_ids,
            bdq.channel
        FROM billing_dispatch_queue bdq
        LEFT JOIN billing_dispatch_rules bdr ON bdr.id = bdq.dispatch_rule_id
        WHERE bdq.tenant_id = 14
        AND bdq.scheduled_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY bdq.scheduled_at DESC
    ");
    $stmt->execute();
    $queueItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($queueItems)) {
        echo "   ❌ NENHUM envio na fila para este cliente!\n";
        echo "   ⚠️  PROBLEMA: Faturas não estão sendo enfileiradas pelo planejador!\n";
    } else {
        foreach ($queueItems as $item) {
            $statusIcon = [
                'queued' => '⏳',
                'processing' => '🔄',
                'sent' => '✅',
                'failed' => '❌'
            ][$item['status']] ?? '❓';
            
            $invoiceIds = json_decode($item['invoice_ids'], true);
            $invoiceStr = is_array($invoiceIds) ? implode(', ', $invoiceIds) : $item['invoice_ids'];
            
            echo sprintf(
                "   %s [%s] %s - Faturas: [%s] - Canal: %s - Agendado: %s - Tentativas: %d\n",
                $statusIcon,
                $item['stage'] ?? 'N/A',
                $item['rule_name'] ?? 'Manual',
                $invoiceStr,
                $item['channel'],
                $item['scheduled_at'],
                $item['attempts']
            );
            
            if ($item['error_message']) {
                echo "      ⚠️  Erro: " . substr($item['error_message'], 0, 100) . "\n";
            }
        }
    }

    // Notificações enviadas
    echo "\n\n📨 NOTIFICAÇÕES ENVIADAS (ÚLTIMOS 30 DIAS):\n";
    $stmt = $pdo->prepare("
        SELECT 
            bn.id,
            bn.sent_at,
            bn.triggered_by,
            bn.status,
            bn.gateway_message_id,
            bn.channel,
            bdr.name as rule_name,
            i.asaas_id,
            i.due_date
        FROM billing_notifications bn
        JOIN invoices i ON i.id = bn.invoice_id
        LEFT JOIN billing_dispatch_rules bdr ON bdr.id = bn.dispatch_rule_id
        WHERE i.tenant_id = 14
        AND bn.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAYS)
        ORDER BY bn.sent_at DESC
    ");
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($notifications)) {
        echo "   ❌ NENHUMA notificação enviada nos últimos 30 dias!\n";
    } else {
        foreach ($notifications as $notif) {
            $statusIcon = [
                'sent' => '✅',
                'sent_uncertain' => '⚠️',
                'failed' => '❌',
                'pending' => '⏳'
            ][$notif['status']] ?? '❓';
            
            echo sprintf(
                "   %s Fatura %s (venc: %s) - %s - Canal: %s - Por: %s - %s\n",
                $statusIcon,
                $notif['asaas_id'],
                $notif['due_date'],
                $notif['rule_name'] ?? 'Manual',
                $notif['channel'],
                $notif['triggered_by'],
                $notif['sent_at'] ?? 'não enviado'
            );
        }
    }
}

// 5. LOGS DOS CRONS
echo "\n\n┌─────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 5. VERIFICAÇÃO DOS LOGS DOS CRONS                                          │\n";
echo "└─────────────────────────────────────────────────────────────────────────────┘\n";

$logFiles = [
    'billing_dispatch.log' => 'Planejador (billing_auto_dispatch.php)',
    'billing_worker.log' => 'Worker (billing_queue_worker.php)'
];

foreach ($logFiles as $logFile => $description) {
    $logPath = __DIR__ . '/../logs/' . $logFile;
    echo "\n📋 " . $description . ":\n";
    
    if (!file_exists($logPath)) {
        echo "   ❌ Arquivo não encontrado: $logPath\n";
        echo "   ⚠️  PROBLEMA: Cron pode não estar configurado ou nunca foi executado!\n";
    } else {
        $lastModified = filemtime($logPath);
        $hoursSinceModified = (time() - $lastModified) / 3600;
        $fileSize = filesize($logPath);
        
        echo sprintf(
            "   Última modificação: %s (%.1f horas atrás) - Tamanho: %s\n",
            date('Y-m-d H:i:s', $lastModified),
            $hoursSinceModified,
            $fileSize > 1024 ? round($fileSize/1024, 2) . ' KB' : $fileSize . ' bytes'
        );
        
        if ($hoursSinceModified > 24) {
            echo "   ⚠️  ALERTA: Log não atualizado há mais de 24 horas - Cron pode não estar rodando!\n";
        }
        
        // Últimas 5 linhas
        $lines = file($logPath);
        if ($lines && count($lines) > 0) {
            $lastLines = array_slice($lines, -5);
            echo "   Últimas linhas:\n";
            foreach ($lastLines as $line) {
                echo "   │ " . trim($line) . "\n";
            }
        }
    }
}

// 6. DIAGNÓSTICO FINAL
echo "\n\n┌─────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 6. DIAGNÓSTICO E PROBLEMAS IDENTIFICADOS                                   │\n";
echo "└─────────────────────────────────────────────────────────────────────────────┘\n\n";

$problems = [];
$warnings = [];

// Verifica clientes ativos
if (empty($activeClients)) {
    $problems[] = "Nenhum cliente com cobrança automática ativa";
}

// Verifica clientes sem canal configurado
foreach ($activeClients as $client) {
    if (!$client['billing_auto_channel']) {
        $warnings[] = sprintf(
            "Cliente #%d (%s) sem canal configurado",
            $client['id'],
            $client['nome_fantasia'] ?: '(sem nome)'
        );
    }
}

// Verifica fila vazia
if (empty($queueStats)) {
    $problems[] = "Nenhum item enfileirado nos últimos 7 dias - Planejador NÃO está rodando";
}

// Verifica logs dos crons
foreach ($logFiles as $logFile => $description) {
    $logPath = __DIR__ . '/../logs/' . $logFile;
    if (!file_exists($logPath)) {
        $problems[] = "Log do cron não encontrado ($description)";
    } else {
        $hoursSinceModified = (time() - filemtime($logPath)) / 3600;
        if ($hoursSinceModified > 24) {
            $problems[] = sprintf(
                "Log do cron desatualizado há %.1f horas ($description)",
                $hoursSinceModified
            );
        }
    }
}

// Verifica cliente 14
if ($client14 && $client14['billing_auto_send'] && empty($queueItems)) {
    $problems[] = "Cliente #14 tem cobrança ativa mas NENHUM item na fila - Planejador não está enfileirando";
}

// Verifica se cliente 14 tem faturas mas sem notificações
if ($client14 && !empty($invoices) && empty($notifications)) {
    $problems[] = "Cliente #14 tem " . count($invoices) . " faturas mas NENHUMA notificação enviada";
}

if (!empty($problems)) {
    echo "🔴 PROBLEMAS CRÍTICOS:\n\n";
    foreach ($problems as $i => $problem) {
        echo "   " . ($i + 1) . ". ❌ " . $problem . "\n";
    }
}

if (!empty($warnings)) {
    echo "\n⚠️  AVISOS:\n\n";
    foreach ($warnings as $i => $warning) {
        echo "   " . ($i + 1) . ". ⚠️  " . $warning . "\n";
    }
}

if (empty($problems) && empty($warnings)) {
    echo "✅ Nenhum problema crítico identificado!\n";
}

echo "\n╔═══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                          AUDITORIA CONCLUÍDA                                  ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════════╝\n";
