<?php
require 'vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__);
$db = DB::getConnection();

echo "=== Buscando COMERCIAL MARIMAR ===\n\n";

$stmt = $db->prepare("
    SELECT 
        id, name, razao_social, cnpj,
        phone_minhareceita, email, website_minhareceita,
        address_minhareceita, city, state,
        source, found_at
    FROM prospecting_results 
    WHERE name LIKE '%MARIMAR%' OR razao_social LIKE '%MARIMAR%'
    LIMIT 1
");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "✓ Empresa encontrada no banco:\n";
    echo "ID: " . $result['id'] . "\n";
    echo "Nome: " . $result['name'] . "\n";
    echo "Razão Social: " . $result['razao_social'] . "\n";
    echo "CNPJ: " . $result['cnpj'] . "\n";
    echo "Telefone (Minha Receita): " . ($result['phone_minhareceita'] ?: 'NULL') . "\n";
    echo "Email: " . ($result['email'] ?: 'NULL') . "\n";
    echo "Website (Minha Receita): " . ($result['website_minhareceita'] ?: 'NULL') . "\n";
    echo "Endereço: " . ($result['address_minhareceita'] ?: 'NULL') . "\n";
    echo "Cidade: " . $result['city'] . "\n";
    echo "Estado: " . $result['state'] . "\n";
    echo "Fonte: " . $result['source'] . "\n";
    echo "Encontrado em: " . $result['found_at'] . "\n";
} else {
    echo "✗ Empresa não encontrada no banco\n";
}

echo "\n=== Testando API Minha Receita ===\n\n";

// CNPJ da imagem: 45.364.877/0002-08
$cnpj = '45364877000208';

echo "Buscando CNPJ: $cnpj\n\n";

$url = "https://minhareceita.org/$cnpj";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_USERAGENT, 'PixelHub/1.0');
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && $response) {
    $data = json_decode($response, true);
    
    if ($data) {
        echo "✓ Resposta da API recebida\n\n";
        echo "Email: " . ($data['email'] ?? 'NULL') . "\n";
        echo "Telefone: " . ($data['telefone'] ?? 'NULL') . "\n";
        echo "Telefone 1: " . ($data['telefone_1'] ?? 'NULL') . "\n";
        echo "DDD Telefone 1: " . ($data['ddd_telefone_1'] ?? 'NULL') . "\n";
        echo "Telefone 2: " . ($data['telefone_2'] ?? 'NULL') . "\n";
        echo "DDD Telefone 2: " . ($data['ddd_telefone_2'] ?? 'NULL') . "\n";
        
        echo "\n=== Dados completos da API ===\n";
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo "✗ Erro ao decodificar JSON\n";
        echo "Resposta bruta: " . substr($response, 0, 500) . "\n";
    }
} else {
    echo "✗ Erro na requisição HTTP\n";
    echo "Código HTTP: $httpCode\n";
}
