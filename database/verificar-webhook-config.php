<?php

/**
 * Script para verificar configuração do webhook para pixel12digital
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;

try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();

echo "=== Verificando configuração do webhook ===\n\n";

// 1. Verifica tenant_message_channels para pixel12digital
echo "1. Verificando tenant_message_channels para 'pixel12digital':\n";
$stmt = $db->prepare("
    SELECT 
        id,
        tenant_id,
        provider,
        channel_id,
        is_enabled,
        created_at,
        updated_at
    FROM tenant_message_channels
    WHERE provider = 'wpp_gateway'
    AND (
        channel_id = 'pixel12digital'
        OR LOWER(TRIM(REPLACE(channel_id, ' ', ''))) = LOWER(TRIM(REPLACE('pixel12digital', ' ', '')))
    )
");
$stmt->execute();
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($channels)) {
    echo "   ❌ NENHUM CANAL encontrado para 'pixel12digital'\n";
} else {
    echo "   ✅ Encontrados " . count($channels) . " canal(is):\n";
    foreach ($channels as $channel) {
        echo "   - ID: {$channel['id']}\n";
        echo "     Tenant ID: " . ($channel['tenant_id'] ?: 'NULL') . "\n";
        echo "     Channel ID: " . ($channel['channel_id'] ?: 'NULL') . "\n";
        echo "     Is Enabled: " . ($channel['is_enabled'] ? 'true' : 'false') . "\n";
        echo "\n";
    }
}

// 2. Verifica URL do webhook esperada
echo "\n2. URL do webhook esperada:\n";
$webhookUrl = Env::get('PIXELHUB_WHATSAPP_WEBHOOK_URL');
if (empty($webhookUrl)) {
    // Tenta construir a URL baseada no padrão
    $baseUrl = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $webhookUrl = "{$protocol}://{$baseUrl}/api/whatsapp/webhook";
    echo "   ⚠️  PIXELHUB_WHATSAPP_WEBHOOK_URL não configurado no .env\n";
    echo "   URL estimada: {$webhookUrl}\n";
} else {
    echo "   ✅ URL configurada: {$webhookUrl}\n";
}

// 3. Verifica se o webhook está configurado no gateway
echo "\n3. Verificando webhook no gateway para 'pixel12digital':\n";
try {
    $client = new WhatsAppGatewayClient();
    
    // Tenta obter informações do canal
    $channelInfo = $client->getChannel('pixel12digital');
    
    if ($channelInfo['success']) {
        echo "   ✅ Canal encontrado no gateway\n";
        $channel = $channelInfo['channel'] ?? $channelInfo['raw'] ?? [];
        echo "   Dados do canal:\n";
        echo "   " . json_encode($channel, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        
        // Verifica se há webhook configurado
        if (isset($channel['webhook']) || isset($channel['webhook_url'])) {
            $webhook = $channel['webhook'] ?? $channel['webhook_url'] ?? null;
            echo "\n   Webhook configurado: " . ($webhook ?: 'NULL') . "\n";
            
            if ($webhook && $webhook !== $webhookUrl) {
                echo "   ⚠️  ATENÇÃO: Webhook no gateway ({$webhook}) é diferente do esperado ({$webhookUrl})\n";
            } elseif ($webhook === $webhookUrl) {
                echo "   ✅ Webhook está configurado corretamente\n";
            } else {
                echo "   ❌ Webhook NÃO está configurado no gateway\n";
            }
        } else {
            echo "\n   ⚠️  Não foi possível verificar webhook no gateway (campo não encontrado)\n";
        }
    } else {
        echo "   ❌ Erro ao buscar canal: " . ($channelInfo['error'] ?? 'Erro desconhecido') . "\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Erro ao conectar ao gateway: " . $e->getMessage() . "\n";
}

// 4. Verifica eventos recentes que chegaram via webhook
echo "\n4. Verificando eventos recentes recebidos via webhook (últimas 2 horas):\n";
$stmt2 = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as from_field,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as channel_id
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    AND JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) = 'pixel12digital'
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt2->execute();
$recentInbound = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($recentInbound)) {
    echo "   ❌ NENHUM EVENTO INBOUND encontrado nas últimas 2 horas para pixel12digital\n";
    echo "   Isso indica que o webhook pode não estar funcionando corretamente.\n";
} else {
    echo "   ✅ Encontrados " . count($recentInbound) . " evento(s) inbound recentes:\n";
    foreach ($recentInbound as $event) {
        $time = date('H:i:s', strtotime($event['created_at']));
        echo "   - {$time}: " . ($event['text'] ? substr($event['text'], 0, 50) : 'NULL') . "\n";
        echo "     From: " . ($event['from_field'] ?: 'NULL') . "\n";
        echo "\n";
    }
}

echo "\n=== Conclusão ===\n";
echo "Se o webhook não está configurado ou não está recebendo mensagens:\n";
echo "1. Configure o webhook no gateway usando: setChannelWebhook('pixel12digital', '{$webhookUrl}')\n";
echo "2. Verifique se o gateway está enviando webhooks para mensagens inbound\n";
echo "3. Verifique os logs do gateway para ver se há erros ao enviar webhooks\n";

echo "\n=== Fim da verificação ===\n";

