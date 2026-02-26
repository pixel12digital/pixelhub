<?php
/**
 * SIMULAÇÃO DE COBRANÇA AUTOMÁTICA
 * 
 * Este script simula o que o sistema faria SEM enviar mensagens reais.
 * Mostra exatamente quais faturas seriam processadas e quais mensagens seriam enviadas.
 */

require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';
require_once __DIR__ . '/src/Services/BillingSenderService.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Services\BillingSenderService;

Env::load();
$db = DB::getConnection();

$tenantId = $argv[1] ?? 32;

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║          SIMULAÇÃO DE COBRANÇA AUTOMÁTICA                    ║\n";
echo "║          (NENHUMA MENSAGEM SERÁ ENVIADA)                     ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// ─── 1. Busca dados do tenant ───────────────────────────────────
echo "═══ TENANT ID {$tenantId} ═══\n\n";
$tenant = $db->prepare("
    SELECT id, name, billing_auto_send, billing_auto_channel, is_billing_test, 
           asaas_customer_id, phone, email
    FROM tenants
    WHERE id = ?
")->execute([$tenantId]) ? $db->query("SELECT * FROM tenants WHERE id = {$tenantId}")->fetch(PDO::FETCH_ASSOC) : null;

if (!$tenant) {
    echo "❌ Tenant não encontrado!\n";
    exit(1);
}

echo "Nome: {$tenant['name']}\n";
echo "Auto Send: " . ($tenant['billing_auto_send'] ? '✅ SIM' : '❌ NÃO') . "\n";
echo "Canal: {$tenant['billing_auto_channel']}\n";
echo "Modo Teste: " . ($tenant['is_billing_test'] ? '✅ SIM' : '❌ NÃO') . "\n";
echo "Telefone: {$tenant['phone']}\n";
echo "Email: {$tenant['email']}\n";
echo "\n";

// ─── 2. Verifica se seria processado ────────────────────────────
if (!$tenant['billing_auto_send']) {
    echo "⚠️  BLOQUEIO: Automático está DESATIVADO\n";
    echo "   → Este tenant NÃO seria processado\n\n";
    exit(0);
}

if (!$tenant['is_billing_test']) {
    echo "⚠️  BLOQUEIO: Tenant NÃO está marcado como TESTE\n";
    echo "   → Durante fase de testes, apenas tenants com is_billing_test=1 são processados\n";
    echo "   → Este tenant seria PULADO na varredura\n\n";
    echo "💡 Para incluir na simulação, marque como teste:\n";
    echo "   UPDATE tenants SET is_billing_test = 1 WHERE id = {$tenantId};\n\n";
    
    // Continua a simulação mesmo assim para mostrar o que aconteceria
    echo "═══ CONTINUANDO SIMULAÇÃO (ignorando bloqueio de teste) ═══\n\n";
}

// ─── 3. Busca regras ativas ─────────────────────────────────────
echo "═══ REGRAS DE DISPARO ATIVAS ═══\n\n";
$rules = $db->query("
    SELECT * FROM billing_dispatch_rules
    WHERE is_enabled = 1
    ORDER BY days_offset ASC
")->fetchAll(PDO::FETCH_ASSOC);

echo "Total de regras: " . count($rules) . "\n\n";

// ─── 4. Para cada regra, busca faturas elegíveis ────────────────
$totalEligible = 0;
$simulatedMessages = [];

foreach ($rules as $rule) {
    $ruleId = (int) $rule['id'];
    $ruleName = $rule['name'];
    $daysOffset = (int) $rule['days_offset'];
    $channels = json_decode($rule['channels'], true) ?: ['whatsapp'];
    
    echo "─── Regra #{$ruleId}: {$ruleName} (offset={$daysOffset}) ───\n";
    
    // Monta query conforme offset
    if ($daysOffset < 0) {
        $absDays = abs($daysOffset);
        $stmt = $db->prepare("
            SELECT bi.*, t.name AS tenant_name, t.phone, t.email
            FROM billing_invoices bi
            JOIN tenants t ON t.id = bi.tenant_id
            WHERE bi.status = 'pending'
              AND (bi.is_deleted IS NULL OR bi.is_deleted = 0)
              AND DATEDIFF(bi.due_date, CURDATE()) = ?
              AND t.id = ?
            ORDER BY bi.due_date
        ");
        $stmt->execute([$absDays, $tenantId]);
    } elseif ($daysOffset === 0) {
        $stmt = $db->prepare("
            SELECT bi.*, t.name AS tenant_name, t.phone, t.email
            FROM billing_invoices bi
            JOIN tenants t ON t.id = bi.tenant_id
            WHERE bi.status IN ('pending', 'overdue')
              AND (bi.is_deleted IS NULL OR bi.is_deleted = 0)
              AND bi.due_date = CURDATE()
              AND t.id = ?
            ORDER BY bi.due_date
        ");
        $stmt->execute([$tenantId]);
    } else {
        $stmt = $db->prepare("
            SELECT bi.*, t.name AS tenant_name, t.phone, t.email
            FROM billing_invoices bi
            JOIN tenants t ON t.id = bi.tenant_id
            WHERE bi.status = 'overdue'
              AND (bi.is_deleted IS NULL OR bi.is_deleted = 0)
              AND DATEDIFF(CURDATE(), bi.due_date) = ?
              AND t.id = ?
            ORDER BY bi.due_date
        ");
        $stmt->execute([$daysOffset, $tenantId]);
    }
    
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($invoices)) {
        echo "   Nenhuma fatura elegível\n\n";
        continue;
    }
    
    echo "   ✅ " . count($invoices) . " fatura(s) encontrada(s)\n";
    
    foreach ($invoices as $inv) {
        $invId = (int) $inv['id'];
        
        // Verifica anti-spam
        $recentlySent = BillingSenderService::wasRecentlySent($db, $invId, $ruleId, 20, $tenant['billing_auto_channel']);
        if ($recentlySent) {
            echo "   ⏭️  Fatura #{$invId}: PULADA (enviada recentemente)\n";
            continue;
        }
        
        $maxRepeats = (int) $rule['max_repeats'];
        if ($maxRepeats > 0) {
            $sentCount = BillingSenderService::countSentForRule($db, $invId, $ruleId);
            if ($sentCount >= $maxRepeats) {
                echo "   ⏭️  Fatura #{$invId}: PULADA (max repeats atingido: {$sentCount}/{$maxRepeats})\n";
                continue;
            }
        }
        
        // Verifica canal
        $tenantChannel = $tenant['billing_auto_channel'];
        $channelMatch = false;
        foreach ($channels as $rc) {
            if ($tenantChannel === 'both' || $tenantChannel === $rc) {
                $channelMatch = true;
                break;
            }
        }
        
        if (!$channelMatch) {
            echo "   ⏭️  Fatura #{$invId}: PULADA (canal incompatível)\n";
            continue;
        }
        
        // Esta fatura SERIA enviada!
        $totalEligible++;
        echo "   📤 Fatura #{$invId}: SERIA ENVIADA\n";
        echo "      Vencimento: {$inv['due_date']}\n";
        echo "      Valor: R$ {$inv['amount']}\n";
        echo "      Status: {$inv['status']}\n";
        
        // Simula mensagem
        $channelsToSend = [];
        if ($tenantChannel === 'both') {
            $channelsToSend = ['whatsapp', 'email'];
        } else {
            $channelsToSend = [$tenantChannel];
        }
        
        foreach ($channelsToSend as $ch) {
            $simulatedMessages[] = [
                'invoice_id' => $invId,
                'rule_id' => $ruleId,
                'rule_name' => $ruleName,
                'channel' => $ch,
                'due_date' => $inv['due_date'],
                'amount' => $inv['amount'],
                'phone' => $inv['phone'],
                'email' => $inv['email'],
            ];
        }
    }
    
    echo "\n";
}

// ─── 5. Resumo da simulação ─────────────────────────────────────
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                    RESUMO DA SIMULAÇÃO                       ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "Total de faturas elegíveis: {$totalEligible}\n";
echo "Total de mensagens que SERIAM enviadas: " . count($simulatedMessages) . "\n\n";

if (empty($simulatedMessages)) {
    echo "✅ Nenhuma mensagem seria enviada neste momento.\n\n";
    echo "Motivos possíveis:\n";
    echo "- Nenhuma fatura vencida/a vencer\n";
    echo "- Faturas já foram notificadas recentemente\n";
    echo "- Limite de repetições atingido\n";
    echo "- Canal incompatível\n";
} else {
    echo "═══ MENSAGENS QUE SERIAM ENVIADAS ═══\n\n";
    
    foreach ($simulatedMessages as $i => $msg) {
        echo "Mensagem #" . ($i + 1) . ":\n";
        echo "  Fatura: #{$msg['invoice_id']}\n";
        echo "  Regra: {$msg['rule_name']}\n";
        echo "  Canal: " . strtoupper($msg['channel']) . "\n";
        echo "  Vencimento: {$msg['due_date']}\n";
        echo "  Valor: R$ {$msg['amount']}\n";
        
        if ($msg['channel'] === 'whatsapp') {
            echo "  Destino: {$msg['phone']}\n";
            echo "  📱 Mensagem WhatsApp:\n";
            echo "     \"Olá! Identificamos uma fatura em aberto...\"\n";
        } else {
            echo "  Destino: {$msg['email']}\n";
            echo "  📧 E-mail:\n";
            echo "     Assunto: Fatura em aberto - {$tenant['name']}\n";
        }
        
        echo "\n";
    }
    
    echo "⚠️  IMPORTANTE: Esta é apenas uma SIMULAÇÃO!\n";
    echo "   Nenhuma mensagem foi realmente enviada.\n";
}

echo "\n═══ STATUS DO SISTEMA ═══\n\n";

if ($tenant['billing_auto_send'] && $tenant['is_billing_test'] && $totalEligible > 0) {
    echo "✅ Sistema OK: Tenant seria processado e mensagens seriam enviadas\n";
} elseif (!$tenant['billing_auto_send']) {
    echo "❌ Automático DESATIVADO: Ative para processar\n";
} elseif (!$tenant['is_billing_test']) {
    echo "⚠️  Modo TESTE: Marque is_billing_test=1 para processar durante testes\n";
} elseif ($totalEligible === 0) {
    echo "ℹ️  Nenhuma fatura elegível no momento\n";
}

echo "\n";
