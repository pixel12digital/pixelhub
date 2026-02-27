<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Services\AsaasClient;

$db = DB::getConnection();

echo "=== DIAGNÓSTICO DE SINCRONIZAÇÃO - TENANT 6 ===\n\n";

// 1. Busca dados do tenant
$stmt = $db->prepare("SELECT id, name, cpf_cnpj, asaas_customer_id FROM tenants WHERE id = 6");
$stmt->execute();
$tenant = $stmt->fetch();

if (!$tenant) {
    die("Tenant 6 não encontrado!\n");
}

echo "TENANT:\n";
echo "  ID: {$tenant['id']}\n";
echo "  Nome: {$tenant['name']}\n";
echo "  CPF/CNPJ: {$tenant['cpf_cnpj']}\n";
echo "  Asaas Customer ID: {$tenant['asaas_customer_id']}\n\n";

// 2. Busca faturas no banco local
$stmt = $db->prepare("
    SELECT id, asaas_payment_id, due_date, amount, status, is_deleted, 
           paid_at, description, billing_type, created_at, updated_at
    FROM billing_invoices 
    WHERE tenant_id = 6
    ORDER BY due_date DESC
");
$stmt->execute();
$localInvoices = $stmt->fetchAll();

echo "FATURAS NO BANCO LOCAL (billing_invoices):\n";
echo "Total: " . count($localInvoices) . "\n\n";

foreach ($localInvoices as $inv) {
    echo "  ID: {$inv['id']} | Payment ID: {$inv['asaas_payment_id']}\n";
    echo "    Vencimento: {$inv['due_date']} | Valor: R$ {$inv['amount']}\n";
    echo "    Status: {$inv['status']} | Deletada: {$inv['is_deleted']}\n";
    echo "    Tipo: {$inv['billing_type']} | Descrição: {$inv['description']}\n";
    echo "    Pago em: " . ($inv['paid_at'] ?? 'NULL') . "\n";
    echo "    Criado: {$inv['created_at']} | Atualizado: {$inv['updated_at']}\n\n";
}

// 3. Busca faturas no Asaas
if (!empty($tenant['asaas_customer_id'])) {
    echo "\n=== FATURAS NO ASAAS ===\n";
    
    try {
        $queryParams = http_build_query([
            'customer' => $tenant['asaas_customer_id'],
            'limit' => 100,
            'order' => 'desc',
            'sort' => 'dueDate',
        ]);
        
        $response = AsaasClient::request('GET', '/payments?' . $queryParams, null);
        $asaasPayments = $response['data'] ?? [];
        
        echo "Total de payments no Asaas: " . count($asaasPayments) . "\n\n";
        
        foreach ($asaasPayments as $payment) {
            echo "  Payment ID: {$payment['id']}\n";
            echo "    Vencimento: {$payment['dueDate']} | Valor: R$ {$payment['value']}\n";
            echo "    Status: {$payment['status']}\n";
            echo "    Tipo: {$payment['billingType']} | Descrição: " . ($payment['description'] ?? 'N/A') . "\n";
            echo "    Deletado: " . (isset($payment['deleted']) && $payment['deleted'] ? 'SIM' : 'NÃO') . "\n";
            echo "    Confirmed Date: " . ($payment['confirmedDate'] ?? 'NULL') . "\n";
            echo "    Payment Date: " . ($payment['paymentDate'] ?? 'NULL') . "\n";
            echo "    Invoice URL: " . ($payment['invoiceUrl'] ?? 'NULL') . "\n";
            
            // Verifica se existe no banco local
            $stmt = $db->prepare("SELECT id, status, amount, is_deleted FROM billing_invoices WHERE asaas_payment_id = ?");
            $stmt->execute([$payment['id']]);
            $localMatch = $stmt->fetch();
            
            if ($localMatch) {
                echo "    ✓ EXISTE NO BANCO LOCAL (ID: {$localMatch['id']})\n";
                echo "      Status local: {$localMatch['status']} | Valor local: R$ {$localMatch['amount']} | Deletada: {$localMatch['is_deleted']}\n";
                
                // Compara valores
                $statusMapping = [
                    'PENDING' => 'pending',
                    'CONFIRMED' => 'paid',
                    'RECEIVED' => 'paid',
                    'RECEIVED_IN_CASH' => 'paid',
                    'OVERDUE' => 'overdue',
                    'CANCELED' => 'canceled',
                    'REFUNDED' => 'refunded',
                ];
                $expectedStatus = $statusMapping[strtoupper($payment['status'])] ?? 'pending';
                
                if ($localMatch['status'] !== $expectedStatus) {
                    echo "      ⚠️ DIVERGÊNCIA DE STATUS: Asaas={$payment['status']}, Local={$localMatch['status']}, Esperado={$expectedStatus}\n";
                }
                
                if ((float)$localMatch['amount'] !== (float)$payment['value']) {
                    echo "      ⚠️ DIVERGÊNCIA DE VALOR: Asaas=R$ {$payment['value']}, Local=R$ {$localMatch['amount']}\n";
                }
            } else {
                echo "    ✗ NÃO EXISTE NO BANCO LOCAL\n";
            }
            
            echo "\n";
        }
        
        // 4. Verifica faturas locais que não existem no Asaas
        echo "\n=== FATURAS LOCAIS SEM CORRESPONDÊNCIA NO ASAAS ===\n";
        $asaasPaymentIds = array_column($asaasPayments, 'id');
        $orphanedInvoices = array_filter($localInvoices, function($inv) use ($asaasPaymentIds) {
            return !in_array($inv['asaas_payment_id'], $asaasPaymentIds);
        });
        
        if (empty($orphanedInvoices)) {
            echo "Nenhuma fatura órfã encontrada.\n";
        } else {
            echo "Total: " . count($orphanedInvoices) . "\n\n";
            foreach ($orphanedInvoices as $inv) {
                echo "  ID Local: {$inv['id']} | Payment ID: {$inv['asaas_payment_id']}\n";
                echo "    Vencimento: {$inv['due_date']} | Valor: R$ {$inv['amount']} | Status: {$inv['status']}\n\n";
            }
        }
        
    } catch (Exception $e) {
        echo "ERRO ao buscar payments no Asaas: " . $e->getMessage() . "\n";
    }
} else {
    echo "Tenant não possui asaas_customer_id configurado.\n";
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
