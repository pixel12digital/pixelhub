<?php

namespace PixelHub\Services;

/**
 * Cliente HTTP para comunicação com a API do Asaas
 * 
 * Usa cURL nativo do PHP, sem dependências externas.
 */
class AsaasClient
{
    /**
     * Executa uma requisição HTTP para a API do Asaas
     * 
     * @param string $method Método HTTP (GET, POST, PUT, DELETE)
     * @param string $path Caminho da API (ex: '/customers', '/payments')
     * @param array|null $payload Dados do body (será convertido para JSON)
     * @return array Resposta decodificada do Asaas
     * @throws \RuntimeException Em caso de erro HTTP ou de configuração
     */
    public static function request(string $method, string $path, ?array $payload = null): array
    {
        $config = AsaasConfig::getConfig();
        $apiKey = AsaasConfig::getApiKey();
        $baseUrl = rtrim($config['base_url'], '/');
        $url = $baseUrl . '/' . ltrim($path, '/');

        // Inicializa cURL
        $ch = curl_init($url);

        // Headers
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'access_token: ' . $apiKey,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ]);

        // Adiciona payload se houver
        if ($payload !== null && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        // Executa requisição
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Trata erro de cURL
        if ($response === false || !empty($error)) {
            throw new \RuntimeException("Erro ao comunicar com Asaas: " . ($error ?: 'Resposta vazia'));
        }

        // Decodifica JSON
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Erro ao decodificar resposta do Asaas: " . json_last_error_msg());
        }

        // Trata erros HTTP
        if ($httpCode >= 400) {
            $errorMessage = $data['errors'][0]['description'] ?? $data['message'] ?? 'Erro desconhecido';
            throw new \RuntimeException("Erro do Asaas (HTTP {$httpCode}): {$errorMessage}", $httpCode);
        }

        return $data;
    }

    /**
     * Busca um customer pelo CPF/CNPJ
     * 
     * @param string $cpfCnpj CPF ou CNPJ (apenas números)
     * @return array|null Customer encontrado ou null
     */
    public static function findCustomerByCpfCnpj(string $cpfCnpj): ?array
    {
        try {
            // Remove formatação
            $cpfCnpj = preg_replace('/[^0-9]/', '', $cpfCnpj);
            
            // Busca customers com filtro cpfCnpj
            // A API do Asaas aceita query params: /customers?cpfCnpj=...
            $response = self::request('GET', '/customers?cpfCnpj=' . urlencode($cpfCnpj), null);
            
            // A API retorna um objeto com 'data' contendo array de customers
            if (isset($response['data']) && is_array($response['data']) && !empty($response['data'])) {
                // Retorna o primeiro resultado (CPF/CNPJ deve ser único)
                return $response['data'][0];
            }
            
            return null;
        } catch (\RuntimeException $e) {
            // Se não encontrar (404 ou lista vazia), retorna null
            if (strpos($e->getMessage(), '404') !== false) {
                return null;
            }
            // Para outros erros, relança a exceção
            throw $e;
        }
    }

    /**
     * Busca um customer pelo email
     *
     * @param string $email Email
     * @return array|null Customer encontrado ou null
     */
    public static function findCustomerByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $response = self::request('GET', '/customers?email=' . urlencode($email), null);

        if (isset($response['data']) && is_array($response['data']) && !empty($response['data'])) {
            return $response['data'][0];
        }

        return null;
    }

    /**
     * Busca um customer por CPF/CNPJ ou email (helper)
     *
     * @param string $identifier Email ou CPF/CNPJ
     * @return array|null
     */
    public static function findCustomerByCpfCnpjOrEmail(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if (empty($identifier)) {
            return null;
        }

        // Email
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return self::findCustomerByEmail($identifier);
        }

        // CPF/CNPJ
        $digits = preg_replace('/[^0-9]/', '', $identifier);
        if (in_array(strlen($digits), [11, 14], true)) {
            return self::findCustomerByCpfCnpj($digits);
        }

        return null;
    }

    /**
     * Busca todos os customers pelo CPF/CNPJ
     * 
     * Pode retornar múltiplos customers caso existam duplicidades no Asaas.
     * 
     * @param string $cpfCnpj CPF ou CNPJ (apenas números)
     * @return array Array de customers encontrados (pode estar vazio)
     */
    public static function findCustomersByCpfCnpj(string $cpfCnpj): array
    {
        try {
            // Remove formatação
            $cpfCnpj = preg_replace('/[^0-9]/', '', $cpfCnpj);
            
            if (empty($cpfCnpj)) {
                return [];
            }
            
            // Busca customers com filtro cpfCnpj
            // A API do Asaas aceita query params: /customers?cpfCnpj=...
            $response = self::request('GET', '/customers?cpfCnpj=' . urlencode($cpfCnpj), null);
            
            // A API retorna um objeto com 'data' contendo array de customers
            if (isset($response['data']) && is_array($response['data'])) {
                return $response['data'];
            }
            
            return [];
        } catch (\RuntimeException $e) {
            // Se não encontrar (404 ou lista vazia), retorna array vazio
            if (strpos($e->getMessage(), '404') !== false) {
                return [];
            }
            // Para outros erros, relança a exceção
            throw $e;
        }
    }

    /**
     * Cria um novo customer no Asaas
     * 
     * @param array $data Dados do customer
     * @return array Customer criado
     */
    public static function createCustomer(array $data): array
    {
        return self::request('POST', '/customers', $data);
    }

    /**
     * Busca um customer pelo ID
     * 
     * @param string $customerId ID do customer no Asaas
     * @return array Customer encontrado
     * @throws \RuntimeException Se customer não encontrado
     */
    public static function getCustomer(string $customerId): array
    {
        return self::request('GET', "/customers/{$customerId}", null);
    }

    /**
     * Atualiza um customer existente
     * 
     * @param string $customerId ID do customer no Asaas
     * @param array $data Dados para atualizar
     * @return array Customer atualizado
     */
    public static function updateCustomer(string $customerId, array $data): array
    {
        return self::request('PUT', "/customers/{$customerId}", $data);
    }

    /**
     * Cria um pagamento único
     * 
     * @param array $data Dados do pagamento
     * @return array Payment criado
     */
    public static function createPayment(array $data): array
    {
        return self::request('POST', '/payments', $data);
    }

    /**
     * Cria uma assinatura recorrente
     * 
     * @param array $data Dados da assinatura
     * @return array Subscription criada
     */
    public static function createSubscription(array $data): array
    {
        return self::request('POST', '/subscriptions', $data);
    }

    /**
     * Lista todos os customers do Asaas com paginação
     * 
     * @param int $limit Limite por página (padrão 100, máximo 100)
     * @param int $offset Offset para paginação
     * @return array ['data' => array, 'hasMore' => bool, 'totalCount' => int]
     */
    public static function listAllCustomers(int $limit = 100, int $offset = 0): array
    {
        // Limita o máximo a 100 (limite da API do Asaas)
        $limit = min($limit, 100);
        
        $queryParams = http_build_query([
            'limit' => $limit,
            'offset' => $offset,
        ]);
        
        $response = self::request('GET', '/customers?' . $queryParams, null);
        
        // A API do Asaas retorna: { "object": "list", "data": [...], "hasMore": bool, "totalCount": int }
        return [
            'data' => $response['data'] ?? [],
            'hasMore' => $response['hasMore'] ?? false,
            'totalCount' => $response['totalCount'] ?? 0,
        ];
    }
}

