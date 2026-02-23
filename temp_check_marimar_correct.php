<?php
require 'vendor/autoload.php';

use PixelHub\Core\Env;
use PixelHub\Services\MinhaReceitaClient;

Env::load(__DIR__);

echo "=== Testando CNPJ Correto: 15.354.872/0002-06 ===\n\n";

$cnpj = '15354872000206';

try {
    $client = new MinhaReceitaClient();
    
    // Usa reflexão para acessar o método privado get()
    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('get');
    $method->setAccessible(true);
    
    $url = "https://minhareceita.org/$cnpj";
    echo "URL: $url\n\n";
    
    $data = $method->invoke($client, $url);
    
    if ($data) {
        echo "✓ Resposta da API recebida\n\n";
        
        echo "=== Todos os campos relacionados a contato ===\n";
        foreach ($data as $key => $value) {
            if (stripos($key, 'email') !== false || 
                stripos($key, 'telefone') !== false || 
                stripos($key, 'fone') !== false ||
                stripos($key, 'ddd') !== false) {
                $displayValue = is_array($value) ? json_encode($value) : ($value ?: 'NULL');
                echo "$key: $displayValue\n";
            }
        }
        
        echo "\n=== Testando normalizeCompany ===\n";
        $normalizeMethod = $reflection->getMethod('normalizeCompany');
        $normalizeMethod->setAccessible(true);
        
        $normalized = $normalizeMethod->invoke($client, $data);
        
        if ($normalized) {
            echo "✓ Normalização bem-sucedida\n\n";
            echo "Email: " . ($normalized['email'] ?? 'NULL') . "\n";
            echo "Phone: " . ($normalized['phone'] ?? 'NULL') . "\n";
            echo "Telefone Secundário: " . ($normalized['telefone_secundario'] ?? 'NULL') . "\n";
            echo "Website: " . ($normalized['website'] ?? 'NULL') . "\n";
        } else {
            echo "✗ Normalização retornou NULL\n";
        }
        
    } else {
        echo "✗ API retornou dados vazios\n";
    }
    
} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
}
