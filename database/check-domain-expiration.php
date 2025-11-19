<?php

/**
 * Script CLI para verificar vencimentos de domínio e enviar e-mails de aviso
 * 
 * Uso: php database/check-domain-expiration.php
 * 
 * Este script deve ser executado diariamente via cron:
 * 0 9 * * * cd /caminho/do/projeto && php database/check-domain-expiration.php
 */

// Carrega autoload do Composer se existir, senão carrega manualmente
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Autoload manual simples
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
use PixelHub\Core\EmailHelper;

// Carrega .env
Env::load();

// Inicia sessão se necessário (pode ser necessário para alguns helpers)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "=== Verificação de Vencimento de Domínios - Pixel Hub ===\n\n";

try {
    $db = DB::getConnection();
    
    // Busca todas as hospedagens com domain_expiration_date não nulo
    $stmt = $db->query("
        SELECT ha.*, t.name as tenant_name, t.email as tenant_email
        FROM hosting_accounts ha
        INNER JOIN tenants t ON ha.tenant_id = t.id
        WHERE ha.domain_expiration_date IS NOT NULL
        ORDER BY ha.domain_expiration_date ASC
    ");
    $hostingAccounts = $stmt->fetchAll();
    
    if (empty($hostingAccounts)) {
        echo "Nenhuma hospedagem com data de vencimento de domínio cadastrada.\n";
        exit(0);
    }
    
    echo "Encontradas " . count($hostingAccounts) . " hospedagens com data de vencimento.\n\n";
    
    $today = strtotime('today');
    $emailsSent = 0;
    $emailsFailed = 0;
    $skipped = 0;
    
    foreach ($hostingAccounts as $hosting) {
        $expirationDate = $hosting['domain_expiration_date'];
        $expDate = strtotime($expirationDate);
        $daysLeft = floor(($expDate - $today) / (60 * 60 * 24));
        
        $domain = $hosting['domain'] ?? 'domínio não informado';
        $tenantName = $hosting['tenant_name'] ?? 'Cliente';
        
        echo "→ Domínio: {$domain} (Cliente: {$tenantName})\n";
        echo "  Data de vencimento: {$expirationDate}\n";
        echo "  Dias restantes: {$daysLeft}\n";
        
        // Verifica se precisa enviar aviso
        $shouldNotify = false;
        $notificationFlag = null;
        
        if ($daysLeft == 30 && empty($hosting['domain_notified_30'])) {
            $shouldNotify = true;
            $notificationFlag = 'domain_notified_30';
            echo "  → Aviso de 30 dias necessário\n";
        } elseif ($daysLeft == 15 && empty($hosting['domain_notified_15'])) {
            $shouldNotify = true;
            $notificationFlag = 'domain_notified_15';
            echo "  → Aviso de 15 dias necessário\n";
        } elseif ($daysLeft == 7 && empty($hosting['domain_notified_7'])) {
            $shouldNotify = true;
            $notificationFlag = 'domain_notified_7';
            echo "  → Aviso de 7 dias necessário\n";
        } else {
            echo "  → Nenhum aviso necessário neste momento\n";
            $skipped++;
        }
        
        if ($shouldNotify && $notificationFlag) {
            // Prepara dados do tenant
            $tenant = [
                'name' => $hosting['tenant_name'],
                'email' => $hosting['tenant_email'],
            ];
            
            // Prepara dados da hospedagem
            $hostingData = [
                'domain' => $hosting['domain'],
                'domain_expiration_date' => $hosting['domain_expiration_date'],
            ];
            
            // Envia e-mail
            $emailSent = EmailHelper::sendDomainExpirationWarning($tenant, $hostingData, $daysLeft);
            
            if ($emailSent) {
                // Atualiza flag de notificação (usa whitelist para segurança)
                $allowedFlags = ['domain_notified_30', 'domain_notified_15', 'domain_notified_7'];
                if (in_array($notificationFlag, $allowedFlags)) {
                    $stmt = $db->prepare("
                        UPDATE hosting_accounts 
                        SET `{$notificationFlag}` = 1, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$hosting['id']]);
                }
                
                echo "  ✓ E-mail enviado com sucesso\n";
                $emailsSent++;
            } else {
                echo "  ✗ Falha ao enviar e-mail\n";
                $emailsFailed++;
            }
        }
        
        echo "\n";
    }
    
    echo "=== Resumo ===\n";
    echo "E-mails enviados: {$emailsSent}\n";
    echo "E-mails com falha: {$emailsFailed}\n";
    echo "Registros ignorados: {$skipped}\n";
    echo "Total processado: " . count($hostingAccounts) . "\n";
    
} catch (\Exception $e) {
    echo "\n✗ Erro fatal: " . $e->getMessage() . "\n";
    error_log("Erro fatal no check-domain-expiration.php: " . $e->getMessage());
    exit(1);
}

echo "\n✓ Processo concluído!\n";

