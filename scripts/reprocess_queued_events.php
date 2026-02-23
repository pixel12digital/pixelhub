<?php
/**
 * Script de Reprocessamento - Eventos Queued Pendentes
 * 
 * Reprocessa eventos que ficaram travados em 'queued'.
 * Útil para recuperar eventos após falha do worker.
 * 
 * Uso:
 *   php scripts/reprocess_queued_events.php [--limit=50] [--dry-run]
 */

define('ROOT_PATH', __DIR__ . '/../');
require_once ROOT_PATH . 'src/Core/Env.php';
require_once ROOT_PATH . 'src/Core/DB.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

// Parse argumentos
$options = getopt('', ['limit:', 'dry-run']);
$limit = isset($options['limit']) ? (int)$options['limit'] : 50;
$dryRun = isset($options['dry-run']);

echo "=== REPROCESSAMENTO DE EVENTOS QUEUED ===\n";
echo "Limite: {$limit} eventos\n";
echo "Modo: " . ($dryRun ? "DRY-RUN (simulação)" : "PRODUÇÃO") . "\n\n";

try {
    $db = DB::getConnection();
    
    // Buscar eventos queued
    $stmt = $db->prepare("
        SELECT 
            id,
            event_id,
            event_type,
            tenant_id,
            payload,
            metadata,
            retry_count,
            created_at,
            TIMESTAMPDIFF(HOUR, created_at, NOW()) as hours_queued
        FROM communication_events
        WHERE status = 'queued'
        ORDER BY created_at ASC
        LIMIT :limit
    ");
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($events) === 0) {
        echo "✅ Nenhum evento queued encontrado.\n";
        exit(0);
    }
    
    echo "Encontrados " . count($events) . " evento(s) queued:\n\n";
    
    // Agrupa por tipo e tenant
    $stats = [];
    foreach ($events as $event) {
        $key = $event['event_type'] . '|' . ($event['tenant_id'] ?: 'NULL');
        if (!isset($stats[$key])) {
            $stats[$key] = [
                'type' => $event['event_type'],
                'tenant_id' => $event['tenant_id'] ?: 'NULL',
                'count' => 0,
                'oldest_hours' => 0
            ];
        }
        $stats[$key]['count']++;
        $stats[$key]['oldest_hours'] = max($stats[$key]['oldest_hours'], $event['hours_queued']);
    }
    
    echo "Distribuição:\n";
    foreach ($stats as $stat) {
        echo sprintf(
            "  %s (tenant: %s): %d evento(s), mais antigo há %d horas\n",
            $stat['type'],
            $stat['tenant_id'],
            $stat['count'],
            $stat['oldest_hours']
        );
    }
    
    if ($dryRun) {
        echo "\n⚠️  DRY-RUN: Nenhuma alteração será feita.\n";
        echo "Execute sem --dry-run para reprocessar.\n";
        exit(0);
    }
    
    echo "\n";
    $confirm = readline("Deseja reprocessar estes eventos? (sim/não): ");
    
    if (strtolower(trim($confirm)) !== 'sim') {
        echo "Operação cancelada.\n";
        exit(0);
    }
    
    echo "\nReprocessando...\n\n";
    
    // Reset eventos para retry_count=0 e next_retry_at=NULL
    // Isso permite que o worker pegue eles novamente
    $resetStmt = $db->prepare("
        UPDATE communication_events
        SET retry_count = 0,
            next_retry_at = NULL,
            error_message = NULL,
            updated_at = NOW()
        WHERE status = 'queued'
        ORDER BY created_at ASC
        LIMIT :limit
    ");
    $resetStmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $resetStmt->execute();
    $resetCount = $resetStmt->rowCount();
    
    echo "✅ {$resetCount} evento(s) resetados para reprocessamento.\n";
    echo "\nAgora execute o worker para processar:\n";
    echo "  php scripts/event_queue_worker.php\n\n";
    
    echo "Ou configure o cron para rodar automaticamente:\n";
    echo "  * * * * * cd " . ROOT_PATH . " && php scripts/event_queue_worker.php >> logs/event_worker.log 2>&1\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
