<?php
// Teste opportunities com usuário autenticado completo
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Teste Opportunities Autenticado</h1>";

// Inicia sessão ANTES de qualquer output
ob_start();
session_start();

// Simula usuário autenticado COMPLETO na chave correta
$_SESSION['pixelhub_user'] = [
    'id' => 1,
    'name' => 'Test User',
    'email' => 'test@example.com',
    'is_internal' => 1,
    'role' => 'admin',
    'status' => 'active'
];

// Limpa output buffer
ob_end_clean();

echo "<h2>1. Verificando sessão</h2>";
if (isset($_SESSION['pixelhub_user'])) {
    echo "<p style='color: green;'>✓ Usuário autenticado</p>";
    echo "<p>ID: " . $_SESSION['pixelhub_user']['id'] . "</p>";
    echo "<p>Nome: " . $_SESSION['pixelhub_user']['name'] . "</p>";
    echo "<p>is_internal: " . ($_SESSION['pixelhub_user']['is_internal'] ? 'SIM' : 'NÃO') . "</p>";
} else {
    echo "<p style='color: red;'>✗ Usuário não autenticado</p>";
}

// Simula ambiente
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/opportunities';
$_SERVER['SCRIPT_NAME'] = '/public/index.php';
$_SERVER['HTTP_HOST'] = 'hub.pixel12digital.com.br';
$_SERVER['HTTPS'] = 'on';

// Define BASE_PATH
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '');
}

echo "<h2>2. Testando Auth::isInternal()</h2>";

try {
    require_once 'src/Core/Env.php';
    \PixelHub\Core\Env::load();
    require_once 'src/Core/Auth.php';
    
    $isInternal = \PixelHub\Core\Auth::isInternal();
    echo "<p>Auth::isInternal(): " . ($isInternal ? 'TRUE' : 'FALSE') . "</p>";
    
    if ($isInternal) {
        echo "<p style='color: green;'>✓ Usuário tem permissão interna</p>";
    } else {
        echo "<p style='color: red;'>✗ Usuário não tem permissão interna</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro no Auth: " . $e->getMessage() . "</p>";
}

echo "<h2>3. Carregando opportunities completo</h2>";

try {
    // Carrega autoload
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
    
    // Carrega o index.php completo
    ob_start();
    include 'public/index.php';
    $output = ob_get_clean();
    
    if (strlen($output) > 100) {
        echo "<p style='color: green;'>✓ Página opportunities carregada!</p>";
        echo "<p>Tamanho: " . strlen($output) . " bytes</p>";
        
        if (strpos($output, 'CRM / Comercial — Oportunidades') !== false) {
            echo "<p style='color: green;'>✓ CONTEÚDO CORRETO - Página opportunities funcionando!</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Output gerado mas não é opportunities</p>";
            echo "<pre>" . htmlspecialchars(substr($output, 0, 500)) . "...</pre>";
        }
    } else {
        echo "<p style='color: red;'>✗ Output vazio</p>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Exception: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>Conclusão</h2>";
echo "<p>Se este script funcionar, o problema é que você não está logado no navegador.</p>";
echo "<p>Solução: Faça login em https://hub.pixel12digital.com.br/login</p>";

?>
