<?php

/**
 * Script para configurar o webhook do pixel12digital no gateway
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php';
require_once __DIR__ . '/../src/Services/GatewaySecret.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;

try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

echo "=== Configurando webhook para pixel12digital ===\n\n";

// 1. Obtém URL do webhook
$webhookUrl = Env::get('PIXELHUB_WHATSAPP_WEBHOOK_URL');
if (empty($webhookUrl)) {
    // Tenta construir a URL baseada no padrão
    $baseUrl = $_SERVER['HTTP_HOST'] ?? 'painel.pixel12digital.com.br';
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
    $webhookUrl = "{$protocol}://{$baseUrl}/api/whatsapp/webhook";
    echo "⚠️  PIXELHUB_WHATSAPP_WEBHOOK_URL não configurado no .env\n";
    echo "   Usando URL estimada: {$webhookUrl}\n\n";
} else {
    echo "✅ URL do webhook: {$webhookUrl}\n\n";
}

// 2. Obtém secret do webhook (opcional)
$webhookSecret = Env::get('PIXELHUB_WHATSAPP_WEBHOOK_SECRET', '');

// 3. Configura webhook no gateway
try {
    $client = new WhatsAppGatewayClient();
    
    echo "2. Configurando webhook no gateway para 'pixel12digital'...\n";
    
    $result = $client->setChannelWebhook('pixel12digital', $webhookUrl, !empty($webhookSecret) ? $webhookSecret : null);
    
    if ($result['success']) {
        echo "   ✅ Webhook configurado com sucesso!\n";
        echo "   URL: {$webhookUrl}\n";
        if (!empty($webhookSecret)) {
            echo "   Secret: configurado\n";
        }
    } else {
        echo "   ❌ Erro ao configurar webhook: " . ($result['error'] ?? 'Erro desconhecido') . "\n";
        if (isset($result['raw'])) {
            echo "   Resposta do gateway: " . json_encode($result['raw'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    // 4. Verifica se o webhook foi configurado corretamente
    echo "\n3. Verificando configuração do webhook...\n";
    $channelInfo = $client->getChannel('pixel12digital');
    
    if ($channelInfo['success']) {
        $channel = $channelInfo['channel'] ?? $channelInfo['raw'] ?? [];
        echo "   ✅ Canal encontrado no gateway\n";
        
        // Verifica webhook
        if (isset($channel['webhook']) || isset($channel['webhook_url'])) {
            $webhook = $channel['webhook'] ?? $channel['webhook_url'] ?? null;
            echo "   Webhook no gateway: " . ($webhook ?: 'NULL') . "\n";
            
            if ($webhook === $webhookUrl) {
                echo "   ✅ Webhook está configurado corretamente!\n";
            } else {
                echo "   ⚠️  Webhook no gateway é diferente do esperado\n";
            }
        } else {
            echo "   ⚠️  Não foi possível verificar webhook (campo não encontrado na resposta)\n";
            echo "   Resposta completa: " . json_encode($channel, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        }
    } else {
        echo "   ❌ Erro ao verificar canal: " . ($channelInfo['error'] ?? 'Erro desconhecido') . "\n";
    }
    
} catch (\Exception $e) {
    echo "   ❌ Erro ao conectar ao gateway: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Fim da configuração ===\n";
echo "\nPróximos passos:\n";
echo "1. Envie uma mensagem de teste do WhatsApp para pixel12digital\n";
echo "2. Verifique se a mensagem aparece no painel de comunicação\n";
echo "3. Se não aparecer, verifique os logs do gateway e do webhook\n";

