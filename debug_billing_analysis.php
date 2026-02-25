<?php
/**
 * Script de diagnóstico: Análise de cobrança
 * Rode no servidor: php debug_billing_analysis.php
 */

require 'vendor/autoload.php';
require 'src/Core/DB.php';
require 'src/Core/Env.php';
require 'src/Services/AISuggestReplyService.php';

\PixelHub\Core\Env::load(__DIR__);

echo "=== DIAGNÓSTICO: Análise de Cobrança ===\n\n";

$tenantId = 71; // Beleza ZonaSul

echo "Testando análise para tenant_id: {$tenantId}\n\n";

try {
    $result = \PixelHub\Services\AISuggestReplyService::analyzeBillingContext($tenantId);
    
    echo "✅ Análise executada com sucesso!\n\n";
    echo "Objetivo retornado: " . $result['objective'] . "\n";
    echo "Contexto gerado:\n";
    echo str_repeat('-', 80) . "\n";
    echo $result['context'] . "\n";
    echo str_repeat('-', 80) . "\n\n";
    
    echo "Dados das faturas:\n";
    echo "Total em aberto: R$ " . number_format($result['invoices_data']['total_amount'], 2, ',', '.') . "\n";
    echo "Faturas vencidas: " . $result['invoices_data']['overdue_count'] . "\n";
    echo "Faturas a vencer: " . $result['invoices_data']['pending_count'] . "\n";
    
    if (!empty($result['invoices_data']['services_summary'])) {
        echo "\nResumo de serviços:\n";
        foreach ($result['invoices_data']['services_summary'] as $service => $data) {
            echo "  - {$data['count']}x {$service}: R$ " . number_format($data['amount'], 2, ',', '.') . "\n";
        }
    }
    
    echo "\nTotal de faturas: " . count($result['invoices_data']['invoices']) . "\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO na análise:\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
