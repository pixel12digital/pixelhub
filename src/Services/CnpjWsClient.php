<?php

namespace PixelHub\Services;

/**
 * Cliente para a API pública CNPJ.ws
 *
 * Documentação: https://www.cnpj.ws/docs/api-publica/consultar-cnpj
 * Endpoint de busca por CNAE + município: GET https://publica.cnpj.ws/cnpj?municipio={ibge}&cnae={cnae}&situacao=A
 *
 * Rate limit da API pública: ~3 req/s — sleep entre chamadas está implementado.
 */
class CnpjWsClient
{
    private const BASE_URL = 'https://publica.cnpj.ws';
    private const TIMEOUT  = 15;
    private const SLEEP_MS = 400; // 400ms entre requisições (~2.5 req/s, abaixo do limite)

    // =========================================================================
    // BUSCA POR CNAE + MUNICÍPIO
    // =========================================================================

    /**
     * Busca empresas por CNAE e município (código IBGE)
     *
     * @param string $cnaeCode   Código CNAE sem formatação (ex: "6822600" ou "6822-6/00")
     * @param string $ibgeCode   Código IBGE do município (7 dígitos, ex: "4106902" para Curitiba)
     * @param string $situacao   Situação cadastral: A=Ativa, B=Baixada, I=Inapta, S=Suspensa, N=Nula
     * @param int    $maxResults Máximo de resultados a retornar
     * @return array Lista de empresas normalizadas
     */
    public function searchByCnae(string $cnaeCode, string $ibgeCode, string $situacao = 'A', int $maxResults = 20): array
    {
        $cnaeClean = preg_replace('/\D/', '', $cnaeCode);
        $ibgeClean = preg_replace('/\D/', '', $ibgeCode);

        if (empty($cnaeClean) || empty($ibgeClean)) {
            throw new \InvalidArgumentException('CNAE e código IBGE são obrigatórios');
        }

        $url = self::BASE_URL . '/cnpj?' . http_build_query([
            'municipio' => $ibgeClean,
            'cnae'      => $cnaeClean,
            'situacao'  => $situacao,
        ]);

        $raw = $this->get($url);

        if (!is_array($raw)) {
            return [];
        }

        $results = [];
        $count   = 0;

        foreach ($raw as $item) {
            if ($count >= $maxResults) {
                break;
            }
            $normalized = $this->normalizeCompany($item);
            if ($normalized) {
                $results[] = $normalized;
                $count++;
            }
        }

        return $results;
    }

    /**
     * Busca empresas por CNAE + UF + nome de cidade (resolve IBGE internamente)
     *
     * @param string $cnaeCode  Código CNAE
     * @param string $cityName  Nome da cidade (ex: "Curitiba")
     * @param string $uf        UF (ex: "PR")
     * @param string $situacao  Situação cadastral
     * @param int    $maxResults
     * @return array
     */
    public function searchByCnaeAndCity(string $cnaeCode, string $cityName, string $uf, string $situacao = 'A', int $maxResults = 20): array
    {
        $ibgeCode = $this->resolveIbgeCode($cityName, $uf);

        if (!$ibgeCode) {
            throw new \RuntimeException("Município não encontrado: {$cityName}/{$uf}. Verifique o nome da cidade.");
        }

        return $this->searchByCnae($cnaeCode, $ibgeCode, $situacao, $maxResults);
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
