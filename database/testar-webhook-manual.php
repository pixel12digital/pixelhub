<?php
/**
 * TESTE MANUAL DO WEBHOOK
 * 
 * Simula envio de evento 'message' para verificar se é processado corretamente
 */

// URL do webhook
$webhookUrl = 'http://localhost/painel.pixel12digital/api/whatsapp/webhook';
// Ajuste conforme necessário

// Payload de teste (simula evento 'message' do gateway)
$payload = [
    'event' => 'message',
    'session' => [
        'id' => 'pixel12digital'
    ],
    'message' => [
        'from' => '554796474223@c.us',
        'to' => '554797309525@c.us',
        'text' => 'Envio0907',
        'id' => 'test_' . time(),
        'timestamp' => time()
    ],
    'from' => '554796474223@c.us',
    'to' => '554797309525@c.us',
    'event_id' => 'test_' . time(),
    'correlation_id' => 'test_corr_' . time(),
    'timestamp' => time()
];

echo "=== TESTE MANUAL DO WEBHOOK ===\n\n";
echo "URL: {$webhookUrl}\n";
echo "Payload:\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Envia POST request
$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Webhook-Secret: ' . (getenv('PIXELHUB_WHATSAPP_WEBHOOK_SECRET') ?: '')
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_VERBOSE, false);

echo "Enviando request...\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "Response HTTP Code: {$httpCode}\n";
echo "Response Body:\n";
echo $response . "\n\n";

if ($curlError) {
    echo "cURL Error: {$curlError}\n\n";
}

echo "=== TESTE CONCLUÍDO ===\n";
echo "\nVerifique:\n";
echo "1. HTTP Code deve ser 200\n";
echo "2. Response deve ter 'success': true\n";
echo "3. Verificar se evento foi gravado no banco (communication_events)\n";

