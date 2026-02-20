<?php
// Teste final para verificar se o erro 500 foi corrigido
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Teste Final - Opportunities (Erro 500 Corrigido)</h1>";

// Simula exatamente o ambiente do servidor
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/painel.pixel12digital/opportunities';
$_SERVER['SCRIPT_NAME'] = '/painel.pixel12digital/public/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';

// Inicia sessão (simulando usuário logado)
session_start();
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Test User',
    'email' => 'test@example.com',
    'is_internal' => 1
];

// Carrega o bootstrap completo
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
    
    // Carrega ambiente
    \PixelHub\Core\Env::load();
    
    // Carrega o router
    require_once 'src/Core/Router.php';
    require_once 'src/Core/Security.php';
    
    echo "<p style='color: green;'>✓ Bootstrap carregado com sucesso</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro no bootstrap: " . $e->getMessage() . "</p>";
    exit;
}

// Testa o controller
try {
    require_once 'src/Controllers/OpportunitiesController.php';
    
    $controller = new \PixelHub\Controllers\OpportunitiesController();
    echo "<p style='color: green;'>✓ Controller instanciado</p>";
    
    // Executa o método index
    ob_start();
    $controller->index();
    $output = ob_get_clean();
    
    // Verifica o output
    if (strlen($output) > 1000) {
        echo "<p style='color: green;'>✓ Página opportunities gerada com sucesso!</p>";
        echo "<p>✓ Tamanho do output: " . number_format(strlen($output)) . " bytes</p>";
        
        // Verifica elementos chave
        $checks = [
            'CRM / Comercial — Oportunidades' => 'Título da página',
            'Nova Oportunidade' => 'Botão de criar',
            'Lista' => 'View lista',
            'Kanban' => 'View Kanban',
            'Ativas' => 'Contador de oportunidades'
        ];
        
        echo "<h3>Elementos encontrados:</h3><ul>";
        foreach ($checks as $text => $desc) {
            if (strpos($output, $text) !== false) {
                echo "<li style='color: green;'>✓ $desc</li>";
            } else {
                echo "<li style='color: orange;'>⚠ $desc (não encontrado)</li>";
            }
        }
        echo "</ul>";
        
    } else {
        echo "<p style='color: orange;'>⚠ Output pequeno: " . htmlspecialchars($output) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro no controller: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>Resumo</h2>";
echo "<p><strong>Problema identificado:</strong> O erro 500 acontecia porque o Auth::requireInternal() tentava redirecionar com header() depois que os headers já haviam sido enviados.</p>";
echo "<p><strong>Solução aplicada:</strong> Adicionada verificação !headers_sent() no Auth.php, com fallback para JavaScript redirect.</p>";
echo "<p><strong>Próximos passos:</strong></p>";
echo "<ol>";
echo "<li>Faça upload dos arquivos modificados para o servidor:</li>";
echo "<ul>";
echo "<li>src/Core/Auth.php (já corrigido)</li>";
echo "</ul>";
echo "<li>Teste o acesso no servidor: http://seusite.com/painel.pixel12digital/opportunities</li>";
echo "<li>Se ainda houver erro, verifique o log de erros do servidor</li>";
echo "</ol>";

?>
