<?php
/**
 * Diagnóstico: Por que o QR code não é gerado?
 *
 * Executar na HostMedia: php scripts/diagnostico_qr_code_gateway.php [session_id]
 *
 * Testa cada etapa do fluxo (create, getQr, delete, create, getQr) e mostra
 * a resposta bruta do gateway para identificar onde o fluxo falha.
 */
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
        $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (file_exists($file)) require_once $file;
    });
}
\PixelHub\Core\Env::load();

$channelId = $argv[1] ?? 'pixel12digital';
$channelId = preg_replace('/[^a-zA-Z0-9_-]/', '', $channelId);

$log = function (string $msg) {
    echo date('H:i:s') . ' ' . $msg . "\n";
};

echo "=== Diagnóstico QR Code - Gateway ===\n";
echo "Sessão: {$channelId}\n";
$baseUrl = \PixelHub\Core\Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br:8443');
echo "Base URL: " . ($baseUrl ?: '(env vazio)') . "\n\n";

try {
    $client = new \PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient();
} catch (\Throwable $e) {
    echo "ERRO ao criar cliente: " . $e->getMessage() . "\n";
    exit(1);
}

// 1) listChannels
$log("1) GET /api/channels");
$r1 = $client->listChannels();
$log("   success=" . json_encode($r1['success'] ?? null) . " error=" . ($r1['error'] ?? '') . " status=" . ($r1['status'] ?? ''));
$channels = $r1['raw']['channels'] ?? $r1['channels'] ?? [];
$exists = false;
foreach ($channels as $ch) {
    $id = $ch['id'] ?? $ch['name'] ?? '';
    if (strtolower($id) === strtolower($channelId)) {
        $exists = true;
        $log("   Sessão {$channelId} existe no gateway, status=" . ($ch['status'] ?? '?'));
        break;
    }
}
if (!$exists) {
    $log("   Sessão {$channelId} NÃO existe no gateway");
}
echo "\n";

// 2) getQr (1ª tentativa)
$log("2) GET /api/channels/{$channelId}/qr (1ª tentativa)");
$r2 = $client->getQr($channelId);
$log("   success=" . json_encode($r2['success'] ?? null));
$log("   error=" . ($r2['error'] ?? '(nenhum)'));
$log("   status=" . ($r2['status'] ?? ''));
$raw = $r2['raw'] ?? [];
$qrKeys = ['qr', 'qr_base64', 'qrcode', 'base64Qrimg', 'base64', 'base64Image', 'image'];
$hasQr = false;
foreach ($qrKeys as $k) {
    if (!empty($raw[$k]) && is_string($raw[$k])) {
        $hasQr = true;
        $log("   QR encontrado em raw.{$k} (len=" . strlen($raw[$k]) . ")");
        break;
    }
}
if (!$hasQr && isset($raw['status'])) {
    $log("   raw.status=" . ($raw['status'] ?? ''));
}
if (!$hasQr && isset($raw['message'])) {
    $log("   raw.message=" . ($raw['message'] ?? ''));
}
if (!$hasQr) {
    $toLog = is_array($raw) ? array_diff_key($raw, array_flip($qrKeys)) : (is_string($raw) ? substr($raw, 0, 200) : $raw);
    $log("   Resposta raw (resumida): " . (is_array($toLog) ? json_encode($toLog, JSON_UNESCAPED_UNICODE) : $toLog));
}
echo "\n";

// 3) DELETE
$log("3) DELETE /api/channels/{$channelId}");
$r3 = $client->deleteChannel($channelId);
$log("   success=" . json_encode($r3['success'] ?? null));
$log("   error=" . ($r3['error'] ?? '(nenhum)'));
$log("   status=" . ($r3['status'] ?? ''));
if (isset($r3['raw']) && is_array($r3['raw'])) {
    $log("   raw=" . json_encode($r3['raw'], JSON_UNESCAPED_UNICODE));
}
echo "\n";

// 4) createChannel
$log("4) POST /api/channels (create)");
sleep(1);
$r4 = $client->createChannel($channelId);
$log("   success=" . json_encode($r4['success'] ?? null));
$log("   error=" . ($r4['error'] ?? '(nenhum)'));
if (!$r4['success']) {
    $log("   Resposta Create: " . json_encode($r4, JSON_UNESCAPED_UNICODE));
}
echo "\n";

// 5) getQr (após create)
$log("5) GET /api/channels/{$channelId}/qr (após create, aguardando 3s)");
sleep(3);
$r5 = $client->getQr($channelId);
$log("   success=" . json_encode($r5['success'] ?? null));
$log("   error=" . ($r5['error'] ?? '(nenhum)'));
$raw5 = $r5['raw'] ?? [];
$hasQr5 = false;
foreach ($qrKeys as $k) {
    if (!empty($raw5[$k]) && is_string($raw5[$k])) {
        $hasQr5 = true;
        $log("   QR encontrado em raw.{$k} (len=" . strlen($raw5[$k]) . ")");
        break;
    }
}
if (!$hasQr5) {
    $log("   raw.status=" . ($raw5['status'] ?? ''));
    $log("   raw.message=" . ($raw5['message'] ?? ''));
    $log("   Resposta completa: " . json_encode($r5, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

echo "\n=== Conclusão ===\n";
if ($hasQr5) {
    echo "O gateway RETORNA o QR após create. O problema pode estar no Pixel Hub (extractQr, timeout).\n";
} elseif ($r5['error'] ?? null) {
    echo "O gateway retorna ERRO em getQr: " . $r5['error'] . "\n";
    echo "Possível causa: WPPConnect não gera QR para esta sessão. Ver docs/PACOTE_VPS_PATCH_GETQRCODE_JSON_CONNECTED.md\n";
} elseif (strtoupper($raw5['status'] ?? '') === 'CONNECTED') {
    echo "O gateway retorna status CONNECTED sem QR (sessão zombie). Aplicar patch getQRCode na VPS.\n";
} elseif (strtoupper($raw5['status'] ?? '') === 'INITIALIZING') {
    echo "Sessão em INITIALIZING - WPPConnect pode demorar. Aumentar tentativas ou intervalo.\n";
} else {
    echo "O gateway não retorna QR. Verifique: DELETE suportado? Create funcionou? WPPConnect saudável?\n";
}
