<?php

/**
 * SDR Planner — Planejador Diário de Prospecção
 *
 * Executa 1x/dia via cron, seg–sáb, às 08:00:
 *   0 8 * * 1-6  cd ~/hub.pixel12digital.com.br && php scripts/sdr_planner.php >> logs/sdr_planner.log 2>&1
 *
 * Fluxo:
 * 1. Verifica pausa manual (sdr_paused)
 * 2. Para cada receita SDR ativa, seleciona leads com telefone
 * 3. Gera horários humanizados (não robóticos) na janela 09:00–17:00
 * 4. Insere em sdr_dispatch_queue + sdr_conversations
 * 5. O worker (sdr_worker.php) consome a fila ao longo do dia
 */

// ─── Bootstrap ──────────────────────────────────────────────────
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
spl_autoload_register(function ($class) {
    $prefix  = 'PixelHub\\';
    $baseDir = __DIR__ . '/../src/';
    $len     = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require $file;
});

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Services\SdrDispatchService;

Env::load();

$startTime = microtime(true);
$L = '[SDR_PLANNER]';

echo "{$L} === Início do planejamento SDR === " . date('Y-m-d H:i:s') . "\n";

// ─── 0. Verifica pausa ──────────────────────────────────────────
if (SdrDispatchService::isPaused()) {
    echo "{$L} SDR pausado manualmente. Nenhum envio agendado. (Envie 'retomar' via WhatsApp para ativar)\n";
    exit(0);
}

// ─── 1. Conexão DB ──────────────────────────────────────────────
try {
    $db = DB::getConnection();
} catch (\Exception $e) {
    echo "{$L} FATAL: Banco inacessível: {$e->getMessage()}\n";
    exit(1);
}

// ─── 2. Busca receitas SDR ativas (Orsegups) ────────────────────
// Receitas cujo tenant é Orsegups (busca por nome, flexível)
$recipes = $db->query("
    SELECT pr.id, pr.name, pr.source,
           t.id AS tenant_id, t.name AS tenant_name
    FROM prospecting_recipes pr
    LEFT JOIN tenants t ON t.id = pr.tenant_id
    WHERE pr.status = 'active'
      AND (
          t.name LIKE '%Orsegups%'
          OR t.name LIKE '%orsegups%'
          OR pr.tenant_id IN (
              SELECT id FROM tenants WHERE name LIKE '%Orsegups%' OR name LIKE '%orsegups%'
          )
      )
    ORDER BY pr.id ASC
")->fetchAll(\PDO::FETCH_ASSOC);

if (empty($recipes)) {
    echo "{$L} Nenhuma receita SDR ativa encontrada. Configure receitas vinculadas ao tenant Orsegups.\n";
    exit(0);
}

echo "{$L} Receitas ativas: " . count($recipes) . "\n";

// ─── 3. Para cada receita, planeja o dia ───────────────────────
$maxPerDay = (int) (Env::get('SDR_MAX_PER_DAY', '80'));
$totalSlots = $maxPerDay;
$totalEnqueued = 0;
$totalSkipped  = 0;

// Distribui o budget entre as receitas ativas
$perRecipe = (int) ceil($totalSlots / count($recipes));

foreach ($recipes as $recipe) {
    echo "{$L} Receita #{$recipe['id']}: {$recipe['name']} ({$recipe['source']})\n";

    try {
        $sessionName = Env::get('SDR_WHAPI_SESSION', 'orsegups');
        $stats = SdrDispatchService::planDay($recipe['id'], min($perRecipe, $totalSlots - $totalEnqueued), $sessionName);

        echo "{$L}   Enfileirados: {$stats['enqueued']}\n";
        echo "{$L}   Sem telefone: {$stats['skipped_no_phone']}\n";

        $totalEnqueued += $stats['enqueued'];
    } catch (\Throwable $e) {
        echo "{$L}   ERRO na receita #{$recipe['id']}: {$e->getMessage()}\n";
        error_log("{$L} Erro receita #{$recipe['id']}: " . $e->getMessage());
    }

    if ($totalEnqueued >= $totalSlots) {
        echo "{$L} Budget diário atingido ({$totalSlots}). Parando.\n";
        break;
    }
}

// ─── 4. Resumo ──────────────────────────────────────────────────
$elapsed = round(microtime(true) - $startTime, 2);
echo "\n{$L} === Resumo do Planejamento ===\n";
echo "{$L} Total enfileirado: {$totalEnqueued} de {$totalSlots} slots disponíveis\n";
echo "{$L} Janela de envio: " . SdrDispatchService::DISPATCH_WINDOW_START . " – " . SdrDispatchService::DISPATCH_WINDOW_END . "\n";
echo "{$L} Worker (sdr_worker.php) enviará ao longo do dia.\n";
echo "{$L} Tempo: {$elapsed}s\n";
echo "{$L} === Fim === " . date('Y-m-d H:i:s') . "\n";

// Log em arquivo
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
file_put_contents(
    $logDir . '/sdr_planner.log',
    "[" . date('Y-m-d H:i:s') . "] enqueued={$totalEnqueued} time={$elapsed}s\n",
    FILE_APPEND
);
