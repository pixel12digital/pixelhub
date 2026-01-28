<?php
/**
 * Diagnóstico de POST grande - TEMPORÁRIO
 * Este script testa se o servidor aceita POST bodies grandes
 */

// Log no início imediato
error_log('[DEBUG_LARGE_POST] Script iniciado - ' . date('Y-m-d H:i:s'));
error_log('[DEBUG_LARGE_POST] Content-Length: ' . ($_SERVER['CONTENT_LENGTH'] ?? 'N/A'));
error_log('[DEBUG_LARGE_POST] Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'N/A'));
error_log('[DEBUG_LARGE_POST] Request-Method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));

header('Content-Type: application/json');

$result = [
    'timestamp' => date('Y-m-d H:i:s'),
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
    'content_length_header' => $_SERVER['CONTENT_LENGTH'] ?? null,
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
    'php_limits' => [
        'post_max_size' => ini_get('post_max_size'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'memory_limit' => ini_get('memory_limit'),
    ],
    'sapi' => php_sapi_name(),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Tenta ler o corpo raw
    $rawInput = file_get_contents('php://input');
    $rawLength = strlen($rawInput);
    
    $result['raw_input_length'] = $rawLength;
    $result['raw_input_length_kb'] = round($rawLength / 1024, 2);
    $result['raw_input_length_mb'] = round($rawLength / 1024 / 1024, 2);
    
    // Verifica se $_POST foi populado
    $result['post_populated'] = !empty($_POST);
    $result['post_keys'] = array_keys($_POST);
    
    // Se tiver 'type' no POST, mostra
    if (isset($_POST['type'])) {
        $result['post_type'] = $_POST['type'];
    }
    
    // Se tiver base64Ptt, mostra o tamanho
    if (isset($_POST['base64Ptt'])) {
        $result['base64Ptt_length'] = strlen($_POST['base64Ptt']);
        $result['base64Ptt_length_kb'] = round(strlen($_POST['base64Ptt']) / 1024, 2);
        $result['base64Ptt_length_mb'] = round(strlen($_POST['base64Ptt']) / 1024 / 1024, 2);
    }
    
    // Compara Content-Length header com tamanho real recebido
    $expectedLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    $result['content_match'] = $expectedLength === $rawLength;
    $result['content_difference'] = $expectedLength - $rawLength;
    
    if (!$result['content_match']) {
        $result['warning'] = 'CORPO DA REQUISIÇÃO FOI TRUNCADO! Esperado: ' . $expectedLength . ' bytes, Recebido: ' . $rawLength . ' bytes';
    }
    
    error_log('[DEBUG_LARGE_POST] Raw length: ' . $rawLength . ', Expected: ' . $expectedLength);
} else {
    $result['info'] = 'Envie um POST para testar';
    $result['test_curl'] = 'curl -X POST -d "test=123" ' . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '') . '/debug_large_post.php';
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
