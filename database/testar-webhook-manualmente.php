<?php

/**
 * Script para testar o webhook manualmente
 * Simula uma chamada de webhook para verificar se está funcionando
 */

require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;

try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

echo "=== Teste Manual do Webhook ===\n\n";

// Simula payload de uma mensagem
$testPayload = [
    'event' => 'message',
    'session' => [
        'id' => 'pixel12digital',
        'name' => 'pixel12digital'
    ],
    'message' => [
        'id' => 'test_' . time(),
        'from' => '554796164699@c.us',
        'to' => '554797309525@c.us',
        'text' => 'teste-1516',
        'timestamp' => time()
    ],
    'raw' => [
        'provider' => 'wppconnect',
        'payload' => [
            'event' => 'message',
            'from' => '554796164699@c.us',
            'to' => '554797309525@c.us'
        ]
    ]
];

$webhookUrl = Env::get('PIXELHUB_WHATSAPP_WEBHOOK_URL');
if (empty($webhookUrl)) {
    $baseUrl = $_SERVER['HTTP_HOST'] ?? 'painel.pixel12digital.com.br';
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
    $webhookUrl = "{$protocol}://{$baseUrl}/api/whatsapp/webhook";
}

echo "1. URL do webhook: {$webhookUrl}\n";
echo "2. Payload de teste:\n";
echo json_encode($testPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

echo "3. Enviando requisição de teste...\n";

$ch = curl_init($webhookUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($testPayload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Webhook-Secret: ' . (Env::get('PIXELHUB_WHATSAPP_WEBHOOK_SECRET', '') ?: 'test')
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "   ❌ Erro cURL: {$error}\n";
} else {
    echo "   ✅ Requisição enviada\n";
    echo "   HTTP Code: {$httpCode}\n";
    echo "   Response: {$response}\n";
    
    if ($httpCode === 200) {
        echo "\n   ✅ Webhook respondeu com sucesso (200)\n";
    } else {
        echo "\n   ⚠️  Webhook respondeu com código {$httpCode}\n";
    }
}

echo "\n=== Fim do teste ===\n";

