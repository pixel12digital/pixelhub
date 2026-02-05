<?php
/**
 * Verifica existência das tabelas da Fase 1 e 2 do plano de confiabilidade do Inbox.
 * webhook_raw_logs (Fase 1.2), media_process_queue (Fase 2)
 *
 * Uso: php database/check-tables-inbox-fase12.php
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}

\PixelHub\Core\Env::load();

$db = \PixelHub\Core\DB::getConnection();

echo "=== VERIFICAÇÃO TABELAS INBOX (FASE 1.2 + 2) ===\n\n";

$tables = [
    'webhook_raw_logs' => 'Fase 1.2 - Payload bruto webhooks',
    'media_process_queue' => 'Fase 2 - Fila processamento mídia',
];

$allOk = true;

foreach ($tables as $table => $desc) {
    echo "$table ($desc):\n";
    try {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $stmt = $db->query("SELECT COUNT(*) as c FROM `$table`");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['c'];
            echo "   OK - $count registros\n";
        } else {
            echo "   NAO EXISTE - rode: php database/migrate.php\n";
            $allOk = false;
        }
    } catch (\Throwable $e) {
        echo "   ERRO: " . $e->getMessage() . "\n";
        $allOk = false;
    }
}

echo "\n" . ($allOk ? "Todas as tabelas OK.\n" : "Acao necessaria: php database/migrate.php\n");
