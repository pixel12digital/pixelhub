<?php
// Debug completo do router para opportunities
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Router Completo - Opportunities</h1>";

// Inicia sessão
ob_start();
session_start();

// Simula usuário logado
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Test User',
    'email' => 'test@example.com',
    'is_internal' => 1
];

// Simula ambiente produção
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/opportunities';
$_SERVER['SCRIPT_NAME'] = '/public/index.php';
$_SERVER['HTTP_HOST'] = 'hub.pixel12digital.com.br';
$_SERVER['HTTPS'] = 'on';

// Define BASE_PATH
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '');
}

ob_end_clean();

echo "<h2>1. Ambiente</h2>";
echo "<p>REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>BASE_PATH: " . BASE_PATH . "</p>";

echo "<h2>2. Carregando Router</h2>";

try {
    // Carrega ambiente
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    } else {
        spl_autoload_register(function ($class) {
            $prefix = 'PixelHub\\';
            $baseDir = __DIR__ . '/src/';
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) return;
            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) require $file;
        });
    }
    
    \PixelHub\Core\Env::load();
    
    // Carrega router
    $router = new \PixelHub\Core\Router();
    
    echo "<p style='color: green;'>✓ Router carregado</p>";
    
    // Adiciona todas as rotas (simula o index.php)
    require_once 'src/Core/Controller.php';
    require_once 'src/Core/Auth.php';
    
    // Rotas do index.php (só opportunities)
    $router->get('/opportunities', 'OpportunitiesController@index');
    
    echo "<h2>3. Testando dispatch</h2>";
    
    // Captura qualquer erro
    set_error_handler(function ($severity, $message, $file, $line) {
        echo "<p style='color: red;'>Erro PHP: $message em $file:$line</p>";
    });
    
    // Testa o dispatch
    ob_start();
    $router->dispatch('GET', '/opportunities');
    $output = ob_get_clean();
    
    restore_error_handler();
    
    if (strlen($output) > 0) {
        echo "<p style='color: green;'>✓ Router executou com sucesso</p>";
        echo "<p>Output: " . strlen($output) . " bytes</p>";
        
        if (strpos($output, 'CRM / Comercial — Oportunidades') !== false) {
            echo "<p style='color: green;'>✓ Página opportunities gerada!</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Output gerado</p>";
            echo "<pre>" . htmlspecialchars(substr($output, 0, 500)) . "...</pre>";
        }
    } else {
        echo "<p style='color: red;'>✗ Nenhum output</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Exception: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>4. Verificando se OpportunitiesController existe</h2>";

if (file_exists('src/Controllers/OpportunitiesController.php')) {
    echo "<p style='color: green;'>✓ OpportunitiesController.php existe</p>";
} else {
    echo "<p style='color: red;'>✗ OpportunitiesController.php NÃO existe</p>";
}

echo "<h2>Comando para servidor:</h2>";
echo "<pre><code>cd ~/hub.pixel12digital.com.br
php debug_router_complete.php</code></pre>";

?>
