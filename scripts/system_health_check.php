<?php

// Health Check do Gateway WhatsApp e Sessões
//
// Executa verificação periódica e cria/resolve alertas automaticamente.
//
// Cron recomendado (a cada 5 minutos):
//   STAR/5 * * * * cd ~/hub.pixel12digital.com.br && php scripts/system_health_check.php >> logs/health_check.log 2>&1
//   (substituir STAR por *)

// Carrega autoload
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

use PixelHub\Core\Env;
use PixelHub\Controllers\SystemAlertController;

// Carrega .env
Env::load(__DIR__ . '/../.env', true);

// Inicia sessão se necessário (para DB)
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] Iniciando health check...\n";

try {
    $results = SystemAlertController::runHealthCheck();

    $gatewayStatus = $results['gateway_reachable'] ? 'OK' : 'OFFLINE';
    echo "[{$timestamp}] Gateway: {$gatewayStatus}\n";

    if (!empty($results['sessions'])) {
        foreach ($results['sessions'] as $sessionId => $status) {
            $icon = $status === 'connected' ? 'OK' : 'ALERTA';
            echo "[{$timestamp}] Sessão {$sessionId}: {$status} [{$icon}]\n";
        }
    }

    if ($results['alerts_created'] > 0) {
        echo "[{$timestamp}] Alertas criados/atualizados: {$results['alerts_created']}\n";
    }
    if ($results['alerts_resolved'] > 0) {
        echo "[{$timestamp}] Alertas resolvidos: {$results['alerts_resolved']}\n";
    }
    if (!empty($results['errors'])) {
        foreach ($results['errors'] as $err) {
            echo "[{$timestamp}] ERRO: {$err}\n";
        }
    }

    echo "[{$timestamp}] Health check concluído.\n";

} catch (\Throwable $e) {
    echo "[{$timestamp}] ERRO FATAL: {$e->getMessage()}\n";
    error_log("[SystemHealthCheck] ERRO FATAL: {$e->getMessage()}");
    exit(1);
}
