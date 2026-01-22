<?php

namespace PixelHub\Core;

use PixelHub\Core\Security;

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

        // Verifica se headers já foram enviados (evita erro fatal)
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        } else {
            // Se headers já foram enviados, tenta definir status code de qualquer forma
            @http_response_code($statusCode);
            error_log("[Controller::json] AVISO: Headers já foram enviados antes de json() ser chamado");
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            error_log("[Controller::json] ERRO: Falha ao codificar JSON. Erro: " . json_last_error_msg());
            $data = ['success' => false, 'error' => 'Erro ao processar resposta'];
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        
        echo $json;
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
    
    /**
     * Valida CSRF token (opcional - não quebra formulários antigos)
     */
    protected function validateCsrf(bool $required = false): bool
    {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
        
        if (!$token && !$required) {
            // Se não for obrigatório e não tiver token, permite (compatibilidade)
            return true;
        }
        
        return Security::validateCsrf($token);
    }
    
    /**
     * Obtém token CSRF para usar em views
     */
    protected function csrfToken(): string
    {
        return Security::csrfToken();
    }
    
    /**
     * Sanitiza input
     */
    protected function sanitize(string $input, bool $allowHtml = false): string
    {
        return Security::sanitize($input, $allowHtml);
    }
}

