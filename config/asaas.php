<?php

/**
 * Configuração do Asaas para Pixel Hub
 * 
 * Valores podem ser sobrescritos via variáveis de ambiente:
 * - ASAAS_API_KEY
 * - ASAAS_ENV
 * - ASAAS_API_BASE_URL (opcional)
 * - ASAAS_WEBHOOK_TOKEN
 */
return [
    'api_key'       => null,         // default: lido via getenv('ASAAS_API_KEY')
    'env'           => 'production', // ou 'sandbox', lido de ASAAS_ENV
    'base_url'      => null,         // se null, decidir com base em 'env'
    'webhook_token' => null,         // token para validar webhooks desse projeto (ASAAS_WEBHOOK_TOKEN)
];

