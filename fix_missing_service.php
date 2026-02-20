<?php
// Correção imediata para o OpportunityProductService
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Verificando OpportunityProductService</h1>";

// Verifica se o arquivo existe
if (file_exists('src/Services/OpportunityProductService.php')) {
    echo "<p style='color: green;'>✓ Arquivo existe localmente</p>";
    
    // Verifica se pode carregar
    try {
        require_once 'src/Services/OpportunityProductService.php';
        echo "<p style='color: green;'>✓ Arquivo carregado com sucesso</p>";
        
        // Testa se a classe existe
        if (class_exists('PixelHub\Services\OpportunityProductService')) {
            echo "<p style='color: green;'>✓ Classe OpportunityProductService encontrada</p>";
        } else {
            echo "<p style='color: red;'>✗ Classe não encontrada</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Erro ao carregar: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Arquivo NÃO existe localmente</p>";
}

echo "<h2>Solução:</h2>";
echo "<p>Execute no servidor:</p>";
echo "<pre><code># Verificar se o arquivo existe no servidor
ls -la /home/pixel12digital/hub.pixel12digital.com.br/src/Services/OpportunityProductService.php

# Se não existir, fazer pull
git pull origin main

# Verificar permissões
ls -la /home/pixel12digital/hub.pixel12digital.com.br/src/Services/OpportunityProductService.php</code></pre>";

?>
