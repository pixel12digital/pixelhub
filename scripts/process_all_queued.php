<?php
/**
 * Processa TODOS os eventos queued pendentes
 * 
 * Executa o worker em loop até não haver mais eventos queued.
 */

define('ROOT_PATH', __DIR__ . '/../');
require_once ROOT_PATH . 'src/Core/Env.php';
require_once ROOT_PATH . 'src/Core/DB.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== PROCESSAMENTO EM LOTE DE EVENTOS QUEUED ===\n";
echo "Iniciado em: " . date('Y-m-d H:i:s') . "\n\n";

$totalProcessed = 0;
$totalFailed = 0;
$iterations = 0;
$maxIterations = 100; // Limite de segurança

try {
    $db = DB::getConnection();
    
    while ($iterations < $maxIterations) {
        $iterations++;
        
        // Verifica quantos eventos queued ainda existem
        $stmt = $db->query("SELECT COUNT(*) as count FROM communication_events WHERE status = 'queued'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $queuedCount = $result['count'];
        
        if ($queuedCount === 0) {
            echo "\n✅ Todos os eventos foram processados!\n";
            break;
        }
        
        echo "[Iteração {$iterations}] {$queuedCount} evento(s) queued restantes...\n";
        
        // Executa o worker
        $output = [];
        $returnCode = 0;
        exec('php ' . ROOT_PATH . 'scripts/event_queue_worker.php 2>&1', $output, $returnCode);
        
        // Analisa o output para contar processados/falhados
        $lastLine = end($output);
        if (preg_match('/Worker finalizado: (\d+) processados, (\d+) falharam/', $lastLine, $matches)) {
            $processed = (int)$matches[1];
            $failed = (int)$matches[2];
            
            $totalProcessed += $processed;
            $totalFailed += $failed;
            
            echo "  → Processados: {$processed}, Falhados: {$failed}\n";
        }
        
        // Se não processou nenhum, pode estar travado
        if ($processed === 0 && $queuedCount > 0) {
            echo "\n⚠️  Worker não processou nenhum evento. Verificando se há eventos travados...\n";
            
            // Libera eventos em processing há mais de 5 minutos
            $releaseStmt = $db->query("
                UPDATE communication_events
                SET status = 'queued', updated_at = NOW()
                WHERE status = 'processing'
                    AND updated_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            $released = $releaseStmt->rowCount();
            
            if ($released > 0) {
                echo "  → Liberados {$released} eventos travados.\n";
            } else {
                echo "  → Nenhum evento travado encontrado. Pode haver eventos com erro permanente.\n";
                break;
            }
        }
        
        // Pequena pausa entre iterações
        usleep(500000); // 0.5 segundos
    }
    
    if ($iterations >= $maxIterations) {
        echo "\n⚠️  Limite de iterações atingido ({$maxIterations}).\n";
    }
    
    echo "\n=== RESUMO FINAL ===\n";
    echo "Total processado: {$totalProcessed}\n";
    echo "Total falhado: {$totalFailed}\n";
    echo "Iterações: {$iterations}\n";
    echo "Finalizado em: " . date('Y-m-d H:i:s') . "\n";
    
    // Estatísticas finais
    $finalStats = $db->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM communication_events
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY status
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nStatus dos eventos (últimas 24h):\n";
    foreach ($finalStats as $stat) {
        echo "  {$stat['status']}: {$stat['count']}\n";
    }
    
} catch (Exception $e) {
    echo "\nERRO: " . $e->getMessage() . "\n";
    exit(1);
}
