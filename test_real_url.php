<?php
// Teste com a URL real
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Teste URL Real: /opportunities</h1>";

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

// URL REAL - sem /painel.pixel12digital
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/opportunities';
$_SERVER['SCRIPT_NAME'] = '/public/index.php';
$_SERVER['HTTP_HOST'] = 'hub.pixel12digital.com.br';
$_SERVER['HTTPS'] = 'on';

// Limpa output
ob_end_clean();

echo "<h2>URL: /opportunities</h2>";

// Define BASE_PATH correto
$scriptDir = '/public';
if (substr($scriptDir, -7) === '/public') {
    $scriptDir = substr($scriptDir, 0, -7);
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '');
}

echo "<p>BASE_PATH: " . BASE_PATH . "</p>";
echo "<p>REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "</p>";

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
    
    // Testa o router
    require_once 'src/Core/Router.php';
    
    echo "<h2>Testando router...</h2>";
    
    // Captura output
    ob_start();
    include 'public/index.php';
    $output = ob_get_clean();
    
    if (strlen($output) > 100) {
        echo "<p style='color: green;'>✓ Funcionou com URL /opportunities!</p>";
        echo "<p>Tamanho: " . strlen($output) . " bytes</p>";
        
        if (strpos($output, 'CRM / Comercial — Oportunidades') !== false) {
            echo "<p style='color: green;'>✓ Página opportunities carregada!</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Output gerado</p>";
            echo "<pre>" . htmlspecialchars(substr($output, 0, 500)) . "...</pre>";
        }
    } else {
        echo "<p style='color: red;'>✗ Output vazio</p>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Exception: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>Comando para servidor:</h2>";
echo "<pre><code>cd ~/hub.pixel12digital.com.br
php test_real_url.php</code></pre>";

?>
