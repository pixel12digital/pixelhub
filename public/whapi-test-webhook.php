<?php
// Teste isolado: loga todos os headers + body e responde 200
// URL: https://hub.pixel12digital.com.br/whapi-test-webhook.php
// Ver log: https://hub.pixel12digital.com.br/whapi-test-webhook.php?log=1

$logDir  = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    @mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/webhook-test.log';

if (isset($_GET['log'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo file_exists($logFile) ? file_get_contents($logFile) : "(sem registros ainda)\n";
    exit;
}

if (isset($_GET['clear'])) {
    @unlink($logFile);
    echo "log apagado\n";
    exit;
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$body    = file_get_contents('php://input');
$method  = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$ip      = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$log  = "=== " . date('Y-m-d H:i:s') . " | {$method} | IP={$ip} ===\n";
$log .= "HEADERS:\n" . print_r($headers, true) . "\n";
$log .= "BODY (primeiros 500 chars):\n" . substr($body, 0, 500) . "\n";
$log .= str_repeat('-', 60) . "\n";

@file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success'          => true,
    'code'             => 'TEST_RECEIVED',
    'method'           => $method,
    'ip'               => $ip,
    'headers_received' => array_keys($headers),
]);
