<?php
/**
 * Healthcheck: verifica sessões WhatsApp e força tentativa de reconexão quando desconectadas
 * OU em estado "zombie" (UI mostra Conectado mas nenhum evento de mensagem chega).
 *
 * A UI do gateway pode mostrar "Conectado" mesmo quando a sessão não recebe eventos.
 * Este script corrige isso verificando também a última mensagem em webhook_raw_logs.
 *
 * ONDE RODAR: HostMedia ou Local (precisa acessar banco e gateway via .env)
 *
 * Uso: php scripts/healthcheck_whatsapp_sessions.php [--dry-run] [--silent-hours=4]
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

$options = getopt('', ['dry-run', 'silent-hours:', 'verbose']);
$dryRun = isset($options['dry-run']);
$silentHours = isset($options['silent-hours']) ? (int) $options['silent-hours'] : 4;
$verbose = isset($options['verbose']);

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
    if ($verbose) {
        $log("[healthcheck-sessions] --verbose: success=" . json_encode($result['success'] ?? null));
        $log("[healthcheck-sessions] --verbose: error=" . ($result['error'] ?? ''));
        $log("[healthcheck-sessions] --verbose: error_code=" . ($result['error_code'] ?? ''));
        $log("[healthcheck-sessions] --verbose: status=" . ($result['status'] ?? ''));
        $log("[healthcheck-sessions] --verbose: Dica: verifique WPP_GATEWAY_BASE_URL e WPP_GATEWAY_SECRET no .env");
        $baseUrl = \PixelHub\Core\Env::get('WPP_GATEWAY_BASE_URL', '');
        $hasPort8443 = strpos($baseUrl, ':8443') !== false;
        $log("[healthcheck-sessions] --verbose: WPP_GATEWAY_BASE_URL=" . ($baseUrl ?: '(vazio, usa default)') . ($hasPort8443 ? '' : ' — ATENÇÃO: gateway costuma exigir :8443'));
    }
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

// CORREÇÃO: Verifica canais "zombie" - API diz Conectado mas sem eventos de mensagem
// A UI pode mostrar Conectado mesmo quando a sessão não recebe onmessage.
$zombieChannels = [];
if (class_exists(\PixelHub\Core\DB::class)) {
    try {
        $db = \PixelHub\Core\DB::getConnection();
        $hasWebhookLogs = $db->query("SHOW TABLES LIKE 'webhook_raw_logs'")->rowCount() > 0;
        if ($hasWebhookLogs) {
            $silentThreshold = date('Y-m-d H:i:s', strtotime("-{$silentHours} hours"));
            foreach ($result['channels'] as $ch) {
                $id = $ch['id'] ?? $ch['name'] ?? 'unknown';
                if (in_array($id, $disconnected)) continue; // já na lista
                $normalized = strtolower(str_replace(' ', '', $id));
                $stmt = $db->prepare("
                    SELECT created_at FROM webhook_raw_logs
                    WHERE event_type IN ('message', 'onmessage', 'onselfmessage', 'message.sent', 'message.received')
                    AND (payload_json LIKE ? OR payload_json LIKE ?)
                    ORDER BY created_at DESC LIMIT 1
                ");
                $stmt->execute(["%{$id}%", "%{$normalized}%"]);
                $last = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$last || $last['created_at'] < $silentThreshold) {
                    $zombieChannels[] = $id;
                }
            }
        }
    } catch (\Throwable $e) {
        $log("[healthcheck-sessions] AVISO: não foi possível verificar zombie (webhook_raw_logs): " . $e->getMessage());
    }
}

$toReconnect = array_unique(array_merge($disconnected, $zombieChannels));

if (empty($toReconnect)) {
    exit(0);
}

if (!empty($disconnected)) {
    $log("[healthcheck-sessions] Canais desconectados (API): " . implode(', ', $disconnected));
}
if (!empty($zombieChannels)) {
    $log("[healthcheck-sessions] Canais 'zombie' (sem mensagens há {$silentHours}h): " . implode(', ', $zombieChannels));
}

foreach ($toReconnect as $channelId) {
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
