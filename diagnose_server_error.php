<?php
// Diagnóstico do erro 500 no servidor para /opportunities
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Diagnóstico Erro Servidor - /opportunities</h1>";

// 1. Verifica se o problema está na URL reescrita
echo "<h2>1. Testando diferentes formatos de URL</h2>";

$testUrls = [
    '/painel.pixel12digital/opportunities',
    '/painel.pixel12digital/opportunities/',
    '/painel.pixel12digital/opportunities/index',
    '/painel.pixel12digital/public/opportunities',
    '/opportunities'
];

foreach ($testUrls as $url) {
    echo "<p>Testando URL: <strong>$url</strong>... ";
    
    // Simula o ambiente
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = $url;
    $_SERVER['SCRIPT_NAME'] = '/painel.pixel12digital/public/index.php';
    $_SERVER['HTTP_HOST'] = 'localhost';
    
    // Limpa output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    try {
        include 'public/index.php';
        $output = ob_get_contents();
        ob_end_clean();
        
        if (strpos($output, 'CRM / Comercial — Oportunidades') !== false) {
            echo "<span style='color: green;'>✓ SUCESSO</span></p>";
        } else {
            echo "<span style='color: orange;'>⚠ Output gerado mas sem conteúdo esperado</span></p>";
        }
    } catch (Exception $e) {
        ob_end_clean();
        echo "<span style='color: red;'>✗ Exception: " . $e->getMessage() . "</span></p>";
    } catch (Error $e) {
        ob_end_clean();
        echo "<span style='color: red;'>✗ Error: " . $e->getMessage() . "</span></p>";
    }
}

// 2. Verifica configurações específicas do servidor
echo "<h2>2. Verificando configurações que podem causar erro 500</h2>";

$checks = [
    'allow_url_fopen' => ini_get('allow_url_fopen'),
    'allow_url_include' => ini_get('allow_url_include'),
    'file_uploads' => ini_get('file_uploads'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'post_max_size' => ini_get('post_max_size'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'max_input_vars' => ini_get('max_input_vars'),
    'disable_functions' => ini_get('disable_functions'),
];

foreach ($checks as $key => $value) {
    echo "<p><strong>$key:</strong> $value</p>";
}

// 3. Verifica permissões dos arquivos críticos
echo "<h2>3. Verificando permissões dos arquivos</h2>";

$criticalFiles = [
    'public/index.php',
    'src/Controllers/OpportunitiesController.php',
    'src/Services/OpportunityService.php',
    'views/opportunities/index.php',
    '.htaccess',
    'public/.htaccess'
];

foreach ($criticalFiles as $file) {
    if (file_exists($file)) {
        $perms = substr(sprintf('%o', fileperms($file)), -4);
        $readable = is_readable($file) ? 'SIM' : 'NÃO';
        echo "<p>$file: $perms (legível: $readable)</p>";
    } else {
        echo "<p style='color: red;'>$file: NÃO EXISTE</p>";
    }
}

// 4. Verifica se há algum erro específico no log do Apache/Nginx
echo "<h2>4. Possíveis causas do erro 500</h2>";
echo "<ul>";
echo "<li><strong>Permissões:</strong> Verifique se os arquivos têm permissão de leitura (644 para arquivos, 755 para diretórios)</li>";
echo "<li><strong>.htaccess:</strong> Verifique se o módulo rewrite está ativo no servidor</li>";
echo "<li><strong>PHP:</strong> Verifique se não há funções desabilitadas necessárias</li>";
echo "<li><strong>Memória:</strong> O servidor pode ter um limite de memória mais restritivo</li>";
echo "<li><strong>Timeout:</strong> Pode estar ocorrendo timeout por excesso de processamento</li>";
echo "<li><strong>Log de erros:</strong> Verifique o log de erros do servidor web (Apache/Nginx)</li>";
echo "</ul>";

// 5. Teste específico para o método index() do controller
echo "<h2>5. Teste isolado do OpportunitiesController::index()</h2>";

try {
    // Simula ambiente mínimo
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/painel.pixel12digital/opportunities';
    
    // Sessão
    session_start();
    $_SESSION['user'] = [
        'id' => 1,
        'name' => 'Test User',
        'email' => 'test@example.com',
        'is_internal' => 1
    ];
    
    // Carrega mínimo necessário
    require_once 'src/Core/Env.php';
    \PixelHub\Core\Env::load();
    
    require_once 'src/Core/DB.php';
    require_once 'src/Core/Auth.php';
    require_once 'src/Core/Controller.php';
    require_once 'src/Services/OpportunityService.php';
    require_once 'src/Services/OpportunityProductService.php';
    require_once 'src/Services/ContactService.php';
    require_once 'src/Services/LeadService.php';
    require_once 'src/Services/PhoneNormalizer.php';
    require_once 'src/Services/TrackingCodesService.php';
    require_once 'src/Controllers/OpportunitiesController.php';
    
    // Instancia e testa
    $controller = new \PixelHub\Controllers\OpportunitiesController();
    
    echo "<p style='color: green;'>✓ Controller instanciado com sucesso</p>";
    
    // Captura output do método index
    ob_start();
    $controller->index();
    $output = ob_get_clean();
    
    if (strlen($output) > 0) {
        echo "<p style='color: green;'>✓ Método index() executado, output: " . strlen($output) . " bytes</p>";
        
        // Verifica se contém elementos esperados
        if (strpos($output, 'CRM / Comercial — Oportunidades') !== false) {
            echo "<p style='color: green;'>✓ Título da página encontrado</p>";
        }
        if (strpos($output, 'Nova Oportunidade') !== false) {
            echo "<p style='color: green;'>✓ Botão Nova Oportunidade encontrado</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠ Método index() executou mas não gerou output</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Exception no controller: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "<p style='color: red;'>✗ Error no controller: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>Conclusão e Recomendações</h2>";
echo "<p>Se todos os testes acima funcionaram localmente, o problema está no ambiente do servidor.</p>";
echo "<p><strong>Ações recomendadas no servidor:</strong></p>";
echo "<ol>";
echo "<li>Verifique o log de erros do Apache/Nginx: <code>/var/log/apache2/error.log</code> ou <code>/var/log/nginx/error.log</code></li>";
echo "<li>Verifique se o módulo rewrite está ativo: <code>a2enmod rewrite</code> (Apache)</li>";
echo "<li>Verifique permissões: <code>chmod 644 *.php</code> e <code>chmod 755 .</code></li>";
echo "<li>Teste acesso direto: <code>curl -I http://seusite.com/painel.pixel12digital/opportunities</code></li>";
echo "<li>Verifique se há algum firewall ou WAF bloqueando o acesso</li>";
echo "<li>Compare as configurações do PHP local vs servidor com <code>phpinfo()</code></li>";
echo "</ol>";

?>
