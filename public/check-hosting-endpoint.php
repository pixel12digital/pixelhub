<?php

/**
 * Script de verificação para produção
 * Acesse: https://seu-dominio.com/public/check-hosting-endpoint.php
 * 
 * Verifica se o endpoint /hosting/view está funcionando corretamente
 */

// Inicia sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
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
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Controllers\HostingController;

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Verificação do Endpoint /hosting/view</h1>\n";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .ok { color: green; } .error { color: red; } .info { color: blue; } pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }</style>\n";

try {
    echo "<h2>1. Carregando Env...</h2>\n";
    Env::load();
    echo "<p class='ok'>✓ Env carregado</p>\n";
    
    echo "<h2>2. Verificando se HostingController tem método show()...</h2>\n";
    if (class_exists('PixelHub\\Controllers\\HostingController')) {
        $reflection = new ReflectionClass('PixelHub\\Controllers\\HostingController');
        if ($reflection->hasMethod('show')) {
            echo "<p class='ok'>✓ Método show() encontrado</p>\n";
        } else {
            echo "<p class='error'>✗ Método show() NÃO encontrado</p>\n";
            echo "<p class='info'>Métodos disponíveis:</p>\n";
            echo "<pre>";
            foreach ($reflection->getMethods() as $method) {
                if ($method->isPublic()) {
                    echo "- " . $method->getName() . "\n";
                }
            }
            echo "</pre>\n";
        }
        
        // Verifica se ainda tem método view() (não deveria ter)
        if ($reflection->hasMethod('view')) {
            echo "<p class='error'>⚠ ATENÇÃO: Método view() ainda existe! Isso pode causar conflito.</p>\n";
        } else {
            echo "<p class='ok'>✓ Método view() não existe (correto)</p>\n";
        }
    } else {
        echo "<p class='error'>✗ Classe HostingController não encontrada</p>\n";
    }
    
    echo "<h2>3. Verificando rota em index.php...</h2>\n";
    $indexContent = file_get_contents(__DIR__ . '/index.php');
    if (strpos($indexContent, "HostingController@show") !== false) {
        echo "<p class='ok'>✓ Rota configurada corretamente: HostingController@show</p>\n";
    } elseif (strpos($indexContent, "HostingController@view") !== false) {
        echo "<p class='error'>✗ Rota ainda usa HostingController@view (precisa ser atualizada para @show)</p>\n";
    } else {
        echo "<p class='error'>✗ Rota /hosting/view não encontrada em index.php</p>\n";
    }
    
    echo "<h2>4. Testando conexão DB...</h2>\n";
    $db = DB::getConnection();
    echo "<p class='ok'>✓ Conexão DB OK</p>\n";
    
    echo "<h2>5. Verificando se há contas de hospedagem...</h2>\n";
    $stmt = $db->query("SELECT COUNT(*) FROM hosting_accounts");
    $count = $stmt->fetchColumn();
    echo "<p class='info'>Total de contas: {$count}</p>\n";
    
    if ($count > 0) {
        echo "<h2>6. Testando endpoint diretamente (simulação)...</h2>\n";
        echo "<p class='info'>Para testar o endpoint real, acesse:</p>\n";
        echo "<pre>GET " . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "/hosting/view?id=1</pre>\n";
        echo "<p class='info'>Ou use o botão 'Ver' na interface do cliente.</p>\n";
    }
    
    echo "<h2>7. Verificando estrutura da resposta esperada...</h2>\n";
    echo "<p class='info'>O endpoint deve retornar JSON com a seguinte estrutura:</p>\n";
    echo "<pre>{\n";
    echo "  \"success\": true,\n";
    echo "  \"hosting\": { ... },\n";
    echo "  \"provider_name\": \"...\",\n";
    echo "  \"status_hospedagem\": { ... },\n";
    echo "  \"status_dominio\": { ... }\n";
    echo "}</pre>\n";
    
    echo "<h2>✓ Verificação concluída!</h2>\n";
    echo "<p class='info'><strong>Próximos passos:</strong></p>\n";
    echo "<ol>\n";
    echo "<li>Se algum item acima falhou, faça <code>git pull</code> em produção</li>\n";
    echo "<li>Limpe o cache do navegador (Ctrl+Shift+Delete)</li>\n";
    echo "<li>Teste o botão 'Ver' na interface do cliente</li>\n";
    echo "<li>Verifique o console do navegador (F12) para erros JavaScript</li>\n";
    echo "</ol>\n";
    
} catch (\Throwable $e) {
    echo "<h2 class='error'>✗ ERRO:</h2>\n";
    echo "<pre style='background: #ffe6e6;'>\n";
    echo "Mensagem: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "Arquivo: " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . htmlspecialchars($e->getTraceAsString()) . "\n";
    echo "</pre>\n";
}

