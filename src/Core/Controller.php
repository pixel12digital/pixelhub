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
        // ===== INSTRUMENTAÇÃO: Captura payload final antes de json_encode =====
        // OBJETIVO: Detectar mutações do channel_id fora do método send()
        $isSendRoute = isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/communication-hub/send') !== false;
        
        if ($isSendRoute) {
            // Extrai request_id do header (definido no método send())
            $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
            if (!$requestId && function_exists('apache_request_headers')) {
                $headers = apache_request_headers();
                $requestId = $headers['X-Request-ID'] ?? null;
            }
            if (!$requestId) {
                // Fallback: gera novo (não ideal, mas melhor que nada)
                $requestId = substr(str_replace('-', '', bin2hex(random_bytes(8))), 0, 16);
            }
            $logPrefix = "[Controller::json][rid={$requestId}]";
            
            // Loga o payload completo ANTES de qualquer processamento (SANITIZADO)
            error_log("{$logPrefix} ===== PAYLOAD FINAL ANTES json_encode =====");
            error_log("{$logPrefix} URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
            error_log("{$logPrefix} Status Code: {$statusCode}");
            
            // Sanitiza payload para não vazar dados sensíveis
            $sanitizedData = $data;
            
            // Remove/mascara dados sensíveis
            if (isset($sanitizedData['message'])) {
                $msg = $sanitizedData['message'];
                $sanitizedData['message'] = strlen($msg) > 50 ? substr($msg, 0, 50) . '... (truncated, len=' . strlen($msg) . ')' : $msg;
            }
            if (isset($sanitizedData['to'])) {
                $phone = $sanitizedData['to'];
                // Mascara telefone: mantém apenas últimos 4 dígitos
                if (strlen($phone) > 4) {
                    $sanitizedData['to'] = str_repeat('*', strlen($phone) - 4) . substr($phone, -4);
                }
            }
            if (isset($sanitizedData['base64Ptt'])) {
                $sanitizedData['base64Ptt'] = '[REMOVED - sensitive data]';
            }
            if (isset($sanitizedData['raw']) && is_array($sanitizedData['raw'])) {
                // Remove dados sensíveis do raw também
                if (isset($sanitizedData['raw']['base64Ptt'])) {
                    $sanitizedData['raw']['base64Ptt'] = '[REMOVED - sensitive data]';
                }
                if (count($sanitizedData['raw']) > 10) {
                    $sanitizedData['raw'] = array_slice($sanitizedData['raw'], 0, 10);
                    $sanitizedData['raw']['_truncated'] = true;
                }
            }
            
            // Loga apenas campos seguros para diagnóstico
            $safeFields = [
                'success' => $sanitizedData['success'] ?? null,
                'error' => $sanitizedData['error'] ?? null,
                'error_code' => $sanitizedData['error_code'] ?? null,
                'channel_id' => $sanitizedData['channel_id'] ?? null,
                'tenant_id' => $sanitizedData['tenant_id'] ?? null,
                'thread_id' => $sanitizedData['thread_id'] ?? null,
            ];
            
            // Loga especificamente o channel_id se existir
            if (isset($data['channel_id'])) {
                error_log("{$logPrefix} channel_id no payload ANTES json_encode: '" . $data['channel_id'] . "'");
                error_log("{$logPrefix} channel_id tipo: " . gettype($data['channel_id']));
                error_log("{$logPrefix} channel_id length: " . strlen((string)$data['channel_id']));
            } else {
                error_log("{$logPrefix} channel_id: NÃO PRESENTE no payload");
            }
            
            // Loga apenas campos seguros
            error_log("{$logPrefix} Campos seguros do payload: " . json_encode($safeFields, JSON_UNESCAPED_UNICODE));
            error_log("{$logPrefix} ===== FIM PAYLOAD FINAL =====");
        }
        
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
        
        // Loga o JSON final (primeiros 500 chars) para verificar se houve mutação
        if ($isSendRoute && isset($logPrefix)) {
            // Extrai apenas channel_id do JSON para verificar mutação
            $jsonDecoded = json_decode($json, true);
            if (isset($jsonDecoded['channel_id'])) {
                error_log("{$logPrefix} channel_id no JSON FINAL (após json_encode): '" . $jsonDecoded['channel_id'] . "'");
            }
            
            // Loga JSON sanitizado (sem dados sensíveis)
            $jsonSanitized = $jsonDecoded ?? [];
            if (isset($jsonSanitized['message'])) {
                $jsonSanitized['message'] = '[REDACTED]';
            }
            if (isset($jsonSanitized['to'])) {
                $jsonSanitized['to'] = '[REDACTED]';
            }
            if (isset($jsonSanitized['base64Ptt'])) {
                $jsonSanitized['base64Ptt'] = '[REDACTED]';
            }
            
            $jsonPreview = json_encode($jsonSanitized, JSON_UNESCAPED_UNICODE);
            $jsonPreview = strlen($jsonPreview) > 500 ? substr($jsonPreview, 0, 500) . '... (truncated)' : $jsonPreview;
            error_log("{$logPrefix} JSON final sanitizado (primeiros 500 chars): " . $jsonPreview);
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

