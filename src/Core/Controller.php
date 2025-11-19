<?php

namespace PixelHub\Core;

/**
 * Classe base para controllers
 */
abstract class Controller
{
    /**
     * Renderiza uma view
     */
    protected function view(string $view, array $data = []): void
    {
        extract($data);
        
        $viewPath = __DIR__ . '/../../views/' . str_replace('.', '/', $view) . '.php';
        
        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View não encontrada: {$viewPath}");
        }

        require $viewPath;
    }

    /**
     * Retorna JSON
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Redireciona para uma URL
     * Método centralizado - ÚNICA forma de fazer redirect no projeto
     */
    protected function redirect(string $path): void
    {
        // Se vier uma URL absoluta (http...), redireciona direto
        if (preg_match('#^https?://#i', $path)) {
            header("Location: {$path}");
            exit;
        }

        // Caminho relativo ou começando com /
        if (function_exists('pixelhub_url')) {
            $url = pixelhub_url($path);
        } elseif (defined('BASE_PATH')) {
            $base = BASE_PATH;
            $path = '/' . ltrim($path, '/');
            $url = $base . $path;
        } else {
            // fallback teórico, mas em prática BASE_PATH sempre deve existir
            $url = $path;
        }

        header("Location: {$url}");
        exit;
    }
}

