<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== VERIFICANDO DEFINIÇÃO DE CANAIS ===\n";

// 1. Verificar canais configurados em tenant_message_channels
echo "\n1. CANAIS CONFIGURADOS NO TENANT:\n";
$stmt = $db->prepare("
    SELECT id, tenant_id, provider, channel_id, is_enabled, created_at
    FROM tenant_message_channels 
    ORDER BY provider, channel_id
");
$stmt->execute();
$tenantChannels = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($tenantChannels as $ch) {
    $status = $ch['is_enabled'] ? '✅ ATIVO' : '❌ INATIVO';
    echo sprintf("- ID:%d | Provider:%s | Channel:%s | %s\n", 
        $ch['id'], $ch['provider'], $ch['channel_id'], $status);
}

// 2. Verificar configurações em whatsapp_provider_configs
echo "\n2. CONFIGURAÇÕES WHATAPP PROVIDERS:\n";
$stmt = $db->prepare("
    SELECT id, provider_type, session_name, is_active, 
           whapi_channel_id, whapi_api_token, meta_phone_number_id
    FROM whatsapp_provider_configs 
    ORDER BY provider_type, session_name
");
$stmt->execute();
$providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($providers as $p) {
    $status = $p['is_active'] ? '✅ ATIVO' : '❌ INATIVO';
    $token = $p['whapi_api_token'] ? '🔑 CONFIGURADO' : '❌ SEM TOKEN';
    $channel = $p['whapi_channel_id'] ?? 'N/D';
    $metaPhone = $p['meta_phone_number_id'] ?? 'N/D';
    
    echo sprintf("- ID:%d | Type:%s | Sessão:%s | %s\n", 
        $p['id'], $p['provider_type'], $p['session_name'], $status);
    echo sprintf("  Token:%s | Channel:%s | Meta:%s\n", 
        $token, $channel, $metaPhone);
}

// 3. Verificar quais canais realmente funcionam (testando API)
echo "\n3. TESTE DE CONEXÃO DOS CANAIS:\n";
foreach ($providers as $p) {
    if ($p['is_active'] && $p['provider_type'] === 'whapi' && $p['whapi_api_token']) {
        echo sprintf("\nTestando sessão: %s\n", $p['session_name']);
        
        // Descriptografar token
        $apiToken = $p['whapi_api_token'];
        if (strpos($apiToken, 'encrypted:') === 0) {
            require_once __DIR__ . '/src/Core/CryptoHelper.php';
            $token = \PixelHub\Core\CryptoHelper::decrypt(substr($apiToken, 10));
        } else {
            $token = $apiToken;
        }
        
        // Testar status do canal
        $url = "https://gate.whapi.cloud/status";
        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            echo "  ✅ CONEXÃO OK\n";
        } else {
            echo "  ❌ ERRO CONEXÃO (HTTP {$httpCode})\n";
        }
    } elseif ($p['is_active'] && $p['provider_type'] === 'meta_official') {
        echo sprintf("\nSessão Meta: %s (não testado neste script)\n", $p['session_name']);
    }
}

// 4. Verificar histórico de envios
echo "\n\n4. HISTÓRICO DE ENVIOS SDR (ÚLTIMOS 10):\n";
$stmt = $db->prepare("
    SELECT id, session_name, phone, establishment_name, status, 
           scheduled_at, sent_at, whapi_message_id, error
    FROM sdr_dispatch_queue 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute();
$envios = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($envios as $e) {
    $statusIcon = $e['status'] === 'sent' ? '✅' : ($e['status'] === 'failed' ? '❌' : '⏳');
    $dataEnvio = $e['sent_at'] ? date('d/m H:i', strtotime($e['sent_at'])) : 'N/A';
    
    echo sprintf("%s ID:%d | %s | %s\n", 
        $statusIcon, $e['id'], $e['session_name'], $e['establishment_name']);
    echo sprintf("   Tel:%s | Envio:%s | MsgID:%s\n", 
        $e['phone'], $dataEnvio, substr($e['whapi_message_id'] ?? 'N/A', 0, 15) . '...');
    
    if ($e['error']) {
        echo sprintf("   Erro: %s\n", $e['error']);
    }
}

// 5. Sugerir correções
echo "\n\n5. ANÁLISE E SUGESTÕES:\n";
echo "Canais que devem existir:\n";
echo "- ✅ pixel12digital (Whapi)\n";
echo "- ✅ orsegups (Whapi)\n";
echo "- ✅ meta_official (Meta API)\n";

echo "\nCanais para remover (se existirem):\n";
echo "- ❌ wppconnect (obsoleto)\n";
echo "- ❌ wpp_gateway (obsoleto)\n";

// Verificar canais obsoletos
foreach ($providers as $p) {
    if (in_array($p['provider_type'], ['wppconnect', 'wpp_gateway'])) {
        echo sprintf("- ⚠️ Encontrado obsoleto: %s (ID:%d) - REMOVER\n", 
            $p['provider_type'], $p['id']);
    }
}

echo "\n=== FIM ===\n";
