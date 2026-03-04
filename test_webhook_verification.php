<?php
/**
 * Script para testar verificação do webhook Meta
 */

echo "=== TESTE DE VERIFICAÇÃO DO WEBHOOK META ===\n\n";

// Simula requisição GET de verificação do Meta
$verifyToken = 'pixelhub_meta_webhook_2026';
$challenge = 'test_challenge_' . time();

$url = 'https://hub.pixel12digital.com.br/api/whatsapp/meta/webhook?' . http_build_query([
    'hub.mode' => 'subscribe',
    'hub.verify_token' => $verifyToken,
    'hub.challenge' => $challenge
]);

echo "1. Testando endpoint de verificação...\n";
echo "   URL: {$url}\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "2. Resultado:\n";
echo "   HTTP Status: {$httpCode}\n";

if ($error) {
    echo "   ❌ Erro cURL: {$error}\n";
    exit(1);
}

if ($httpCode === 200) {
    if ($response === $challenge) {
        echo "   ✅ SUCESSO! Webhook respondeu corretamente ao challenge\n";
        echo "   Challenge enviado: {$challenge}\n";
        echo "   Challenge recebido: {$response}\n\n";
        echo "🎉 Endpoint está funcionando! Pode configurar no Meta Business Suite.\n";
    } else {
        echo "   ⚠️  Webhook respondeu 200 mas challenge incorreto\n";
        echo "   Esperado: {$challenge}\n";
        echo "   Recebido: {$response}\n";
    }
} else {
    echo "   ❌ Erro HTTP {$httpCode}\n";
    echo "   Resposta: {$response}\n\n";
    
    if ($httpCode === 404) {
        echo "⚠️  Rota não encontrada. Verifique:\n";
        echo "   1. Arquivo .htaccess está funcionando?\n";
        echo "   2. Rota está registrada em public/index.php?\n";
        echo "   3. Criar workaround: public/api/whatsapp/meta/webhook.php\n";
    } elseif ($httpCode === 403) {
        echo "⚠️  Verify token incorreto ou não configurado no banco\n";
    }
}

echo "\n=== FIM ===\n";
