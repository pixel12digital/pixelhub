<?php

namespace PixelHub\Core;

/**
 * Classe para rate limiting (proteção contra brute force)
 */
class RateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 300; // 5 minutos
    private const LOCKOUT_SECONDS = 900; // 15 minutos após muitas tentativas
    
    /**
     * Verifica se pode fazer tentativa (rate limiting suave)
     */
    public static function canAttempt(string $key): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $sessionKey = "rate_limit_{$key}";
        $lockoutKey = "rate_lockout_{$key}";
        
        // Verifica se está em lockout
        if (isset($_SESSION[$lockoutKey])) {
            $lockoutTime = $_SESSION[$lockoutKey];
            if (time() < $lockoutTime) {
                return false;
            }
            // Lockout expirou, remove
            unset($_SESSION[$lockoutKey]);
        }
        
        // Verifica tentativas recentes
        if (!isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = [
                'attempts' => 0,
                'first_attempt' => time()
            ];
            return true;
        }
        
        $data = $_SESSION[$sessionKey];
        $now = time();
        
        // Se passou a janela de tempo, reseta
        if ($now - $data['first_attempt'] > self::WINDOW_SECONDS) {
            $_SESSION[$sessionKey] = [
                'attempts' => 0,
                'first_attempt' => $now
            ];
            return true;
        }
        
        // Se excedeu tentativas, ativa lockout
        if ($data['attempts'] >= self::MAX_ATTEMPTS) {
            $_SESSION[$lockoutKey] = $now + self::LOCKOUT_SECONDS;
            unset($_SESSION[$sessionKey]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Registra uma tentativa falha
     */
    public static function recordFailure(string $key): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $sessionKey = "rate_limit_{$key}";
        
        if (!isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = [
                'attempts' => 1,
                'first_attempt' => time()
            ];
        } else {
            $_SESSION[$sessionKey]['attempts']++;
        }
    }
    
    /**
     * Limpa tentativas (chamado após sucesso)
     */
    public static function clearAttempts(string $key): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $sessionKey = "rate_limit_{$key}";
        $lockoutKey = "rate_lockout_{$key}";
        
        unset($_SESSION[$sessionKey]);
        unset($_SESSION[$lockoutKey]);
    }
    
    /**
     * Retorna tempo restante de lockout em segundos
     */
    public static function getLockoutTimeRemaining(string $key): int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $lockoutKey = "rate_lockout_{$key}";
        
        if (!isset($_SESSION[$lockoutKey])) {
            return 0;
        }
        
        $remaining = $_SESSION[$lockoutKey] - time();
        return max(0, $remaining);
    }
}

