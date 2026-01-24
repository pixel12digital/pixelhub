<?php
/**
 * Logger simples para erros estruturais do Communication Hub.
 *
 * Recebe JSON via POST (sendBeacon/fetch) e anexa em um arquivo de log.
 * Uso interno de diagnóstico.
 */

// Garante que erros não vazem para a saída HTTP
ini_set('display_errors', '0');

// Lê o corpo bruto da requisição
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

// Monta registro de log com contexto adicional
$entry = [
    'timestamp'          => date('c'),
    'url'                => isset($data['url']) ? (string) $data['url'] : '',
    'context'            => isset($data['context']) ? (string) $data['context'] : '',
    'thread_id'          => isset($data['threadId']) ? (string) $data['threadId'] : '',
    'stack'              => isset($data['stack']) ? (string) $data['stack'] : '',
    'placeholder_parent' => $data['placeholderParent'] ?? null,
    'content_parent'     => $data['contentParent'] ?? null,
    'container'          => $data['container'] ?? null,
    'pane'               => $data['pane'] ?? null,
    'duplicates'         => $data['duplicates'] ?? null,
    'domPath'            => $data['domPath'] ?? null,
];

// Caminho do arquivo de log (no próprio diretório database/)
$logFile = __DIR__ . '/structural_errors.log';

// Garante que o diretório existe (por segurança)
if (!is_dir(__DIR__)) {
    @mkdir(__DIR__, 0777, true);
}

// Anexa linha JSON
file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);

// Resposta mínima
http_response_code(204);
exit;


