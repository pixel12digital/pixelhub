<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

/**
 * Client para a Google Places API (New)
 * 
 * Usa Text Search para buscar empresas por palavra-chave + cidade.
 * Chave de API lida do banco (integration_settings) com fallback para .env.
 */
class GooglePlacesClient
{
    private const TEXT_SEARCH_URL = 'https://places.googleapis.com/v1/places:searchText';
    private const PLACE_DETAILS_URL = 'https://places.googleapis.com/v1/places/';

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = self::resolveApiKey();
    }

    /**
     * Resolve a chave de API: banco → .env → exceção
     */
    public static function resolveApiKey(): string
    {
        // 1. Tenta banco
        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("SELECT integration_value, is_encrypted FROM integration_settings WHERE integration_key = 'google_maps_api_key' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && !empty($row['integration_value'])) {
                $value = $row['integration_value'];
                if ($row['is_encrypted']) {
                    $value = CryptoHelper::decrypt($value);
                }
                if (!empty($value)) {
                    return $value;
                }
            }
        } catch (\Exception $e) {
            // Tabela pode não existir ainda
        }

        // 2. Fallback para .env
        $envKey = \PixelHub\Core\Env::get('GOOGLE_MAPS_API_KEY', '');
        if (!empty($envKey)) {
            return $envKey;
        }

        throw new \RuntimeException('Chave da Google Maps API não configurada. Acesse Configurações > Integrações > Google Maps.');
    }

    /**
     * Verifica se a chave está configurada (sem lançar exceção)
     */
    public static function hasApiKey(): bool
    {
        try {
            self::resolveApiKey();
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Busca empresas por texto + cidade usando Text Search
     * 
     * @param string $query       Ex: "imobiliária Curitiba PR"
     * @param int    $maxResults  Máximo de resultados (até 60, paginado em grupos de 20)
     * @return array Lista de lugares normalizados
     */
    public function textSearch(string $query, int $maxResults = 20): array
    {
        $results = [];
        $nextPageToken = null;
        $fetched = 0;

        do {
            $payload = [
                'textQuery'    => $query,
                'languageCode' => 'pt-BR',
                'maxResultCount' => min(20, $maxResults - $fetched),
            ];

            if ($nextPageToken) {
                $payload['pageToken'] = $nextPageToken;
            }

            $response = $this->post(self::TEXT_SEARCH_URL, $payload, [
                'X-Goog-FieldMask: places.id,places.displayName,places.formattedAddress,places.nationalPhoneNumber,places.internationalPhoneNumber,places.websiteUri,places.rating,places.userRatingCount,places.location,places.types,nextPageToken',
            ]);

            if (empty($response['places'])) {
                break;
            }

            foreach ($response['places'] as $place) {
                $results[] = $this->normalizePlaceResult($place);
                $fetched++;
                if ($fetched >= $maxResults) {
                    break 2;
                }
            }

            $nextPageToken = $response['nextPageToken'] ?? null;

        } while ($nextPageToken && $fetched < $maxResults);

        return $results;
    }

    /**
     * Normaliza um resultado do Google Places para formato interno
     */
    private function normalizePlaceResult(array $place): array
    {
        $address = $place['formattedAddress'] ?? '';

        // Extrai cidade e estado do endereço formatado (heurística BR)
        $city  = null;
        $state = null;
        if (preg_match('/,\s*([^,]+)\s*-\s*([A-Z]{2})\s*,/', $address, $m)) {
            $city  = trim($m[1]);
            $state = trim($m[2]);
        }

        return [
            'google_place_id'     => $place['id'] ?? '',
            'name'                => $place['displayName']['text'] ?? '',
            'address'             => $address,
            'city'                => $city,
            'state'               => $state,
            'phone'               => $place['nationalPhoneNumber'] ?? $place['internationalPhoneNumber'] ?? null,
            'website'             => $place['websiteUri'] ?? null,
            'rating'              => isset($place['rating']) ? (float) $place['rating'] : null,
            'user_ratings_total'  => $place['userRatingCount'] ?? null,
            'lat'                 => $place['location']['latitude'] ?? null,
            'lng'                 => $place['location']['longitude'] ?? null,
            'google_types'        => $place['types'] ?? [],
        ];
    }

    /**
     * Testa a chave de API com uma busca mínima
     * 
     * @return array ['success' => bool, 'message' => string]
     */
    public function testApiKey(): array
    {
        try {
            $payload = [
                'textQuery'      => 'restaurante',
                'languageCode'   => 'pt-BR',
                'maxResultCount' => 1,
            ];

            $response = $this->post(self::TEXT_SEARCH_URL, $payload, [
                'X-Goog-FieldMask: places.id,places.displayName',
            ]);

            if (isset($response['places']) || isset($response['error']) === false) {
                return ['success' => true, 'message' => 'Chave válida! API respondeu corretamente.'];
            }

            return ['success' => false, 'message' => 'Resposta inesperada da API.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Faz requisição POST para a Places API
     */
    private function post(string $url, array $payload, array $extraHeaders = []): array
    {
        $headers = array_merge([
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . $this->apiKey,
        ], $extraHeaders);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException('Erro de conexão com Google Places API: ' . $curlErr);
        }

        $data = json_decode($body, true);

        if ($httpCode !== 200) {
            $errMsg = $data['error']['message'] ?? 'Erro HTTP ' . $httpCode;
            throw new \RuntimeException('Google Places API: ' . $errMsg);
        }

        return $data ?? [];
    }
}
