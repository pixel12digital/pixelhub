<?php

namespace PixelHub\Core;

/**
 * Helper para criptografia de senhas usando AES-256-CBC
 */
class CryptoHelper
{
    /**
     * Obtém a chave de criptografia do .env
     */
    private static function getKey(): string
    {
        $key = Env::get('INFRA_SECRET_KEY') ?: '';
        // Normaliza para 32 bytes usando SHA256
        return substr(hash('sha256', $key, true), 0, 32);
    }

    /**
     * Criptografa uma string
     */
    public static function encrypt(string $plain): string
    {
        if (empty($plain)) {
            return '';
        }

        $key = self::getKey();
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        if ($cipher === false) {
            throw new \RuntimeException('Erro ao criptografar dados');
        }
        
        return base64_encode($iv . $cipher);
    }

    /**
     * Descriptografa uma string
     */
    public static function decrypt(?string $encoded): string
    {
        if (empty($encoded)) {
            return '';
        }

        $data = base64_decode($encoded, true);
        if ($data === false || strlen($data) < 17) {
            return '';
        }

        $iv = substr($data, 0, 16);
        $cipher = substr($data, 16);
        $key = self::getKey();
        
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        if ($plain === false) {
            // Se falhar, pode ser porque a chave mudou
            throw new \RuntimeException('Não foi possível descriptografar. A chave INFRA_SECRET_KEY pode ter sido alterada.');
        }
        
        return $plain;
    }
}

