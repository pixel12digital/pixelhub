<?php
// Endpoint mínimo para diagnóstico Whapi — sem bootstrap, sem DB
// URL de teste: https://hub.pixel12digital.com.br/whapi-ping.php
// Lê IPs conectados para debug: https://hub.pixel12digital.com.br/whapi-ping.php?log=1

$ip   = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
$meth = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ts   = date('Y-m-d H:i:s');
$entry = "[{$ts}] {$meth} from {$ip} UA={$ua}\n";

// Grava log num arquivo acessível via URL
$logFile = __DIR__ . '/whapi-ping-log.txt';
@file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

// Modo de leitura do log (só exibe se ?log=1)
if (isset($_GET['log'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo file_exists($logFile) ? file_get_contents($logFile) : "(sem registros ainda)\n";
    exit;
}

$body = json_encode(['success' => true, 'code' => 'PONG', 'ts' => time(), 'your_ip' => $ip]);
ignore_user_abort(true);
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('Content-Length: ' . strlen($body));
header('Connection: close');
echo $body;
flush();
