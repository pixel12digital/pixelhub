<?php

namespace PixelHub\Core;

/**
 * Router simples para gerenciar rotas
 */
class Router
{
    private array $routes = [];
    private array $middlewares = [];

    /**
     * Adiciona uma rota GET
     * @param string $path
     * @param string|callable $handler String no formato "Controller@method" ou Closure
     */
    public function get(string $path, $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * Adiciona uma rota POST
     * @param string $path
     * @param string|callable $handler String no formato "Controller@method" ou Closure
     */
    public function post(string $path, $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * Adiciona uma rota
     */
    private function addRoute(string $method, string $path, $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
        ];
    }

    /**
     * Adiciona middleware global
     */
    public function middleware(string $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Resolve a rota atual (usa REQUEST_URI diretamente - método legado)
     */
    public function resolve(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        
        // Remove barra final
        $uri = rtrim($uri, '/') ?: '/';
        
        $this->dispatch($method, $uri);
    }

    /**
     * Despacha uma rota específica (método novo - aceita path calculado)
     */
    public function dispatch(string $method, string $path): void
    {
        // Normaliza o path
        $path = rtrim($path, '/') ?: '/';

        if (function_exists('pixelhub_log')) {
            pixelhub_log("Router::dispatch: Buscando rota {$method} {$path}");
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->matchPath($route['path'], $path)) {
                if (function_exists('pixelhub_log')) {
                    pixelhub_log("Router::dispatch: Rota encontrada! {$method} {$route['path']} -> {$path}");
                }
                
                // Executa middlewares
                foreach ($this->middlewares as $middleware) {
                    $this->executeMiddleware($middleware);
                }

                // Executa o handler
                $this->executeHandler($route['handler']);
                return;
            }
        }

        // Rota não encontrada
        if (function_exists('pixelhub_log')) {
            pixelhub_log("Router::dispatch: 404 - Rota não encontrada: {$method} {$path}");
        }
        error_log("404 - Rota não encontrada: {$method} {$path}");
        error_log("Rotas registradas: " . json_encode($this->routes, JSON_PRETTY_PRINT));
        http_response_code(404);
        echo "404 - Página não encontrada";
    }

    /**
     * Verifica se o path da rota corresponde à URI
     */
    private function matchPath(string $routePath, string $uri): bool
    {
        // Normaliza ambos os paths
        $routePath = rtrim($routePath, '/') ?: '/';
        $uri = rtrim($uri, '/') ?: '/';
        
        // Se for exatamente igual, retorna true
        if ($routePath === $uri) {
            return true;
        }
        
        // Se a rota termina com * (wildcard), verifica se URI começa com o prefixo
        if (substr($routePath, -1) === '*') {
            $prefix = rtrim(substr($routePath, 0, -1), '/');
            return strpos($uri, $prefix) === 0;
        }
        
        // Converte {param} para regex
        $pattern = preg_replace('/\{[^}]+\}/', '[^/]+', $routePath);
        $pattern = '#^' . $pattern . '$#';
        
        return (bool) preg_match($pattern, $uri);
    }

    /**
     * Executa um middleware
     */
    private function executeMiddleware(string $middleware): void
    {
        if (class_exists($middleware)) {
            $instance = new $middleware();
            if (method_exists($instance, 'handle')) {
                $instance->handle();
            }
        }
    }

    /**
     * Executa o handler da rota
     */
    private function executeHandler($handler): void
    {
        // Se for uma closure ou função callable, executa diretamente
        if (is_callable($handler)) {
            call_user_func($handler);
            return;
        }
        
        // Se for string no formato Controller@method
        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$controller, $method] = explode('@', $handler);
            $controllerClass = "PixelHub\\Controllers\\{$controller}";
            
            if (function_exists('pixelhub_log')) {
                pixelhub_log("Router: Tentando executar {$controllerClass}@{$method}");
            }
            
            if (!class_exists($controllerClass)) {
                $errorMsg = "Controller {$controllerClass} não encontrado";
                if (function_exists('pixelhub_log')) {
                    pixelhub_log("Router: ERRO - {$errorMsg}");
                }
                throw new \RuntimeException($errorMsg);
            }
            
            if (function_exists('pixelhub_log')) {
                pixelhub_log("Router: Classe {$controllerClass} encontrada, instanciando...");
            }
            
            try {
                $controllerInstance = new $controllerClass();
                
                if (!method_exists($controllerInstance, $method)) {
                    $errorMsg = "Método {$method} não encontrado em {$controllerClass}";
                    if (function_exists('pixelhub_log')) {
                        pixelhub_log("Router: ERRO - {$errorMsg}");
                    }
                    throw new \RuntimeException($errorMsg);
                }
                
                if (function_exists('pixelhub_log')) {
                    pixelhub_log("Router: Método {$method} encontrado, executando...");
                }
                
                $controllerInstance->$method();
                
            } catch (\Throwable $e) {
                $errorMsg = "Router: ERRO ao executar handler: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine();
                if (function_exists('pixelhub_log')) {
                    pixelhub_log($errorMsg);
                    pixelhub_log("Stack trace: " . $e->getTraceAsString());
                } else {
                    error_log($errorMsg);
                }
                
                // Se display_errors estiver habilitado, mostra o erro
                $displayErrors = ini_get('display_errors');
                if ($displayErrors == '1' || $displayErrors == 'On') {
                    while (ob_get_level() > 0) {
                        @ob_end_clean();
                    }
                    http_response_code(500);
                    echo "<h1>Erro 500 - Router::executeHandler</h1>\n";
                    echo "<h2>Mensagem:</h2>\n";
                    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>\n";
                    echo "<h2>Arquivo:</h2>\n";
                    echo "<pre>" . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</pre>\n";
                    echo "<h2>Stack Trace:</h2>\n";
                    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
                    exit;
                }
                
                throw $e;
            }
        } else {
            throw new \RuntimeException("Handler inválido: " . gettype($handler));
        }
    }
}

