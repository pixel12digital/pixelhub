<?php
require 'vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Services\MinhaReceitaClient;

Env::load(__DIR__);

echo "=== Testando API Minha Receita - CNPJ Filial ===\n\n";

// CNPJ da filial (da imagem): 45.364.877/0002-08
$cnpjFilial = '45364877000208';

echo "Buscando CNPJ Filial: $cnpjFilial\n\n";

try {
    $client = new MinhaReceitaClient();
    
    // Usa reflexão para acessar o método privado get()
    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('get');
    $method->setAccessible(true);
    
    $url = "https://minhareceita.org/$cnpjFilial";
    $data = $method->invoke($client, $url);
    
    if ($data) {
        echo "✓ Resposta da API recebida\n\n";
        
        echo "=== Campos de Contato ===\n";
        echo "Email: " . ($data['email'] ?? 'NULL') . "\n";
        echo "Telefone: " . ($data['telefone'] ?? 'NULL') . "\n";
        echo "Telefone 1: " . ($data['telefone_1'] ?? 'NULL') . "\n";
        echo "DDD Telefone 1: " . ($data['ddd_telefone_1'] ?? 'NULL') . "\n";
        echo "Telefone 2: " . ($data['telefone_2'] ?? 'NULL') . "\n";
        echo "DDD Telefone 2: " . ($data['ddd_telefone_2'] ?? 'NULL') . "\n";
        
        echo "\n=== Campos Disponíveis na API ===\n";
        foreach ($data as $key => $value) {
            if (stripos($key, 'email') !== false || stripos($key, 'telefone') !== false || stripos($key, 'fone') !== false) {
                echo "$key: " . (is_array($value) ? json_encode($value) : $value) . "\n";
            }
        }
        
        echo "\n=== Testando normalizeCompany ===\n";
        $reflection = new ReflectionClass($client);
        $normalizeMethod = $reflection->getMethod('normalizeCompany');
        $normalizeMethod->setAccessible(true);
        
        $normalized = $normalizeMethod->invoke($client, $data);
        
        if ($normalized) {
            echo "✓ Normalização bem-sucedida\n";
            echo "Email normalizado: " . ($normalized['email'] ?? 'NULL') . "\n";
            echo "Telefone normalizado: " . ($normalized['phone'] ?? 'NULL') . "\n";
            echo "Telefone secundário: " . ($normalized['telefone_secundario'] ?? 'NULL') . "\n";
        } else {
            echo "✗ Normalização retornou NULL (empresa filtrada)\n";
        }
        
    } else {
        echo "✗ API retornou dados vazios\n";
    }
    
} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
