<?php
// Teste opportunities com sessão completa
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Teste Opportunities com Sessão Completa</h1>";

// Inicia sessão ANTES de qualquer output
ob_start();
session_start();

// Simula usuário logado COMPLETO
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Test User',
    'email' => 'test@example.com',
    'is_internal' => 1,
    'role' => 'admin',
    'status' => 'active'
];

// Simula ambiente do servidor
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/painel.pixel12digital/opportunities';
$_SERVER['SCRIPT_NAME'] = '/painel.pixel12digital/public/index.php';
$_SERVER['HTTP_HOST'] = 'hub.pixel12digital.com.br';
$_SERVER['HTTPS'] = 'on';

// Limpa output buffer
ob_end_clean();

echo "<h2>Teste 1: Verificando sessão</h2>";
if (isset($_SESSION['user'])) {
    echo "<p style='color: green;'>✓ Usuário na sessão</p>";
    echo "<pre>" . print_r($_SESSION['user'], true) . "</pre>";
} else {
    echo "<p style='color: red;'>✗ Usuário não está na sessão</p>";
}

echo "<h2>Teste 2: Carregando opportunities</h2>";

try {
    // Carrega o bootstrap completo
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
    
    // Captura output
    ob_start();
    include 'public/index.php';
    $output = ob_get_clean();
    
    if (strlen($output) > 100) {
        echo "<p style='color: green;'>✓ Página carregada!</p>";
        echo "<p>Tamanho: " . strlen($output) . " bytes</p>";
        
        if (strpos($output, 'CRM / Comercial — Oportunidades') !== false) {
            echo "<p style='color: green;'>✓ Página opportunities funcionando!</p>";
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

echo "<h2>Solução</h2>";
echo "<p>O problema é que você não está logado no navegador.</p>";
echo "<p>Execute no navegador:</p>";
echo "<ol>";
echo "<li>1. Acesse: https://hub.pixel12digital.com.br/login</li>";
echo "<li>2. Faça login com seu usuário e senha</li>";
echo "<li>3. Depois acesse: https://hub.pixel12digital.com.br/opportunities</li>";
echo "</ol>";

?>
