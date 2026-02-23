<?php

namespace PixelHub\Services;

/**
 * Cliente para enriquecimento de dados via CNPJ.ws
 * 
 * API Pública: https://www.cnpj.ws/docs/api-publica
 * Rate Limit: 3 requisições/minuto (gratuito)
 * 
 * Retorna dados de contato que a Minha Receita não fornece:
 * - Email
 * - Telefone(s)
 * - Website
 */
class CnpjWsEnrichmentClient
{
    private const BASE_URL = 'https://www.cnpj.ws';
    private const RATE_LIMIT_DELAY_MS = 20000; // 20 segundos entre requisições (3/min)
    
    /**
     * Busca dados de contato de uma empresa pelo CNPJ
     * 
     * @param string $cnpj CNPJ com ou sem formatação
     * @return array|null Dados de contato ou null se não encontrado
     * @throws \Exception Se houver erro na API
     */
    public function getContactData(string $cnpj): ?array
    {
        $cnpjClean = preg_replace('/\D/', '', $cnpj);
        
        if (empty($cnpjClean) || strlen($cnpjClean) !== 14) {
            throw new \InvalidArgumentException('CNPJ inválido');
        }
        
        $url = self::BASE_URL . '/cnpj/' . $cnpjClean;
        
        try {
            $data = $this->get($url);
            
            if (!$data || !isset($data['estabelecimento'])) {
                return null;
            }
            
            return $this->extractContactData($data);
            
        } catch (\Exception $e) {
            error_log('[CnpjWsEnrichmentClient] Erro ao buscar CNPJ ' . $cnpjClean . ': ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Extrai dados de contato relevantes da resposta da API
     */
    private function extractContactData(array $data): array
    {
        $estabelecimento = $data['estabelecimento'] ?? [];
        
        // Email
        $email = null;
        if (!empty($estabelecimento['email'])) {
            $email = trim($estabelecimento['email']);
            // Valida email básico
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = null;
            }
        }
        
        // Telefones
        $telefone1 = $this->formatPhone(
            $estabelecimento['ddd1'] ?? null,
            $estabelecimento['telefone1'] ?? null
        );
        
        $telefone2 = $this->formatPhone(
            $estabelecimento['ddd2'] ?? null,
            $estabelecimento['telefone2'] ?? null
        );
        
        // Website (se disponível)
        $website = null;
        if (!empty($data['website'])) {
            $website = trim($data['website']);
        }
        
        return [
            'email' => $email,
            'phone' => $telefone1,
            'phone_secondary' => $telefone2,
            'website' => $website,
            'source' => 'cnpjws',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Formata telefone no padrão E.164
     */
    private function formatPhone(?string $ddd, ?string $numero): ?string
    {
        if (empty($ddd) || empty($numero)) {
            return null;
        }
        
        $ddd = preg_replace('/\D/', '', $ddd);
        $numero = preg_replace('/\D/', '', $numero);
        
        if (empty($ddd) || empty($numero)) {
            return null;
        }
        
        // Formato E.164: +55DDNNNNNNNNN
        return '+55' . $ddd . $numero;
    }
    
    /**
     * Faz requisição GET para a API
     */
    private function get(string $url): ?array
    {
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'PixelHub/1.0 (Prospecting Tool)',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception('Erro na requisição: ' . $error);
        }
        
        if ($httpCode === 429) {
            throw new \Exception('Rate limit excedido. Aguarde 1 minuto.');
        }
        
        if ($httpCode === 404) {
            return null; // CNPJ não encontrado
        }
        
        if ($httpCode !== 200) {
            throw new \Exception('Erro HTTP ' . $httpCode);
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Erro ao decodificar JSON: ' . json_last_error_msg());
        }
        
        return $data;
    }
}
