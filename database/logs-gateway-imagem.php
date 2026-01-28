<?php
/**
 * Diagnóstico: Consultar logs do gateway para envio de imagem
 * Acesse via: /database/logs-gateway-imagem.php
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DE ENVIO DE IMAGEM ===\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n\n";

// Verificar se há arquivo de log PHP
$possibleLogPaths = [
    '/var/log/php/error.log',
    '/var/log/php8.1-fpm.log',
    '/var/log/apache2/error.log',
    '/home/*/logs/error.log',
    ini_get('error_log'),
];

echo "=== 1) Verificando caminhos de log ===\n";
foreach ($possibleLogPaths as $path) {
    if ($path && file_exists($path) && is_readable($path)) {
        echo "✓ Encontrado: {$path}\n";
    }
}

$errorLog = ini_get('error_log');
echo "\nPHP error_log configurado: " . ($errorLog ?: 'N/A') . "\n";

// Tentar ler últimas linhas do log se disponível
if ($errorLog && file_exists($errorLog) && is_readable($errorLog)) {
    echo "\n=== 2) Últimas 50 linhas com 'sendImage' ou 'WhatsAppGateway' ===\n";
    $lines = file($errorLog);
    $filtered = array_filter($lines, function($line) {
        return stripos($line, 'sendImage') !== false 
            || stripos($line, 'WhatsAppGateway') !== false
            || stripos($line, 'image') !== false;
    });
    $recent = array_slice($filtered, -50);
    foreach ($recent as $line) {
        echo $line;
    }
}

// Verificar eventos recentes de imagem no banco
echo "\n\n=== 3) Últimos eventos de imagem no banco ===\n";
try {
    $pdo = DB::getConnection();
    $sql = "SELECT event_id, conversation_id, status, 
                   JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')) as msg_type,
                   JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.message_id')) as wpp_message_id,
                   created_at
            FROM communication_events 
            WHERE JSON_EXTRACT(payload, '$.type') = 'image'
            ORDER BY created_at DESC
            LIMIT 10";
    $stmt = $pdo->query($sql);
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hasWppId = !empty($row['wpp_message_id']) ? '✓' : '✗';
        echo "[{$row['created_at']}] {$hasWppId} conv={$row['conversation_id']} status={$row['status']} wpp_id=" . ($row['wpp_message_id'] ?: 'NULL') . "\n";
    }
} catch (Exception $e) {
    echo "Erro ao consultar banco: " . $e->getMessage() . "\n";
}

// Verificar se WhatsAppGatewayClient tem logs customizados
echo "\n\n=== 4) Verificando arquivos de log customizados ===\n";
$customLogPaths = [
    __DIR__ . '/../storage/logs/gateway.log',
    __DIR__ . '/../storage/logs/whatsapp.log',
    __DIR__ . '/../logs/gateway.log',
];
foreach ($customLogPaths as $path) {
    if (file_exists($path)) {
        echo "Encontrado: {$path}\n";
        echo "Últimas 20 linhas:\n";
        $lines = file($path);
        $recent = array_slice($lines, -20);
        foreach ($recent as $line) {
            echo $line;
        }
    }
}

echo "\n\n=== 5) Info do ambiente ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'CLI') . "\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Max execution time: " . ini_get('max_execution_time') . "\n";
