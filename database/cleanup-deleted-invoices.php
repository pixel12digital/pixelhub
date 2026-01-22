<?php
/**
 * Script de limpeza: Corrige cobranças "fantasma" que foram deletadas/canceladas no Asaas
 * 
 * Este script verifica cada invoice local marcada como overdue ou pending,
 * consulta o Asaas pelo payment_id, e se a cobrança estiver deleted/CANCELED,
 * atualiza localmente como canceled e is_deleted = 1.
 * 
 * Uso:
 *   php database/cleanup-deleted-invoices.php
 * 
 * IMPORTANTE: Execute este script apenas uma vez após implementar as mudanças.
 * Ele corrige dados pré-existentes que podem estar incorretos.
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
use PixelHub\Services\AsaasClient;
use PixelHub\Services\AsaasConfig;
use PixelHub\Services\AsaasBillingService;

// Carrega .env
Env::load();

// Valida configuração do Asaas
try {
    AsaasConfig::getConfig();
} catch (\RuntimeException $e) {
    echo "ERRO: Configuração do Asaas não encontrada.\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    exit(1);
}

$db = DB::getConnection();

echo "=== Limpeza de Cobranças Deletadas/Canceladas ===\n\n";

// Busca todas as invoices que estão como pending ou overdue
$stmt = $db->query("
    SELECT id, asaas_payment_id, tenant_id, status, due_date, amount
    FROM billing_invoices
    WHERE status IN ('pending', 'overdue')
    AND (is_deleted IS NULL OR is_deleted = 0)
    ORDER BY tenant_id, due_date DESC
");
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($invoices)) {
    echo "Nenhuma cobrança pendente/vencida encontrada para verificar.\n";
    exit(0);
}

echo "Encontradas " . count($invoices) . " cobranças para verificar.\n\n";

$updated = 0;
$notFound = 0;
$errors = 0;
$skipped = 0;

foreach ($invoices as $invoice) {
    $invoiceId = $invoice['id'];
    $paymentId = $invoice['asaas_payment_id'];
    $tenantId = $invoice['tenant_id'];
    
    echo "Verificando invoice #{$invoiceId} (Asaas payment: {$paymentId})... ";
    
    try {
        // Busca payment no Asaas
        $response = AsaasClient::request('GET', "/payments/{$paymentId}", null);
        $payment = $response;
        
        // Verifica se foi deletada ou cancelada
        $asaasDeleted = !empty($payment['deleted']) || ($payment['deleted'] ?? false) === true;
        $asaasStatus = strtoupper($payment['status'] ?? 'PENDING');
        
        if ($asaasDeleted || in_array($asaasStatus, ['CANCELED', 'REFUNDED'])) {
            // Atualiza como cancelada e deletada
            $stmt = $db->prepare("
                UPDATE billing_invoices
                SET status = 'canceled',
                    is_deleted = 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$invoiceId]);
            
            // Atualiza status financeiro do tenant
            AsaasBillingService::refreshTenantBillingStatus($tenantId);
            
            $updated++;
            echo "✓ ATUALIZADA (deleted={$asaasDeleted}, status={$asaasStatus})\n";
        } else {
            // Cobrança ainda está ativa no Asaas
            $skipped++;
            echo "OK (status={$asaasStatus})\n";
        }
        
    } catch (\RuntimeException $e) {
        // Se retornar 404, a cobrança não existe mais no Asaas
        if (strpos($e->getMessage(), '404') !== false) {
            // Marca como deletada
            $stmt = $db->prepare("
                UPDATE billing_invoices
                SET status = 'canceled',
                    is_deleted = 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$invoiceId]);
            
            // Atualiza status financeiro do tenant
            AsaasBillingService::refreshTenantBillingStatus($tenantId);
            
            $notFound++;
            echo "✓ ATUALIZADA (não encontrada no Asaas - 404)\n";
        } else {
            // Outro erro (API, rede, etc.)
            $errors++;
            echo "✗ ERRO: " . $e->getMessage() . "\n";
        }
    }
    
    // Pequena pausa para não sobrecarregar a API
    usleep(200000); // 200ms
}

echo "\n=== Resumo ===\n";
echo "Total verificadas: " . count($invoices) . "\n";
echo "Atualizadas (deletadas/canceladas): {$updated}\n";
echo "Atualizadas (não encontradas - 404): {$notFound}\n";
echo "Mantidas (ainda ativas): {$skipped}\n";
echo "Erros: {$errors}\n";
echo "\nLimpeza concluída!\n";

