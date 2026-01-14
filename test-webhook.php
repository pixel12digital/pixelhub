<?php
/**
 * Script para disparar webhook de teste controlado
 * 
 * Uso: php test-webhook.php [payload_hash]
 * 
 * Se não passar payload_hash, gera um aleatório para teste
 */

// Configurações
// Detecta ambiente (produção ou local)
$isProduction = isset($argv[2]) && $argv[2] === 'prod';
$baseUrl = $isProduction 
    ? 'https://hub.pixel12digital.com.br'  // URL de produção
    : 'http://localhost/painel.pixel12digital/public';  // URL local

$webhookUrl = "{$baseUrl}/api/whatsapp/webhook";
$webhookSecret = getenv('PIXELHUB_WHATSAPP_WEBHOOK_SECRET') ?: '';

// Se tiver arquivo .env, tenta ler de lá
if (file_exists(__DIR__ . '/.env')) {
    $envContent = file_get_contents(__DIR__ . '/.env');
    if (preg_match('/PIXELHUB_WHATSAPP_WEBHOOK_SECRET=(.+)/', $envContent, $matches)) {
        $webhookSecret = trim($matches[1]);
    }
}

// Se for produção e não tiver secret, avisa
if ($isProduction && empty($webhookSecret)) {
    echo "⚠️  AVISO: PIXELHUB_WHATSAPP_WEBHOOK_SECRET não configurado!\n";
    echo "   O webhook pode retornar 403 se o secret for obrigatório.\n\n";
}

// Gera payload_hash se não fornecido
$payloadHash = $argv[1] ?? substr(md5(time() . rand()), 0, 8);

// Payload de teste (simula mensagem do WhatsApp Gateway)
$payload = [
    'event' => 'message',
    'from' => '554796164699@c.us',
    'to' => '5511999999999@c.us',
    'message' => [
        'id' => '3EB0' . strtoupper(substr(md5(time()), 0, 12)),
        'from' => '554796164699@c.us',
        'to' => '5511999999999@c.us',
        'body' => 'Mensagem de teste - payload_hash: ' . $payloadHash,
        'timestamp' => time(),
        'notifyName' => 'Teste Webhook'
    ],
    'session' => [
        'id' => 'whatsapp_35',
        'session' => 'whatsapp_35'
    ],
    'channel' => 'whatsapp_35',
    'channelId' => 'whatsapp_35',
    'timestamp' => time(),
    'id' => 'test_' . time() . '_' . $payloadHash
];

// Adiciona correlation_id se payload_hash foi fornecido
if (isset($argv[1])) {
    $payload['correlation_id'] = 'test_' . $payloadHash;
}

// Prepara requisição
$ch = curl_init($webhookUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Webhook-Secret: ' . $webhookSecret
    ]
]);

echo "=== Disparando Webhook de Teste ===\n";
echo "Ambiente: " . ($isProduction ? "PRODUÇÃO" : "LOCAL") . "\n";
echo "URL: $webhookUrl\n";
echo "Payload Hash: $payloadHash\n";
echo "Message ID: {$payload['message']['id']}\n";
echo "From: {$payload['from']}\n";
echo "Channel ID: {$payload['channel']}\n";
echo "\n";

// Dispara webhook
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Exibe resultado
echo "=== Resposta ===\n";
echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "Erro: $error\n";
} else {
    echo "Response: $response\n";
}

// Calcula payload_hash do payload enviado para verificação
$sentPayloadHash = substr(md5(json_encode($payload)), 0, 8);
echo "\n=== Para Rastrear nos Logs ===\n";
echo "Busque por: payload_hash=$sentPayloadHash\n";
echo "Ou por: message_id={$payload['message']['id']}\n";
echo "\n";

// Instruções
echo "=== Próximos Passos ===\n";
echo "1. Execute: .\\monitor-logs.ps1 (em outro terminal)\n";
echo "2. Verifique os logs para:\n";
echo "   - [HUB_WEBHOOK_IN] com payload_hash=$sentPayloadHash\n";
echo "   - [HUB_PHONE_NORM] com from=554796164699\n";
echo "   - [HUB_CHANNEL_ID] com channel_id=whatsapp_35\n";
echo "   - [HUB_CONV_MATCH] para match/criação de conversa\n";
echo "   - [HUB_MSG_SAVE_OK] com message_id={$payload['message']['id']}\n";
echo "\n";

