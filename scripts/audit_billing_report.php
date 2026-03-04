<?php
/**
 * RELATÓRIO DE AUDITORIA - Sistema de Cobrança Automática
 * Foco: Identificar por que os envios não estão acontecendo
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
echo "║              AUDITORIA - SISTEMA DE COBRANÇA AUTOMÁTICA                       ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════════╝\n\n";

// PROBLEMA PRINCIPAL IDENTIFICADO
echo "🔴 PROBLEMA PRINCIPAL IDENTIFICADO:\n\n";

$stmt = $pdo->query("
    SELECT COUNT(*) as total
    FROM billing_dispatch_queue
    WHERE scheduled_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$queueCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

if ($queueCount == 0) {
    echo "   ❌ NENHUM item na fila de envios nos últimos 7 dias!\n";
    echo "   ⚠️  O PLANEJADOR (billing_auto_dispatch.php) NÃO ESTÁ RODANDO!\n\n";
    echo "   Isso significa que:\n";
    echo "   - O cron não está configurado OU\n";
    echo "   - O cron está configurado mas não está executando OU\n";
    echo "   - O script está falhando silenciosamente\n\n";
}

// Verificar logs
echo "📋 VERIFICAÇÃO DOS LOGS:\n\n";

$logFiles = [
    'billing_dispatch.log' => 'Planejador',
    'billing_worker.log' => 'Worker'
];

$logProblems = [];

foreach ($logFiles as $logFile => $description) {
    $logPath = __DIR__ . '/../logs/' . $logFile;
    
    if (!file_exists($logPath)) {
        $logProblems[] = "$description: Arquivo não existe";
        echo "   ❌ $description: logs/$logFile NÃO EXISTE\n";
    } else {
        $lastModified = filemtime($logPath);
        $hoursSince = (time() - $lastModified) / 3600;
        
        if ($hoursSince > 24) {
            $logProblems[] = sprintf("$description: Não atualizado há %.1f horas", $hoursSince);
            echo sprintf("   ⚠️  $description: Última atualização há %.1f horas\n", $hoursSince);
        } else {
            echo sprintf("   ✅ $description: Atualizado há %.1f horas\n", $hoursSince);
        }
    }
}

// Clientes com cobrança ativa
echo "\n\n👥 CLIENTES COM COBRANÇA AUTOMÁTICA:\n\n";

$stmt = $pdo->query("
    SELECT 
        t.id,
        t.nome_fantasia,
        t.billing_auto_channel,
        t.is_billing_test,
        COUNT(DISTINCT i.id) as total_invoices,
        COUNT(DISTINCT CASE WHEN i.status IN ('pending', 'overdue') THEN i.id END) as actionable_invoices
    FROM tenants t
    LEFT JOIN invoices i ON i.tenant_id = t.id
    WHERE t.billing_auto_send = 1
    GROUP BY t.id
    HAVING total_invoices > 0
    ORDER BY t.id
");

$clientsWithInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($clientsWithInvoices)) {
    echo "   ⚠️  NENHUM cliente com cobrança ativa TEM FATURAS!\n";
    echo "   Isso explica por que não há envios.\n\n";
} else {
    foreach ($clientsWithInvoices as $client) {
        $icon = $client['is_billing_test'] ? '🧪' : '✅';
        echo sprintf(
            "   %s #%d: %s - Canal: %s - Faturas: %d (%d acionáveis)\n",
            $icon,
            $client['id'],
            $client['nome_fantasia'] ?: '(sem nome)',
            $client['billing_auto_channel'],
            $client['total_invoices'],
            $client['actionable_invoices']
        );
    }
}

// Cliente 14 específico
echo "\n\n🔍 ANÁLISE DO CLIENTE #14:\n\n";

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

if ($client14) {
    echo "   Cliente: " . ($client14['nome_fantasia'] ?: '(sem nome)') . "\n";
    echo "   Cobrança automática: " . ($client14['billing_auto_send'] ? '✅ ATIVA' : '❌ INATIVA') . "\n";
    echo "   Canal configurado: " . ($client14['billing_auto_channel'] ?: '❌ NÃO') . "\n";
    echo "   Asaas Customer ID: " . ($client14['asaas_customer_id'] ?: '❌ NÃO VINCULADO') . "\n\n";
    
    // Faturas
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM invoices WHERE tenant_id = 14");
    $stmt->execute();
    $invoiceCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "   📄 Faturas: $invoiceCount\n";
    
    if ($invoiceCount == 0) {
        echo "   ❌ PROBLEMA: Cliente não tem NENHUMA fatura sincronizada!\n";
        echo "   ⚠️  Sem faturas, o sistema não tem o que cobrar.\n\n";
        echo "   AÇÃO NECESSÁRIA:\n";
        echo "   1. Sincronizar faturas do Asaas (botão 'Sincronizar' na aba Financial)\n";
        echo "   2. Verificar se o cliente tem assinatura ativa no Asaas\n";
        echo "   3. Verificar se o asaas_customer_id está correto\n";
    } else {
        // Listar faturas
        $stmt = $pdo->prepare("
            SELECT 
                id,
                asaas_invoice_id,
                status,
                due_date,
                amount,
                DATEDIFF(due_date, CURDATE()) as days_until_due
            FROM invoices
            WHERE tenant_id = 14
            ORDER BY due_date DESC
            LIMIT 10
        ");
        $stmt->execute();
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\n   Últimas faturas:\n";
        foreach ($invoices as $inv) {
            $daysInfo = $inv['days_until_due'] >= 0 
                ? "vence em {$inv['days_until_due']} dias" 
                : "vencida há " . abs($inv['days_until_due']) . " dias";
            
            echo sprintf(
                "   - #%d (%s) - %s - R$ %.2f - %s - %s\n",
                $inv['id'],
                $inv['asaas_invoice_id'],
                $inv['due_date'],
                $inv['amount'],
                strtoupper($inv['status']),
                $daysInfo
            );
        }
    }
    
    // Envios na fila
    echo "\n   📬 Envios na fila (últimos 30 dias): ";
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM billing_dispatch_queue
        WHERE tenant_id = 14
        AND scheduled_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $queueCount14 = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "$queueCount14\n";
    
    if ($queueCount14 == 0 && $invoiceCount > 0) {
        echo "   ❌ PROBLEMA: Tem faturas mas NENHUM envio na fila!\n";
    }
    
    // Notificações enviadas
    echo "   📨 Notificações enviadas (últimos 30 dias): ";
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM billing_notifications bn
        JOIN invoices i ON i.id = bn.invoice_id
        WHERE i.tenant_id = 14
        AND bn.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $notifCount14 = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "$notifCount14\n";
}

// DIAGNÓSTICO FINAL
echo "\n\n╔═══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                           DIAGNÓSTICO FINAL                                   ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════════╝\n\n";

$criticalIssues = [];

if ($queueCount == 0) {
    $criticalIssues[] = "Fila de envios vazia - PLANEJADOR NÃO ESTÁ RODANDO";
}

if (!empty($logProblems)) {
    foreach ($logProblems as $problem) {
        $criticalIssues[] = "Log: $problem";
    }
}

if ($client14 && $client14['billing_auto_send'] && $invoiceCount == 0) {
    $criticalIssues[] = "Cliente #14 não tem faturas sincronizadas";
}

if (!empty($criticalIssues)) {
    echo "🔴 PROBLEMAS CRÍTICOS IDENTIFICADOS:\n\n";
    foreach ($criticalIssues as $i => $issue) {
        echo "   " . ($i + 1) . ". $issue\n";
    }
    
    echo "\n\n📝 AÇÕES CORRETIVAS NECESSÁRIAS:\n\n";
    
    if ($queueCount == 0) {
        echo "   1. CONFIGURAR CRON DO PLANEJADOR:\n";
        echo "      Adicionar no crontab do servidor:\n";
        echo "      0 8 * * 1-5 cd /path/to/pixelhub && php scripts/billing_auto_dispatch.php >> logs/billing_dispatch.log 2>&1\n\n";
        
        echo "   2. CONFIGURAR CRON DO WORKER:\n";
        echo "      Adicionar no crontab do servidor:\n";
        echo "      */5 8-11 * * 1-5 cd /path/to/pixelhub && php scripts/billing_queue_worker.php >> logs/billing_worker.log 2>&1\n\n";
    }
    
    if ($client14 && $invoiceCount == 0) {
        echo "   3. SINCRONIZAR FATURAS DO CLIENTE #14:\n";
        echo "      - Acessar: https://hub.pixel12digital.com.br/tenants/view?id=14&tab=financial\n";
        echo "      - Clicar em 'Sincronizar com Asaas'\n";
        echo "      - Verificar se faturas aparecem após sincronização\n\n";
    }
    
} else {
    echo "✅ Sistema funcionando corretamente!\n";
}

echo "\n" . str_repeat("═", 80) . "\n";
echo "Auditoria concluída em " . date('Y-m-d H:i:s') . "\n";
