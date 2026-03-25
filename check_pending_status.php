<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

$db = DB::getConnection();

echo "=== VERIFICANDO STATUS PENDING ===\n";

// Buscar o job específico
$stmt = $db->prepare("
    SELECT id, session_name, whapi_message_id, phone
    FROM sdr_dispatch_queue 
    WHERE id = 2
");
$stmt->execute();
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    echo "❌ Job ID 2 não encontrado!\n";
    exit;
}

echo "Job ID: " . $job['id'] . "\n";
echo "Telefone: " . $job['phone'] . "\n";
echo "Message ID: " . $job['whapi_message_id'] . "\n";
echo "Sessão: " . $job['session_name'] . "\n\n";

// Consultar status atualizado
$stmtToken = $db->prepare("
    SELECT whapi_api_token 
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
        if ($data) {
            echo "Status atual: " . ($data['status'] ?? 'N/A') . "\n";
            
            if (isset($data['timestamp'])) {
                $dataHora = date('d/m/Y H:i:s', $data['timestamp']);
                echo "Timestamp: {$dataHora}\n";
            }
            
            if (isset($data['error'])) {
                echo "❌ Erro: " . ($data['error']['message'] ?? 'Desconhecido') . "\n";
            }
            
            // Verificar se há informações de entrega
            if (isset($data['delivery'])) {
                echo "Delivery: " . json_encode($data['delivery']) . "\n";
            }
            
            if (isset($data['read'])) {
                echo "Read: " . json_encode($data['read']) . "\n";
            }
        } else {
            echo "Resposta inválida: " . substr($response, 0, 200) . "...\n";
        }
    } else {
        echo "❌ Erro na consulta API\n";
    }
} else {
    echo "❌ Configuração da sessão não encontrada\n";
}

echo "\n=== RECOMENDAÇÕES ===\n";
echo "1. Se o status continuar 'pending' por horas:\n";
echo "   - O número pode não ter WhatsApp\n";
echo "   - O destinatário pode ter bloqueado o remetente\n";
echo "2. Tente enviar uma mensagem manual para testar\n";
echo "3. Verifique se o número está correto: 5547991953981\n";

echo "\n=== FIM ===\n";
