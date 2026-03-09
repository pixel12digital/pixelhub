<?php

namespace PixelHub\Core;

use PixelHub\Core\DB;
use PixelHub\Core\RateLimiter;

/**
 * Classe para gerenciar autenticação
 */
class Auth
{
    private const SESSION_KEY = 'pixelhub_user';

    /**
     * Faz login do usuário
     */
    public static function login(string $email, string $password): ?array
    {
        // Rate limiting: verifica se pode tentar
        $rateLimitKey = 'login_' . md5($email . ($_SERVER['REMOTE_ADDR'] ?? ''));
        
        if (!RateLimiter::canAttempt($rateLimitKey)) {
            $lockoutTime = RateLimiter::getLockoutTimeRemaining($rateLimitKey);
            error_log("Tentativa de login bloqueada por rate limit: {$email} (lockout: {$lockoutTime}s)");
            return null;
        }
        
        $db = DB::getConnection();
        
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            RateLimiter::recordFailure($rateLimitKey);
            error_log("Tentativa de login com email não encontrado: {$email}");
            return null;
        }

        // Bloqueia login de usuários inativos
        if (isset($user['is_active']) && !$user['is_active']) {
            error_log("Tentativa de login de usuário inativo: {$email}");
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            RateLimiter::recordFailure($rateLimitKey);
            error_log("Tentativa de login com senha incorreta para: {$email}");
            return null;
        }
        
        // Login bem-sucedido: limpa tentativas
        RateLimiter::clearAttempts($rateLimitKey);

        // Registra último login
        try {
            $stmtLogin = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
            $stmtLogin->execute([$user['id']]);
            $user['last_login_at'] = date('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            // Não bloqueia login se falhar
        }

        // Remove password_hash antes de salvar na sessão
        unset($user['password_hash']);
        
        $_SESSION[self::SESSION_KEY] = $user;
        
        error_log("Login bem-sucedido: {$email} (ID: {$user['id']})");
        
        return $user;
    }

    /**
     * Faz logout
     */
    public static function logout(): void
    {
        if (isset($_SESSION[self::SESSION_KEY])) {
            $email = $_SESSION[self::SESSION_KEY]['email'] ?? 'unknown';
            unset($_SESSION[self::SESSION_KEY]);
            error_log("Logout realizado: {$email}");
        }
    }

    /**
     * Retorna o usuário logado
     */
    public static function user(): ?array
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    /**
     * Verifica se o usuário está autenticado
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Verifica se o usuário é interno (Pixel12)
     */
    public static function isInternal(): bool
    {
        $user = self::user();
        return $user && (bool) $user['is_internal'];
    }

    /**
     * Requer autenticação (redireciona se não estiver logado)
     */
    public static function requireAuth(): void
    {
        if (!self::check()) {
            // Verifica se é requisição AJAX/JSON (Content-Type ou Accept header)
            $isJsonRequest = (
                (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
                (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            );
            
            if ($isJsonRequest) {
                // Limpa output buffer antes de enviar JSON
                while (ob_get_level() > 0) {
                    @ob_end_clean();
                }
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'Não autenticado'
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            
            // Para requisições normais, redireciona para login
            $url = function_exists('pixelhub_url')
                ? pixelhub_url('/login')
                : '/login';

            if (!headers_sent()) {
            header("Location: {$url}");
        } else {
            echo "<script>window.location.href='{$url}';</script>";
        }
            exit;
        }

        // Libera o lock do arquivo de sessão imediatamente após confirmar autenticação.
        // Sem isso, requisições AJAX de polling (5s/15s/60s) serializam com a navegação,
        // pois o PHP mantém lock exclusivo no arquivo de sessão até o request terminar.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /**
     * Requer que o usuário seja interno
     */
    public static function requireInternal(): void
    {
        self::requireAuth();
        
        if (!self::isInternal()) {
            // Verifica se é requisição AJAX/JSON (Content-Type ou Accept header)
            $isJsonRequest = (
                (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
                (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            );
            
            if ($isJsonRequest) {
                // Limpa output buffer antes de enviar JSON
                while (ob_get_level() > 0) {
                    @ob_end_clean();
                }
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'Nao autorizado (requireInternal)',
                    'error_code' => 'NOT_INTERNAL',
                    'debug' => null
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            
            // Para requisições normais, retorna HTML
            http_response_code(403);
            echo "Acesso negado. Apenas usuários internos podem acessar esta área.";
            exit;
        }
    }
}
