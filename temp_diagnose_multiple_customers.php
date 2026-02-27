<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Services\AsaasClient;

$db = DB::getConnection();

echo "=== DIAGNÓSTICO - MÚLTIPLOS CUSTOMERS ASAAS ===\n\n";

$cpfCnpj = '42262755000104';
echo "CNPJ: {$cpfCnpj}\n\n";

// 1. Busca tenant no banco
$stmt = $db->prepare("SELECT id, name, cpf_cnpj, asaas_customer_id FROM tenants WHERE cpf_cnpj = ? OR document = ?");
$stmt->execute([$cpfCnpj, $cpfCnpj]);
$tenant = $stmt->fetch();

if ($tenant) {
    echo "TENANT ENCONTRADO:\n";
    echo "  ID: {$tenant['id']}\n";
    echo "  Nome: {$tenant['name']}\n";
    echo "  asaas_customer_id atual: " . ($tenant['asaas_customer_id'] ?? 'NULL') . "\n\n";
} else {
    echo "Tenant não encontrado no banco local.\n\n";
}

// 2. Busca TODOS os customers no Asaas com este CNPJ
echo "=== BUSCANDO CUSTOMERS NO ASAAS ===\n";
try {
    $allCustomers = AsaasClient::findCustomersByCpfCnpj($cpfCnpj);
    
    echo "Total de customers encontrados: " . count($allCustomers) . "\n\n";
    
    foreach ($allCustomers as $idx => $customer) {
        echo "CUSTOMER #" . ($idx + 1) . ":\n";
        echo "  ID: {$customer['id']}\n";
        echo "  Nome: " . ($customer['name'] ?? 'N/A') . "\n";
        echo "  Email: " . ($customer['email'] ?? 'N/A') . "\n";
        echo "  CPF/CNPJ: " . ($customer['cpfCnpj'] ?? 'N/A') . "\n";
        echo "  Deletado: " . (isset($customer['deleted']) && $customer['deleted'] ? 'SIM' : 'NÃO') . "\n";
        
        // Busca payments deste customer
        try {
            $queryParams = http_build_query([
                'customer' => $customer['id'],
                'limit' => 100,
                'order' => 'desc',
                'sort' => 'dueDate',
            ]);
            
            $response = AsaasClient::request('GET', '/payments?' . $queryParams, null);
            $payments = $response['data'] ?? [];
            
            echo "  Total de payments: " . count($payments) . "\n";
            
            if (!empty($payments)) {
                echo "  Payments:\n";
                foreach ($payments as $payment) {
                    $status = $payment['status'] ?? 'N/A';
                    $value = $payment['value'] ?? 0;
                    $dueDate = $payment['dueDate'] ?? 'N/A';
                    $description = $payment['description'] ?? 'N/A';
                    
                    echo "    - ID: {$payment['id']} | Venc: {$dueDate} | R$ {$value} | Status: {$status}\n";
                    echo "      Descrição: {$description}\n";
                    
                    // Verifica se existe no banco local
                    if ($tenant) {
                        $stmt = $db->prepare("SELECT id, tenant_id, status FROM billing_invoices WHERE asaas_payment_id = ?");
                        $stmt->execute([$payment['id']]);
                        $localInvoice = $stmt->fetch();
                        
                        if ($localInvoice) {
                            echo "      ✓ Existe no banco (ID: {$localInvoice['id']}, Tenant: {$localInvoice['tenant_id']}, Status: {$localInvoice['status']})\n";
                        } else {
                            echo "      ✗ NÃO existe no banco local\n";
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            echo "  Erro ao buscar payments: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    // 3. Verifica qual customer ID está configurado como principal
    if ($tenant && !empty($tenant['asaas_customer_id'])) {
        $isPrincipal = false;
        foreach ($allCustomers as $customer) {
            if ($customer['id'] === $tenant['asaas_customer_id']) {
                $isPrincipal = true;
                echo "✓ O asaas_customer_id do tenant ({$tenant['asaas_customer_id']}) está na lista de customers encontrados.\n";
                break;
            }
        }
        
        if (!$isPrincipal) {
            echo "⚠️ O asaas_customer_id do tenant ({$tenant['asaas_customer_id']}) NÃO está na lista de customers encontrados!\n";
        }
    }
    
    // 4. Recomendação
    echo "\n=== RECOMENDAÇÃO ===\n";
    if (count($allCustomers) > 1) {
        echo "Este CNPJ possui " . count($allCustomers) . " customers no Asaas.\n";
        echo "A sincronização deve importar faturas de TODOS eles.\n";
        echo "Customer ID desejado como principal: 130867367\n\n";
        
        $found130867367 = false;
        foreach ($allCustomers as $customer) {
            if ($customer['id'] === 'cus_000006026367') {
                $found130867367 = true;
                echo "✓ Customer cus_000006026367 encontrado na lista!\n";
            }
        }
        
        if (!$found130867367) {
            echo "⚠️ Customer ID 130867367 (cus_000006026367) não encontrado. Verificar ID correto.\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERRO ao buscar customers: " . $e->getMessage() . "\n";
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
