<?php
/**
 * Captura as últimas linhas ROUTE do log (Passo 2 - evidência de rota/porta/IP).
 * Uso: após um envio de áudio no Hub, abrir esta URL e copiar o JSON.
 *
 * Requer ?token=XXX igual a ROUTE_LOG_CAPTURE_TOKEN no .env (ou deixe a variável vazia para desabilitar o script).
 * Opcional: ?request_id=YYY para filtrar só linhas desse request_id.
 *
 * Exemplo: https://hub.pixel12digital.com.br/capture-route-log.php?token=SEU_TOKEN&request_id=9ae2f5866699a63c
 */
header('Content-Type: application/json; charset=utf-8');

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
        if (file_exists($file)) require $file;
    });
}

try {
    \PixelHub\Core\Env::load(__DIR__ . '/../.env');
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'env_load_failed', 'message' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$expectedToken = (string) \PixelHub\Core\Env::get('ROUTE_LOG_CAPTURE_TOKEN', '');
$givenToken = isset($_GET['token']) ? (string) $_GET['token'] : '';
$requestIdFilter = isset($_GET['request_id']) ? trim((string) $_GET['request_id']) : '';

// Se veio placeholder do doc, ignora o filtro e devolve as últimas linhas ROUTE (qualquer request_id)
$placeholderPatterns = ['REQUEST_ID_ANOTADO', 'REQUEST_ID_DA_RESPOSTA', 'YYY', 'SEU_REQUEST_ID'];
$isPlaceholder = $requestIdFilter !== '' && in_array(strtoupper($requestIdFilter), array_map('strtoupper', $placeholderPatterns), true);
if ($isPlaceholder) {
    $requestIdFilter = '';
}

// Se ROUTE_LOG_CAPTURE_TOKEN estiver definido no .env, exige ?token= correto
if ($expectedToken !== '') {
    if ($givenToken === '' || !hash_equals($expectedToken, $givenToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'token_required',
            'message' => 'Use ?token=VALOR_DE_ROUTE_LOG_CAPTURE_TOKEN no .env'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$logDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs';
$logFile = realpath($logDir) ? realpath($logDir) . DIRECTORY_SEPARATOR . 'pixelhub.log' : $logDir . DIRECTORY_SEPARATOR . 'pixelhub.log';

$out = [
    'success' => true,
    'log_path' => $logFile,
    'request_id_filter' => $requestIdFilter !== '' ? $requestIdFilter : null,
    'lines' => [],
    'count' => 0,
    'hint' => 'Linhas que contêm [WhatsAppGateway::request] ROUTE (mais recentes primeiro). Use request_id real da resposta do erro (ex.: 9ae2f5866699a63c).',
];
if ($isPlaceholder) {
    $out['warning'] = 'request_id era placeholder (REQUEST_ID_ANOTADO etc.); filtro ignorado. Devolvendo últimas linhas ROUTE. Use request_id real na próxima vez.';
}

if (!is_file($logFile) || !is_readable($logFile)) {
    $out['success'] = false;
    $out['error'] = 'log_not_found';
    $out['message'] = 'Arquivo não existe ou não é legível: ' . $logFile;
    $out['log_path'] = $logFile;
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$maxBytes = 2 * 1024 * 1024; // últimas 2 MB
$size = filesize($logFile);
$content = $size > $maxBytes
    ? (string) file_get_contents($logFile, false, null, $size - $maxBytes, $maxBytes)
    : (string) file_get_contents($logFile);

$allLines = preg_split('/\r\n|\r|\n/', $content);
$allLines = array_reverse(array_filter($allLines));
$maxLines = 50;
$collected = [];

foreach ($allLines as $line) {
    if (stripos($line, 'ROUTE') === false) {
        continue;
    }
    if ($requestIdFilter !== '' && strpos($line, $requestIdFilter) === false) {
        continue;
    }
    $collected[] = $line;
    if (count($collected) >= $maxLines) {
        break;
    }
}

// Se filtrou por request_id e não achou nada, tenta sem filtro e avisa (útil para debug)
if (count($collected) === 0 && $requestIdFilter !== '') {
    $collected = [];
    foreach ($allLines as $line) {
        if (stripos($line, 'ROUTE') === false) continue;
        $collected[] = $line;
        if (count($collected) >= $maxLines) break;
    }
    if (count($collected) > 0) {
        $out['warning'] = ($out['warning'] ?? '') . ' Nenhuma linha com request_id=' . $requestIdFilter . '; abaixo as últimas ROUTE (qualquer request_id).';
    }
}

$out['lines'] = $collected;
$out['count'] = count($collected);

if ($out['count'] === 0) {
    $out['hint_if_empty'] = 'Nenhuma linha ROUTE em pixelhub.log. A linha ROUTE pode estar no error_log do Apache (domínio hub). Use o request_id real da resposta do erro (Dados do erro no console → request_id).';
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
