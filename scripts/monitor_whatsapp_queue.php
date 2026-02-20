<?php
/**
 * MONITORAMENTO DA FILA WHATSAPP
 * 
 * SEGURANÇA: 100% PASSIVO
 * - Apenas lê dados
 * - Não altera nada
 * - Pode rodar a qualquer momento
 */

define('ROOT_PATH', dirname(__DIR__) . '/');
require_once ROOT_PATH . 'src/Core/Env.php';

PixelHub\Core\Env::load();

$config = require ROOT_PATH . 'config/database.php';

$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}", 
    $config['username'], 
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== MONITORAMENTO WHATSAPP QUEUE ===\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Status da fila
$queueSql = "SELECT 
               status, 
               COUNT(*) as count,
               MAX(created_at) as oldest,
               MIN(created_at) as newest
            FROM communication_events 
            GROUP BY status
            ORDER BY status";

$stmt = $pdo->query($queueSql);
$statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "📊 STATUS DOS EVENTOS:\n";
foreach ($statusCounts as $status) {
    $age = '';
    if ($status['oldest']) {
        $age = " (mais antigo: {$status['oldest']})";
    }
    echo "  {$status['status']}: {$status['count']}$age\n";
}

// 2. Eventos recentes não processados
$recentSql = "SELECT id, event_type, tenant_id, created_at
             FROM communication_events 
             WHERE status = 'queued' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
             ORDER BY created_at DESC 
             LIMIT 10";

$stmt = $pdo->query($recentSql);
$recentQueued = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($recentQueued)) {
    echo "\n⏰ EVENTOS RECENTES NÃO PROCESSADOS (última hora):\n";
    foreach ($recentQueued as $event) {
        $age = (new DateTime($event['created_at']))->diff(new DateTime())->format('%i min %s seg');
        echo "  ID:{$event['id']} | {$event['event_type']} | {$event['tenant_id']} | há $age\n";
    }
}

// 3. Taxa de processamento
$processingSql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                 FROM communication_events 
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 GROUP BY DATE(created_at)
                 ORDER BY date DESC";

$stmt = $pdo->query($processingSql);
$processingStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($processingStats)) {
    echo "\n📈 ESTATÍSTICAS DAS ÚLTIMAS 24H:\n";
    foreach ($processingStats as $stat) {
        $rate = $stat['total'] > 0 ? round(($stat['processed'] / $stat['total']) * 100, 1) : 0;
        echo "  {$stat['date']}: {$stat['processed']}/{$stat['total']} processados ($rate%)\n";
        if ($stat['failed'] > 0) {
            echo "    ⚠️  {$stat['failed']} falharam\n";
        }
    }
}

// 4. Verificar se worker está rodando
$pidFile = ROOT_PATH . 'storage/whatsapp_worker.pid';
$workerStatus = '❌ Parado';

if (file_exists($pidFile)) {
    $pid = (int)file_get_contents($pidFile);
    if (posix_kill($pid, 0)) {
        $workerStatus = "✅ Rodando (PID: $pid)";
    } else {
        $workerStatus = '⚠️  PID file existe mas processo não encontrado';
    }
}

echo "\n🤖 STATUS DO WORKER: $workerStatus\n";

// 5. Verificar conversas criadas recentemente
$conversationsSql = "SELECT COUNT(*) as count, 
                           DATE(created_at) as date
                    FROM conversations 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    GROUP BY DATE(created_at)
                    ORDER BY date DESC";

$stmt = $pdo->query($conversationsSql);
$convStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($convStats)) {
    echo "\n💬 CONVERSAS CRIADAS (últimas 24h):\n";
    foreach ($convStats as $stat) {
        echo "  {$stat['date']}: {$stat['count']} conversas\n";
    }
}

// 6. Alertas
$alerts = [];

// Fila grande
$queuedCount = array_filter($statusCounts, fn($s) => $s['status'] === 'queued')[0]['count'] ?? 0;
if ($queuedCount > 10) {
    $alerts[] = "⚠️  Fila grande: $queuedCount eventos pendentes";
}

// Worker parado com fila
if ($workerStatus === '❌ Parado' && $queuedCount > 0) {
    $alerts[] = "🚨 CRÍTICO: Worker parado com $queuedCount eventos na fila!";
}

// Alta taxa de falhas
$failedCount = array_filter($statusCounts, fn($s) => $s['status'] === 'failed')[0]['count'] ?? 0;
$totalCount = array_sum(array_column($statusCounts, 'count'));
if ($totalCount > 0 && ($failedCount / $totalCount) > 0.1) {
    $alerts[] = "⚠️  Alta taxa de falhas: " . round(($failedCount / $totalCount) * 100, 1) . "%";
}

if (!empty($alerts)) {
    echo "\n🚨 ALERTAS:\n";
    foreach ($alerts as $alert) {
        echo "  $alert\n";
    }
}

echo "\n=== FIM DO MONITORAMENTO ===\n";
