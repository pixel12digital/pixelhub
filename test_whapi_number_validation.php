<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

$db = DB::getConnection();

echo "=== TESTANDO API DE VALIDAÇÃO DE NÚMEROS WHAPI ===\n";

// Pegar token da sessão orsegups
$stmt = $db->prepare("
    SELECT whapi_api_token, whapi_channel_id 
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'whapi' AND session_name = 'orsegups'
");
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config || !$config['whapi_api_token']) {
    echo "❌ Configuração não encontrada!\n";
    exit;
}

// Descriptografar token
$apiToken = $config['whapi_api_token'];
if (!empty($apiToken) && strpos($apiToken, 'encrypted:') === 0) {
    $token = CryptoHelper::decrypt(substr($apiToken, 10));
} else {
    $token = $apiToken;
}

echo "Token: " . substr($token, 0, 20) . "...\n";
echo "Channel ID: " . $config['whapi_channel_id'] . "\n\n";

// Testar validação do número da Amore Mio
$testNumber = "5547991953981";

echo "Testando validação do número: {$testNumber}\n";

// Preparar requisição para API de validação
$url = "https://gate.whapi.cloud/contacts";
$data = [
    'contacts' => [$testNumber]
];

$headers = [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: {$httpCode}\n";
echo "Resposta: " . substr($response, 0, 500) . "...\n\n";

if ($response !== false) {
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200 && isset($responseData['contacts'])) {
        echo "✅ API de validação funcionando!\n\n";
        
        foreach ($responseData['contacts'] as $contact) {
            echo "Número: " . $contact['contact'] . "\n";
            echo "Status: " . ($contact['exists'] ? '✅ TEM WhatsApp' : '❌ NÃO TEM WhatsApp') . "\n";
            
            if (isset($contact['exists'])) {
                if ($contact['exists']) {
                    echo "→ Pode receber mensagens\n";
                } else {
                    echo "→ NÃO pode receber mensagens (não tem WhatsApp)\n";
                }
            }
            
            if (isset($contact['error'])) {
                echo "Erro: " . $contact['error'] . "\n";
            }
            
            echo "\n";
        }
    } else {
        echo "❌ Erro na resposta da API\n";
        
        // Tentar endpoint alternativo
        echo "\nTentando endpoint alternativo...\n";
        testAlternativeEndpoint($token, $testNumber);
    }
} else {
    echo "❌ Erro na requisição\n";
}

// Função para testar endpoint alternativo
function testAlternativeEndpoint($token, $number) {
    echo "Testando endpoint: /validate/contacts\n";
    
    $url = "https://gate.whapi.cloud/validate/contacts";
    $data = [
        'contacts' => [$number]
    ];
    
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Status: {$httpCode}\n";
    echo "Resposta: " . substr($response, 0, 300) . "...\n\n";
}

echo "\n=== INTEGRAÇÃO SUGERIDA ===\n";
echo "1. Antes de enviar mensagens SDR, verificar se o número tem WhatsApp\n";
echo "2. Se não tiver, marcar como 'invalid_number' e não enviar\n";
echo "3. Se tiver, prosseguir com envio normal\n";
echo "4. Salvar status de validação na tabela sdr_dispatch_queue\n\n";

echo "Endpoints para usar:\n";
echo "- POST /contacts (principal)\n";
echo "- POST /validate/contacts (alternativo)\n\n";

echo "Resposta esperada:\n";
echo "{\n";
echo "  \"contacts\": [\n";
echo "    {\n";
echo "      \"contact\": \"5547991953981\",\n";
echo "      \"exists\": false\n";
echo "    }\n";
echo "  ]\n";
echo "}\n";

echo "\n=== FIM ===\n";
