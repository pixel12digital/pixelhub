<?php
/**
 * Diagnóstico: Erro "Canal não encontrado" ao enviar mensagem
 * 
 * Verifica:
 * 1. Dados da conversa (thread_id=whatsapp_2)
 * 2. Channel_id na conversa
 * 3. Canais habilitados no banco
 * 4. Status do canal no gateway
 */

require_once __DIR__ . '/../public/index.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;
use PixelHub\Services\GatewaySecret;

$db = DB::getConnection();

echo "=== DIAGNÓSTICO: Erro 'Canal não encontrado' ===\n\n";

// 1. Verifica conversa ID 2
echo "1. VERIFICANDO CONVERSA ID 2:\n";
$convStmt = $db->prepare("SELECT id, tenant_id, channel_id, contact_external_id, conversation_key FROM conversations WHERE id = 2");
$convStmt->execute();
$conv = $convStmt->fetch();

if ($conv) {
    echo "   ✅ Conversa encontrada:\n";
    echo "      - ID: {$conv['id']}\n";
    echo "      - Tenant ID: " . ($conv['tenant_id'] ?: 'NULL') . "\n";
    echo "      - Channel ID: " . ($conv['channel_id'] ?: 'NULL') . "\n";
    echo "      - Contact: " . ($conv['contact_external_id'] ?: 'NULL') . "\n";
    echo "      - Key: " . ($conv['conversation_key'] ?: 'NULL') . "\n\n";
} else {
    echo "   ❌ Conversa ID 2 não encontrada!\n\n";
    exit(1);
}

// 2. Verifica canais habilitados
echo "2. VERIFICANDO CANAIS HABILITADOS:\n";
$channelsStmt = $db->query("
    SELECT id, tenant_id, channel_id, session_id, provider, is_enabled 
    FROM tenant_message_channels 
    WHERE provider = 'wpp_gateway' AND is_enabled = 1
");
$channels = $channelsStmt->fetchAll();

if (empty($channels)) {
    echo "   ❌ Nenhum canal habilitado encontrado!\n\n";
} else {
    echo "   ✅ Canais encontrados: " . count($channels) . "\n";
    foreach ($channels as $ch) {
        echo "      - ID: {$ch['id']}, Tenant: " . ($ch['tenant_id'] ?: 'NULL') . ", Channel: {$ch['channel_id']}, Session: " . ($ch['session_id'] ?: 'N/A') . "\n";
    }
    echo "\n";
}

// 3. Verifica gateway
echo "3. VERIFICANDO GATEWAY:\n";
$baseUrl = Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br');
$secret = GatewaySecret::getDecrypted();

echo "   - Base URL: {$baseUrl}\n";
echo "   - Secret: " . (!empty($secret) ? 'CONFIGURADO (' . strlen($secret) . ' chars)' : 'VAZIO') . "\n\n";

if (empty($secret)) {
    echo "   ❌ Secret não configurado! Configure WPP_GATEWAY_SECRET no .env\n\n";
    exit(1);
}

// 4. Testa cada canal encontrado
$gateway = new WhatsAppGatewayClient($baseUrl, $secret);

echo "4. TESTANDO CANAIS NO GATEWAY:\n";
foreach ($channels as $ch) {
    $testChannelId = $ch['session_id'] ?: $ch['channel_id'];
    echo "   Testando: {$testChannelId}...\n";
    
    $channelInfo = $gateway->getChannel($testChannelId);
    
    if ($channelInfo['success']) {
        $channelData = $channelInfo['raw'] ?? [];
        $status = $channelData['channel']['status'] ?? $channelData['status'] ?? 'unknown';
        $connected = $channelData['connected'] ?? false;
        
        echo "      ✅ Canal existe no gateway\n";
        echo "      - Status: {$status}\n";
        echo "      - Connected: " . ($connected ? 'SIM' : 'NÃO') . "\n";
    } else {
        $error = $channelInfo['error'] ?? 'Erro desconhecido';
        $statusCode = $channelInfo['status'] ?? 'N/A';
        echo "      ❌ Canal NÃO encontrado no gateway\n";
        echo "      - Erro: {$error}\n";
        echo "      - HTTP: {$statusCode}\n";
    }
    echo "\n";
}

// 5. Verifica eventos recentes da conversa
echo "5. VERIFICANDO EVENTOS RECENTES DA CONVERSA:\n";
$eventsStmt = $db->prepare("
    SELECT event_id, event_type, created_at, 
           JSON_EXTRACT(payload, '$.channel_id') as payload_channel_id,
           JSON_EXTRACT(payload, '$.sessionId') as payload_session_id,
           JSON_EXTRACT(metadata, '$.channel_id') as metadata_channel_id
    FROM communication_events
    WHERE JSON_EXTRACT(metadata, '$.conversation_id') = 2
       OR (JSON_EXTRACT(payload, '$.from') = ? OR JSON_EXTRACT(payload, '$.to') = ?)
    ORDER BY created_at DESC
    LIMIT 5
");
$contactId = $conv['contact_external_id'] ?: '';
$eventsStmt->execute([$contactId, $contactId]);
$events = $eventsStmt->fetchAll();

if (empty($events)) {
    echo "   ⚠️ Nenhum evento encontrado para esta conversa\n\n";
} else {
    echo "   ✅ Eventos encontrados: " . count($events) . "\n";
    foreach ($events as $evt) {
        echo "      - {$evt['event_type']} em {$evt['created_at']}\n";
        echo "        Channel (payload): " . ($evt['payload_channel_id'] ?: 'NULL') . "\n";
        echo "        SessionId (payload): " . ($evt['payload_session_id'] ?: 'NULL') . "\n";
        echo "        Channel (metadata): " . ($evt['metadata_channel_id'] ?: 'NULL') . "\n";
    }
    echo "\n";
}

// 6. Recomendações
echo "6. RECOMENDAÇÕES:\n";
if (empty($conv['channel_id'])) {
    echo "   ⚠️ Conversa não tem channel_id - será resolvido automaticamente\n";
    if (!empty($channels)) {
        $firstChannel = $channels[0];
        $suggestedChannel = $firstChannel['session_id'] ?: $firstChannel['channel_id'];
        echo "   💡 Sugestão: Atualizar conversa com channel_id: {$suggestedChannel}\n";
    }
} else {
    $convChannelId = $conv['channel_id'];
    $foundInGateway = false;
    foreach ($channels as $ch) {
        $testChannelId = $ch['session_id'] ?: $ch['channel_id'];
        if ($testChannelId === $convChannelId) {
            $foundInGateway = true;
            $channelInfo = $gateway->getChannel($testChannelId);
            if ($channelInfo['success']) {
                echo "   ✅ Channel_id da conversa existe e está válido no gateway\n";
            } else {
                echo "   ❌ Channel_id da conversa existe no banco mas NÃO no gateway\n";
                echo "   💡 Ação: Verificar se o canal está conectado no gateway ou atualizar channel_id\n";
            }
            break;
        }
    }
    if (!$foundInGateway) {
        echo "   ❌ Channel_id da conversa não está em nenhum canal habilitado\n";
        echo "   💡 Ação: Atualizar channel_id da conversa com um canal válido\n";
    }
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
