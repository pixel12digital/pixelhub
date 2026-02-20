<?php
// Script para verificar autenticação no servidor
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Verificação de Autenticação - Servidor</h1>";

// Simula ambiente do servidor
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/painel.pixel12digital/opportunities';
$_SERVER['SCRIPT_NAME'] = '/painel.pixel12digital/public/index.php';
$_SERVER['HTTP_HOST'] = 'hub.pixel12digital.com.br';
$_SERVER['HTTPS'] = 'on';

// Inicia sessão
session_start();

echo "<h2>1. Verificando sessão</h2>";
echo "<p>ID da sessão: " . session_id() . "</p>";
echo "<p>Status da sessão: " . session_status() . "</p>";

if (isset($_SESSION['user'])) {
    echo "<p style='color: green;'>✓ Usuário está na sessão</p>";
    echo "<pre>" . print_r($_SESSION['user'], true) . "</pre>";
} else {
    echo "<p style='color: red;'>✗ Usuário não está na sessão</p>";
    echo "<p>Conteúdo da sessão:</p>";
    echo "<pre>" . print_r($_SESSION, true) . "</pre>";
}

// Testa o Auth diretamente
echo "<h2>2. Testando Auth::requireInternal()</h2>";
try {
    require_once 'src/Core/Env.php';
    \PixelHub\Core\Env::load();
    require_once 'src/Core/DB.php';
    require_once 'src/Core/Auth.php';
    
    echo "<p>Carregando Auth...</p>";
    
    // Verifica isInternal
    $isInternal = \PixelHub\Core\Auth::isInternal();
    echo "<p>isInternal(): " . ($isInternal ? 'TRUE' : 'FALSE') . "</p>";
    
    // Verifica requireAuth
    echo "<p>Testando requireAuth()...</p>";
    \PixelHub\Core\Auth::requireAuth();
    echo "<p style='color: green;'>✓ requireAuth() OK</p>";
    
    // Verifica requireInternal
    echo "<p>Testando requireInternal()...</p>";
    \PixelHub\Core\Auth::requireInternal();
    echo "<p style='color: green;'>✓ requireInternal() OK</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro em Auth: " . $e->getMessage() . "</p>";
}

// Testa com usuário mock
echo "<h2>3. Testando com usuário mock</h2>";
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Test User',
    'email' => 'test@example.com',
    'is_internal' => 1
];

try {
    $isInternal = \PixelHub\Core\Auth::isInternal();
    echo "<p>isInternal() com mock: " . ($isInternal ? 'TRUE' : 'FALSE') . "</p>";
    
    \PixelHub\Core\Auth::requireInternal();
    echo "<p style='color: green;'>✓ requireInternal() com mock OK</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro com mock: " . $e->getMessage() . "</p>";
}

echo "<h2>Diagnóstico</h2>";
echo "<p>Se o usuário não estiver autenticado no servidor, a página vai redirecionar para o login.</p>";
echo "<p>Verifique:</p>";
echo "<ul>";
echo "<li>Se você está logado no sistema</li>";
echo "<li>Se a sessão está sendo mantida entre requisições</li>";
echo "<li>Se há algum problema com o cookie de sessão</li>";
echo "</ul>";

echo "<h2>Instruções para o servidor</h2>";
echo "<p>Execute este script no servidor:</p>";
echo "<pre><code>cd ~/hub.pixel12digital.com.br<br>php check_auth_server.php</code></pre>";

?>
