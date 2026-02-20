<?php
// Script para verificar o erro específico do servidor
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simula o ambiente do servidor acessando /opportunities
echo "<h1>Debug Server Opportunities - Simulando acesso via web</h1>";

// 1. Carrega o bootstrap completo como o index.php faz
echo "<h2>1. Carregando bootstrap completo</h2>";

// Inicia sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carrega autoload do Composer se existir, senão carrega manualmente
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "<p>✓ Autoload do Composer carregado</p>";
} else {
    // Autoload manual simples
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/src/';
        
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    });
    echo "<p>✓ Autoload manual carregado</p>";
}

// Carrega classes necessárias
try {
    require_once 'src/Core/Env.php';
    require_once 'src/Core/Router.php';
    require_once 'src/Core/Security.php';
    
    // Define BASE_PATH como se fosse acesso web
    $_SERVER['SCRIPT_NAME'] = '/painel.pixel12digital/public/index.php';
    $_SERVER['REQUEST_URI'] = '/painel.pixel12digital/opportunities';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    // Simula o cálculo do BASE_PATH do index.php
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    
    if (substr($scriptDir, -7) === '/public') {
        $scriptDir = substr($scriptDir, 0, -7);
    }
    
    if (!defined('BASE_PATH')) {
        if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '') {
            define('BASE_PATH', '');
        } else {
            define('BASE_PATH', $scriptDir);
        }
    }
    
    echo "<p>✓ BASE_PATH definido: " . BASE_PATH . "</p>";
    
    // Carrega ambiente
    \PixelHub\Core\Env::load();
    echo "<p>✓ Ambiente carregado</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro no bootstrap: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}

// 2. Testa autenticação
echo "<h2>2. Testando autenticação</h2>";
try {
    require_once 'src/Core/Auth.php';
    
    // Simula usuário logado
    $_SESSION['user'] = [
        'id' => 1,
        'name' => 'Test User',
        'email' => 'test@example.com',
        'is_internal' => 1
    ];
    
    echo "<p>✓ Sessão de usuário simulada</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro na autenticação: " . $e->getMessage() . "</p>";
}

// 3. Testa o controller diretamente
echo "<h2>3. Testando OpportunitiesController</h2>";
try {
    require_once 'src/Controllers/OpportunitiesController.php';
    
    $controller = new \PixelHub\Controllers\OpportunitiesController();
    echo "<p>✓ Controller instanciado</p>";
    
    // Tenta executar o método index
    ob_start();
    $controller->index();
    $output = ob_get_clean();
    
    echo "<p>✓ Método index() executado</p>";
    echo "<p>✓ Output gerado: " . strlen($output) . " caracteres</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro no controller: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// 4. Verifica configurações do servidor que podem afetar
echo "<h2>4. Verificando configurações do servidor</h2>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>Memory Limit: " . ini_get('memory_limit') . "</p>";
echo "<p>Max Execution Time: " . ini_get('max_execution_time') . "</p>";
echo "<p>Display Errors: " . (ini_get('display_errors') ? 'On' : 'Off') . "</p>";
echo "<p>Error Reporting: " . error_reporting() . "</p>";
echo "<p>Log Errors: " . (ini_get('log_errors') ? 'On' : 'Off') . "</p>";
echo "<p>Error Log: " . ini_get('error_log') . "</p>";

// 5. Verifica se há algum erro específico do Apache/Nginx
echo "<h2>5. Verificando variáveis do servidor</h2>";
$server_vars = ['SERVER_SOFTWARE', 'DOCUMENT_ROOT', 'SCRIPT_FILENAME', 'HTTP_HOST', 'HTTPS'];
foreach ($server_vars as $var) {
    $value = $_SERVER[$var] ?? 'N/A';
    echo "<p>$var: $value</p>";
}

// 6. Testa se o problema está na view
echo "<h2>6. Testando renderização da view</h2>";
try {
    // Simula os dados que o controller passa para a view
    $opportunities = \PixelHub\Services\OpportunityService::list([]);
    $counts = \PixelHub\Services\OpportunityService::countByStatus();
    
    echo "<p>✓ Dados obtidos: " . count($opportunities) . " oportunidades</p>";
    
    // Tenta carregar a view diretamente
    $viewFile = __DIR__ . '/views/opportunities/index.php';
    if (file_exists($viewFile)) {
        echo "<p>✓ Arquivo da view existe</p>";
        
        // Simula as variáveis que a view espera
        $filters = [];
        $users = [];
        $products = [];
        $sources = [];
        $stages = \PixelHub\Services\OpportunityService::STAGES;
        
        ob_start();
        include $viewFile;
        $viewOutput = ob_get_clean();
        
        echo "<p>✓ View renderizada: " . strlen($viewOutput) . " caracteres</p>";
        
        // Verifica se há algum erro de variável indefinida
        if (strpos($viewOutput, 'Undefined variable') !== false) {
            echo "<p style='color: orange;'>⚠ Possíveis variáveis indefinidas na view</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Arquivo da view NÃO existe</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro na view: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>Conclusão</h2>";
echo "<p>Se este script funcionar localmente, o problema está no ambiente do servidor.</p>";
echo "<p>Verifique:</p>";
echo "<ul>";
echo "<li>Se o .env está configurado corretamente no servidor</li>";
echo "<li>Se as permissões dos arquivos estão corretas</li>";
echo "<li>Se o módulo rewrite do Apache/Nginx está funcionando</li>";
echo "<li>Se há algum erro no log de erro do servidor web</li>";
echo "</ul>";

?>
