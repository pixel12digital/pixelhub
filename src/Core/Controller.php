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
     * 
     * Garante que sempre retorna JSON válido, mesmo em caso de erro.
     * Limpa output buffer antes de enviar para evitar corrupção.
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        // Limpa qualquer output anterior que possa corromper o JSON
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

