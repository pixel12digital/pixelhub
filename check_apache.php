<?php
// Verifica configuração do Apache
echo "<h1>Verificação Apache</h1>";

// Verifica se mod_rewrite está ativo
if (in_array('mod_rewrite', apache_get_modules())) {
    echo "<p>✅ mod_rewrite está ATIVO</p>";
} else {
    echo "<p style='color: red;'>❌ mod_rewrite está INATIVO</p>";
    echo "<p>Verifique se LoadModule rewrite_module está descomentado no httpd.conf</p>";
}

// Verifica se AllowOverride está ativo
echo "<h2>AllowOverride</h2>";
$allowOverride = ini_get('allow_url_include');
echo "<p>allow_url_include: " . ($allowOverride ? 'On' : 'Off') . "</p>";

// Verifica document root
echo "<h2>Document Root</h2>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";

// Verifica se está usando .htaccess
echo "<h2>.htaccess</h2>";
$htaccessFiles = [
    __DIR__ . '/.htaccess',
    __DIR__ . '/public/.htaccess'
];

foreach ($htaccessFiles as $file) {
    if (file_exists($file)) {
        echo "<p>✅ Encontrado: " . $file . "</p>";
    } else {
        echo "<p>❌ Não encontrado: " . $file . "</p>";
    }
}

// Teste se reescrita funciona
echo "<h2>Teste Reescrita URL</h2>";
echo "<p>Tente acessar: <a href='/test_apache.php'>/test_apache.php</a></p>";
echo "<p>Tente acessar: <a href='/diagnose_tracking.php'>/diagnose_tracking.php</a></p>";
echo "<p>Tente acessar: <a href='/analyze_404.php'>/analyze_404.php</a></p>";

// Verifica logs do Apache
echo "<h2>Logs do Apache (se disponíveis)</h2>";
$logFiles = [
    'C:/xampp/apache/logs/error.log',
    'C:/xampp/apache/logs/access.log'
];

foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        echo "<p>✅ Log encontrado: " . $logFile . "</p>";
        
        // Últimas 5 linhas do log
        $lines = file($logFile);
        $totalLines = count($lines);
        $start = max(0, $totalLines - 5);
        
        echo "<h4>Últimas 5 linhas de " . basename($logFile) . ":</h4>";
        echo "<pre style='background: #f8f9fa; padding: 10px; font-size: 12px;'>";
        for ($i = $start; $i < $totalLines; $i++) {
            echo htmlspecialchars($lines[$i]) . "\n";
        }
        echo "</pre>";
    } else {
        echo "<p>❌ Log não encontrado: " . $logFile . "</p>";
    }
}

// Verifica se PHP está configurado para exibir erros
echo "<h2>Configuração PHP</h2>";
echo "<p>display_errors: " . (ini_get('display_errors') ? 'On' : 'Off') . "</p>";
echo "<p>error_reporting: " . ini_get('error_reporting') . "</p>";

echo "<h2>Conclusão</h2>";
echo "<p>Se todos os testes acima derem 404, o problema está na configuração do Apache/XAMPP.</p>";
echo "<p>Verifique:</p>";
echo "<ul>";
echo "<li>1. Se o Apache está realmente rodando</li>";
echo "<li>2. Se o módulo mod_rewrite está ativo</li>";
echo "<li>3. Se o AllowOverride está habilitado</li>";
echo "<li>4. Se há erros nos logs do Apache</li>";
echo "</ul>";
?>
