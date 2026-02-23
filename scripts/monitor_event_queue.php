<?php
/**
 * Monitor de Fila de Eventos
 * 
 * Exibe estatísticas da fila de eventos em tempo real.
 * 
 * Uso:
 *   php scripts/monitor_event_queue.php [--watch]
 */

define('ROOT_PATH', __DIR__ . '/../');
require_once ROOT_PATH . 'src/Core/Env.php';
require_once ROOT_PATH . 'src/Core/DB.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

$watch = in_array('--watch', $argv);

function displayStats(PDO $db) {
    echo "\033[2J\033[H"; // Clear screen
    echo "=== MONITOR DE FILA DE EVENTOS ===\n";
    echo "Atualizado em: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Estatísticas gerais
    $stmt = $db->query("
        SELECT 
            status,
            COUNT(*) as count,
            MIN(created_at) as oldest,
            MAX(created_at) as newest
        FROM communication_events
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY status
        ORDER BY 
            CASE status
                WHEN 'queued' THEN 1
                WHEN 'processing' THEN 2
                WHEN 'processed' THEN 3
                WHEN 'failed' THEN 4
                ELSE 5
            END
    ");
    
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "--- STATUS DOS EVENTOS (últimas 24h) ---\n";
    $totalQueued = 0;
    $totalProcessing = 0;
    $totalProcessed = 0;
    $totalFailed = 0;
    
    foreach ($stats as $stat) {
        $icon = match($stat['status']) {
            'queued' => '⏳',
            'processing' => '⚙️',
            'processed' => '✅',
            'failed' => '❌',
            default => '❓'
        };
        
        echo sprintf(
            "%s %-12s: %4d eventos (mais antigo: %s)\n",
            $icon,
            strtoupper($stat['status']),
            $stat['count'],
            $stat['oldest']
        );
        
        switch($stat['status']) {
            case 'queued': $totalQueued = $stat['count']; break;
            case 'processing': $totalProcessing = $stat['count']; break;
            case 'processed': $totalProcessed = $stat['count']; break;
            case 'failed': $totalFailed = $stat['count']; break;
        }
    }
    
    // Alerta se houver eventos queued há muito tempo
    if ($totalQueued > 0) {
        $oldestQueued = $db->query("
            SELECT 
                TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutes_ago
            FROM communication_events
            WHERE status = 'queued'
            ORDER BY created_at ASC
            LIMIT 1
        ")->fetch();
        
        if ($oldestQueued && $oldestQueued['minutes_ago'] > 5) {
            echo "\n🔴 ALERTA: Evento mais antigo em fila há {$oldestQueued['minutes_ago']} minutos!\n";
            echo "   Verifique se o worker está rodando.\n";
        }
    }
    
    // Eventos queued por tipo
    if ($totalQueued > 0) {
        echo "\n--- EVENTOS QUEUED POR TIPO ---\n";
        
        $queuedByType = $db->query("
            SELECT 
                event_type,
                tenant_id,
                COUNT(*) as count,
                MIN(created_at) as oldest,
                TIMESTAMPDIFF(MINUTE, MIN(created_at), NOW()) as minutes_waiting
            FROM communication_events
            WHERE status = 'queued'
            GROUP BY event_type, tenant_id
            ORDER BY count DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($queuedByType as $row) {
            echo sprintf(
                "  %s (tenant: %s): %d evento(s), aguardando há %d min\n",
                $row['event_type'],
                $row['tenant_id'] ?: 'NULL',
                $row['count'],
                $row['minutes_waiting']
            );
        }
    }
    
    // Eventos failed recentes
    if ($totalFailed > 0) {
        echo "\n--- EVENTOS FAILED (últimos 5) ---\n";
        
        $failedEvents = $db->query("
            SELECT 
                event_id,
                event_type,
                tenant_id,
                error_message,
                retry_count,
                created_at
            FROM communication_events
            WHERE status = 'failed'
            ORDER BY updated_at DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($failedEvents as $evt) {
            echo sprintf(
                "  %s | %s (tenant: %s) | Tentativas: %d\n",
                substr($evt['event_id'], 0, 8),
                $evt['event_type'],
                $evt['tenant_id'] ?: 'NULL',
                $evt['retry_count']
            );
            echo "  Erro: " . substr($evt['error_message'] ?? 'N/A', 0, 80) . "\n\n";
        }
    }
    
    // Taxa de processamento (últimas 24h)
    echo "\n--- TAXA DE PROCESSAMENTO (24h) ---\n";
    $total = $totalQueued + $totalProcessing + $totalProcessed + $totalFailed;
    if ($total > 0) {
        $successRate = ($totalProcessed / $total) * 100;
        $failureRate = ($totalFailed / $total) * 100;
        
        echo sprintf("Total: %d eventos\n", $total);
        echo sprintf("Sucesso: %.1f%% (%d eventos)\n", $successRate, $totalProcessed);
        echo sprintf("Falha: %.1f%% (%d eventos)\n", $failureRate, $totalFailed);
        echo sprintf("Pendente: %.1f%% (%d eventos)\n", (($totalQueued + $totalProcessing) / $total) * 100, $totalQueued + $totalProcessing);
    }
    
    // Eventos processing travados
    $stuckProcessing = $db->query("
        SELECT COUNT(*) as count
        FROM communication_events
        WHERE status = 'processing'
            AND updated_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ")->fetch();
    
    if ($stuckProcessing['count'] > 0) {
        echo "\n🔴 ALERTA: {$stuckProcessing['count']} evento(s) travado(s) em 'processing' há mais de 5 minutos!\n";
        echo "   Execute o worker para liberar.\n";
    }
}

try {
    $db = DB::getConnection();
    
    if ($watch) {
        echo "Modo watch ativado. Pressione Ctrl+C para sair.\n\n";
        while (true) {
            displayStats($db);
            sleep(5);
        }
    } else {
        displayStats($db);
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
