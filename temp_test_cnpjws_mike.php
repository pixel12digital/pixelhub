<?php
require 'vendor/autoload.php';

use PixelHub\Core\Env;
use PixelHub\Services\CnpjWsEnrichmentClient;

Env::load(__DIR__);

echo "=== Testando CNPJ.ws - MAIKE MANDEL DE SOUTO ===\n\n";

// CNPJ do print: 64.682.810/0001-58
$cnpj = '64682810000158';

echo "CNPJ: $cnpj\n";
echo "URL: https://www.cnpj.ws/cnpj/$cnpj\n\n";

try {
    $client = new CnpjWsEnrichmentClient();
    
    echo "Chamando getContactData()...\n\n";
    $contactData = $client->getContactData($cnpj);
    
    if ($contactData) {
        echo "✓ Dados encontrados!\n\n";
        echo "Email: " . ($contactData['email'] ?? 'NULL') . "\n";
        echo "Telefone: " . ($contactData['phone'] ?? 'NULL') . "\n";
        echo "Telefone 2: " . ($contactData['phone_secondary'] ?? 'NULL') . "\n";
        echo "Website: " . ($contactData['website'] ?? 'NULL') . "\n";
        echo "Source: " . ($contactData['source'] ?? 'NULL') . "\n";
        
        echo "\n=== Dados completos ===\n";
        print_r($contactData);
    } else {
        echo "✗ getContactData() retornou NULL\n";
    }
    
} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Testando requisição direta ===\n\n";

$url = "https://www.cnpj.ws/cnpj/$cnpj";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_USERAGENT => 'PixelHub/1.0 (Prospecting Tool)',
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "CURL Error: $error\n";
}

if ($httpCode === 200 && $response) {
    $data = json_decode($response, true);
    
    if ($data) {
        echo "\n✓ JSON decodificado com sucesso\n";
        
        echo "\n=== Estrutura da resposta ===\n";
        echo "Chaves principais: " . implode(', ', array_keys($data)) . "\n";
        
        if (isset($data['estabelecimento'])) {
            echo "\n=== Dados do estabelecimento ===\n";
            $est = $data['estabelecimento'];
            echo "Email: " . ($est['email'] ?? 'NULL') . "\n";
            echo "DDD1: " . ($est['ddd1'] ?? 'NULL') . "\n";
            echo "Telefone1: " . ($est['telefone1'] ?? 'NULL') . "\n";
            echo "DDD2: " . ($est['ddd2'] ?? 'NULL') . "\n";
            echo "Telefone2: " . ($est['telefone2'] ?? 'NULL') . "\n";
        } else {
            echo "\n✗ Chave 'estabelecimento' não encontrada\n";
            echo "Estrutura completa:\n";
            print_r($data);
        }
    } else {
        echo "\n✗ Erro ao decodificar JSON\n";
        echo "Resposta bruta (primeiros 500 chars):\n";
        echo substr($response, 0, 500) . "\n";
    }
} else {
    echo "\n✗ Erro na requisição HTTP\n";
    if ($response) {
        echo "Resposta: " . substr($response, 0, 200) . "\n";
    }
}
