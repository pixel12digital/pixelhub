<?php
/**
 * Diagnóstico de limites PHP - TEMPORÁRIO
 * Verificar se .htaccess/.user.ini estão sendo aplicados
 */

header('Content-Type: application/json');

echo json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'sapi_name' => php_sapi_name(), // cli, fpm-fcgi, apache2handler, etc.
    'limites' => [
        'post_max_size' => ini_get('post_max_size'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'max_input_time' => ini_get('max_input_time'),
    ],
    'arquivos_config' => [
        'htaccess_existe' => file_exists(__DIR__ . '/.htaccess'),
        'user_ini_existe' => file_exists(__DIR__ . '/.user.ini'),
    ],
    'esperado' => [
        'post_max_size' => '64M',
        'upload_max_filesize' => '64M',
        'memory_limit' => '256M',
    ]
], JSON_PRETTY_PRINT);
