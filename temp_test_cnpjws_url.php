<?php
echo "=== Testando URLs diferentes do CNPJ.ws ===\n\n";

$cnpj = '64682810000158';

$urls = [
    "https://www.cnpj.ws/cnpj/$cnpj",
    "https://www.cnpj.ws/$cnpj",
    "https://publica.cnpj.ws/cnpj/$cnpj",
];

foreach ($urls as $url) {
    echo "Testando: $url\n";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_USERAGENT => 'PixelHub/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['estabelecimento'])) {
            echo "✓ SUCESSO! Estrutura correta encontrada\n";
            $est = $data['estabelecimento'];
            echo "Email: " . ($est['email'] ?? 'NULL') . "\n";
            echo "Telefone: " . ($est['ddd1'] ?? '') . ($est['telefone1'] ?? '') . "\n";
            break;
        } else {
            echo "JSON decodificado mas estrutura diferente\n";
        }
    }
    
    echo "\n";
}
