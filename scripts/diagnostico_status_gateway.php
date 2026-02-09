<?php
/**
 * Diagnóstico: O que o gateway retorna para o Pixel Hub?
 *
 * Executar: php scripts/diagnostico_status_gateway.php
 *
 * Mostra a resposta bruta de GET /api/channels para identificar
 * de onde vem o status "connected" quando o dispositivo não está conectado.
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

echo "=== Diagnóstico: Resposta do gateway para GET /api/channels ===\n\n";

try {
    $client = new \PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient();
    $result = $client->listChannels();
} catch (\Throwable $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Resposta completa (raw):\n";
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$channels = $result['raw']['channels'] ?? $result['channels'] ?? [];
if (!empty($channels)) {
    echo "--- Canais e status (origem do que o Pixel Hub exibe) ---\n";
    foreach ($channels as $ch) {
        $id = $ch['id'] ?? $ch['name'] ?? $ch['channel_id'] ?? '?';
        $status = $ch['status'] ?? '?';
        echo "  {$id}: status={$status}\n";
    }
}

echo "\n=== Conclusão ===\n";
echo "O status exibido no Pixel Hub vem diretamente desta resposta do gateway.\n";
echo "Se pixel12digital mostra 'connected' mas o dispositivo está desconectado,\n";
echo "o gateway (ou WPPConnect) está retornando dados desatualizados.\n";
echo "\nPróximo passo: Rodar o bloco em docs/DIAGNOSTICO_STATUS_FALSO_CONECTADO.md na VPS.\n";
