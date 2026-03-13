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
    private const MAX_PER_PAGE = 100;
    private const SLEEP_BETWEEN_REQUESTS_MS = 200; // 200ms entre requisições para evitar rate limit

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
     * @param callable|null $progressCallback Callback para reportar progresso: fn(int $fetched, int $filtered, int $valid)
     * @param int         $maxRequests Máximo de requisições (0 = ilimitado)
     * @return array Lista de empresas normalizadas
     */
    public function searchByCnaeAndRegion(string $cnaeCode, string $uf, ?string $ibgeCode = null, int $maxResults = 100, ?callable $progressCallback = null, int $maxRequests = 0): array
    {
        $cnaeClean = preg_replace('/\D/', '', $cnaeCode);
        if (empty($cnaeClean)) {
            throw new \InvalidArgumentException('Código CNAE inválido');
        }

        $uf = strtoupper(trim($uf));

        $results = [];
        $cursor  = null;
        $perPage = self::MAX_PER_PAGE;
        $totalFetched = 0;
        $totalFiltered = 0;
        $requestCount = 0;

        do {
            $params = [
                'cnae'  => $cnaeClean,
                'limit' => $perPage,
            ];

            if (!empty($uf) && strlen($uf) === 2) {
                $params['uf'] = $uf;
            }

            if (!empty($ibgeCode)) {
                $params['municipio'] = preg_replace('/\D/', '', $ibgeCode);
            }

            if ($cursor !== null) {
                $params['cursor'] = $cursor;
            }

            $url = self::BASE_URL . '/?' . http_build_query($params);

            // Sleep entre requisições (exceto na primeira)
            if ($requestCount > 0) {
                usleep(self::SLEEP_BETWEEN_REQUESTS_MS * 1000);
            }
            $requestCount++;

            $raw = $this->get($url);

            $batch = $raw['data'] ?? [];
            if (empty($batch)) {
                break;
            }

            $batchSize = count($batch);
            $totalFetched += $batchSize;

            foreach ($batch as $item) {
                $normalized = $this->normalizeCompany($item);
                if ($normalized) {
                    $results[] = $normalized;
                } else {
                    $totalFiltered++;
                }
                
                if (count($results) >= $maxResults) {
                    break 2;
                }
            }

            // Callback de progresso
            if ($progressCallback !== null) {
                call_user_func($progressCallback, $totalFetched, $totalFiltered, count($results));
            }

            $cursor = $raw['cursor'] ?? null;

            // Limite de requisições (se configurado)
            if ($maxRequests > 0 && $requestCount >= $maxRequests) {
                error_log('[MinhaReceitaClient] Limite de ' . $maxRequests . ' requisições atingido. Total buscado: ' . $totalFetched);
                break;
            }

        } while ($cursor !== null && count($results) < $maxResults);

        return $results;
    }

    /**
     * Busca prévia para estimar quantidade de resultados
     * Retorna apenas a primeira página para análise rápida
     *
     * @param string      $cnaeCode Código CNAE
     * @param string      $uf       UF
     * @param string|null $ibgeCode Código IBGE do município (opcional)
     * @return array ['total_fetched' => int, 'valid_count' => int, 'filtered_count' => int, 'has_more' => bool]
     */
    public function previewCount(string $cnaeCode, string $uf, ?string $ibgeCode = null): array
    {
        $cnaeClean = preg_replace('/\D/', '', $cnaeCode);
        if (empty($cnaeClean)) {
            throw new \InvalidArgumentException('Código CNAE inválido');
        }

        $uf = strtoupper(trim($uf));

        $params = [
            'cnae'  => $cnaeClean,
            'limit' => self::MAX_PER_PAGE,
        ];

        if (!empty($uf) && strlen($uf) === 2) {
            $params['uf'] = $uf;
        }

        if (!empty($ibgeCode)) {
            $params['municipio'] = preg_replace('/\D/', '', $ibgeCode);
        }

        $url = self::BASE_URL . '/?' . http_build_query($params);

        try {
            $raw = $this->get($url);
        } catch (\Exception $e) {
            throw new \RuntimeException('Erro ao buscar prévia: ' . $e->getMessage());
        }

        $batch = $raw['data'] ?? [];
        $cursor = $raw['cursor'] ?? null;
        $totalFetched = count($batch);
        $validCount = 0;
        $filteredCount = 0;

        foreach ($batch as $item) {
            $normalized = $this->normalizeCompany($item);
            if ($normalized) {
                $validCount++;
            } else {
                $filteredCount++;
            }
        }

        return [
            'total_fetched'   => $totalFetched,
            'valid_count'     => $validCount,
            'filtered_count'  => $filteredCount,
            'has_more'        => $cursor !== null,
            'filter_rate'     => $totalFetched > 0 ? round(($filteredCount / $totalFetched) * 100, 1) : 0,
        ];
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
        $tipoLog     = trim($item['descricao_tipo_de_logradouro'] ?? '');
        $logradouro  = trim($item['logradouro'] ?? '');
        $numero      = trim($item['numero'] ?? '');
        $complemento = trim($item['complemento'] ?? '') ?: null;
        $bairro      = trim($item['bairro'] ?? '') ?: null;
        $municipio   = trim($item['municipio'] ?? '');
        $uf          = strtoupper(trim($item['uf'] ?? ''));
        $cep         = preg_replace('/\D/', '', $item['cep'] ?? '') ?: null;

        $logFull = trim(($tipoLog ? $tipoLog . ' ' : '') . $logradouro);
        $addressParts = array_filter([
            $logFull . ($numero ? ', ' . $numero : ''),
            $complemento,
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
        $dataSituacao = !empty($item['data_situacao_cadastral']) ? $item['data_situacao_cadastral'] : null;
        $motivoSituacao = isset($item['motivo_situacao_cadastral']) ? (int) $item['motivo_situacao_cadastral'] : null;
        $descricaoMotivo = $item['descricao_motivo_situacao_cadastral'] ?? null;

        // FILTRO: Ignora empresas com situação cadastral indesejada
        $situacoesInvalidas = ['INAPTA', 'BAIXADA', 'SUSPENSA', 'NULA'];
        if ($situacaoCadastral && in_array(strtoupper(trim($situacaoCadastral)), $situacoesInvalidas)) {
            return null;
        }

        // Situação especial
        $situacaoEspecial = !empty($item['situacao_especial']) ? $item['situacao_especial'] : null;
        $dataSituacaoEspecial = !empty($item['data_situacao_especial']) ? $item['data_situacao_especial'] : null;

        // Data de início de atividade
        $dataInicio = !empty($item['data_inicio_atividade']) ? $item['data_inicio_atividade'] : null;

        // Porte
        $porte = $item['porte'] ?? null;
        $codigoPorte = isset($item['codigo_porte']) ? (int) $item['codigo_porte'] : null;

        // Natureza jurídica
        $naturezaJuridica = $item['natureza_juridica'] ?? null;
        $codigoNatureza = isset($item['codigo_natureza_juridica']) ? (int) $item['codigo_natureza_juridica'] : null;

        // Qualificação do responsável
        $qualificacaoResp = isset($item['qualificacao_do_responsavel']) ? (int) $item['qualificacao_do_responsavel'] : null;

        // Regime tributário - MEI
        $opcaoMei = isset($item['opcao_pelo_mei']) ? (bool) $item['opcao_pelo_mei'] : null;
        $dataOpcaoMei = !empty($item['data_opcao_pelo_mei']) ? $item['data_opcao_pelo_mei'] : null;
        $dataExclusaoMei = !empty($item['data_exclusao_do_mei']) ? $item['data_exclusao_do_mei'] : null;

        // Regime tributário - Simples Nacional
        $opcaoSimples = isset($item['opcao_pelo_simples']) ? (bool) $item['opcao_pelo_simples'] : null;
        $dataOpcaoSimples = !empty($item['data_opcao_pelo_simples']) ? $item['data_opcao_pelo_simples'] : null;
        $dataExclusaoSimples = !empty($item['data_exclusao_do_simples']) ? $item['data_exclusao_do_simples'] : null;

        // Capital social (vem em centavos)
        $capitalSocial = null;
        if (isset($item['capital_social']) && is_numeric($item['capital_social'])) {
            $capitalSocial = (int) $item['capital_social'];
        }

        // Identificador matriz/filial
        $matrizFilial = isset($item['identificador_matriz_filial']) ? (int) $item['identificador_matriz_filial'] : null;

        // QSA - Quadro de Sócios e Administradores
        $qsa = null;
        if (!empty($item['qsa']) && is_array($item['qsa'])) {
            $qsa = array_map(function($socio) {
                return [
                    'nome' => $socio['nome_socio'] ?? $socio['nome'] ?? '',
                    'qualificacao' => $socio['qualificacao_socio'] ?? $socio['qualificacao'] ?? '',
                    'cpf_cnpj' => $socio['cpf_cnpj_socio'] ?? $socio['cpf_cnpj'] ?? null,
                    'data_entrada' => $socio['data_entrada_sociedade'] ?? null,
                ];
            }, $item['qsa']);
        }

        return [
            'cnpj'                           => $cnpj,
            'name'                           => $name,
            'razao_social'                   => $razao,
            'address'                        => $address ?: null,
            'complemento'                    => $complemento,
            'bairro'                         => $bairro,
            'cep'                            => $cep,
            'city'                           => $municipio ?: null,
            'state'                          => $uf ?: null,
            'phone'                          => $phone,
            'telefone_secundario'            => $phone2,
            'email'                          => $email,
            'website'                        => null,
            'cnae_code'                      => $cnaeCode,
            'cnae_description'               => $cnaeDesc,
            'cnaes_secundarios'              => $cnaesSecundarios,
            'situacao_cadastral'             => $situacaoCadastral,
            'data_situacao_cadastral'        => $dataSituacao,
            'motivo_situacao_cadastral'      => $motivoSituacao,
            'descricao_motivo_situacao'      => $descricaoMotivo,
            'situacao_especial'              => $situacaoEspecial,
            'data_situacao_especial'         => $dataSituacaoEspecial,
            'data_inicio_atividade'          => $dataInicio,
            'porte'                          => $porte,
            'codigo_porte'                   => $codigoPorte,
            'natureza_juridica'              => $naturezaJuridica,
            'codigo_natureza_juridica'       => $codigoNatureza,
            'qualificacao_responsavel'       => $qualificacaoResp,
            'opcao_pelo_mei'                 => $opcaoMei,
            'data_opcao_mei'                 => $dataOpcaoMei,
            'data_exclusao_mei'              => $dataExclusaoMei,
            'opcao_pelo_simples'             => $opcaoSimples,
            'data_opcao_simples'             => $dataOpcaoSimples,
            'data_exclusao_simples'          => $dataExclusaoSimples,
            'capital_social'                 => $capitalSocial,
            'identificador_matriz_filial'    => $matrizFilial,
            'qsa'                            => $qsa,
            'source'                         => 'minhareceita',
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
