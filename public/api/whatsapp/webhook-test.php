<?php

/**
 * Endpoint de teste para verificar se o webhook está acessível
 * Acesse: /api/whatsapp/webhook-test
 */

header('Content-Type: application/json; charset=utf-8');

$response = [
    'success' => true,
    'message' => 'Webhook endpoint is accessible',
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => [
        'php_version' => PHP_VERSION,
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
        'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
    ],
    'headers' => [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'N/A',
    ]
];

// Verifica se recebeu algum payload
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    $response['received_payload'] = [
        'length' => strlen($rawInput),
        'preview' => substr($rawInput, 0, 200),
        'is_json' => json_decode($rawInput) !== null
    ];
}

http_response_code(200);
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

