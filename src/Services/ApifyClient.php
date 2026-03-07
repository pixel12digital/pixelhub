<?php

namespace PixelHub\Services;

/**
 * Cliente para a API Apify
 *
 * Documentação: https://docs.apify.com/api/v2
 * Actors utilizados:
 *  - apify/instagram-hashtag-scraper : busca posts por hashtag, extrai perfis únicos
 *  - apify/instagram-profile-scraper : busca dados completos do perfil (telefone business)
 */
class ApifyClient
{
    private const BASE_URL   = 'https://api.apify.com/v2';
    private const TIMEOUT    = 240;

    private const ACTOR_HASHTAG = 'apify~instagram-hashtag-scraper';
    private const ACTOR_PROFILE = 'apify~instagram-profile-scraper';

    // =========================================================================
    // API KEY
    // =========================================================================

    public static function hasApiKey(): bool
    {
        try {
            $key = self::resolveApiKey();
            return !empty($key);
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function resolveApiKey(): string
    {
        $db   = \PixelHub\Core\DB::getConnection();
        $stmt = $db->prepare("
            SELECT integration_value, is_encrypted
            FROM integration_settings
            WHERE integration_key = 'apify_api_key'
            LIMIT 1
        ");
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row || empty($row['integration_value'])) {
            throw new \RuntimeException('Chave API Apify não configurada. Acesse Configurações → Integrações → Apify.');
        }

        $value = $row['integration_value'];
        if ($row['is_encrypted']) {
            $value = \PixelHub\Core\CryptoHelper::decrypt($value);
        }

        return $value;
    }

    public function testApiKey(string $apiKey): array
    {
        try {
            $url = self::BASE_URL . '/users/me?token=' . urlencode($apiKey);
            $raw = $this->get($url);
            if (!empty($raw['data']['id'])) {
                $plan = $raw['data']['plan']['id'] ?? 'unknown';
                return ['success' => true, 'message' => 'Conexão com Apify estabelecida! Plano: ' . $plan];
            }
            return ['success' => false, 'message' => 'Resposta inesperada da API Apify.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // =========================================================================
    // FASE 1: BUSCA POR HASHTAG
    // =========================================================================

    /**
     * Busca perfis únicos a partir de hashtags do Instagram
     * Usa apify/instagram-hashtag-scraper
     *
     * @param  string[] $hashtags   Lista de hashtags (sem #): ['imobiliaria', 'corretor']
     * @param  int      $maxResults Máximo de perfis únicos
     * @return array[]  Lista de perfis normalizados
     */
    public function scrapeByHashtags(array $hashtags, int $maxResults = 200): array
    {
        $apiKey = self::resolveApiKey();

        $hashtags = array_map(fn($h) => ltrim(trim($h), '#'), $hashtags);
        $hashtags = array_values(array_filter($hashtags));

        if (empty($hashtags)) {
            throw new \InvalidArgumentException('Informe ao menos uma hashtag.');
        }

        $input = [
            'hashtags'     => $hashtags,
            'resultsLimit' => min($maxResults * 3, 600),
        ];

        $posts = $this->runActorSync(self::ACTOR_HASHTAG, $input, $apiKey);

        return $this->extractUniqueProfilesFromPosts($posts, $maxResults);
    }

    /**
     * Extrai perfis únicos (por ownerUsername) a partir de posts retornados
     */
    private function extractUniqueProfilesFromPosts(array $posts, int $max): array
    {
        $seen     = [];
        $profiles = [];

        foreach ($posts as $post) {
            $username = trim($post['ownerUsername'] ?? $post['owner']['username'] ?? '');
            if (empty($username) || isset($seen[$username])) {
                continue;
            }
            $seen[$username] = true;

            $profiles[] = [
                'instagram_username'   => $username,
                'name'                 => trim($post['ownerFullName'] ?? $post['owner']['full_name'] ?? $username),
                'instagram_profile_pic'=> $post['ownerProfilePicUrl'] ?? $post['owner']['profile_pic_url'] ?? null,
                'instagram_followers'  => null,
                'instagram_is_business'=> null,
                'instagram_category'   => null,
                'instagram_bio'        => null,
                'phone_instagram'      => null,
                'email_instagram'      => null,
                'website_instagram'    => null,
                'instagram_city'       => null,
            ];

            if (count($profiles) >= $max) {
                break;
            }
        }

        return $profiles;
    }

    // =========================================================================
    // FASE 2: ENRIQUECIMENTO DE PERFIL (TELEFONE)
    // =========================================================================

    /**
     * Busca dados completos de um ou mais perfis Instagram
     * Usa apify/instagram-profile-scraper
     *
     * @param  string[] $usernames Lista de usernames
     * @return array[]  Lista de dados de perfil
     */
    public function scrapeProfiles(array $usernames): array
    {
        $apiKey = self::resolveApiKey();

        $usernames = array_values(array_filter(array_map('trim', $usernames)));
        if (empty($usernames)) {
            throw new \InvalidArgumentException('Informe ao menos um username.');
        }

        $input = [
            'usernames'    => $usernames,
            'resultsLimit' => count($usernames),
        ];

        $raw = $this->runActorSync(self::ACTOR_PROFILE, $input, $apiKey);

        return array_map([$this, 'normalizeProfile'], $raw);
    }

    /**
     * Normaliza dados de perfil retornados pelo apify/instagram-profile-scraper
     */
    public function normalizeProfile(array $item): array
    {
        return [
            'instagram_username'    => $item['username'] ?? null,
            'name'                  => $item['fullName'] ?? $item['full_name'] ?? null,
            'instagram_followers'   => $item['followersCount'] ?? $item['followers_count'] ?? null,
            'instagram_is_business' => isset($item['isBusinessAccount']) ? (int) $item['isBusinessAccount'] : null,
            'instagram_category'    => $item['businessCategoryName'] ?? $item['business_category_name'] ?? null,
            'instagram_bio'         => $item['biography'] ?? null,
            'instagram_profile_pic' => $item['profilePicUrl'] ?? $item['profile_pic_url'] ?? null,
            'phone_instagram'       => $this->normalizePhone(
                $item['businessPhoneNumber'] ?? $item['business_phone_number'] ??
                $this->extractPhoneFromWaMe($item['biography'] ?? '') ??
                $this->extractPhoneFromWaMe($item['externalUrl'] ?? $item['external_url'] ?? '') ??
                null
            ),
            'email_instagram'       => $item['businessEmail'] ?? $item['business_email'] ?? null,
            'website_instagram'     => $item['externalUrl'] ?? $item['external_url'] ?? null,
            'instagram_city'        => $item['cityName'] ?? $item['city_name'] ?? null,
        ];
    }

    // =========================================================================
    // HTTP
    // =========================================================================

    /**
     * Executa um actor Apify de forma síncrona e retorna os itens do dataset
     *
     * Endpoint: POST /v2/acts/{actorId}/run-sync-get-dataset-items
     */
    private function runActorSync(string $actorId, array $input, string $apiKey): array
    {
        $url = self::BASE_URL . '/acts/' . $actorId . '/run-sync-get-dataset-items'
             . '?token=' . urlencode($apiKey)
             . '&timeout=' . self::TIMEOUT
             . '&memory=512';

        $json = json_encode($input, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT + 30,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
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

        if ($code === 401) {
            throw new \RuntimeException('Chave API Apify inválida ou sem permissão.');
        }

        if ($code === 402) {
            throw new \RuntimeException('Limite do plano Apify atingido. Verifique seu saldo em apify.com.');
        }

        if ($code !== 200 && $code !== 201) {
            $decoded = json_decode($body, true);
            $msg     = $decoded['error']['message'] ?? $decoded['message'] ?? "HTTP {$code}";
            throw new \RuntimeException('Apify retornou erro: ' . $msg);
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Resposta inválida da Apify: ' . json_last_error_msg());
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function get(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'User-Agent: PixelHub/1.0',
            ],
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) throw new \RuntimeException('Erro cURL: ' . $err);
        if ($code !== 200) throw new \RuntimeException("API retornou HTTP {$code}");

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Resposta inválida: ' . json_last_error_msg());
        }

        return $decoded;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Extrai número de telefone de links wa.me/PHONE na bio ou URL
     */
    private function extractPhoneFromWaMe(?string $text): ?string
    {
        if (empty($text)) return null;
        if (preg_match('/wa\.me\/([\d]+)/i', $text, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Normaliza telefone para E.164 brasileiro se possível
     */
    private function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);

        if (empty($digits)) {
            return null;
        }

        if (str_starts_with($phone, '+')) {
            return '+' . $digits;
        }

        if (strlen($digits) === 11 || strlen($digits) === 10) {
            return '+55' . $digits;
        }

        return $phone;
    }
}
