<?php

namespace PixelHub\Services;

use PixelHub\Core\Env;
use PixelHub\Core\CryptoHelper;

/**
 * Service para obter o secret descriptografado do WhatsApp Gateway
 * 
 * Fonte única para o secret - garante que todas as rotas usam o mesmo valor.
 */
class GatewaySecret
{
    /**
     * Obtém o secret descriptografado do gateway
     * 
     * Este é o método único que deve ser usado para obter o secret.
     * Garante que todas as rotas (test_connection, send_real, CommunicationHub)
     * usem o mesmo valor.
     * 
     * @return string Secret descriptografado (vazio se não configurado)
     */
    public static function getDecrypted(): string
    {
        $secretRaw = trim(Env::get('WPP_GATEWAY_SECRET', ''));
        
        if (empty($secretRaw)) {
            return '';
        }
        
        // Secret em texto puro (ex: 64 hex chars do gateway) — usar direto, sem descriptografar
        // Evita que base64_decode + openssl_decrypt produzam lixo e enviem secret errado ao gateway
        if (strlen($secretRaw) >= 32 && ctype_xdigit($secretRaw)) {
            return $secretRaw;
        }
        
        try {
            $secret = CryptoHelper::decrypt($secretRaw);
            if (empty($secret)) {
                return $secretRaw;
            }
            return $secret;
        } catch (\Exception $e) {
            return $secretRaw;
        }
    }
}

