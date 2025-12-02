<?php
/**
 * Script de diagnóstico para identificar problemas com o compartilhamento de gravações
 * Acesse: /screen-recordings/debug-share.php
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../../src/';
        
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

use PixelHub\Core\DB;
use PixelHub\Core\Env;

// Carrega variáveis de ambiente
try {
    Env::load();
} catch (\Exception $e) {
    // Ignora erro de env
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico - Compartilhamento de Gravações</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        h2 {
            color: #569cd6;
            margin-top: 30px;
            border-left: 4px solid #569cd6;
            padding-left: 10px;
        }
        .section {
            background: #252526;
            border: 1px solid #3e3e42;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        .success {
            color: #4ec9b0;
        }
        .error {
            color: #f48771;
        }
        .warning {
            color: #dcdcaa;
        }
        .info {
            color: #569cd6;
        }
        pre {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .test-item {
            margin: 10px 0;
            padding: 10px;
            background: #2d2d30;
            border-left: 3px solid #3e3e42;
        }
        .test-item.pass {
            border-left-color: #4ec9b0;
        }
        .test-item.fail {
            border-left-color: #f48771;
        }
        .test-item.warn {
            border-left-color: #dcdcaa;
        }
        code {
            background: #1e1e1e;
            padding: 2px 6px;
            border-radius: 3px;
            color: #ce9178;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnóstico - Compartilhamento de Gravações</h1>
        <p class="info">Gerado em: <?= date('Y-m-d H:i:s') ?></p>

        <?php
        $tests = [];
        $errors = [];
        $warnings = [];

        // ============================================
        // TESTE 1: Verificação de arquivos
        // ============================================
        echo '<h2>1. Verificação de Arquivos</h2>';
        echo '<div class="section">';

        $shareFile = __DIR__ . '/share.php';
        $shareExists = file_exists($shareFile);
        $tests['share_file_exists'] = $shareExists;
        
        echo '<div class="test-item ' . ($shareExists ? 'pass' : 'fail') . '">';
        echo '<strong>share.php existe:</strong> ' . ($shareExists ? '<span class="success">✓ SIM</span>' : '<span class="error">✗ NÃO</span>');
        echo '<br><code>' . htmlspecialchars($shareFile) . '</code>';
        if (!$shareExists) {
            $errors[] = 'Arquivo share.php não encontrado';
        }
        echo '</div>';

        $indexFile = __DIR__ . '/../index.php';
        $indexExists = file_exists($indexFile);
        $tests['index_file_exists'] = $indexExists;
        
        echo '<div class="test-item ' . ($indexExists ? 'pass' : 'fail') . '">';
        echo '<strong>index.php existe:</strong> ' . ($indexExists ? '<span class="success">✓ SIM</span>' : '<span class="error">✗ NÃO</span>');
        echo '<br><code>' . htmlspecialchars($indexFile) . '</code>';
        if (!$indexExists) {
            $errors[] = 'Arquivo index.php não encontrado';
        }
        echo '</div>';

        // Verifica permissões
        if ($shareExists) {
            $shareReadable = is_readable($shareFile);
            echo '<div class="test-item ' . ($shareReadable ? 'pass' : 'fail') . '">';
            echo '<strong>share.php é legível:</strong> ' . ($shareReadable ? '<span class="success">✓ SIM</span>' : '<span class="error">✗ NÃO</span>');
            if (!$shareReadable) {
                $errors[] = 'Arquivo share.php não é legível';
            }
            echo '</div>';
        }

        echo '</div>';

        // ============================================
        // TESTE 2: Verificação de rotas no index.php
        // ============================================
        echo '<h2>2. Verificação de Rotas no index.php</h2>';
        echo '<div class="section">';

        if ($indexExists) {
            $indexContent = file_get_contents($indexFile);
            
            // Verifica se a rota está registrada
            $hasRoute = strpos($indexContent, '/screen-recordings/share') !== false;
            $tests['route_registered'] = $hasRoute;
            
            echo '<div class="test-item ' . ($hasRoute ? 'pass' : 'fail') . '">';
            echo '<strong>Rota /screen-recordings/share encontrada no index.php:</strong> ';
            echo $hasRoute ? '<span class="success">✓ SIM</span>' : '<span class="error">✗ NÃO</span>';
            if (!$hasRoute) {
                $errors[] = 'Rota /screen-recordings/share não encontrada no index.php';
            }
            echo '</div>';

            // Verifica se está na seção de rotas públicas
            $isPublicRoute = strpos($indexContent, '// Rotas públicas') !== false && 
                           strpos($indexContent, '// Rotas públicas') < strpos($indexContent, '/screen-recordings/share');
            echo '<div class="test-item ' . ($isPublicRoute ? 'pass' : 'warn') . '">';
            echo '<strong>Rota está na seção de rotas públicas:</strong> ';
            echo $isPublicRoute ? '<span class="success">✓ SIM</span>' : '<span class="warning">⚠ NÃO (pode estar em rotas protegidas)</span>';
            if (!$isPublicRoute) {
                $warnings[] = 'Rota pode estar na seção de rotas protegidas';
            }
            echo '</div>';

            // Verifica bypass direto
            $hasBypass = strpos($indexContent, 'ATALHO: Se for /screen-recordings/share') !== false;
            echo '<div class="test-item ' . ($hasBypass ? 'pass' : 'warn') . '">';
            echo '<strong>Bypass direto implementado:</strong> ';
            echo $hasBypass ? '<span class="success">✓ SIM</span>' : '<span class="warning">⚠ NÃO</span>';
            echo '</div>';

            // Mostra trecho relevante do código
            if ($hasRoute) {
                $routePos = strpos($indexContent, '/screen-recordings/share');
                $start = max(0, $routePos - 200);
                $end = min(strlen($indexContent), $routePos + 500);
                $snippet = substr($indexContent, $start, $end - $start);
                
                echo '<div class="test-item">';
                echo '<strong>Trecho do código (contexto):</strong>';
                echo '<pre>' . htmlspecialchars($snippet) . '</pre>';
                echo '</div>';
            }
        }

        echo '</div>';

        // ============================================
        // TESTE 3: Verificação de variáveis de ambiente
        // ============================================
        echo '<h2>3. Variáveis de Ambiente e Paths</h2>';
        echo '<div class="section">';

        echo '<div class="test-item">';
        echo '<strong>__DIR__ (diretório atual):</strong><br>';
        echo '<code>' . htmlspecialchars(__DIR__) . '</code>';
        echo '</div>';

        echo '<div class="test-item">';
        echo '<strong>REQUEST_URI:</strong><br>';
        echo '<code>' . htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A') . '</code>';
        echo '</div>';

        echo '<div class="test-item">';
        echo '<strong>SCRIPT_NAME:</strong><br>';
        echo '<code>' . htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? 'N/A') . '</code>';
        echo '</div>';

        echo '<div class="test-item">';
        echo '<strong>REQUEST_METHOD:</strong><br>';
        echo '<code>' . htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'N/A') . '</code>';
        echo '</div>';

        // Calcula path como o index.php faz
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        
        if ($scriptDir !== '' && $scriptDir !== '/') {
            if (strpos($uri, $scriptDir) === 0) {
                $calculatedPath = substr($uri, strlen($scriptDir));
            } else {
                $calculatedPath = $uri;
            }
        } else {
            $calculatedPath = $uri;
        }
        
        $calculatedPath = '/' . trim($calculatedPath, '/');
        if ($calculatedPath === '//') {
            $calculatedPath = '/';
        }
        $calculatedPath = rtrim($calculatedPath, '/') ?: '/';

        echo '<div class="test-item">';
        echo '<strong>Path calculado (como index.php faz):</strong><br>';
        echo '<code>' . htmlspecialchars($calculatedPath) . '</code>';
        echo '</div>';

        $expectedPath = '/screen-recordings/share';
        $pathMatches = ($calculatedPath === $expectedPath || strpos($calculatedPath, $expectedPath . '?') === 0);
        echo '<div class="test-item ' . ($pathMatches ? 'pass' : 'fail') . '">';
        echo '<strong>Path corresponde a /screen-recordings/share:</strong> ';
        echo $pathMatches ? '<span class="success">✓ SIM</span>' : '<span class="error">✗ NÃO</span>';
        if (!$pathMatches) {
            $errors[] = "Path calculado não corresponde. Esperado: {$expectedPath}, Obtido: {$calculatedPath}";
        }
        echo '</div>';

        echo '</div>';

        // ============================================
        // TESTE 4: Verificação de banco de dados
        // ============================================
        echo '<h2>4. Verificação de Banco de Dados</h2>';
        echo '<div class="section">';

        try {
            $db = DB::getConnection();
            $tests['db_connection'] = true;
            
            echo '<div class="test-item pass">';
            echo '<strong>Conexão com banco:</strong> <span class="success">✓ OK</span>';
            echo '</div>';

            // Verifica se a tabela existe
            $tableCheck = $db->query("SHOW TABLES LIKE 'screen_recordings'");
            $tableExists = $tableCheck->rowCount() > 0;
            $tests['table_exists'] = $tableExists;
            
            echo '<div class="test-item ' . ($tableExists ? 'pass' : 'fail') . '">';
            echo '<strong>Tabela screen_recordings existe:</strong> ';
            echo $tableExists ? '<span class="success">✓ SIM</span>' : '<span class="error">✗ NÃO</span>';
            if (!$tableExists) {
                $errors[] = 'Tabela screen_recordings não existe';
            }
            echo '</div>';

            if ($tableExists) {
                // Conta registros
                $countStmt = $db->query("SELECT COUNT(*) as total FROM screen_recordings");
                $count = $countStmt->fetch(PDO::FETCH_ASSOC);
                $totalRecords = $count['total'] ?? 0;
                
                echo '<div class="test-item">';
                echo '<strong>Total de gravações no banco:</strong> <span class="info">' . $totalRecords . '</span>';
                echo '</div>';

                // Verifica se há registros com public_token
                $tokenStmt = $db->query("SELECT COUNT(*) as total FROM screen_recordings WHERE public_token IS NOT NULL AND public_token != ''");
                $tokenCount = $tokenStmt->fetch(PDO::FETCH_ASSOC);
                $totalWithToken = $tokenCount['total'] ?? 0;
                
                echo '<div class="test-item">';
                echo '<strong>Gravações com public_token:</strong> <span class="info">' . $totalWithToken . '</span>';
                echo '</div>';

                // Lista últimos 3 registros
                $lastStmt = $db->query("SELECT id, file_path, public_token, created_at FROM screen_recordings ORDER BY id DESC LIMIT 3");
                $lastRecords = $lastStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($lastRecords)) {
                    echo '<div class="test-item">';
                    echo '<strong>Últimos 3 registros:</strong>';
                    echo '<pre>' . htmlspecialchars(json_encode($lastRecords, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                    echo '</div>';
                }
            }

        } catch (\Exception $e) {
            $tests['db_connection'] = false;
            $errors[] = 'Erro ao conectar ao banco: ' . $e->getMessage();
            
            echo '<div class="test-item fail">';
            echo '<strong>Conexão com banco:</strong> <span class="error">✗ ERRO</span>';
            echo '<br><code>' . htmlspecialchars($e->getMessage()) . '</code>';
            echo '</div>';
        }

        echo '</div>';

        // ============================================
        // TESTE 5: Teste de acesso direto ao share.php
        // ============================================
        echo '<h2>5. Teste de Acesso Direto</h2>';
        echo '<div class="section">';

        if ($shareExists) {
            // Tenta incluir o arquivo para ver se há erros de sintaxe
            ob_start();
            $syntaxError = false;
            try {
                // Não executa, apenas verifica sintaxe
                $tokens = token_get_all(file_get_contents($shareFile));
                $syntaxError = false;
            } catch (\Exception $e) {
                $syntaxError = true;
                $errors[] = 'Erro de sintaxe no share.php: ' . $e->getMessage();
            }
            ob_end_clean();

            echo '<div class="test-item ' . (!$syntaxError ? 'pass' : 'fail') . '">';
            echo '<strong>Sintaxe do share.php:</strong> ';
            echo !$syntaxError ? '<span class="success">✓ OK</span>' : '<span class="error">✗ ERRO</span>';
            echo '</div>';

            // Verifica se o arquivo tem o código necessário
            $shareContent = file_get_contents($shareFile);
            $hasTokenCheck = strpos($shareContent, 'public_token') !== false;
            $hasDBConnection = strpos($shareContent, 'DB::getConnection') !== false;
            
            echo '<div class="test-item ' . ($hasTokenCheck ? 'pass' : 'warn') . '">';
            echo '<strong>Verificação de token no código:</strong> ';
            echo $hasTokenCheck ? '<span class="success">✓ SIM</span>' : '<span class="warning">⚠ NÃO</span>';
            echo '</div>';

            echo '<div class="test-item ' . ($hasDBConnection ? 'pass' : 'warn') . '">';
            echo '<strong>Conexão com banco no código:</strong> ';
            echo $hasDBConnection ? '<span class="success">✓ SIM</span>' : '<span class="warning">⚠ NÃO</span>';
            echo '</div>';
        }

        echo '</div>';

        // ============================================
        // TESTE 6: Verificação de .htaccess
        // ============================================
        echo '<h2>6. Verificação de .htaccess</h2>';
        echo '<div class="section">';

        $htaccessPublic = __DIR__ . '/../.htaccess';
        $htaccessRoot = __DIR__ . '/../../.htaccess';
        
        $htaccessPublicExists = file_exists($htaccessPublic);
        $htaccessRootExists = file_exists($htaccessRoot);

        echo '<div class="test-item ' . ($htaccessPublicExists ? 'pass' : 'warn') . '">';
        echo '<strong>.htaccess em public/ existe:</strong> ';
        echo $htaccessPublicExists ? '<span class="success">✓ SIM</span>' : '<span class="warning">⚠ NÃO</span>';
        echo '</div>';

        if ($htaccessPublicExists) {
            $htaccessContent = file_get_contents($htaccessPublic);
            $hasRewriteRule = strpos($htaccessContent, 'RewriteRule') !== false;
            
            echo '<div class="test-item ' . ($hasRewriteRule ? 'pass' : 'warn') . '">';
            echo '<strong>RewriteRule configurado:</strong> ';
            echo $hasRewriteRule ? '<span class="success">✓ SIM</span>' : '<span class="warning">⚠ NÃO</span>';
            echo '</div>';

            if ($hasRewriteRule) {
                echo '<div class="test-item">';
                echo '<strong>Conteúdo do .htaccess (public/):</strong>';
                echo '<pre>' . htmlspecialchars($htaccessContent) . '</pre>';
                echo '</div>';
            }
        }

        echo '</div>';

        // ============================================
        // RESUMO
        // ============================================
        echo '<h2>📊 Resumo</h2>';
        echo '<div class="section">';

        $totalTests = count($tests);
        $passedTests = count(array_filter($tests));
        $failedTests = $totalTests - $passedTests;

        echo '<div class="test-item">';
        echo '<strong>Testes executados:</strong> ' . $totalTests . '<br>';
        echo '<strong>Testes aprovados:</strong> <span class="success">' . $passedTests . '</span><br>';
        echo '<strong>Testes falhados:</strong> <span class="error">' . $failedTests . '</span><br>';
        echo '<strong>Avisos:</strong> <span class="warning">' . count($warnings) . '</span><br>';
        echo '<strong>Erros:</strong> <span class="error">' . count($errors) . '</span>';
        echo '</div>';

        if (!empty($errors)) {
            echo '<div class="test-item fail">';
            echo '<strong>Erros encontrados:</strong>';
            echo '<ul>';
            foreach ($errors as $error) {
                echo '<li class="error">' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        if (!empty($warnings)) {
            echo '<div class="test-item warn">';
            echo '<strong>Avisos:</strong>';
            echo '<ul>';
            foreach ($warnings as $warning) {
                echo '<li class="warning">' . htmlspecialchars($warning) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        // Recomendações
        echo '<div class="test-item">';
        echo '<strong>🔧 Recomendações:</strong>';
        echo '<ul>';
        if (!$shareExists) {
            echo '<li class="error">Criar o arquivo share.php em ' . htmlspecialchars(__DIR__) . '</li>';
        }
        if (!$hasRoute) {
            echo '<li class="error">Adicionar a rota /screen-recordings/share no index.php</li>';
        }
        if (!$pathMatches) {
            echo '<li class="error">Verificar o cálculo do path no index.php. Path esperado: /screen-recordings/share</li>';
        }
        if (empty($errors) && empty($warnings)) {
            echo '<li class="success">Tudo parece estar configurado corretamente! Verifique os logs do servidor para mais detalhes.</li>';
        }
        echo '</ul>';
        echo '</div>';

        echo '</div>';

        // ============================================
        // Links de teste
        // ============================================
        echo '<h2>🔗 Links de Teste</h2>';
        echo '<div class="section">';
        
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $basePath = $scriptDir;
        
        echo '<div class="test-item">';
        echo '<strong>URLs para testar:</strong><br>';
        echo '<a href="' . htmlspecialchars($baseUrl . $basePath . '/screen-recordings/share?token=TESTE&debug=1') . '" target="_blank" style="color: #4ec9b0;">';
        echo htmlspecialchars($baseUrl . $basePath . '/screen-recordings/share?token=TESTE&debug=1');
        echo '</a><br><br>';
        echo '<a href="' . htmlspecialchars($baseUrl . $basePath . '/screen-recordings/debug-share.php') . '" target="_blank" style="color: #4ec9b0;">';
        echo 'Executar este diagnóstico novamente';
        echo '</a>';
        echo '</div>';

        echo '</div>';
        ?>

    </div>
</body>
</html>

