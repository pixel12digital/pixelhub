<?php
// Diagnóstico do gateway-wrapper - verificar status, sessões e webhook
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\Env;
use PixelHub\Services\GatewaySecret;
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;

Env::load(__DIR__);

echo "=== DIAGNÓSTICO DO GATEWAY-WRAPPER ===\n\n";

try {
    $gateway = new WhatsAppGatewayClient();
    
    // 1. Listar canais/sessões
    echo "--- 1. Sessões no gateway ---\n";
    $channels = $gateway->listChannels();
    if ($channels['success']) {
        foreach ($channels['channels'] ?? $channels['data'] ?? [] as $ch) {
            $id = $ch['id'] ?? $ch['session'] ?? $ch['name'] ?? 'UNKNOWN';
            $status = $ch['status'] ?? $ch['state'] ?? 'UNKNOWN';
            echo sprintf("  Session: %s | Status: %s\n", $id, $status);
        }
    } else {
        echo "  ERRO ao listar canais: " . ($channels['error'] ?? json_encode($channels)) . "\n";
    }
    
    // 2. Status da sessão pixel12digital
    echo "\n--- 2. Status pixel12digital ---\n";
    try {
        $status = $gateway->getSessionStatus('pixel12digital');
        echo "  " . json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    } catch (Exception $e) {
        echo "  ERRO: " . $e->getMessage() . "\n";
    }
    
    // 3. Verificar webhook configurado
    echo "\n--- 3. Webhook configurado ---\n";
    $webhookUrl = Env::get('PIXELHUB_WHATSAPP_WEBHOOK_URL', 'NÃO DEFINIDO');
    echo "  URL configurada no .env: {$webhookUrl}\n";
    
    // Tenta buscar config do webhook no gateway
    try {
        // Tenta endpoint de webhook config
        $baseUrl = Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br');
        $secret = GatewaySecret::getDecrypted();
        
        $ch = curl_init($baseUrl . '/api/webhook');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'X-Gateway-Secret: ' . $secret,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        echo "  GET /api/webhook: HTTP {$httpCode}\n";
        if ($curlError) echo "  cURL error: {$curlError}\n";
        if ($resp) echo "  Response: " . substr($resp, 0, 500) . "\n";
    } catch (Exception $e) {
        echo "  ERRO: " . $e->getMessage() . "\n";
    }
    
    // 4. Tenta buscar config de webhook por sessão
    echo "\n--- 4. Webhook por sessão (pixel12digital) ---\n";
    try {
        $baseUrl = Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br');
        $secret = GatewaySecret::getDecrypted();
        
        $ch = curl_init($baseUrl . '/api/sessions/pixel12digital/webhook');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'X-Gateway-Secret: ' . $secret,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        echo "  GET /api/sessions/pixel12digital/webhook: HTTP {$httpCode}\n";
        if ($curlError) echo "  cURL error: {$curlError}\n";
        if ($resp) echo "  Response: " . substr($resp, 0, 500) . "\n";
    } catch (Exception $e) {
        echo "  ERRO: " . $e->getMessage() . "\n";
    }
    
    // 5. Tenta endpoint de health/status
    echo "\n--- 5. Health check do gateway ---\n";
    try {
        $baseUrl = Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br');
        $secret = GatewaySecret::getDecrypted();
        
        foreach (['/api/health', '/api/status', '/health', '/api/sessions'] as $endpoint) {
            $ch = curl_init($baseUrl . $endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'X-Gateway-Secret: ' . $secret,
                    'Content-Type: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            echo "  {$endpoint}: HTTP {$httpCode}";
            if ($curlError) echo " | cURL: {$curlError}";
            if ($resp && $httpCode >= 200 && $httpCode < 400) echo " | " . substr($resp, 0, 300);
            echo "\n";
        }
    } catch (Exception $e) {
        echo "  ERRO: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "ERRO GERAL: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIM DIAGNÓSTICO GATEWAY ===\n";
