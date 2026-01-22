<?php
/**
 * Script de Debug - Verificar cálculo de rotas
 */

// Simula o ambiente do index.php
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

if (!defined('BASE_PATH')) {
    if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '') {
        define('BASE_PATH', '');
    } else {
        define('BASE_PATH', $scriptDir);
    }
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Calcula path (mesma lógica do index.php)
$path = $uri;
if (defined('BASE_PATH') && BASE_PATH !== '' && BASE_PATH !== '/') {
    if (strpos($uri, BASE_PATH) === 0) {
        $path = substr($uri, strlen(BASE_PATH));
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
    }
}

if ($path === $uri && $scriptDir !== '' && $scriptDir !== '/') {
    if (strpos($uri, $scriptDir) === 0) {
        $path = substr($uri, strlen($scriptDir));
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
    }
}

if ($path === $uri && ($path === '' || $path[0] !== '/')) {
    $path = '/' . $path;
}

$path = '/' . trim($path, '/');
if ($path === '//') {
    $path = '/';
}
if ($path === '') {
    $path = '/';
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Route - PixelHub</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .info { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #4CAF50; }
        .info strong { display: inline-block; width: 200px; }
        h1 { color: #333; }
        .test-link { display: inline-block; margin: 10px 5px; padding: 10px 15px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; }
        .test-link:hover { background: #45a049; }
    </style>
</head>
<body>
    <h1>🔍 Debug de Rotas - PixelHub</h1>
    
    <div class="info">
        <strong>REQUEST_URI:</strong> <?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A') ?><br>
        <strong>SCRIPT_NAME:</strong> <?= htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? 'N/A') ?><br>
        <strong>scriptDir:</strong> <?= htmlspecialchars($scriptDir) ?><br>
        <strong>BASE_PATH:</strong> <?= htmlspecialchars(defined('BASE_PATH') ? BASE_PATH : 'NÃO DEFINIDO') ?><br>
        <strong>URI (parse_url):</strong> <?= htmlspecialchars($uri) ?><br>
        <strong>Path calculado:</strong> <strong style="color: #4CAF50;"><?= htmlspecialchars($path) ?></strong><br>
        <strong>REQUEST_METHOD:</strong> <?= htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'GET') ?><br>
    </div>

    <h2>Testar Rotas:</h2>
    <a href="/communication-hub" class="test-link">/communication-hub</a>
    <a href="<?= BASE_PATH ?>/communication-hub" class="test-link"><?= BASE_PATH ?>/communication-hub</a>
    <a href="/dashboard" class="test-link">/dashboard</a>
    <a href="<?= BASE_PATH ?>/dashboard" class="test-link"><?= BASE_PATH ?>/dashboard</a>

    <h2>Verificar se Controller existe:</h2>
    <div class="info">
        <?php
        $controllerFile = __DIR__ . '/../src/Controllers/CommunicationHubController.php';
        if (file_exists($controllerFile)) {
            echo "✅ Controller existe: " . $controllerFile . "<br>";
            require_once $controllerFile;
            if (class_exists('PixelHub\\Controllers\\CommunicationHubController')) {
                echo "✅ Classe carregada com sucesso<br>";
                $reflection = new ReflectionClass('PixelHub\\Controllers\\CommunicationHubController');
                if ($reflection->hasMethod('index')) {
                    echo "✅ Método index() existe<br>";
                } else {
                    echo "❌ Método index() NÃO existe<br>";
                }
            } else {
                echo "❌ Classe não encontrada após require<br>";
            }
        } else {
            echo "❌ Controller não existe: " . $controllerFile . "<br>";
        }
        ?>
    </div>

    <h2>Verificar se View existe:</h2>
    <div class="info">
        <?php
        $viewFile = __DIR__ . '/../views/communication_hub/index.php';
        if (file_exists($viewFile)) {
            echo "✅ View existe: " . $viewFile . "<br>";
        } else {
            echo "❌ View não existe: " . $viewFile . "<br>";
        }
        ?>
    </div>

    <h2>Testar Router diretamente:</h2>
    <div class="info">
        <?php
        // Autoload
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

        require_once __DIR__ . '/../src/Core/Router.php';
        $router = new \PixelHub\Core\Router();
        $router->get('/communication-hub', 'CommunicationHubController@index');
        
        // Testa match
        $testPath = '/communication-hub';
        echo "Testando match: '{$testPath}'<br>";
        
        // Usa reflexão para acessar método privado matchPath
        $reflection = new ReflectionClass($router);
        $matchMethod = $reflection->getMethod('matchPath');
        $matchMethod->setAccessible(true);
        
        $routes = $reflection->getProperty('routes');
        $routes->setAccessible(true);
        $routesArray = $routes->getValue($router);
        
        foreach ($routesArray as $route) {
            if ($route['method'] === 'GET') {
                $matched = $matchMethod->invoke($router, $route['path'], $testPath);
                echo "Rota: '{$route['path']}' -> Match: " . ($matched ? '✅ SIM' : '❌ NÃO') . "<br>";
            }
        }
        ?>
    </div>
</body>
</html>

