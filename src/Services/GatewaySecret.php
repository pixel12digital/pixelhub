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
        $secretRaw = Env::get('WPP_GATEWAY_SECRET', '');
        
        if (empty($secretRaw)) {
            return '';
        }
        
        try {
            $secret = CryptoHelper::decrypt($secretRaw);
            if (empty($secret)) {
                // Se descriptografia retornou vazio, pode ser que não esteja criptografado
                return $secretRaw;
            }
            return $secret;
        } catch (\Exception $e) {
            // Se falhar, tenta usar diretamente (pode não estar criptografado)
            return $secretRaw;
        }
    }
}

