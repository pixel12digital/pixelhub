<?php
// Teste direto da página opportunities
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Teste Direto - Opportunities</h1>";

// Simula ambiente exato do servidor
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/painel.pixel12digital/opportunities';
$_SERVER['SCRIPT_NAME'] = '/painel.pixel12digital/public/index.php';
$_SERVER['HTTP_HOST'] = 'hub.pixel12digital.com.br';
$_SERVER['HTTPS'] = 'on';

// Inicia sessão
session_start();

// Simula usuário logado
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Test User',
    'email' => 'test@example.com',
    'is_internal' => 1
];

echo "<h2>Teste 1: Carregar bootstrap completo</h2>";

try {
    // Carrega o index.php completo
    ob_start();
    include 'public/index.php';
    $output = ob_get_clean();
    
    if (strlen($output) > 100) {
        echo "<p style='color: green;'>✓ Index.php carregado</p>";
        echo "<p>Tamanho: " . strlen($output) . " bytes</p>";
        
        if (strpos($output, 'CRM / Comercial — Oportunidades') !== false) {
            echo "<p style='color: green;'>✓ Página opportunities funcionando!</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Output gerado mas não é a página opportunities</p>";
            echo "<pre>" . htmlspecialchars(substr($output, 0, 500)) . "...</pre>";
        }
    } else {
        echo "<p style='color: red;'>✗ Output vazio ou pequeno</p>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Exception: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>Teste 2: Comparar com outra página que funciona</h2>";

// Testa com outra rota que funciona
$_SERVER['REQUEST_URI'] = '/painel.pixel12digital/communication-hub';

try {
    ob_start();
    include 'public/index.php';
    $output2 = ob_get_clean();
    
    if (strlen($output2) > 100) {
        echo "<p style='color: green;'>✓ Communication-hub funciona</p>";
    } else {
        echo "<p style='color: red;'>✗ Communication-hub também falha</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Communication-hub Exception: " . $e->getMessage() . "</p>";
}

echo "<h2>Comando para executar no servidor:</h2>";
echo "<pre><code>cd ~/hub.pixel12digital.com.br
php test_opportunities_direct.php</code></pre>";

?>
