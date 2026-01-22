<?php

/**
 * Script para testar o método view() do HostingController
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Services\HostingProviderService;

Env::load();

// Inicia sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "=== Teste do método view() - HostingController ===\n\n";

try {
    $db = DB::getConnection();
    
    // Busca uma conta de hospedagem para testar
    $stmt = $db->query("SELECT id FROM hosting_accounts LIMIT 1");
    $testAccount = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$testAccount) {
        echo "✗ Nenhuma conta de hospedagem encontrada para testar\n";
        exit(1);
    }
    
    $id = $testAccount['id'];
    echo "Testando com hosting_account id = {$id}\n\n";
    
    // Simula o que o método view() faz
    echo "1. Buscando conta de hospedagem...\n";
    $stmt = $db->prepare("SELECT * FROM hosting_accounts WHERE id = ?");
    $stmt->execute([$id]);
    $hostingAccount = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$hostingAccount) {
        echo "✗ Conta não encontrada\n";
        exit(1);
    }
    echo "✓ Conta encontrada: {$hostingAccount['domain']}\n\n";
    
    echo "2. Buscando nome do provedor...\n";
    $providerMap = HostingProviderService::getSlugToNameMap();
    $providerSlug = $hostingAccount['current_provider'] ?? '';
    $providerName = $providerMap[$providerSlug] ?? $providerSlug;
    echo "✓ Provedor: {$providerName}\n\n";
    
    echo "3. Calculando status...\n";
    $calculateStatus = function($expirationDate, $type = '') {
        if (empty($expirationDate)) {
            $text = $type === 'domain' ? 'Domínio: Sem data' : ($type === 'hosting' ? 'Hospedagem: Sem data' : 'Sem data');
            return [
                'text' => $text,
                'style' => 'background: #e9ecef; color: #6c757d; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 600; display: inline-block;'
            ];
        }
        
        $expDate = strtotime($expirationDate);
        $today = strtotime('today');
        $daysLeft = floor(($expDate - $today) / (60 * 60 * 24));
        
        $daysInfo = '';
        if ($daysLeft > 0) {
            $daysInfo = $daysLeft == 1 ? ' (vence em 1 dia)' : ' (vence em ' . $daysLeft . ' dias)';
        } elseif ($daysLeft == 0) {
            $daysInfo = ' (vence hoje)';
        } else {
            $daysOverdue = abs($daysLeft);
            $daysInfo = $daysOverdue == 1 ? ' (vencido há 1 dia)' : ' (vencido há ' . $daysOverdue . ' dias)';
        }
        
        if ($daysLeft > 30) {
            $statusText = $type === 'domain' ? 'Domínio: Ativo' : ($type === 'hosting' ? 'Hospedagem: Ativa' : 'Ativo');
            $text = $statusText . $daysInfo;
            return [
                'text' => $text,
                'style' => 'background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 600; display: inline-block;'
            ];
        } elseif ($daysLeft >= 15 && $daysLeft <= 30) {
            $statusText = $type === 'domain' ? 'Domínio: Vencendo' : ($type === 'hosting' ? 'Hospedagem: Vencendo' : 'Vencendo');
            $text = $statusText . $daysInfo;
            return [
                'text' => $text,
                'style' => 'background: #fff3cd; color: #856404; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 600; display: inline-block;'
            ];
        } elseif ($daysLeft >= 0 && $daysLeft < 15) {
            $statusText = $type === 'domain' ? 'Domínio: Urgente' : ($type === 'hosting' ? 'Hospedagem: Urgente' : 'Urgente');
            $text = $statusText . $daysInfo;
            return [
                'text' => $text,
                'style' => 'background: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 600; display: inline-block;'
            ];
        } else {
            $statusText = $type === 'domain' ? 'Domínio: Vencido' : ($type === 'hosting' ? 'Hospedagem: Vencida' : 'Vencido');
            $text = $statusText . $daysInfo;
            return [
                'text' => $text,
                'style' => 'background: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 600; display: inline-block;'
            ];
        }
    };
    
    $hostingStatus = $calculateStatus($hostingAccount['hostinger_expiration_date'] ?? null, 'hosting');
    $domainStatus = $calculateStatus($hostingAccount['domain_expiration_date'] ?? null, 'domain');
    echo "✓ Status calculado\n\n";
    
    echo "4. Formatando valor...\n";
    $amount = $hostingAccount['amount'] ?? 0;
    $billingCycle = $hostingAccount['billing_cycle'] ?? 'mensal';
    $amountFormatted = $amount > 0 ? 'R$ ' . number_format($amount, 2, ',', '.') . ' / ' . $billingCycle : '-';
    echo "✓ Valor formatado: {$amountFormatted}\n\n";
    
    echo "5. Montando JSON...\n";
    $jsonData = [
        'id' => $hostingAccount['id'],
        'domain' => $hostingAccount['domain'],
        'provider' => $providerName,
        'plan_name' => $hostingAccount['plan_name'] ?? '-',
        'amount' => $amountFormatted,
        'hosting_status' => $hostingStatus,
        'domain_status' => $domainStatus,
        'hosting_panel_url' => $hostingAccount['hosting_panel_url'] ?? '',
        'hosting_panel_username' => $hostingAccount['hosting_panel_username'] ?? '',
        'hosting_panel_password' => $hostingAccount['hosting_panel_password'] ?? '',
        'site_admin_url' => $hostingAccount['site_admin_url'] ?? '',
        'site_admin_username' => $hostingAccount['site_admin_username'] ?? '',
        'site_admin_password' => $hostingAccount['site_admin_password'] ?? '',
        'hostinger_expiration_date' => $hostingAccount['hostinger_expiration_date'] ?? null,
        'domain_expiration_date' => $hostingAccount['domain_expiration_date'] ?? null,
    ];
    
    $json = json_encode($jsonData);
    if ($json === false) {
        echo "✗ Erro ao codificar JSON: " . json_last_error_msg() . "\n";
        exit(1);
    }
    echo "✓ JSON gerado com sucesso\n\n";
    
    echo "=== JSON Gerado ===\n";
    echo $json . "\n\n";
    
    echo "✓ Todos os testes passaram!\n";
    
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

