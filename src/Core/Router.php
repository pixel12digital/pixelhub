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
        // PATCH C: Log diagnóstico para POST /communication-hub/send
        // PATCH D: Marcar que Router foi atingido
        if ($method === 'POST' && strpos($path, '/communication-hub/send') !== false) {
            header('X-PixelHub-Stage: router-hit');
            error_log('[Router] POST /communication-hub/send HIT');
            error_log('[Router] URI=' . ($_SERVER['REQUEST_URI'] ?? ''));
            error_log('[Router] CT=' . ($_SERVER['CONTENT_TYPE'] ?? ''));
            error_log('[Router] Accept=' . ($_SERVER['HTTP_ACCEPT'] ?? ''));
            error_log('[Router] XRW=' . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
            error_log('[Router] POST=' . json_encode($_POST, JSON_UNESCAPED_UNICODE));
        }
        
        // Normaliza o path
        $path = rtrim($path, '/') ?: '/';

        if (function_exists('pixelhub_log')) {
            pixelhub_log("Router::dispatch: Buscando rota {$method} {$path}");
        }
        error_log("Router::dispatch: Buscando rota {$method} {$path}");

        // Log de todas as rotas registradas para debug (apenas para POST requests problemáticos)
        if ($method === 'POST' && (strpos($path, 'whatsapp-gateway') !== false || strpos($path, 'communication-hub') !== false)) {
            error_log("Router::dispatch: Rotas POST registradas:");
            foreach ($this->routes as $idx => $route) {
                if ($route['method'] === 'POST') {
                    error_log("Router::dispatch:   [{$idx}] {$route['method']} {$route['path']}");
                }
            }
        }

        // PATCH C: Try/catch específico para rota /communication-hub/send
        $isSendRoute = ($method === 'POST' && strpos($path, '/communication-hub/send') !== false);
        
        if ($isSendRoute) {
            try {
                foreach ($this->routes as $route) {
                    if ($route['method'] === $method && $this->matchPath($route['path'], $path)) {
                        // PATCH D: Marcar que Router vai despachar
                        header('X-PixelHub-Stage: router-dispatch');
                        if (function_exists('pixelhub_log')) {
                            pixelhub_log("Router::dispatch: Rota encontrada! {$method} {$route['path']} -> {$path}");
                        }
                        error_log("Router::dispatch: Rota encontrada! {$method} {$route['path']} -> {$path}");
                        
                        // Executa middlewares
                        foreach ($this->middlewares as $middleware) {
                            $this->executeMiddleware($middleware);
                        }

                        // Executa o handler
                        $this->executeHandler($route['handler']);
                        return;
                    }
                }
                
                // Rota não encontrada (dentro do try para ser capturado)
                if (function_exists('pixelhub_log')) {
                    pixelhub_log("Router::dispatch: 404 - Rota não encontrada: {$method} {$path}");
                }
                error_log("Router::dispatch: 404 - Rota não encontrada: {$method} {$path}");
                error_log("Rotas registradas: " . json_encode($this->routes, JSON_PRETTY_PRINT));
                http_response_code(404);
                echo "404 - Página não encontrada";
                
            } catch (\Throwable $e) {
                error_log('[Router] EXCECAO em /communication-hub/send: ' . $e->getMessage());
                error_log('[Router] TRACE: ' . $e->getTraceAsString());
                
                while (ob_get_level() > 0) {
                    @ob_end_clean();
                }
                
                // PATCH D: Marcar exceção no Router
                header('X-PixelHub-Stage: router-exception');
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro interno no router',
                    'error_code' => 'ROUTER_EXCEPTION',
                    'debug' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            
            return; // Retorna após processar a rota específica
        }

        // Fluxo normal para outras rotas
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->matchPath($route['path'], $path)) {
                if (function_exists('pixelhub_log')) {
                    pixelhub_log("Router::dispatch: Rota encontrada! {$method} {$route['path']} -> {$path}");
                }
                error_log("Router::dispatch: Rota encontrada! {$method} {$route['path']} -> {$path}");
                
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
        error_log("Router::dispatch: 404 - Rota não encontrada: {$method} {$path}");
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
                $errorMsg = "Router: ❌❌❌ ERRO ao executar handler ❌❌❌";
                $errorMsg .= "\nMensagem: " . $e->getMessage();
                $errorMsg .= "\nArquivo: " . $e->getFile() . ":" . $e->getLine();
                $errorMsg .= "\nTipo: " . get_class($e);
                $errorMsg .= "\nStack trace:\n" . $e->getTraceAsString();
                $errorMsg .= "\nController: {$controllerClass}";
                $errorMsg .= "\nMethod: {$method}";
                $errorMsg .= "\nPath: {$path}";
                
                if (function_exists('pixelhub_log')) {
                    pixelhub_log($errorMsg);
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
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => false,
                        'error' => 'Erro interno do servidor',
                        'error_code' => 'ROUTER_ERROR',
                        'debug' => $e->getMessage()
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    exit;
                }
                
                // Para requisições JSON, retorna JSON mesmo sem display_errors
                $isJsonRequest = (
                    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                    (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
                    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                );
                
                if ($isJsonRequest) {
                    while (ob_get_level() > 0) {
                        @ob_end_clean();
                    }
                    http_response_code(500);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => false,
                        'error' => 'Erro interno do servidor',
                        'error_code' => 'ROUTER_ERROR'
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    exit;
                }
                
                throw $e;
            }
        } else {
            throw new \RuntimeException("Handler inválido: " . gettype($handler));
        }
    }
}

