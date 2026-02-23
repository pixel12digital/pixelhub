<?php

namespace PixelHub\Services;

/**
 * Cliente para a API CNPJ.ws (plano comercial)
 *
 * Documentação: https://www.cnpj.ws/docs/api-publica/consultar-cnpj
 * Endpoint de busca: GET https://api.cnpj.ws/v1/estabelecimentos?cnae_fiscal_principal={cnae}&municipio_id={ibge}&situacao_cadastral=ATIVA
 * Autenticação: Bearer token no header Authorization
 */
class CnpjWsClient
{
    private const BASE_URL     = 'https://publica.cnpj.ws';
    private const BASE_URL_PUB = 'https://publica.cnpj.ws';
    private const TIMEOUT      = 20;
    private const SLEEP_MS     = 350;

    // =========================================================================
    // BUSCA POR CNAE + MUNICÍPIO
    // =========================================================================

    /**
     * Testa a chave API fazendo uma consulta simples
     *
     * @param string $apiKey Chave a testar
     * @return array ['success' => bool, 'message' => string]
     */
    public function testApiKey(string $apiKey): array
    {
        try {
            // GET /v2/pesquisa — endpoint de pesquisa da API comercial
            $url = self::BASE_URL . '/v2/pesquisa?' . http_build_query([
                'atividade_principal_id' => '4755501',
                'cidade_id'              => '4205407',
                'situacao_cadastral'     => 'Ativa',
                'limite'                 => 1,
            ]);
            $raw = $this->get($url, $apiKey);
            if (isset($raw['cnpjs']) || isset($raw['data']) || is_array($raw)) {
                return ['success' => true, 'message' => 'Conexão com CNPJ.ws estabelecida com sucesso!'];
            }
            return ['success' => false, 'message' => 'Resposta inesperada da API.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Busca empresas por CNAE + cidade via API CNPJ.ws comercial
     *
     * @param string $cnaeCode   Código CNAE (ex: "4755-5/01" ou "4755501")
     * @param string $cityName   Nome da cidade (ex: "Curitiba")
     * @param string $uf         UF (ex: "PR")
     * @param string $situacao   Situação cadastral: A=Ativa
     * @param int    $maxResults Máximo de resultados
     * @return array Lista de empresas normalizadas
     */
    public function searchByCnaeAndCity(string $cnaeCode, string $cityName, string $uf, string $situacao = 'A', int $maxResults = 20): array
    {
        $cnaeClean = preg_replace('/\D/', '', $cnaeCode);
        if (empty($cnaeClean)) {
            throw new \InvalidArgumentException('Código CNAE inválido');
        }

        $apiKey = $this->resolveApiKey();

        // Resolve código IBGE do município
        $ibgeCode = $this->resolveIbgeCode($cityName, $uf);
        if (!$ibgeCode) {
            throw new \RuntimeException("Município não encontrado: {$cityName}/{$uf}.");
        }

        // API comercial: situacao_cadastral usa formato capitalizado ("Ativa", "Baixada"...)
        $situacaoParam = $situacao === 'A' ? 'Ativa' : ucfirst(strtolower($situacao));

        // GET /v2/pesquisa — endpoint de pesquisa com filtros
        $url = self::BASE_URL . '/v2/pesquisa?' . http_build_query([
            'atividade_principal_id' => $cnaeClean,
            'cidade_id'              => $ibgeCode,
            'situacao_cadastral'     => $situacaoParam,
            'limite'                 => min($maxResults, 100),
        ]);

        $raw = $this->get($url, $apiKey);

        // Resposta: { cnpjs: [...], tem_proxima_pagina: bool, proximo_cursor: string }
        $cnpjList = $raw['cnpjs'] ?? [];

        if (empty($cnpjList)) {
            return [];
        }

        // A pesquisa retorna apenas CNPJs — precisamos consultar cada um individualmente
        $results = [];
        foreach ($cnpjList as $cnpj) {
            if (count($results) >= $maxResults) break;
            usleep(self::SLEEP_MS * 1000);
            try {
                $detail = $this->get(self::BASE_URL . '/cnpj/' . $cnpj, $apiKey);
                $normalized = $this->normalizeCompany($detail);
                if ($normalized) $results[] = $normalized;
            } catch (\Exception $e) {
                error_log('[CnpjWsClient] Erro ao consultar CNPJ ' . $cnpj . ': ' . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Resolve a chave API do banco de dados
     */
    private function resolveApiKey(): string
    {
        try {
            $key = \PixelHub\Services\ProspectingService::getCnpjWsApiKey();
        } catch (\Exception $e) {
            $key = null;
        }
        if (empty($key)) {
            throw new \RuntimeException('Chave API CNPJ.ws não configurada. Acesse Configurações > Integrações > CNPJ.ws para configurar.');
        }
        return $key;
    }

    /**
     * @deprecated Use searchByCnaeAndCity diretamente
     */
    public function searchByCnae(string $cnaeCode, string $ibgeCode, string $situacao = 'A', int $maxResults = 20): array
    {
        return [];
    }

    // =========================================================================
    // RESOLUÇÃO DE CÓDIGO IBGE
    // =========================================================================

    /**
     * Resolve o código IBGE de um município via API do IBGE
     *
     * @param string $cityName Nome da cidade
     * @param string $uf       UF (ex: PR)
     * @return string|null Código IBGE de 7 dígitos ou null se não encontrado
     */
    public function resolveIbgeCode(string $cityName, string $uf): ?string
    {
        $uf = strtolower(trim($uf));
        $url = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados/' . urlencode($uf) . '/municipios';

        try {
            $data = $this->get($url);
        } catch (\Exception $e) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        $cityNorm = $this->normalizeString($cityName);

        foreach ($data as $municipio) {
            $nome = $municipio['nome'] ?? '';
            if ($this->normalizeString($nome) === $cityNorm) {
                return (string) $municipio['id'];
            }
        }

        // Busca parcial como fallback
        foreach ($data as $municipio) {
            $nome = $municipio['nome'] ?? '';
            if (str_contains($this->normalizeString($nome), $cityNorm)) {
                return (string) $municipio['id'];
            }
        }

        return null;
    }

    /**
     * Busca CNAEs por código ou palavra-chave livre (ex: "loja", "cama mesa banho", "imobiliária")
     *
     * Usa a API do IBGE/CONCLA que indexa as atividades de cada subclasse,
     * permitindo busca por termos informais que não aparecem na descrição principal.
     *
     * Endpoint: GET https://servicodados.ibge.gov.br/api/v2/cnae/subclasses?busca={termo}
     *
     * @param string $query Código (ex: "4755") ou palavra-chave livre (ex: "cama mesa banho")
     * @param int    $limit Máximo de resultados
     * @return array Lista de ['code' => '...', 'desc' => '...']
     */
    public function searchCnae(string $query, int $limit = 15): array
    {
        $query = trim($query);
        if (strlen($query) < 2) {
            return [];
        }

        // Se for código numérico, busca direta pelo código
        if (preg_match('/^\d{4,}/', $query)) {
            $code = preg_replace('/\D/', '', $query);
            $url  = 'https://servicodados.ibge.gov.br/api/v2/cnae/subclasses/' . urlencode($code);
            try {
                $item = $this->get($url);
                if (!empty($item['id'])) {
                    return [[
                        'code' => $this->formatCnaeCode((string) $item['id']),
                        'desc' => $item['descricao'] ?? '',
                    ]];
                }
            } catch (\Exception $e) {
                // fallthrough para busca por texto
            }
        }

        // Busca por palavra-chave — indexa descrição + atividades de cada subclasse
        $url = 'https://servicodados.ibge.gov.br/api/v2/cnae/subclasses?' . http_build_query(['busca' => $query]);

        try {
            $data = $this->get($url);
        } catch (\Exception $e) {
            return [];
        }

        if (!is_array($data)) {
            return [];
        }

        $results = [];
        foreach ($data as $item) {
            if (empty($item['id'])) {
                continue;
            }
            $results[] = [
                'code' => $this->formatCnaeCode((string) $item['id']),
                'desc' => $item['descricao'] ?? '',
            ];
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * Formata código CNAE de 7 dígitos para o padrão XXXX-X/XX (ex: 4755501 → 4755-5/01)
     */
    private function formatCnaeCode(string $code): string
    {
        $d = preg_replace('/\D/', '', $code);
        if (strlen($d) === 7) {
            return substr($d, 0, 4) . '-' . $d[4] . '/' . substr($d, 5, 2);
        }
        return $code;
    }

    /**
     * Busca municípios por UF para autocomplete no frontend
     *
     * @param string $uf UF (ex: PR)
     * @return array Lista de ['ibge' => '...', 'name' => '...']
     */
    public function listMunicipiosByUf(string $uf): array
    {
        $uf  = strtolower(trim($uf));
        $url = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados/' . urlencode($uf) . '/municipios';

        try {
            $data = $this->get($url);
        } catch (\Exception $e) {
            return [];
        }

        if (!is_array($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $m) {
            $result[] = [
                'ibge' => (string) $m['id'],
                'name' => $m['nome'] ?? '',
            ];
        }

        usort($result, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $result;
    }

    // =========================================================================
    // NORMALIZAÇÃO
    // =========================================================================

    /**
     * Normaliza um item da API CNPJ.ws para o formato padrão de prospecting_results
     */
    private function normalizeCompany(array $item): ?array
    {
        $cnpj = preg_replace('/\D/', '', $item['cnpj'] ?? '');
        if (empty($cnpj) || strlen($cnpj) !== 14) {
            return null;
        }

        $razao      = trim($item['razao_social'] ?? '');
        $fantasia   = trim($item['nome_fantasia'] ?? '');
        $name       = $fantasia ?: $razao;

        if (empty($name)) {
            return null;
        }

        // Endereço
        $logradouro = trim($item['logradouro'] ?? '');
        $numero     = trim($item['numero'] ?? '');
        $bairro     = trim($item['bairro'] ?? '');
        $municipio  = trim($item['municipio'] ?? '');
        $uf         = strtoupper(trim($item['uf'] ?? ''));
        $cep        = preg_replace('/\D/', '', $item['cep'] ?? '');

        $addressParts = array_filter([$logradouro . ($numero ? ', ' . $numero : ''), $bairro, $municipio . ($uf ? '/' . $uf : ''), $cep ? 'CEP ' . $cep : '']);
        $address = implode(' — ', $addressParts);

        // Telefone: tenta ddd + telefone
        $ddd      = trim($item['ddd_telefone_1'] ?? '');
        $telefone = trim($item['telefone_1'] ?? '');
        $phone    = null;
        if ($ddd && $telefone) {
            $phone = '+55' . preg_replace('/\D/', '', $ddd . $telefone);
        } elseif (!empty($item['ddd_telefone_1'])) {
            $raw = preg_replace('/\D/', '', $item['ddd_telefone_1']);
            if (strlen($raw) >= 10) {
                $phone = '+55' . $raw;
            }
        }

        // Email
        $email = trim($item['email'] ?? '') ?: null;

        // CNAE principal
        $cnaeCode = $item['cnae_fiscal'] ?? null;
        $cnaeDesc = $item['cnae_fiscal_descricao'] ?? null;

        return [
            'cnpj'             => $cnpj,
            'name'             => $name,
            'razao_social'     => $razao,
            'address'          => $address ?: null,
            'city'             => $municipio ?: null,
            'state'            => $uf ?: null,
            'phone'            => $phone,
            'email'            => $email,
            'website'          => null,
            'cnae_code'        => $cnaeCode ? (string) $cnaeCode : null,
            'cnae_description' => $cnaeDesc,
            'situacao'         => $item['descricao_situacao_cadastral'] ?? null,
            'source'           => 'cnpjws',
        ];
    }

    /**
     * Normaliza empresa retornada pela Casa dos Dados para o formato padrão
     */
    private function normalizeCasaDados(array $item): ?array
    {
        $cnpj = preg_replace('/\D/', '', $item['cnpj'] ?? '');
        if (empty($cnpj) || strlen($cnpj) !== 14) {
            return null;
        }

        $razao    = trim($item['razao_social'] ?? '');
        $fantasia = trim($item['nome_fantasia'] ?? '');
        $name     = $fantasia ?: $razao;
        if (empty($name)) return null;

        $logradouro = trim($item['logradouro'] ?? '');
        $numero     = trim($item['numero'] ?? '');
        $bairro     = trim($item['bairro'] ?? '');
        $municipio  = trim($item['municipio'] ?? '');
        $uf         = strtoupper(trim($item['uf'] ?? ''));
        $cep        = preg_replace('/\D/', '', $item['cep'] ?? '');

        $parts   = array_filter([$logradouro . ($numero ? ', ' . $numero : ''), $bairro, $municipio . ($uf ? '/' . $uf : ''), $cep ? 'CEP ' . $cep : '']);
        $address = implode(' — ', $parts);

        $ddd      = preg_replace('/\D/', '', $item['ddd_telefone_1'] ?? '');
        $tel      = preg_replace('/\D/', '', $item['telefone_1'] ?? '');
        $phone    = null;
        if ($ddd && $tel) {
            $phone = '+55' . $ddd . $tel;
        } elseif (strlen($ddd) >= 10) {
            $phone = '+55' . $ddd;
        }

        return [
            'cnpj'             => $cnpj,
            'name'             => $name,
            'razao_social'     => $razao,
            'address'          => $address ?: null,
            'city'             => $municipio ?: null,
            'state'            => $uf ?: null,
            'phone'            => $phone,
            'email'            => trim($item['email'] ?? '') ?: null,
            'website'          => null,
            'cnae_code'        => isset($item['cnae_fiscal']) ? (string) $item['cnae_fiscal'] : null,
            'cnae_description' => $item['cnae_fiscal_descricao'] ?? null,
            'situacao'         => 'ATIVA',
            'source'           => 'cnpjws',
        ];
    }

    // =========================================================================
    // HTTP
    // =========================================================================

    /**
     * Executa POST JSON com cURL e retorna array decodificado
     */
    private function post(string $url, array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $ch   = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: Mozilla/5.0 (compatible; PixelHub/1.0)',
            ],
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \RuntimeException('Erro cURL: ' . $err);
        }
        if ($code === 429) {
            throw new \RuntimeException('Rate limit atingido. Aguarde alguns segundos e tente novamente.');
        }
        if ($code !== 200) {
            throw new \RuntimeException("API Casa dos Dados retornou HTTP {$code}");
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Resposta inválida da API: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Executa GET com cURL e retorna array decodificado
     *
     * @param string      $url    URL completa
     * @param string|null $apiKey Token Bearer (opcional — para endpoints públicos como IBGE)
     */
    private function get(string $url, ?string $apiKey = null): mixed
    {
        $headers = [
            'Accept: application/json',
            'User-Agent: PixelHub/1.0 (hub.pixel12digital.com.br)',
        ];
        if (!empty($apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \RuntimeException('Erro cURL: ' . $err);
        }

        if ($code === 429) {
            throw new \RuntimeException('Rate limit atingido na API CNPJ.ws. Aguarde alguns segundos e tente novamente.');
        }

        if ($code !== 200) {
            throw new \RuntimeException("API retornou HTTP {$code}");
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Resposta inválida da API: ' . json_last_error_msg());
        }

        usleep(self::SLEEP_MS * 1000);

        return $decoded;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Normaliza string para comparação (remove acentos, lowercase)
     */
    private function normalizeString(string $str): string
    {
        $str = mb_strtolower(trim($str), 'UTF-8');
        $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        return preg_replace('/[^a-z0-9 ]/', '', $str);
    }

    /**
     * Formata CNPJ para exibição: XX.XXX.XXX/XXXX-XX
     */
    public static function formatCnpj(string $cnpj): string
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }
        return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    }
}
