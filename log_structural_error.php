<?php
/**
 * Logger simples para erros estruturais do Communication Hub.
 *
 * Recebe JSON via POST (sendBeacon/fetch) e anexa em um arquivo de log.
 * Endpoint acessível em /painel.pixel12digital/log_structural_error.php no localhost.
 */

ini_set('display_errors', '0');

$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
    http_response_code(400);
    exit;
}

$data = json_decode($rawBody, true);
if (!is_array($data)) {
    http_response_code(400);
    exit;
}

$entry = [
    'timestamp' => date('c'),
    'url'       => isset($data['url']) ? (string) $data['url'] : '',
    'stack'     => isset($data['stack']) ? (string) $data['stack'] : '',
    'placeholder_parent' => $data['placeholderParent'] ?? null,
    'content_parent'     => $data['contentParent'] ?? null,
    'container'          => $data['container'] ?? null,
    'duplicates'         => $data['duplicates'] ?? null,
];

$logFile = __DIR__ . '/database/structural_errors.log';
file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);

http_response_code(204);
exit;


