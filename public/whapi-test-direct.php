<?php
// Responde 200 IMEDIATAMENTE - sem nenhum include, sem nada
http_response_code(200);
header('Content-Type: application/json');
header('Content-Length: 2');
header('Connection: close');
echo '{}';

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    @ob_end_flush();
    flush();
}

// Log para diagnóstico (em background)
$log = date('Y-m-d H:i:s') . ' IP:' . ($_SERVER['REMOTE_ADDR'] ?? '?')
     . ' CF-IP:' . ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? 'none')
     . ' METHOD:' . ($_SERVER['REQUEST_METHOD'] ?? '?')
     . ' BODY:' . substr(file_get_contents('php://input'), 0, 200) . "\n";
file_put_contents(__DIR__ . '/../storage/logs/whapi-direct-test.log', $log, FILE_APPEND);
