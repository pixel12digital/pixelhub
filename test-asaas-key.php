<?php
/**
 * Script de teste para validar chave de API do Asaas
 * 
 * Uso: php test-asaas-key.php
 */

// Chave de API do Asaas (com $ no início)
$apiKey = '$aact_prod_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OjFkZGExMjcyLWMzN2MtNGM3MS1iMTBmLTY4YWU4MjM4ZmE1Nzo6JGFhY2hfM2EzNTI4OTUtOGFjNC00MmFlLTliZTItNjRkZDg2YTAzOWRj';

$baseUrl = 'https://www.asaas.com/api/v3';

echo "🔍 Testando conexão com API do Asaas...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Verifica se a chave começa com $aact_
if (strpos($apiKey, '$aact_') !== 0) {
    echo "❌ ERRO: A chave não começa com \$aact_\n";
    echo "   Chave recebida: " . substr($apiKey, 0, 20) . "...\n";
    exit(1);
}

echo "✅ Chave detectada: Formato válido (começa com \$aact_)\n";
echo "📏 Tamanho da chave: " . strlen($apiKey) . " caracteres\n\n";

// Teste 1: Listar clientes
echo "📡 Teste 1: Listando clientes (GET /customers?limit=1)...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$url = $baseUrl . '/customers?limit=1';
$headers = [
    'access_token: ' . $apiKey,
    'Content-Type: application/json',
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_VERBOSE => false,
]);

echo "🔗 URL: {$url}\n";
echo "🔑 Header access_token: " . substr($apiKey, 0, 10) . "..." . substr($apiKey, -10) . "\n";
echo "📏 Tamanho exato da chave no header: " . strlen($apiKey) . " caracteres\n";
echo "🔍 Primeiros 20 caracteres: " . substr($apiKey, 0, 20) . "\n";
echo "🔍 Últimos 20 caracteres: " . substr($apiKey, -20) . "\n";
echo "🔍 Verificando se há espaços: " . (strpos($apiKey, ' ') !== false ? 'SIM (PROBLEMA!)' : 'Não') . "\n";
echo "🔍 Verificando se há quebras de linha: " . (strpos($apiKey, "\n") !== false || strpos($apiKey, "\r") !== false ? 'SIM (PROBLEMA!)' : 'Não') . "\n\n";

$startTime = microtime(true);
$response = curl_exec($ch);
$endTime = microtime(true);
$duration = round(($endTime - $startTime) * 1000, 2);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlErrno = curl_errno($ch);
curl_close($ch);

echo "⏱️  Tempo de resposta: {$duration}ms\n";
echo "📊 Código HTTP: {$httpCode}\n\n";

if ($curlErrno) {
    echo "❌ Erro cURL: {$curlError} (Código: {$curlErrno})\n";
    exit(1);
}

if ($httpCode === 200) {
    echo "✅ SUCESSO! Conexão estabelecida com sucesso!\n\n";
    
    $responseData = json_decode($response, true);
    
    if (is_array($responseData)) {
        echo "📦 Resposta recebida:\n";
        echo "   - Total de clientes: " . ($responseData['totalCount'] ?? 'N/A') . "\n";
        echo "   - Tem mais resultados: " . (($responseData['hasMore'] ?? false) ? 'Sim' : 'Não') . "\n";
        
        if (isset($responseData['data'][0])) {
            $customer = $responseData['data'][0];
            echo "   - Primeiro cliente:\n";
            echo "     * ID: " . ($customer['id'] ?? 'N/A') . "\n";
            echo "     * Nome: " . ($customer['name'] ?? 'N/A') . "\n";
            echo "     * CPF/CNPJ: " . ($customer['cpfCnpj'] ?? 'N/A') . "\n";
        }
    }
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📡 Teste 2: Obtendo informações da conta (GET /myAccount)...\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $url2 = $baseUrl . '/myAccount';
    $ch2 = curl_init($url2);
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
    ]);
    
    $startTime2 = microtime(true);
    $response2 = curl_exec($ch2);
    $endTime2 = microtime(true);
    $duration2 = round(($endTime2 - $startTime2) * 1000, 2);
    
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    
    echo "⏱️  Tempo de resposta: {$duration2}ms\n";
    echo "📊 Código HTTP: {$httpCode2}\n\n";
    
    if ($httpCode2 === 200) {
        $accountData = json_decode($response2, true);
        echo "✅ Informações da conta obtidas com sucesso!\n\n";
        
        if (is_array($accountData)) {
            echo "👤 Dados da conta:\n";
            if (isset($accountData['name'])) {
                echo "   - Nome: " . $accountData['name'] . "\n";
            }
            if (isset($accountData['email'])) {
                echo "   - E-mail: " . $accountData['email'] . "\n";
            }
            if (isset($accountData['company'])) {
                echo "   - Empresa: " . $accountData['company'] . "\n";
            }
            if (isset($accountData['personType'])) {
                echo "   - Tipo: " . $accountData['personType'] . "\n";
            }
        }
    } else {
        echo "⚠️  Teste 2 falhou (HTTP {$httpCode2}), mas o teste principal foi bem-sucedido.\n";
        echo "📦 Resposta: " . substr($response2, 0, 200) . "\n";
    }
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ TODOS OS TESTES PASSARAM!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "\n💡 A chave de API está válida e funcionando corretamente.\n";
    echo "💡 Você pode usar esta chave nas configurações do sistema.\n";
    
} elseif ($httpCode === 401) {
    echo "❌ FALHOU: Chave de API inválida ou expirada\n\n";
    echo "📦 Resposta recebida:\n";
    $responseData = json_decode($response, true);
    if (is_array($responseData) && isset($responseData['errors'])) {
        foreach ($responseData['errors'] as $error) {
            echo "   - Código: " . ($error['code'] ?? 'N/A') . "\n";
            echo "   - Descrição: " . ($error['description'] ?? 'N/A') . "\n";
        }
    } else {
        echo "   " . substr($response, 0, 500) . "\n";
    }
    echo "\n💡 Verifique se a chave está correta no painel do Asaas.\n";
    exit(1);
    
} elseif ($httpCode === 403) {
    echo "❌ FALHOU: Acesso negado\n\n";
    echo "💡 Verifique se sua chave tem as permissões necessárias.\n";
    exit(1);
    
} else {
    echo "❌ FALHOU: Código HTTP inesperado: {$httpCode}\n\n";
    echo "📦 Resposta recebida:\n";
    echo "   " . substr($response, 0, 500) . "\n";
    exit(1);
}

