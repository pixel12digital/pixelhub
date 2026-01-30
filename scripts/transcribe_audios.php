<?php

/**
 * Script CLI para processar transcrições de áudio pendentes
 * 
 * Uso:
 *   php scripts/transcribe_audios.php           # Processa até 10 áudios pendentes
 *   php scripts/transcribe_audios.php --limit=5 # Processa até 5 áudios
 *   php scripts/transcribe_audios.php --stats   # Mostra estatísticas
 *   php scripts/transcribe_audios.php --check   # Verifica se o serviço está configurado
 * 
 * Para cron (executar a cada 5 minutos):
 *   0,5,10,15,20,25,30,35,40,45,50,55 * * * * php /caminho/scripts/transcribe_audios.php
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;
use PixelHub\Services\AudioTranscriptionService;

// Carrega .env
Env::load();

// Parse argumentos
$options = getopt('', ['limit:', 'stats', 'check', 'help']);

// Ajuda
if (isset($options['help']) || in_array('--help', $argv) || in_array('-h', $argv)) {
    echo "=== Transcrição de Áudios - Pixel Hub ===\n\n";
    echo "Uso:\n";
    echo "  php scripts/transcribe_audios.php [opções]\n\n";
    echo "Opções:\n";
    echo "  --limit=N    Número máximo de áudios a processar (padrão: 10)\n";
    echo "  --stats      Mostra estatísticas de transcrição\n";
    echo "  --check      Verifica se o serviço está configurado\n";
    echo "  --help       Mostra esta ajuda\n\n";
    echo "Exemplos:\n";
    echo "  php scripts/transcribe_audios.php              # Processa até 10 áudios\n";
    echo "  php scripts/transcribe_audios.php --limit=5    # Processa até 5 áudios\n";
    echo "  php scripts/transcribe_audios.php --stats      # Mostra estatísticas\n";
    exit(0);
}

echo "=== Transcrição de Áudios - Pixel Hub ===\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n\n";

// Verifica configuração
if (isset($options['check'])) {
    $health = AudioTranscriptionService::checkHealth();
    if ($health['ready']) {
        echo "✓ " . $health['message'] . "\n";
        exit(0);
    } else {
        echo "✗ " . $health['message'] . "\n";
        exit(1);
    }
}

// Mostra estatísticas
if (isset($options['stats'])) {
    $stats = AudioTranscriptionService::getStats();
    echo "Estatísticas de Transcrição:\n";
    echo "  Total de áudios:  {$stats['total_audios']}\n";
    echo "  Pendentes:        {$stats['pending']}\n";
    echo "  Concluídos:       {$stats['completed']}\n";
    echo "  Falhas:           {$stats['failed']}\n";
    echo "  Processando:      {$stats['processing']}\n";
    
    if (isset($stats['error'])) {
        echo "\n  ⚠ Erro: {$stats['error']}\n";
    }
    exit(0);
}

// Processa transcrições pendentes
$limit = isset($options['limit']) ? (int)$options['limit'] : 10;

echo "Processando até {$limit} áudios pendentes...\n\n";

// Verifica se está configurado
$health = AudioTranscriptionService::checkHealth();
if (!$health['ready']) {
    echo "✗ " . $health['message'] . "\n";
    exit(1);
}

// Executa transcrições
$result = AudioTranscriptionService::transcribePending($limit);

echo "=== Resultado ===\n";
echo "  Processados:  {$result['processed']}\n";
echo "  Sucesso:      {$result['success']}\n";
echo "  Falhas:       {$result['failed']}\n";

if (!empty($result['errors'])) {
    echo "\nErros:\n";
    foreach ($result['errors'] as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n✓ Processo concluído!\n";

exit($result['failed'] > 0 ? 1 : 0);
