<?php
/**
 * Script de limpeza de conversas duplicadas
 * 
 * Uso: php scripts/cleanup_duplicate_conversations.php [--dry-run] [--fix]
 * 
 * Opções:
 * --dry-run  : Apenas analisa, não faz alterações (padrão)
 * --fix     : Aplica as correções encontradas
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Services/ConversationDeduplicationService.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Services\ConversationDeduplicationService;

Env::load();
$db = DB::getConnection();

$dryRun = !in_array('--fix', $argv);
$fix = in_array('--fix', $argv);

echo "=== LIMPEZA DE CONVERSAS DUPLICADAS ===\n";
echo "Modo: " . ($dryRun ? 'ANÁLISE APENAS' : 'APLICAR CORREÇÕES') . "\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n\n";

$dedupService = new ConversationDeduplicationService();

// 1. Busca duplicações dos últimos 7 dias
echo "1. Buscando duplicações (últimos 7 dias)...\n";
$duplicates = $dedupService->checkSystemDuplicates(7);

if (empty($duplicates)) {
    echo "✅ Nenhuma duplicação encontrada\n";
    exit(0);
}

echo "Encontradas " . count($duplicates) . " potenciais duplicações:\n\n";

// 2. Analisa cada duplicação
$fixesApplied = 0;
$fixesFailed = 0;

foreach ($duplicates as $dup) {
    echo "Potencial duplicação:\n";
    echo "  Conv {$dup['conv1_id']}: {$dup['contact1']} | {$dup['name1']} | Tenant: {$dup['tenant1_name']} ({$dup['tenant1_id']}) | {$dup['msg1_count']} msgs\n";
    echo "  Conv {$dup['conv2_id']}: {$dup['contact2']} | {$dup['name2']} | Tenant: {$dup['tenant2_name']} ({$dup['tenant2_id']}) | {$dup['msg2_count']} msgs\n";
    echo "  Similaridade: nomes=" . ($dup['name_similarity'] ? 'SIM' : 'NÃO') . " | temporal=" . ($dup['temporal_similarity'] ? 'SIM' : 'NÃO') . "\n";
    
    // Decide qual conversa manter
    $keepConvId = null;
    $mergeConvId = null;
    
    // Critérios para escolher qual manter:
    // 1. Preferir conversa com tenant
    // 2. Preferir conversa com mais mensagens
    // 3. Preferir conversa mais recente
    
    if ($dup['tenant1_id'] && !$dup['tenant2_id']) {
        $keepConvId = $dup['conv1_id'];
        $mergeConvId = $dup['conv2_id'];
    } elseif (!$dup['tenant1_id'] && $dup['tenant2_id']) {
        $keepConvId = $dup['conv2_id'];
        $mergeConvId = $dup['conv1_id'];
    } elseif ($dup['msg1_count'] >= $dup['msg2_count']) {
        $keepConvId = $dup['conv1_id'];
        $mergeConvId = $dup['conv2_id'];
    } else {
        $keepConvId = $dup['conv2_id'];
        $mergeConvId = $dup['conv1_id'];
    }
    
    echo "  Ação: Manter conversa $keepConvId, mesclar $mergeConvId\n";
    
    if ($fix && !$dryRun) {
        echo "  Aplicando merge...\n";
        
        if ($dedupService->mergeConversations($keepConvId, $mergeConvId)) {
            echo "  ✅ Merge aplicado com sucesso\n";
            $fixesApplied++;
        } else {
            echo "  ❌ Erro ao aplicar merge\n";
            $fixesFailed++;
        }
    } else {
        echo "  " . ($dryRun ? "DRY RUN: Não aplicado" : "Não aplicado") . "\n";
    }
    
    echo "---\n";
}

// 3. Resumo
echo "\n=== RESUMO ===\n";
echo "Duplicações encontradas: " . count($duplicates) . "\n";

if ($fix && !$dryRun) {
    echo "Correções aplicadas: $fixesApplied\n";
    echo "Correções falharam: $fixesFailed\n";
    
    if ($fixesApplied > 0) {
        echo "\n✅ Limpeza concluída\n";
    }
} else {
    echo "Modo dry-run: Nenhuma alteração aplicada\n";
    echo "\nPara aplicar as correções, execute:\n";
    echo "php scripts/cleanup_duplicate_conversations.php --fix\n";
}

// 4. Envia email de relatório (se configurado)
if (!$dryRun && $fixesApplied > 0) {
    $report = $this->generateEmailReport($duplicates, $fixesApplied, $fixesFailed);
    $this->sendEmailReport($report);
}

/**
 * Gera relatório em HTML para email
 */
function generateEmailReport(array $duplicates, int $fixesApplied, int $fixesFailed): string
{
    $html = "
    <h2>Relatório de Limpeza - Conversas Duplicadas</h2>
    <p>Data: " . date('Y-m-d H:i:s') . "</p>
    
    <h3>Resumo</h3>
    <ul>
        <li>Duplicações encontradas: " . count($duplicates) . "</li>
        <li>Correções aplicadas: $fixesApplied</li>
        <li>Correções falharam: $fixesFailed</li>
    </ul>
    
    <h3>Detalhes</h3>
    <table border='1' style='border-collapse: collapse; width: 100%;'>
        <thead>
            <tr>
                <th>Conversa 1</th>
                <th>Conversa 2</th>
                <th>Contato</th>
                <th>Similaridade</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
    ";
    
    foreach ($duplicates as $dup) {
        $status = ($fixesApplied > 0) ? 'Corrigido' : 'Pendente';
        
        $html .= "
        <tr>
            <td>ID {$dup['conv1_id']}<br>{$dup['contact1']}<br>{$dup['msg1_count']} msgs</td>
            <td>ID {$dup['conv2_id']}<br>{$dup['contact2']}<br>{$dup['msg2_count']} msgs</td>
            <td>{$dup['name1']} / {$dup['name2']}</td>
            <td>Nomes: " . ($dup['name_similarity'] ? 'SIM' : 'NÃO') . "<br>Temporal: " . ($dup['temporal_similarity'] ? 'SIM' : 'NÃO') . "</td>
            <td>$status</td>
        </tr>
        ";
    }
    
    $html .= "
        </tbody>
    </table>
    
    <p><small>Este relatório foi gerado automaticamente pelo script de limpeza de conversas duplicadas.</small></p>
    ";
    
    return $html;
}

/**
 * Envia relatório por email (se configurado)
 */
function sendEmailReport(string $report): void
{
    $to = Env::get('CLEANUP_REPORT_EMAIL', null);
    $from = Env::get('MAIL_FROM_ADDRESS', 'noreply@pixel12digital.com.br');
    
    if (!$to) {
        error_log("[CleanupDuplicates] Email de relatório não configurado (CLEANUP_REPORT_EMAIL)");
        return;
    }
    
    $subject = 'Relatório - Limpeza de Conversas Duplicadas - ' . date('Y-m-d');
    $headers = [
        'From: ' . $from,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8'
    ];
    
    if (mail($to, $subject, $report, implode("\r\n", $headers))) {
        error_log("[CleanupDuplicates] Relatório enviado para $to");
    } else {
        error_log("[CleanupDuplicates] Erro ao enviar relatório para $to");
    }
}
?>
