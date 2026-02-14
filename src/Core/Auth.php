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

            header("Location: {$url}");
            exit;
        }
    }

    /**
     * Requer que o usuário seja interno
     */
    public static function requireInternal(): void
    {
        self::requireAuth();
        
        // PATCH D: Marcar que Auth foi atingido
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/communication-hub/send') !== false) {
            header('X-PixelHub-Stage: auth-check');
        }
        
        // PATCH C: Log diagnóstico antes da verificação
        error_log('[Auth::requireInternal] CHECK');
        error_log('[Auth::requireInternal] Accept=' . ($_SERVER['HTTP_ACCEPT'] ?? ''));
        error_log('[Auth::requireInternal] XRW=' . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        error_log('[Auth::requireInternal] URI=' . ($_SERVER['REQUEST_URI'] ?? ''));
        error_log('[Auth::requireInternal] SESSION=' . session_id());
        error_log('[Auth::requireInternal] isInternal=' . (self::isInternal() ? 'YES' : 'NO'));
        
        if (!self::isInternal()) {
            // Verifica se é requisição AJAX/JSON (Content-Type ou Accept header)
            $isJsonRequest = (
                (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
                (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            );
            
            error_log('[Auth::requireInternal] NEGADO - isJsonRequest=' . ($isJsonRequest ? 'YES' : 'NO'));
            
            if ($isJsonRequest) {
                // PATCH D: Marcar que Auth negou acesso
                if (strpos($_SERVER['REQUEST_URI'] ?? '', '/communication-hub/send') !== false) {
                    header('X-PixelHub-Stage: auth-denied');
                }
                
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
        
        // PATCH D: Marcar que Auth autorizou acesso
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/communication-hub/send') !== false) {
            header('X-PixelHub-Stage: auth-authorized');
        }
        
        error_log('[Auth::requireInternal] AUTORIZADO');
    }
}

