<?php

namespace PixelHub\Core;

use PixelHub\Core\Env;

/**
 * Helper para funcionalidades relacionadas ao Asaas
 */
class AsaasHelper
{
    /**
     * Constrói a URL do painel web do Asaas para um customer
     * 
     * Converte o asaas_customer_id do formato da API (ex: cus_000090939041)
     * para o ID numérico usado no painel web (ex: 90939041) e monta a URL.
     * 
     * IMPORTANTE: Esta função é exclusiva para links do painel web do Asaas,
     * não para chamadas de API.
     * 
     * @param string $asaasCustomerId ID do customer no formato da API (ex: cus_000090939041)
     * @return string URL completa do painel (ex: https://www.asaas.com/customerAccount/show/90939041)
     */
    public static function buildCustomerPanelUrl(string $asaasCustomerId): string
    {
        if (empty($asaasCustomerId)) {
            return '';
        }

        // Remove o prefixo "cus_" e qualquer caractere não numérico
        $numericId = preg_replace('/[^0-9]/', '', $asaasCustomerId);
        
        // Remove zeros à esquerda
        $numericId = ltrim($numericId, '0');
        
        // Se ficou vazio após remover zeros, usa '0' como fallback
        if (empty($numericId)) {
            $numericId = '0';
        }

        // Busca URL base do painel (pode ser configurada via env)
        $baseUrl = Env::get('ASAAS_DASHBOARD_BASE_URL') ?: 'https://www.asaas.com';
        $baseUrl = rtrim($baseUrl, '/');

        return $baseUrl . '/customerAccount/show/' . $numericId;
    }
}

