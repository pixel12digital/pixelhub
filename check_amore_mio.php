<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

$db = DB::getConnection();

echo "=== VERIFICANDO MENSAGEM AMORE MIO ===\n";

// Buscar jobs da Amore Mio
$stmt = $db->prepare("
    SELECT id, result_id, session_name, phone, establishment_name, message, status, 
           scheduled_at, sent_at, whapi_message_id, error, attempts
    FROM sdr_dispatch_queue 
    WHERE establishment_name LIKE '%Amore Mio%' OR phone LIKE '%Amore%'
    ORDER BY created_at DESC
");
$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$jobs) {
    echo "❌ Nenhum job encontrado para 'Amore Mio'\n";
    
    // Buscar por telefone similar
    echo "\nBuscando por telefones similares...\n";
    $stmt = $db->prepare("
        SELECT id, result_id, session_name, phone, establishment_name, status, scheduled_at, sent_at, whapi_message_id
        FROM sdr_dispatch_queue 
        WHERE phone LIKE '%5547%' OR phone LIKE '%5548%'
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

foreach ($jobs as $job) {
    echo "\n========================================\n";
    echo sprintf("Job ID: %d\n", $job['id']);
    echo sprintf("Resultado ID: %d\n", $job['result_id']);
    echo sprintf("Estabelecimento: %s\n", $job['establishment_name']);
    echo sprintf("Telefone: %s\n", $job['phone']);
    echo sprintf("Sessão: %s\n", $job['session_name']);
    echo sprintf("Status: %s\n", $job['status']);
    echo sprintf("Agendado: %s\n", $job['scheduled_at']);
    echo sprintf("Enviado: %s\n", $job['sent_at'] ?? 'N/A');
    echo sprintf("Whapi Message ID: %s\n", $job['whapi_message_id'] ?? 'N/A');
    echo sprintf("Tentativas: %d\n", $job['attempts']);
    
    if ($job['error']) {
        echo sprintf("Erro: %s\n", $job['error']);
    }
    
    // Se tem whapi_message_id, consultar status na API
    if ($job['whapi_message_id'] && $job['session_name']) {
        echo "\nConsultando status na API Whapi...\n";
        
        // Pegar token da sessão
        $stmtToken = $db->prepare("
            SELECT whapi_api_token, whapi_channel_id 
            FROM whatsapp_provider_configs 
            WHERE provider_type = 'whapi' AND session_name = ?
        ");
        $stmtToken->execute([$job['session_name']]);
        $config = $stmtToken->fetch(PDO::FETCH_ASSOC);
        
        if ($config && $config['whapi_api_token']) {
            // Descriptografar token
            $apiToken = $config['whapi_api_token'];
            if (!empty($apiToken) && strpos($apiToken, 'encrypted:') === 0) {
                $token = CryptoHelper::decrypt(substr($apiToken, 10));
            } else {
                $token = $apiToken;
            }
            
            $url = "https://gate.whapi.cloud/messages/" . $job['whapi_message_id'];
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
            
            echo "HTTP Status: {$httpCode}\n";
            
            if ($response !== false) {
                $data = json_decode($response, true);
                if ($data && isset($data['status'])) {
                    echo "Status WhatsApp: " . $data['status'] . "\n";
                    if (isset($data['error'])) {
                        echo "Erro WhatsApp: " . ($data['error']['message'] ?? 'Desconhecido') . "\n";
                    }
                } else {
                    echo "Resposta: " . substr($response, 0, 200) . "...\n";
                }
            } else {
                echo "Erro na consulta API\n";
            }
        } else {
            echo "❌ Configuração da sessão não encontrada\n";
        }
    }
}

echo "\n=== FIM ===\n";
