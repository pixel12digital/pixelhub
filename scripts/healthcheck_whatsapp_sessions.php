<?php
/**
 * Healthcheck: verifica sessões WhatsApp e força tentativa de reconexão quando desconectadas.
 *
 * Evita perda de mensagens quando a sessão cai (ex: pixel12digital desconectou entre 08/02 e 09/02).
 * Chamar getQr() em sessão desconectada dispara tentativa de reconexão no WPPConnect;
 * se o token ainda for válido, reconecta sem exibir QR (como o clique manual na UI).
 *
 * Uso: php scripts/healthcheck_whatsapp_sessions.php [--dry-run]
 *
 * Cron sugerido (a cada 15 minutos): ver docs/CRON_HEALTHCHECK_SESSOES_WHATSAPP.md
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require_once $file;
    });
}

\PixelHub\Core\Env::load();

$options = getopt('', ['dry-run']);
$dryRun = isset($options['dry-run']);

$log = function (string $msg) {
    echo date('Y-m-d H:i:s') . ' ' . $msg . "\n";
};

try {
    $client = new \PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient();
    $result = $client->listChannels();
} catch (\Throwable $e) {
    $log("[healthcheck-sessions] ERRO ao listar canais: " . $e->getMessage());
    exit(1);
}

if (empty($result['success']) || empty($result['channels'])) {
    $log("[healthcheck-sessions] Nenhum canal encontrado ou resposta inválida.");
    exit(0);
}

$disconnected = [];
foreach ($result['channels'] as $ch) {
    $status = strtolower(trim($ch['status'] ?? ''));
    $id = $ch['id'] ?? $ch['name'] ?? 'unknown';
    if ($status !== 'connected') {
        $disconnected[] = $id;
    }
}

if (empty($disconnected)) {
    // Todas conectadas - sai silenciosamente
    exit(0);
}

$log("[healthcheck-sessions] Canais desconectados: " . implode(', ', $disconnected));

foreach ($disconnected as $channelId) {
    if ($dryRun) {
        $log("[healthcheck-sessions] [DRY-RUN] Chamaria getQr({$channelId})");
        continue;
    }

    try {
        $qrResult = $client->getQr($channelId);
        $success = !empty($qrResult['success']);
        $log("[healthcheck-sessions] getQr({$channelId}): " . ($success ? 'OK (tentativa de reconexão disparada)' : 'Falhou: ' . ($qrResult['error'] ?? 'unknown')));
    } catch (\Throwable $e) {
        $log("[healthcheck-sessions] getQr({$channelId}) EXCEÇÃO: " . $e->getMessage());
    }
}

exit(0);
