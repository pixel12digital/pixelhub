<?php
/**
 * Diagnóstico da página /agenda - acesse via /agenda-debug.php
 * Remove após identificar o problema.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$baseDir = dirname(__DIR__);
if (file_exists($baseDir . '/vendor/autoload.php')) {
    require_once $baseDir . '/vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) use ($baseDir) {
        if (strncmp('PixelHub\\', $class, 9) !== 0) return;
        $file = $baseDir . '/src/' . str_replace('\\', '/', substr($class, 9)) . '.php';
        if (file_exists($file)) require $file;
    });
}

\PixelHub\Core\Env::load();

header('Content-Type: text/plain; charset=utf-8');
echo "=== Diagnostico Agenda ===\n\n";

try {
    $db = \PixelHub\Core\DB::getConnection();
    echo "DB: OK\n";
} catch (\Throwable $e) {
    echo "Erro DB: " . $e->getMessage() . "\n";
    exit;
}

try {
    $stmt = $db->query("SHOW TABLES LIKE 'activity_types'");
    $r = $stmt->fetchAll();
    echo "activity_types existe: " . (count($r) > 0 ? 'sim' : 'nao') . "\n";
} catch (\Throwable $e) {
    echo "Erro SHOW TABLES: " . $e->getMessage() . "\n";
}

try {
    $stmt = $db->query("SHOW COLUMNS FROM agenda_blocks LIKE 'activity_type_id'");
    $r = $stmt->fetchAll();
    echo "agenda_blocks.activity_type_id: " . (count($r) > 0 ? 'existe' : 'nao existe') . "\n";
} catch (\Throwable $e) {
    echo "Erro SHOW COLUMNS: " . $e->getMessage() . "\n";
}

$data = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
try {
    $blocos = \PixelHub\Services\AgendaService::getBlocksByDate($data);
    echo "getBlocksByDate: OK (" . count($blocos) . " blocos)\n";
} catch (\Throwable $e) {
    echo "Erro getBlocksByDate: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

try {
    $domingo = (clone $data)->modify('-' . (int)$data->format('w') . ' days');
    $sabado = (clone $domingo)->modify('+6 days');
    $blocosPorDia = \PixelHub\Services\AgendaService::getBlocksForPeriod($domingo, $sabado);
    echo "getBlocksForPeriod: OK\n";
} catch (\Throwable $e) {
    echo "Erro getBlocksForPeriod: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Fim ===\n";
