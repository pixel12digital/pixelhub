<?php
// Simula acesso web à rota /opportunities
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simula ambiente web
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/painel.pixel12digital/opportunities';
$_SERVER['SCRIPT_NAME'] = '/painel.pixel12digital/public/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';

// Inicia sessão
session_start();

// Simula usuário logado
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Test User',
    'email' => 'test@example.com',
    'is_internal' => 1
];

// Carrega o index.php
try {
    include 'public/index.php';
} catch (Exception $e) {
    echo "<h1>Erro capturado</h1>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "<h1>Erro fatal capturado</h1>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

?>
