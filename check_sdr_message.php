<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== VERIFICANDO ENVIO WHATSAPP ===\n";

// Buscar detalhes do job enviado
$stmt = $db->prepare("
    SELECT id, result_id, session_name, phone, message, status, scheduled_at, sent_at, 
           whapi_message_id, error, attempts
    FROM sdr_dispatch_queue 
    WHERE id = 2
");
$stmt->execute();
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    echo "Job ID 2 não encontrado!\n";
    exit;
}

echo "\nDetalhes do Job:\n";
echo sprintf(
    "ID:%d | Phone:%s | Sessão:%s | Status:%s\n",
    $job['id'],
    $job['phone'],
    $job['session_name'],
    $job['status']
);
echo sprintf("Agendado: %s\n", $job['scheduled_at']);
echo sprintf("Enviado: %s\n", $job['sent_at']);
echo sprintf("Whapi Message ID: %s\n", $job['whapi_message_id'] ?? 'NÃO SALVO');
echo sprintf("Tentativas: %d\n", $job['attempts']);
if ($job['error']) {
    echo sprintf("Erro: %s\n", $job['error']);
}

// Buscar configuração da sessão orsegups
$stmt = $db->prepare("
    SELECT session_name, is_active, whapi_api_token, whapi_channel_id
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'whapi' AND session_name = 'orsegups'
");
$stmt->execute();
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if ($session) {
    echo "\nConfiguração da Sessão 'orsegups':\n";
    echo sprintf("Ativa: %s\n", $session['is_active'] ? 'SIM' : 'NÃO');
    echo sprintf("Channel ID: %s\n", $session['whapi_channel_id'] ?? 'NÃO CONFIGURADO');
    echo sprintf("Token: %s\n", $session['whapi_api_token'] ? 'CONFIGURADO' : 'NÃO CONFIGURADO');
} else {
    echo "\nSessão 'orsegups' não encontrada!\n";
}

// Se tem whapi_message_id, podemos tentar consultar o status na API Whapi
if ($job['whapi_message_id'] && $session) {
    echo "\nConsultando status na API Whapi...\n";
    
    $token = $session['whapi_api_token'];
    $channelId = $session['whapi_channel_id'];
    $messageId = $job['whapi_message_id'];
    
    $url = "https://gate.whapi.cloud/messages/{$messageId}";
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === false) {
        echo "Erro na consulta API: " . curl_error($ch) . "\n";
    } else {
        echo "HTTP Status: {$httpCode}\n";
        echo "Resposta: " . substr($response, 0, 500) . "...\n";
        
        $data = json_decode($response, true);
        if ($data && isset($data['status'])) {
            echo "Status do WhatsApp: " . $data['status'] . "\n";
            if (isset($data['error'])) {
                echo "Erro WhatsApp: " . ($data['error']['message'] ?? 'Desconhecido') . "\n";
            }
        }
    }
} else {
    echo "\n⚠️ Não é possível consultar API: whapi_message_id ou sessão não configurados\n";
}

echo "\n=== FIM ===\n";
