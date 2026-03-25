<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

$db = DB::getConnection();

echo "=== INTEGRANDO VALIDAÇÃO DE NÚMEROS NO SDR ===\n";

// Função para validar número via API Whapi
function validatePhoneNumber($token, $number) {
    $url = "https://gate.whapi.cloud/contacts";
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
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['contacts'][0])) {
            $contact = $data['contacts'][0];
            return [
                'valid' => ($contact['status'] ?? 'invalid') === 'valid',
                'status' => $contact['status'] ?? 'invalid',
                'number' => $contact['input'] ?? $number
            ];
        }
    }
    
    return ['valid' => false, 'status' => 'error', 'number' => $number];
}

// Testar com o número da Amore Mio
echo "1. Testando validação do número da Amore Mio...\n";

$stmt = $db->prepare("
    SELECT whapi_api_token 
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'whapi' AND session_name = 'orsegups'
");
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if ($config) {
    $apiToken = $config['whapi_api_token'];
    if (strpos($apiToken, 'encrypted:') === 0) {
        $token = CryptoHelper::decrypt(substr($apiToken, 10));
    } else {
        $token = $apiToken;
    }
    
    $testNumber = "5547991953981";
    $validation = validatePhoneNumber($token, $testNumber);
    
    echo sprintf("Número: %s\n", $validation['number']);
    echo sprintf("Status: %s\n", $validation['status']);
    echo sprintf("Válido: %s\n", $validation['valid'] ? '✅ SIM' : '❌ NÃO');
    
    // Testar com um número válido
    echo "\n2. Testando com número válido (seu próprio)...\n";
    $validNumber = "554797146908"; // Número do canal orsegups
    $validation2 = validatePhoneNumber($token, $validNumber);
    
    echo sprintf("Número: %s\n", $validation2['number']);
    echo sprintf("Status: %s\n", $validation2['status']);
    echo sprintf("Válido: %s\n", $validation2['valid'] ? '✅ SIM' : '❌ NÃO');
}

// 3. Proposta de integração no SDR
echo "\n3. COMO INTEGRAR NO SDR DISPATCH SERVICE:\n";
echo "Antes de enviar cada mensagem:\n\n";

echo "// Adicionar coluna na tabela sdr_dispatch_queue\n";
echo "ALTER TABLE sdr_dispatch_queue \n";
echo "ADD COLUMN phone_validated TINYINT(1) DEFAULT NULL COMMENT 'NULL=não validado, 1=válido, 0=inválido',\n";
echo "ADD COLUMN phone_validation_status VARCHAR(20) DEFAULT NULL COMMENT 'valid/invalid/error';\n\n";

echo "// No SdrDispatchService, antes de enviar:\n";
echo "public function validateAndSend(\$job) {\n";
echo "    // Validar número\n";
echo "    \$validation = \$this->validatePhoneNumber(\$job['phone']);\n";
echo "    \n";
echo "    // Atualizar status da validação\n";
echo "    \$this->updateValidationStatus(\$job['id'], \$validation);\n";
echo "    \n";
echo "    // Se inválido, marcar como failed\n";
echo "    if (!\$validation['valid']) {\n";
echo "        \$this->markAsFailed(\$job['id'], 'Número sem WhatsApp: ' . \$validation['status']);\n";
echo "        return false;\n";
echo "    }\n";
echo "    \n";
echo "    // Prosseguir com envio normal\n";
echo "    return \$this->sendMessage(\$job);\n";
echo "}\n\n";

echo "// Benefícios:\n";
echo "✅ Evita envios para números sem WhatsApp\n";
echo "✅ Economiza créditos da API\n";
echo "✅ Melhor taxa de entrega\n";
echo "✅ Relatórios mais precisos\n";
echo "✅ Identificação rápida de problemas\n\n";

echo "4. EXEMPLO DE RELATÓRIO COM VALIDAÇÃO:\n";
echo "+----+------------------+--------------+-------------+------------------+\n";
echo "| ID | Empresa          | Telefone     | Validado    | Status Final     |\n";
echo "+----+------------------+--------------+-------------+------------------+\n";
echo "| 1  | Amore Mio        | 5547991953981 | ❌ Inválido | Não enviado      |\n";
echo "| 2  | Empresa XYZ      | 5547991234567 | ✅ Válido  | Enviado          |\n";
echo "+----+------------------+--------------+-------------+------------------+\n\n";

echo "=== PRÓXIMOS PASSOS ===\n";
echo "1. Adicionar colunas de validação na tabela\n";
echo "2. Modificar SdrDispatchService para validar antes de enviar\n";
echo "3. Adicionar validação em lote para jobs pendentes\n";
echo "4. Atualizar interface para mostrar status de validação\n";

echo "\n=== FIM ===\n";
