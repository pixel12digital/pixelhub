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

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->matchPath($route['path'], $path)) {
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
            
            if (class_exists($controllerClass)) {
                $controllerInstance = new $controllerClass();
                if (method_exists($controllerInstance, $method)) {
                    $controllerInstance->$method();
                } else {
                    throw new \RuntimeException("Método {$method} não encontrado em {$controllerClass}");
                }
            } else {
                throw new \RuntimeException("Controller {$controllerClass} não encontrado");
            }
        } else {
            throw new \RuntimeException("Handler inválido: " . gettype($handler));
        }
    }
}

