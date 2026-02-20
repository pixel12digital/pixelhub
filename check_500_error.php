<?php
// Script simples para verificar o erro 500
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Verificação Erro 500 - Opportunities</h1>";

// Teste 1: Verifica se o arquivo .env existe
echo "<h2>Teste 1: Arquivo .env</h2>";
if (file_exists('.env')) {
    echo "<p style='color: green;'>✓ .env existe</p>";
} else {
    echo "<p style='color: red;'>✗ .env NÃO existe - este pode ser o problema!</p>";
}

// Teste 2: Verifica se consegue carregar o ambiente
echo "<h2>Teste 2: Carregar ambiente</h2>";
try {
    require_once 'src/Core/Env.php';
    \PixelHub\Core\Env::load();
    echo "<p style='color: green;'>✓ Ambiente carregado</p>";
    
    // Verifica variáveis críticas
    $dbHost = \PixelHub\Core\Env::get('DB_HOST');
    $dbName = \PixelHub\Core\Env::get('DB_NAME');
    echo "<p>DB_HOST: $dbHost</p>";
    echo "<p>DB_NAME: $dbName</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro ao carregar ambiente: " . $e->getMessage() . "</p>";
}

// Teste 3: Verifica conexão com banco
echo "<h2>Teste 3: Conexão com banco</h2>";
try {
    require_once 'src/Core/DB.php';
    $db = \PixelHub\Core\DB::getConnection();
    echo "<p style='color: green;'>✓ Conexão OK</p>";
    
    // Teste simples
    $stmt = $db->query("SELECT 1");
    echo "<p style='color: green;'>✓ Query simples OK</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro na conexão: " . $e->getMessage() . "</p>";
}

// Teste 4: Verifica o controller
echo "<h2>Teste 4: Controller</h2>";
try {
    require_once 'src/Core/Auth.php';
    require_once 'src/Core/Controller.php';
    require_once 'src/Services/OpportunityService.php';
    require_once 'src/Controllers/OpportunitiesController.php';
    
    echo "<p style='color: green;'>✓ Classes carregadas</p>";
    
    // Simula sessão
    session_start();
    $_SESSION['user'] = ['id' => 1, 'is_internal' => 1];
    
    $controller = new \PixelHub\Controllers\OpportunitiesController();
    echo "<p style='color: green;'>✓ Controller instanciado</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro no controller: " . $e->getMessage() . "</p>";
}

// Teste 5: Simula a rota
echo "<h2>Teste 5: Simulação da rota /opportunities</h2>";
try {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/painel.pixel12digital/opportunities';
    $_SERVER['SCRIPT_NAME'] = '/painel.pixel12digital/public/index.php';
    
    // Captura output
    ob_start();
    $controller->index();
    $output = ob_get_clean();
    
    if (strlen($output) > 100) {
        echo "<p style='color: green;'>✓ Rota executou com sucesso (" . strlen($output) . " bytes)</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Output pequeno: " . htmlspecialchars($output) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro na rota: " . $e->getMessage() . "</p>";
}

echo "<h2>Diagnóstico Final</h2>";
echo "<p>Se todos os testes acima passaram, o problema está no ambiente do servidor.</p>";
echo "<p><strong>Verifique no servidor:</strong></p>";
echo "<ul>";
echo "<li>Se o arquivo .env existe e está com as permissões corretas</li>";
echo "<li>Se as variáveis de ambiente estão configuradas</li>";
echo "<li>Se o banco de dados está acessível</li>";
echo "<li>Se há algum erro no log do servidor web</li>";
echo "<li>Se o módulo rewrite está ativo</li>";
echo "</ul>";

?>
