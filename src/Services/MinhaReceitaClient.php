<?php

namespace PixelHub\Services;

/**
 * Cliente para a API Minha Receita (minhareceita.org)
 *
 * API pública, gratuita e sem autenticação baseada nos dados abertos da Receita Federal.
 * Documentação: https://docs.minhareceita.org/como-usar/
 *
 * Busca em lote: GET https://minhareceita.org/?cnae=XXXX&uf=SC&municipio=IBGE&limit=N
 * Paginação por cursor: &cursor=CNPJ_ULTIMO
 */
class MinhaReceitaClient
{
    private const BASE_URL = 'https://minhareceita.org';
    private const TIMEOUT  = 25;
    private const MAX_PER_PAGE = 20;

    // =========================================================================
    // BUSCA EM LOTE POR CNAE + REGIÃO
    // =========================================================================

    /**
     * Busca empresas ativas por CNAE + UF (+ município opcional)
     *
     * @param string      $cnaeCode   Código CNAE (ex: "4755-5/01" ou "4755501")
     * @param string      $uf         UF (ex: "SC")
     * @param string|null $ibgeCode   Código IBGE do município (ex: "4205407") — opcional
     * @param int         $maxResults Máximo de resultados (paginado automaticamente)
     * @return array Lista de empresas normalizadas
     */
    public function searchByCnaeAndRegion(string $cnaeCode, string $uf, ?string $ibgeCode = null, int $maxResults = 50): array
    {
        $cnaeClean = preg_replace('/\D/', '', $cnaeCode);
        if (empty($cnaeClean)) {
            throw new \InvalidArgumentException('Código CNAE inválido');
        }

        $uf = strtoupper(trim($uf));
        if (empty($uf) || strlen($uf) !== 2) {
            throw new \InvalidArgumentException('UF inválida');
        }

        $results = [];
        $cursor  = null;
        $perPage = min(self::MAX_PER_PAGE, $maxResults);

        do {
            $params = [
                'cnae'  => $cnaeClean,
                'uf'    => $uf,
                'limit' => $perPage,
            ];

            if (!empty($ibgeCode)) {
                $params['municipio'] = preg_replace('/\D/', '', $ibgeCode);
            }

            if ($cursor !== null) {
                $params['cursor'] = $cursor;
            }

            $url = self::BASE_URL . '/?' . http_build_query($params);

            $raw = $this->get($url);

            $batch = $raw['data'] ?? [];
            if (empty($batch)) {
                break;
            }

            foreach ($batch as $item) {
                $normalized = $this->normalizeCompany($item);
                if ($normalized) {
                    $results[] = $normalized;
                }
                if (count($results) >= $maxResults) {
                    break 2;
                }
            }

            $cursor = $raw['cursor'] ?? null;

        } while ($cursor !== null && count($results) < $maxResults);

        return $results;
    }

    /**
     * Resolve o código IBGE de um município via API do IBGE
     *
     * @param string $cityName Nome da cidade (ex: "Florianópolis")
     * @param string $uf       UF (ex: "SC")
     * @return string|null Código IBGE de 7 dígitos ou null se não encontrado
     */
    public function resolveIbgeCode(string $cityName, string $uf): ?string
    {
        $uf  = strtolower(trim($uf));
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

        foreach ($data as $municipio) {
            $nome = $municipio['nome'] ?? '';
            if (str_contains($this->normalizeString($nome), $cityNorm)) {
                return (string) $municipio['id'];
            }
        }

        return null;
    }

    // =========================================================================
    // NORMALIZAÇÃO
    // =========================================================================

    /**
     * Normaliza um item da API Minha Receita para o formato padrão de prospecting_results
     */
    private function normalizeCompany(array $item): ?array
    {
        $cnpj = preg_replace('/\D/', '', $item['cnpj'] ?? '');
        if (empty($cnpj) || strlen($cnpj) !== 14) {
            return null;
        }

        $razao    = trim($item['razao_social'] ?? '');
        $fantasia = trim($item['nome_fantasia'] ?? '');
        $name     = $fantasia ?: $razao;

        if (empty($name)) {
            return null;
        }

        // Endereço
        $tipoLog    = trim($item['descricao_tipo_de_logradouro'] ?? '');
        $logradouro = trim($item['logradouro'] ?? '');
        $numero     = trim($item['numero'] ?? '');
        $bairro     = trim($item['bairro'] ?? '');
        $municipio  = trim($item['municipio'] ?? '');
        $uf         = strtoupper(trim($item['uf'] ?? ''));
        $cep        = preg_replace('/\D/', '', $item['cep'] ?? '');

        $logFull = trim(($tipoLog ? $tipoLog . ' ' : '') . $logradouro);
        $addressParts = array_filter([
            $logFull . ($numero ? ', ' . $numero : ''),
            $bairro,
            $municipio . ($uf ? '/' . $uf : ''),
            $cep ? 'CEP ' . $cep : '',
        ]);
        $address = implode(' — ', $addressParts);

        // Telefone: campo ddd_telefone_1 contém DDD+número concatenados
        $telRaw = preg_replace('/\D/', '', $item['ddd_telefone_1'] ?? '');
        $phone  = null;
        if (strlen($telRaw) >= 10) {
            $phone = '+55' . $telRaw;
        }

        // Telefone secundário
        $telRaw2 = preg_replace('/\D/', '', $item['ddd_telefone_2'] ?? '');
        $phone2  = null;
        if (strlen($telRaw2) >= 10) {
            $phone2 = '+55' . $telRaw2;
        }

        // Email
        $email = trim($item['email'] ?? '') ?: null;
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = null;
        }

        // CNAE principal
        $cnaeCode = isset($item['cnae_fiscal']) ? (string) $item['cnae_fiscal'] : null;
        $cnaeDesc = $item['cnae_fiscal_descricao'] ?? null;

        // CNAEs secundários
        $cnaesSecundarios = null;
        if (!empty($item['cnaes_secundarios']) && is_array($item['cnaes_secundarios'])) {
            $cnaesSecundarios = array_map(function($cnae) {
                return [
                    'codigo' => (string) ($cnae['codigo'] ?? ''),
                    'descricao' => $cnae['descricao'] ?? '',
                ];
            }, $item['cnaes_secundarios']);
        }

        // Situação cadastral
        $situacaoCadastral = $item['descricao_situacao_cadastral'] ?? null;
        $dataSituacao = null;
        if (!empty($item['data_situacao_cadastral'])) {
            $dataSituacao = $item['data_situacao_cadastral'];
        }

        // Data de início de atividade
        $dataInicio = null;
        if (!empty($item['data_inicio_atividade'])) {
            $dataInicio = $item['data_inicio_atividade'];
        }

        // Porte
        $porte = $item['porte'] ?? null;

        // Natureza jurídica
        $naturezaJuridica = $item['natureza_juridica'] ?? null;

        // Regime tributário
        $opcaoMei = null;
        if (isset($item['opcao_pelo_mei'])) {
            $opcaoMei = (bool) $item['opcao_pelo_mei'];
        }

        $opcaoSimples = null;
        if (isset($item['opcao_pelo_simples'])) {
            $opcaoSimples = (bool) $item['opcao_pelo_simples'];
        }

        // Capital social (vem em centavos)
        $capitalSocial = null;
        if (isset($item['capital_social']) && is_numeric($item['capital_social'])) {
            $capitalSocial = (int) $item['capital_social'];
        }

        // Identificador matriz/filial
        $matrizFilial = null;
        if (isset($item['identificador_matriz_filial'])) {
            $matrizFilial = (int) $item['identificador_matriz_filial'];
        }

        return [
            'cnpj'                        => $cnpj,
            'name'                        => $name,
            'razao_social'                => $razao,
            'address'                     => $address ?: null,
            'city'                        => $municipio ?: null,
            'state'                       => $uf ?: null,
            'phone'                       => $phone,
            'telefone_secundario'         => $phone2,
            'email'                       => $email,
            'website'                     => null,
            'cnae_code'                   => $cnaeCode,
            'cnae_description'            => $cnaeDesc,
            'cnaes_secundarios'           => $cnaesSecundarios,
            'situacao_cadastral'          => $situacaoCadastral,
            'data_situacao_cadastral'     => $dataSituacao,
            'data_inicio_atividade'       => $dataInicio,
            'porte'                       => $porte,
            'natureza_juridica'           => $naturezaJuridica,
            'opcao_pelo_mei'              => $opcaoMei,
            'opcao_pelo_simples'          => $opcaoSimples,
            'capital_social'              => $capitalSocial,
            'identificador_matriz_filial' => $matrizFilial,
            'source'                      => 'minhareceita',
        ];
    }

    // =========================================================================
    // HTTP
    // =========================================================================

    /**
     * Executa GET com cURL e retorna array decodificado
     */
    private function get(string $url): mixed
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'User-Agent: PixelHub/1.0 (hub.pixel12digital.com.br)',
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
            throw new \RuntimeException('Rate limit atingido na API Minha Receita. Aguarde alguns segundos e tente novamente.');
        }

        if ($code !== 200) {
            throw new \RuntimeException("API Minha Receita retornou HTTP {$code}");
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Resposta inválida da API: ' . json_last_error_msg());
        }

        return $decoded;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function normalizeString(string $str): string
    {
        $str = mb_strtolower(trim($str), 'UTF-8');
        $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        return preg_replace('/[^a-z0-9 ]/', '', $str);
    }
}
