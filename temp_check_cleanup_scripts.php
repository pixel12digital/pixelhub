<?php
require_once 'vendor/autoload.php';
PixelHub\Core\Env::load();

echo "=== Verificando scripts que podem limpar screen_recordings ===\n\n";

// Procura por scripts que contenham lógica de limpeza
$files = [
    'scripts/cleanup.php',
    'scripts/cleanup_screen_recordings.php',
    'scripts/cleanup_old_files.php',
    'scripts/maintenance.php',
    'scripts/archive_old_records.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "Arquivo encontrado: $file\n";
        $content = file_get_contents($file);
        if (strpos($content, 'screen_recordings') !== false) {
            echo "  -> Contém referência a screen_recordings\n";
        }
        if (strpos($content, 'DELETE') !== false) {
            echo "  -> Contém DELETE\n";
        }
        if (strpos($content, 'unlink') !== false) {
            echo "  -> Contém unlink\n";
        }
        if (strpos($content, 'DATE_SUB') !== false) {
            echo "  -> Contém DATE_SUB (pode limpar registros antigos)\n";
        }
        echo "\n";
    }
}

// Procura por crons que possam limpar
$cronFiles = [
    '.cron',
    'crontab',
    'crons.txt',
    'scripts/cron_cleanup.php'
];

foreach ($cronFiles as $file) {
    if (file_exists($file)) {
        echo "Arquivo de cron encontrado: $file\n";
        $content = file_get_contents($file);
        if (strpos($content, 'screen_recordings') !== false) {
            echo "  -> Contém referência a screen_recordings\n";
        }
        echo "\n";
    }
}

// Verifica se há algum script agendado no banco
$db = PixelHub\Core\DB::getConnection();
try {
    $stmt = $db->query("SELECT * FROM scheduled_tasks WHERE task_script LIKE '%screen_recordings%' OR task_description LIKE '%screen_recordings%'");
    $tasks = $stmt->fetchAll();
    if (!empty($tasks)) {
        echo "Tarefas agendadas encontradas:\n";
        foreach ($tasks as $task) {
            echo "  - {$task['task_description']}\n";
        }
    }
} catch (Exception $e) {
    // Tabela pode não existir
}

echo "\n=== Verificação concluída ===\n";
