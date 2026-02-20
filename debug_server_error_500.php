<?php
// Script para capturar o erro real no servidor
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Erro 500 - Servidor</h1>";

// Simula exatamente o ambiente do servidor
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/painel.pixel12digital/opportunities';
$_SERVER['SCRIPT_NAME'] = '/painel.pixel12digital/public/index.php';
$_SERVER['HTTP_HOST'] = 'hub.pixel12digital.com.br';
$_SERVER['HTTPS'] = 'on';

// Inicia sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simula usuário logado (se não estiver)
if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = [
        'id' => 1,
        'name' => 'Test User',
        'email' => 'test@example.com',
        'is_internal' => 1
    ];
}

echo "<h2>1. Verificando ambiente</h2>";
echo "<p>REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>Memory Limit: " . ini_get('memory_limit') . "</p>";

// Testa conexão com banco
echo "<h2>2. Testando conexão com banco</h2>";
try {
    require_once 'src/Core/Env.php';
    \PixelHub\Core\Env::load();
    require_once 'src/Core/DB.php';
    $db = \PixelHub\Core\DB::getConnection();
    echo "<p style='color: green;'>✓ Conexão OK</p>";
    
    // Testa query simples
    $stmt = $db->query("SELECT 1");
    echo "<p style='color: green;'>✓ Query simples OK</p>";
    
    // Verifica colunas de tracking
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM information_schema.columns 
                          WHERE table_schema = DATABASE() 
                          AND table_name = 'opportunities' 
                          AND column_name = 'tracking_code'");
    $stmt->execute();
    $count = $stmt->fetch()['count'];
    echo "<p>Coluna tracking_code: " . ($count > 0 ? "✓ existe" : "✗ não existe") . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro no banco: " . $e->getMessage() . "</p>";
}

// Testa carregar as classes
echo "<h2>3. Carregando classes</h2>";
try {
    // Autoload
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
    
    // Define BASE_PATH
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if (substr($scriptDir, -7) === '/public') {
        $scriptDir = substr($scriptDir, 0, -7);
    }
    if (!defined('BASE_PATH')) {
        define('BASE_PATH', ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '') ? '' : $scriptDir);
    }
    
    echo "<p style='color: green;'>✓ Autoload OK</p>";
    echo "<p>BASE_PATH: " . BASE_PATH . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro no autoload: " . $e->getMessage() . "</p>";
}

// Testa o controller
echo "<h2>4. Testando OpportunitiesController</h2>";
try {
    require_once 'src/Core/Auth.php';
    require_once 'src/Core/Controller.php';
    require_once 'src/Services/OpportunityService.php';
    require_once 'src/Services/OpportunityProductService.php';
    require_once 'src/Services/ContactService.php';
    require_once 'src/Services/LeadService.php';
    require_once 'src/Services/PhoneNormalizer.php';
    require_once 'src/Services/TrackingCodesService.php';
    require_once 'src/Controllers/OpportunitiesController.php';
    
    echo "<p style='color: green;'>✓ Classes carregadas</p>";
    
    // Instancia o controller
    $controller = new \PixelHub\Controllers\OpportunitiesController();
    echo "<p style='color: green;'>✓ Controller instanciado</p>";
    
    // Testa o método index
    echo "<h2>5. Executando controller->index()</h2>";
    
    // Captura qualquer erro
    set_error_handler(function ($severity, $message, $file, $line) {
        echo "<p style='color: red;'>Erro PHP: $message em $file:$line</p>";
    });
    
    ob_start();
    $controller->index();
    $output = ob_get_clean();
    
    restore_error_handler();
    
    if (strlen($output) > 0) {
        echo "<p style='color: green;'>✓ Output gerado: " . strlen($output) . " bytes</p>";
        
        // Verifica se contém o título esperado
        if (strpos($output, 'CRM / Comercial — Oportunidades') !== false) {
            echo "<p style='color: green;'>✓ Título encontrado</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Título não encontrado</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Nenhum output gerado</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Exception no controller: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "<p style='color: red;'>✗ Error no controller: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>Instruções para o servidor</h2>";
echo "<p>Execute este script no servidor para ver o erro real:</p>";
echo "<pre><code>cd ~/hub.pixel12digital.com.br<br>php debug_server_error_500.php</code></pre>";

?>
