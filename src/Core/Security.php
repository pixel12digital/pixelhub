<?php

namespace PixelHub\Core;

/**
 * Classe para funcionalidades de segurança
 */
class Security
{
    /**
     * Gera token CSRF
     */
    public static function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Valida token CSRF
     */
    public static function validateCsrf(?string $token = null): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = $token ?? ($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '');
        
        if (empty($token) || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Escape para prevenir XSS
     */
    public static function escape(string $string, int $flags = ENT_QUOTES, string $encoding = 'UTF-8'): string
    {
        return htmlspecialchars($string, $flags, $encoding);
    }
    
    /**
     * Sanitiza string removendo caracteres perigosos
     */
    public static function sanitize(string $input, bool $allowHtml = false): string
    {
        $input = trim($input);
        
        if (!$allowHtml) {
            $input = strip_tags($input);
        }
        
        return $input;
    }
    
    /**
     * Valida email
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Aplica headers de segurança
     * Headers são aplicados apenas se ainda não foram enviados
     */
    public static function setSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        
        // X-Frame-Options: Previne clickjacking (permite mesmo origin)
        if (!headers_sent()) {
            header('X-Frame-Options: SAMEORIGIN');
        }
        
        // X-Content-Type-Options: Previne MIME sniffing
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
        }
        
        // X-XSS-Protection: Ativa proteção XSS do navegador (legado, mas ajuda)
        if (!headers_sent()) {
            header('X-XSS-Protection: 1; mode=block');
        }
        
        // Referrer-Policy: Controla informações de referrer
        if (!headers_sent()) {
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
        
        // Content-Security-Policy básico (permissivo para compatibilidade)
        // Permite recursos do mesmo origin, inline scripts/styles, e recursos externos HTTPS
        // Isso garante que o código existente continue funcionando
        if (!headers_sent()) {
            $csp = "default-src 'self' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https: blob:; font-src 'self' data: https:; connect-src 'self' https: wss: ws:; frame-src 'self' https:;";
            header("Content-Security-Policy: {$csp}");
        }
        
        // Permissions-Policy: Limita recursos do navegador (não invasivo)
        if (!headers_sent()) {
            header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        }
    }
}

