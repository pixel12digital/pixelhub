<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;
use PixelHub\Services\MinhaReceitaClient;
use PixelHub\Services\ApifyClient;

/**
 * Service para Prospecção Ativa
 * 
 * Gerencia receitas de busca, executa buscas no Google Places
 * e persiste os resultados na tabela prospecting_results.
 */
class ProspectingService
{
    // =========================================================================
    // INTEGRATION SETTINGS (chave Google Maps)
    // =========================================================================

    /**
     * Salva a chave da Google Maps API no banco
     */
    public static function saveApiKey(string $apiKey, int $userId): void
    {
        $db = DB::getConnection();
        $encrypted = CryptoHelper::encrypt($apiKey);

        $stmt = $db->prepare("
            INSERT INTO integration_settings (integration_key, integration_value, is_encrypted, label, updated_by, created_at, updated_at)
            VALUES ('google_maps_api_key', ?, 1, 'Google Maps API Key', ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                integration_value = VALUES(integration_value),
                is_encrypted = 1,
                updated_by = VALUES(updated_by),
                updated_at = NOW()
        ");
        $stmt->execute([$encrypted, $userId]);
    }

    /**
     * Verifica se a chave está configurada
     */
    public static function hasApiKey(): bool
    {
        return GooglePlacesClient::hasApiKey();
    }

    /**
     * Retorna máscara da chave para exibição (ex: AIza****...****XYZ)
     */
    public static function getMaskedApiKey(): ?string
    {
        try {
            $key = GooglePlacesClient::resolveApiKey();
            if (strlen($key) <= 8) {
                return str_repeat('*', strlen($key));
            }
            return substr($key, 0, 4) . str_repeat('*', strlen($key) - 8) . substr($key, -4);
        } catch (\Exception $e) {
            return null;
        }
    }

    // =========================================================================
    // RECEITAS DE BUSCA
    // =========================================================================

    /**
     * Lista apenas tenants que têm pelo menos 1 receita (para as abas de filtro)
     */
    public static function listTenants(?string $sourceFilter = null): array
    {
        $db = DB::getConnection();
        $sourceCondition = $sourceFilter ? "AND r.source = " . $db->quote($sourceFilter) : '';
        $stmt = $db->query("
            SELECT DISTINCT t.id, t.name, t.company
            FROM tenants t
            INNER JOIN prospecting_recipes r ON r.tenant_id = t.id
            WHERE (t.is_archived IS NULL OR t.is_archived = 0)
            $sourceCondition
            ORDER BY t.name ASC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Busca tenants por nome/empresa para autocomplete no modal (mínimo 2 chars)
     */
    public static function searchTenants(string $q, int $limit = 10): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT id,
                   COALESCE(NULLIF(company,''), name) AS label,
                   name, company
            FROM tenants
            WHERE (is_archived IS NULL OR is_archived = 0)
              AND (name LIKE ? OR company LIKE ?)
            ORDER BY COALESCE(NULLIF(company,''), name) ASC
            LIMIT ?
        ");
        $like = '%' . $q . '%';
        $stmt->execute([$like, $like, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Lista receitas, opcionalmente filtradas por tenant
     * tenant_id = null → agência própria (sem tenant)
     * tenant_id = 0    → todas as receitas (sem filtro)
     */
    public static function listRecipes(?int $tenantId = 0, ?string $sourceFilter = null): array
    {
        $db = DB::getConnection();

        $conditions = [];
        $params = [];

        if ($tenantId === null) {
            $conditions[] = 'r.tenant_id IS NULL';
        } elseif ($tenantId > 0) {
            $conditions[] = 'r.tenant_id = ?';
            $params[] = $tenantId;
        }

        if ($sourceFilter !== null) {
            $conditions[] = 'r.source = ?';
            $params[] = $sourceFilter;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $stmt = $db->prepare("
            SELECT r.*, p.label as product_label,
                   t.name as tenant_name, t.company as tenant_company,
                   (SELECT COUNT(*) FROM prospecting_results pr WHERE pr.recipe_id = r.id) as results_count,
                   (SELECT COUNT(*) FROM prospecting_results pr WHERE pr.recipe_id = r.id AND pr.status = 'new') as new_count,
                   (SELECT COUNT(*) FROM prospecting_results pr WHERE pr.recipe_id = r.id AND pr.lead_id IS NOT NULL) as converted_count
            FROM prospecting_recipes r
            LEFT JOIN opportunity_products p ON p.id = r.product_id
            LEFT JOIN tenants t ON t.id = r.tenant_id
            {$where}
            ORDER BY r.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Busca receita por ID
     */
    public static function findRecipeById(int $id): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT r.*, p.label as product_label
            FROM prospecting_recipes r
            LEFT JOIN opportunity_products p ON p.id = r.product_id
            WHERE r.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row && !empty($row['keywords'])) {
            $row['keywords'] = json_decode($row['keywords'], true) ?: [];
        }
        return $row ?: null;
    }

    /**
     * Cria uma nova receita de busca
     */
    public static function createRecipe(array $data, int $userId): int
    {
        $db = DB::getConnection();

        $name        = trim($data['name'] ?? '');
        $source      = in_array($data['source'] ?? '', ['google_maps', 'minhareceita', 'instagram']) ? $data['source'] : 'google_maps';
        $city        = trim($data['city'] ?? '');
        $state       = strtoupper(trim($data['state'] ?? ''));
        $productId   = !empty($data['product_id']) ? (int) $data['product_id'] : null;
        $placeType   = trim($data['google_place_type'] ?? '') ?: null;
        $radius      = !empty($data['radius_meters']) ? (int) $data['radius_meters'] : 5000;
        $notes       = trim($data['notes'] ?? '') ?: null;
        $cnaeCode    = trim($data['cnae_code'] ?? '') ?: null;
        $cnaeDesc    = trim($data['cnae_description'] ?? '') ?: null;
        $cnaes       = !empty($data['cnaes']) ? $data['cnaes'] : null;

        // Normaliza keywords
        $keywords = self::normalizeKeywords($data['keywords'] ?? []);

        if (empty($name)) {
            throw new \InvalidArgumentException('Nome da receita é obrigatório');
        }
        if ($source === 'google_maps' && empty($city)) {
            throw new \InvalidArgumentException('Cidade é obrigatória para Google Maps');
        }
        if ($source === 'minhareceita' && empty($cnaeCode)) {
            throw new \InvalidArgumentException('CNAE é obrigatório para a fonte Minha Receita');
        }
        if ($source === 'minhareceita' && empty($state)) {
            throw new \InvalidArgumentException('Estado (UF) é obrigatório para a fonte Minha Receita');
        }
        if ($source === 'instagram' && empty($keywords)) {
            throw new \InvalidArgumentException('Informe ao menos uma hashtag para prospecção no Instagram.');
        }

        $tenantId = !empty($data['tenant_id']) ? (int) $data['tenant_id'] : null;

        $stmt = $db->prepare("
            INSERT INTO prospecting_recipes
                (tenant_id, name, source, product_id, city, state, keywords, google_place_type, radius_meters, cnae_code, cnae_description, cnaes, status, notes, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $tenantId,
            $name,
            $source,
            $productId,
            $city,
            $state ?: null,
            json_encode($keywords, JSON_UNESCAPED_UNICODE),
            $placeType,
            $radius,
            $cnaeCode,
            $cnaeDesc,
            $cnaes ? json_encode($cnaes, JSON_UNESCAPED_UNICODE) : null,
            $notes,
            $userId,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Atualiza uma receita existente
     */
    public static function updateRecipe(int $id, array $data): void
    {
        $db = DB::getConnection();

        $keywords = self::normalizeKeywords($data['keywords'] ?? []);
        $state    = strtoupper(trim($data['state'] ?? ''));
        $source   = in_array($data['source'] ?? '', ['google_maps', 'minhareceita', 'instagram']) ? $data['source'] : 'google_maps';

        $tenantId = !empty($data['tenant_id']) ? (int) $data['tenant_id'] : null;

        $cnaes = !empty($data['cnaes']) ? $data['cnaes'] : null;

        $stmt = $db->prepare("
            UPDATE prospecting_recipes SET
                tenant_id = ?,
                name = ?,
                source = ?,
                product_id = ?,
                city = ?,
                state = ?,
                keywords = ?,
                google_place_type = ?,
                radius_meters = ?,
                cnae_code = ?,
                cnae_description = ?,
                cnaes = ?,
                notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $tenantId,
            trim($data['name'] ?? ''),
            $source,
            !empty($data['product_id']) ? (int) $data['product_id'] : null,
            trim($data['city'] ?? ''),
            $state ?: null,
            json_encode($keywords, JSON_UNESCAPED_UNICODE),
            trim($data['google_place_type'] ?? '') ?: null,
            !empty($data['radius_meters']) ? (int) $data['radius_meters'] : 5000,
            trim($data['cnae_code'] ?? '') ?: null,
            trim($data['cnae_description'] ?? '') ?: null,
            $cnaes ? json_encode($cnaes, JSON_UNESCAPED_UNICODE) : null,
            trim($data['notes'] ?? '') ?: null,
            $id,
        ]);
    }

    /**
     * Alterna status da receita (active/paused)
     */
    public static function toggleRecipeStatus(int $id): string
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT status FROM prospecting_recipes WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();

        $newStatus = $current === 'active' ? 'paused' : 'active';
        $db->prepare("UPDATE prospecting_recipes SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $id]);
        return $newStatus;
    }

    /**
     * Exclui uma receita (e seus resultados via CASCADE)
     */
    public static function deleteRecipe(int $id): void
    {
        $db = DB::getConnection();
        $db->prepare("DELETE FROM prospecting_recipes WHERE id = ?")->execute([$id]);
    }

    // =========================================================================
    // EXECUÇÃO DA BUSCA (Google Places)
    // =========================================================================

    /**
     * Executa a busca para uma receita e persiste os resultados
     * Bifurca entre Google Maps e CNPJ.ws conforme recipe['source']
     *
     * @return array ['found' => int, 'new' => int, 'duplicates' => int, 'errors' => string[]]
     */
    public static function runSearch(int $recipeId, int $maxResults = 60): array
    {
        $recipe = self::findRecipeById($recipeId);
        if (!$recipe) {
            throw new \InvalidArgumentException('Receita não encontrada');
        }

        $source = $recipe['source'] ?? 'google_maps';

        if ($source === 'minhareceita') {
            return self::runSearchMinhaReceita($recipe, $recipeId, $maxResults);
        }

        if ($source === 'instagram') {
            return self::runSearchInstagram($recipe, $recipeId, $maxResults);
        }

        return self::runSearchGoogleMaps($recipe, $recipeId, $maxResults);
    }

    /**
     * Prévia de resultados para uma receita Minha Receita
     * Busca apenas a primeira página e estima o total
     *
     * @param int $recipeId ID da receita
     * @return array Estatísticas de prévia
     */
    public static function previewMinhaReceita(int $recipeId): array
    {
        $recipe = self::findRecipeById($recipeId);
        if (!$recipe) {
            throw new \InvalidArgumentException('Receita não encontrada');
        }

        $uf   = $recipe['state'] ?? '';
        $city = $recipe['city'] ?? '';

        // Busca por múltiplos CNAEs se cadastrados
        $cnaes = [];
        if (!empty($recipe['cnaes'])) {
            $cnaesDecoded = is_string($recipe['cnaes']) ? json_decode($recipe['cnaes'], true) : $recipe['cnaes'];
            if (is_array($cnaesDecoded) && count($cnaesDecoded) > 0) {
                $cnaes = $cnaesDecoded;
            }
        }
        
        // Fallback para CNAE único
        if (empty($cnaes) && !empty($recipe['cnae_code'])) {
            $cnaes = [['code' => $recipe['cnae_code'], 'desc' => $recipe['cnae_description'] ?? '']];
        }

        if (empty($cnaes) || empty($uf)) {
            throw new \InvalidArgumentException('CNAE e UF são obrigatórios');
        }

        $client = new MinhaReceitaClient();

        // Resolve código IBGE da cidade se informada
        $ibgeCode = null;
        if (!empty($city)) {
            $ibgeCode = $client->resolveIbgeCode($city, $uf);
        }

        // Busca prévia para cada CNAE
        $totalValid = 0;
        $totalFiltered = 0;
        $totalFetched = 0;
        $hasMore = false;
        $avgFilterRate = 0;

        foreach ($cnaes as $cnae) {
            $cnaeCode = $cnae['code'] ?? '';
            if (empty($cnaeCode)) continue;

            try {
                $preview = $client->previewCount($cnaeCode, $uf, $ibgeCode);
                $totalFetched += $preview['total_fetched'];
                $totalValid += $preview['valid_count'];
                $totalFiltered += $preview['filtered_count'];
                if ($preview['has_more']) {
                    $hasMore = true;
                }
            } catch (\Exception $e) {
                error_log('[ProspectingService] Erro na prévia CNAE ' . $cnaeCode . ': ' . $e->getMessage());
            }
        }

        // Calcula taxa média de filtro
        $avgFilterRate = $totalFetched > 0 ? round(($totalFiltered / $totalFetched) * 100, 1) : 0;

        // Estima total baseado na primeira página
        // Se tem cursor (has_more), estima que há pelo menos 10x mais
        $estimatedTotal = $hasMore ? $totalValid * 10 : $totalValid;
        $estimatedFiltered = $hasMore ? $totalFiltered * 10 : $totalFiltered;

        return [
            'sample_size'        => $totalFetched,
            'valid_in_sample'    => $totalValid,
            'filtered_in_sample' => $totalFiltered,
            'filter_rate'        => $avgFilterRate,
            'has_more'           => $hasMore,
            'estimated_total'    => $estimatedTotal,
            'estimated_filtered' => $estimatedFiltered,
            'city'               => $city ?: 'Todo o estado',
            'state'              => $uf,
            'cnaes_count'        => count($cnaes),
        ];
    }

    /**
     * Executa busca via Minha Receita (dados abertos Receita Federal)
     */
    private static function runSearchMinhaReceita(array $recipe, int $recipeId, int $maxResults): array
    {
        $uf   = $recipe['state'] ?? '';
        $city = $recipe['city'] ?? '';

        // Busca por múltiplos CNAEs se cadastrados
        $cnaes = [];
        if (!empty($recipe['cnaes'])) {
            $cnaesDecoded = is_string($recipe['cnaes']) ? json_decode($recipe['cnaes'], true) : $recipe['cnaes'];
            if (is_array($cnaesDecoded) && count($cnaesDecoded) > 0) {
                $cnaes = $cnaesDecoded;
            }
        }
        
        // Fallback para CNAE único (compatibilidade)
        if (empty($cnaes) && !empty($recipe['cnae_code'])) {
            $cnaes = [['code' => $recipe['cnae_code'], 'desc' => $recipe['cnae_description'] ?? '']];
        }

        if (empty($cnaes) || empty($uf)) {
            throw new \InvalidArgumentException('CNAE e UF são obrigatórios para busca Minha Receita');
        }

        $client = new MinhaReceitaClient();

        // Resolve código IBGE da cidade se informada
        $ibgeCode = null;
        if (!empty($city)) {
            $ibgeCode = $client->resolveIbgeCode($city, $uf);
        }

        // Busca por cada CNAE e consolida resultados (remove duplicados por CNPJ)
        $allPlaces = [];
        $seenCnpjs = [];
        $resultsPerCnae = (int) ceil($maxResults / count($cnaes));

        foreach ($cnaes as $cnae) {
            $cnaeCode = $cnae['code'] ?? '';
            if (empty($cnaeCode)) continue;

            // Callback de progresso para log
            $progressCallback = function($fetched, $filtered, $valid) use ($cnaeCode) {
                error_log("[ProspectingService] CNAE {$cnaeCode}: {$fetched} buscados, {$filtered} filtrados, {$valid} válidos");
            };

            // Calcula maxRequests baseado no volume esperado
            // Para volumes grandes (>1000), permite mais requisições
            $maxRequests = $resultsPerCnae > 1000 ? 0 : 100; // 0 = ilimitado

            $places = $client->searchByCnaeAndRegion($cnaeCode, $uf, $ibgeCode, $resultsPerCnae, $progressCallback, $maxRequests);
            
            foreach ($places as $place) {
                $cnpj = $place['cnpj'] ?? '';
                if (empty($cnpj) || isset($seenCnpjs[$cnpj])) {
                    continue;
                }
                $seenCnpjs[$cnpj] = true;
                $allPlaces[] = $place;
                
                if (count($allPlaces) >= $maxResults) {
                    break 2;
                }
            }
        }

        $places = $allPlaces;

        $found      = count($places);
        $new        = 0;
        $duplicates = 0;
        $errors     = [];

        $db = DB::getConnection();

        foreach ($places as $place) {
            $cnpj = $place['cnpj'] ?? '';
            if (empty($cnpj)) {
                continue;
            }

            try {
                $check = $db->prepare("SELECT id FROM prospecting_results WHERE cnpj = ?");
                $check->execute([$cnpj]);
                if ($check->fetch()) {
                    $duplicates++;
                    continue;
                }

                $stmt = $db->prepare("
                    INSERT INTO prospecting_results
                        (recipe_id, tenant_id, name, razao_social, address_minhareceita, complemento, bairro, cep,
                         city, state, phone_minhareceita, telefone_secundario, email, website_minhareceita, source, cnpj, 
                         cnae_code, cnae_description, cnaes_secundarios, qsa,
                         situacao_cadastral, data_situacao_cadastral, motivo_situacao_cadastral, 
                         descricao_motivo_situacao, situacao_especial, data_situacao_especial,
                         data_inicio_atividade, porte, codigo_porte, natureza_juridica, 
                         codigo_natureza_juridica, qualificacao_responsavel,
                         opcao_pelo_mei, data_opcao_mei, data_exclusao_mei,
                         opcao_pelo_simples, data_opcao_simples, data_exclusao_simples,
                         capital_social, identificador_matriz_filial, status, found_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'minhareceita', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW(), NOW())
                ");
                $stmt->execute([
                    $recipeId,
                    $recipe['tenant_id'] ?? null,
                    $place['name'],
                    $place['razao_social'] ?? null,
                    $place['address'],
                    $place['complemento'] ?? null,
                    $place['bairro'] ?? null,
                    $place['cep'] ?? null,
                    $place['city'],
                    $place['state'],
                    $place['phone'],
                    $place['telefone_secundario'] ?? null,
                    $place['email'],
                    $place['website'],
                    $cnpj,
                    $place['cnae_code'],
                    $place['cnae_description'],
                    !empty($place['cnaes_secundarios']) ? json_encode($place['cnaes_secundarios'], JSON_UNESCAPED_UNICODE) : null,
                    !empty($place['qsa']) ? json_encode($place['qsa'], JSON_UNESCAPED_UNICODE) : null,
                    $place['situacao_cadastral'] ?? null,
                    $place['data_situacao_cadastral'] ?? null,
                    $place['motivo_situacao_cadastral'] ?? null,
                    $place['descricao_motivo_situacao'] ?? null,
                    $place['situacao_especial'] ?? null,
                    $place['data_situacao_especial'] ?? null,
                    $place['data_inicio_atividade'] ?? null,
                    $place['porte'] ?? null,
                    $place['codigo_porte'] ?? null,
                    $place['natureza_juridica'] ?? null,
                    $place['codigo_natureza_juridica'] ?? null,
                    $place['qualificacao_responsavel'] ?? null,
                    isset($place['opcao_pelo_mei']) ? (int) $place['opcao_pelo_mei'] : null,
                    $place['data_opcao_mei'] ?? null,
                    $place['data_exclusao_mei'] ?? null,
                    isset($place['opcao_pelo_simples']) ? (int) $place['opcao_pelo_simples'] : null,
                    $place['data_opcao_simples'] ?? null,
                    $place['data_exclusao_simples'] ?? null,
                    $place['capital_social'] ?? null,
                    $place['identificador_matriz_filial'] ?? null,
                ]);
                $new++;
            } catch (\Exception $e) {
                $errors[] = 'Erro ao salvar "' . $place['name'] . '": ' . $e->getMessage();
            }
        }

        self::updateRecipeStats($recipeId);

        return [
            'found'      => $found,
            'new'        => $new,
            'duplicates' => $duplicates,
            'errors'     => $errors,
        ];
    }

    // =========================================================================
    // EXECUÇÃO DA BUSCA (Instagram via Apify)
    // =========================================================================

    /**
     * Executa busca de perfis Instagram por hashtag via Apify (Fase 1)
     * Telefone é enriquecido sob demanda via enrichWithApifyPhone()
     */
    private static function runSearchInstagram(array $recipe, int $recipeId, int $maxResults): array
    {
        $keywords = is_array($recipe['keywords'])
            ? $recipe['keywords']
            : (json_decode($recipe['keywords'] ?? '[]', true) ?: []);

        if (empty($keywords)) {
            throw new \InvalidArgumentException('Informe ao menos uma hashtag para busca no Instagram.');
        }

        $client   = new ApifyClient();
        $profiles = $client->scrapeByHashtags($keywords, $maxResults);

        $found      = count($profiles);
        $new        = 0;
        $duplicates = 0;
        $errors     = [];

        $db = DB::getConnection();

        foreach ($profiles as $profile) {
            $username = $profile['instagram_username'] ?? '';
            if (empty($username)) {
                continue;
            }

            try {
                $check = $db->prepare("SELECT id FROM prospecting_results WHERE instagram_username = ? LIMIT 1");
                $check->execute([$username]);
                if ($check->fetch()) {
                    $duplicates++;
                    continue;
                }

                $stmt = $db->prepare("
                    INSERT INTO prospecting_results
                        (recipe_id, tenant_id, name, source, instagram_username, instagram_followers,
                         instagram_is_business, instagram_category, instagram_bio, instagram_profile_pic,
                         phone_instagram, email_instagram, website_instagram, instagram_city, status, found_at, updated_at)
                    VALUES (?, ?, ?, 'instagram', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW(), NOW())
                ");
                $stmt->execute([
                    $recipeId,
                    $recipe['tenant_id'] ?? null,
                    $profile['name'] ?: $username,
                    $username,
                    $profile['instagram_followers'],
                    $profile['instagram_is_business'],
                    $profile['instagram_category'],
                    $profile['instagram_bio'],
                    $profile['instagram_profile_pic'],
                    $profile['phone_instagram'],
                    $profile['email_instagram'],
                    $profile['website_instagram'],
                    $profile['instagram_city'],
                ]);
                $new++;
            } catch (\Exception $e) {
                $errors[] = 'Erro ao salvar @' . $username . ': ' . $e->getMessage();
            }
        }

        self::updateRecipeStats($recipeId);

        return [
            'found'      => $found,
            'new'        => $new,
            'duplicates' => $duplicates,
            'errors'     => $errors,
        ];
    }

    /**
     * Enriquece telefone business de um resultado Instagram via Apify (Fase 2)
     */
    public static function enrichWithApifyPhone(int $resultId): array
    {
        $result = self::findResultById($resultId);

        if (!$result) {
            throw new \InvalidArgumentException('Resultado não encontrado');
        }

        $username = $result['instagram_username'] ?? '';
        if (empty($username)) {
            throw new \InvalidArgumentException('Este resultado não possui username Instagram.');
        }

        $client   = new ApifyClient();
        $profiles = $client->scrapeProfiles([$username]);

        $db = DB::getConnection();
        $db->prepare("UPDATE prospecting_results SET apify_phone_enriched_at = NOW(), updated_at = NOW() WHERE id = ?")
           ->execute([$resultId]);

        if (empty($profiles)) {
            return ['success' => true, 'found' => false, 'message' => 'Perfil não encontrado no Instagram.'];
        }

        $profile = $profiles[0];

        $db->prepare("
            UPDATE prospecting_results SET
                name                    = COALESCE(NULLIF(?, ''), name),
                instagram_followers     = COALESCE(?, instagram_followers),
                instagram_is_business   = COALESCE(?, instagram_is_business),
                instagram_category      = COALESCE(?, instagram_category),
                instagram_bio           = COALESCE(?, instagram_bio),
                instagram_profile_pic   = COALESCE(?, instagram_profile_pic),
                phone_instagram         = ?,
                email_instagram         = COALESCE(?, email_instagram),
                website_instagram       = COALESCE(?, website_instagram),
                instagram_city          = COALESCE(?, instagram_city),
                apify_phone_enriched_at = NOW(),
                updated_at              = NOW()
            WHERE id = ?
        ")->execute([
            $profile['name'],
            $profile['instagram_followers'],
            $profile['instagram_is_business'],
            $profile['instagram_category'],
            $profile['instagram_bio'],
            $profile['instagram_profile_pic'],
            $profile['phone_instagram'],
            $profile['email_instagram'],
            $profile['website_instagram'],
            $profile['instagram_city'],
            $resultId,
        ]);

        return [
            'success' => true,
            'found'   => true,
            'phone'   => $profile['phone_instagram'],
            'email'   => $profile['email_instagram'],
            'data'    => $profile,
        ];
    }

    // =========================================================================
    // SALVAR TELEFONE MANUAL
    // =========================================================================

    public static function savePhone(int $resultId, string $phone): array
    {
        $result = self::findResultById($resultId);
        if (!$result) {
            throw new \InvalidArgumentException('Resultado não encontrado');
        }

        $phone = trim($phone);
        if (empty($phone)) {
            throw new \InvalidArgumentException('Telefone inválido');
        }

        $db = DB::getConnection();

        $source = $result['source'] ?? '';
        if ($source === 'instagram') {
            $col = 'phone_instagram';
        } elseif ($source === 'google_maps') {
            $col = 'phone_google';
        } else {
            $col = 'phone_minhareceita';
        }

        $db->prepare("UPDATE prospecting_results SET {$col} = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$phone, $resultId]);

        return ['success' => true, 'phone' => $phone, 'column' => $col];
    }

    // =========================================================================
    // CHAVE APIFY
    // =========================================================================

    public static function saveApifyApiKey(string $apiKey, int $userId): void
    {
        $db        = DB::getConnection();
        $encrypted = CryptoHelper::encrypt($apiKey);

        $stmt = $db->prepare("
            INSERT INTO integration_settings (integration_key, integration_value, is_encrypted, label, updated_by, created_at, updated_at)
            VALUES ('apify_api_key', ?, 1, 'Apify API Key', ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                integration_value = VALUES(integration_value),
                is_encrypted = 1,
                updated_by = VALUES(updated_by),
                updated_at = NOW()
        ");
        $stmt->execute([$encrypted, $userId]);
    }

    public static function hasApifyApiKey(): bool
    {
        return ApifyClient::hasApiKey();
    }

    public static function getMaskedApifyApiKey(): ?string
    {
        try {
            $key = ApifyClient::resolveApiKey();
            if (strlen($key) <= 8) return str_repeat('*', strlen($key));
            return substr($key, 0, 6) . str_repeat('*', strlen($key) - 10) . substr($key, -4);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Executa busca via Google Places API
     */
    private static function runSearchGoogleMaps(array $recipe, int $recipeId, int $maxResults): array
    {
        $client = new GooglePlacesClient();

        $queries = self::buildSearchQueries($recipe);

        $seenPlaceIds = [];
        $allPlaces    = [];

        foreach ($queries as $query) {
            try {
                $batch = $client->textSearch($query, 20);
                foreach ($batch as $place) {
                    $pid = $place['google_place_id'] ?? '';
                    if ($pid && !isset($seenPlaceIds[$pid])) {
                        $seenPlaceIds[$pid] = true;
                        $allPlaces[] = $place;
                    }
                }
            } catch (\Exception $e) {
                // Continua com as demais queries mesmo se uma falhar
            }
        }

        $found      = count($allPlaces);
        $new        = 0;
        $duplicates = 0;
        $errors     = [];

        $db = DB::getConnection();

        foreach ($allPlaces as $place) {
            if (empty($place['google_place_id'])) {
                continue;
            }

            try {
                $check = $db->prepare("SELECT id FROM prospecting_results WHERE google_place_id = ?");
                $check->execute([$place['google_place_id']]);
                if ($check->fetch()) {
                    $duplicates++;
                    continue;
                }

                $stmt = $db->prepare("
                    INSERT INTO prospecting_results
                        (recipe_id, tenant_id, google_place_id, name, address_google, city, state, phone_google, website_google,
                         rating, user_ratings_total, lat, lng, google_types, source, status, found_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'google_maps', 'new', NOW(), NOW())
                ");
                $stmt->execute([
                    $recipeId,
                    $recipe['tenant_id'] ?? null,
                    $place['google_place_id'],
                    $place['name'],
                    $place['address'],
                    $place['city'],
                    $place['state'],
                    $place['phone'],
                    $place['website'],
                    $place['rating'],
                    $place['user_ratings_total'],
                    $place['lat'],
                    $place['lng'],
                    json_encode($place['google_types'], JSON_UNESCAPED_UNICODE),
                ]);
                $new++;
            } catch (\Exception $e) {
                $errors[] = 'Erro ao salvar "' . $place['name'] . '": ' . $e->getMessage();
            }
        }

        self::updateRecipeStats($recipeId);

        return [
            'found'      => $found,
            'new'        => $new,
            'duplicates' => $duplicates,
            'errors'     => $errors,
        ];
    }

    /**
     * Atualiza last_run_at e total_found da receita
     */
    private static function updateRecipeStats(int $recipeId): void
    {
        DB::getConnection()->prepare("
            UPDATE prospecting_recipes
            SET last_run_at = NOW(),
                total_found = (SELECT COUNT(*) FROM prospecting_results WHERE recipe_id = ?),
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$recipeId, $recipeId]);
    }

    /**
     * Monta múltiplas queries — uma por keyword — para maximizar resultados
     * Cada query retorna até 20 resultados únicos da API
     */
    private static function buildSearchQueries(array $recipe): array
    {
        $keywords = $recipe['keywords'] ?? [];
        $location = trim($recipe['city']);
        if (!empty($recipe['state'])) {
            $location .= ' ' . $recipe['state'];
        }

        $queries = [];

        if (!empty($keywords)) {
            foreach ($keywords as $kw) {
                $kw = trim($kw);
                if ($kw !== '') {
                    $queries[] = $kw . ' ' . $location;
                }
            }
        }

        // Fallback: query genérica só com localização se não houver keywords
        if (empty($queries)) {
            $queries[] = $location;
        }

        return $queries;
    }

    /**
     * @deprecated Use buildSearchQueries()
     */
    private static function buildSearchQuery(array $recipe): string
    {
        $queries = self::buildSearchQueries($recipe);
        return $queries[0] ?? '';
    }

    // =========================================================================
    // RESULTADOS
    // =========================================================================

    /**
     * Lista resultados de uma receita com filtros
     */
    public static function listResults(int $recipeId, array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $db = DB::getConnection();

        $where  = ['pr.recipe_id = ?'];
        $params = [$recipeId];

        if (!empty($filters['status'])) {
            $where[]  = 'pr.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[]  = '(pr.name LIKE ? OR pr.address LIKE ? OR pr.phone LIKE ? OR pr.cnpj LIKE ? OR pr.email LIKE ?)';
            $s        = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        // Filtros avançados (Minha Receita)
        if (!empty($filters['situacao'])) {
            $where[]  = 'pr.situacao_cadastral = ?';
            $params[] = $filters['situacao'];
        }

        if (!empty($filters['porte'])) {
            $where[]  = 'pr.porte = ?';
            $params[] = $filters['porte'];
        }

        if (isset($filters['mei']) && $filters['mei'] !== '') {
            $where[]  = 'pr.opcao_pelo_mei = ?';
            $params[] = (int) $filters['mei'];
        }

        if (isset($filters['simples']) && $filters['simples'] !== '') {
            $where[]  = 'pr.opcao_pelo_simples = ?';
            $params[] = (int) $filters['simples'];
        }

        if (!empty($filters['matriz_filial'])) {
            $where[]  = 'pr.identificador_matriz_filial = ?';
            $params[] = (int) $filters['matriz_filial'];
        }

        // Filtros Instagram: telefone / email
        if (!empty($filters['tem_contato'])) {
            if ($filters['tem_contato'] === 'phone') {
                $where[] = "(pr.phone_instagram IS NOT NULL AND pr.phone_instagram != '')";
            } elseif ($filters['tem_contato'] === 'email') {
                $where[] = "(pr.email_instagram IS NOT NULL AND pr.email_instagram != '')";
            } elseif ($filters['tem_contato'] === 'any') {
                $where[] = "((pr.phone_instagram IS NOT NULL AND pr.phone_instagram != '') OR (pr.email_instagram IS NOT NULL AND pr.email_instagram != ''))";
            } elseif ($filters['tem_contato'] === 'not_enriched') {
                $where[] = 'pr.apify_phone_enriched_at IS NULL';
            }
        }

        // Filtro de enriquecimento Google Maps
        if (!empty($filters['google_enrichment'])) {
            if ($filters['google_enrichment'] === 'enriched') {
                // Enriquecidas: tentou E tem google_enriched_at
                $where[] = 'pr.google_enrichment_attempted = 1 AND pr.google_enriched_at IS NOT NULL';
            } elseif ($filters['google_enrichment'] === 'not_found') {
                // Não encontradas: tentou MAS NÃO tem google_enriched_at
                $where[] = 'pr.google_enrichment_attempted = 1 AND pr.google_enriched_at IS NULL';
            } elseif ($filters['google_enrichment'] === 'not_verified') {
                // Não verificadas: nunca tentou
                $where[] = 'pr.google_enrichment_attempted = 0';
            }
        }

        // Filtro de mensagem WA enviada
        if (!empty($filters['wa_sent'])) {
            if ($filters['wa_sent'] === 'sent') {
                $where[] = 'pr.whatsapp_sent_at IS NOT NULL';
            } elseif ($filters['wa_sent'] === 'not_sent') {
                $where[] = 'pr.whatsapp_sent_at IS NULL';
            }
        }

        $whereStr = implode(' AND ', $where);

        $stmt = $db->prepare("
            SELECT pr.*,
                   l.name as lead_name,
                   o.name as opportunity_name, o.stage as opportunity_stage
            FROM prospecting_results pr
            LEFT JOIN leads l ON l.id = pr.lead_id
            LEFT JOIN opportunities o ON o.id = pr.opportunity_id
            WHERE {$whereStr}
            ORDER BY pr.found_at DESC
            LIMIT ? OFFSET ?
        ");
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Conta resultados de uma receita
     */
    public static function countResults(int $recipeId, array $filters = []): int
    {
        $db = DB::getConnection();

        $where  = ['recipe_id = ?'];
        $params = [$recipeId];

        if (!empty($filters['status'])) {
            $where[]  = 'status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[]  = '(name LIKE ? OR address LIKE ? OR phone LIKE ? OR cnpj LIKE ? OR email LIKE ?)';
            $s        = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        // Filtros avançados (Minha Receita)
        if (!empty($filters['situacao'])) {
            $where[]  = 'situacao_cadastral = ?';
            $params[] = $filters['situacao'];
        }

        if (!empty($filters['porte'])) {
            $where[]  = 'porte = ?';
            $params[] = $filters['porte'];
        }

        if (isset($filters['mei']) && $filters['mei'] !== '') {
            $where[]  = 'opcao_pelo_mei = ?';
            $params[] = (int) $filters['mei'];
        }

        if (isset($filters['simples']) && $filters['simples'] !== '') {
            $where[]  = 'opcao_pelo_simples = ?';
            $params[] = (int) $filters['simples'];
        }

        if (!empty($filters['matriz_filial'])) {
            $where[]  = 'identificador_matriz_filial = ?';
            $params[] = (int) $filters['matriz_filial'];
        }

        // Filtros Instagram: telefone / email
        if (!empty($filters['tem_contato'])) {
            if ($filters['tem_contato'] === 'phone') {
                $where[] = "(phone_instagram IS NOT NULL AND phone_instagram != '')";
            } elseif ($filters['tem_contato'] === 'email') {
                $where[] = "(email_instagram IS NOT NULL AND email_instagram != '')";
            } elseif ($filters['tem_contato'] === 'any') {
                $where[] = "((phone_instagram IS NOT NULL AND phone_instagram != '') OR (email_instagram IS NOT NULL AND email_instagram != ''))";
            } elseif ($filters['tem_contato'] === 'not_enriched') {
                $where[] = 'apify_phone_enriched_at IS NULL';
            }
        }

        // Filtro de enriquecimento Google Maps
        if (!empty($filters['google_enrichment'])) {
            if ($filters['google_enrichment'] === 'enriched') {
                $where[] = 'google_enrichment_attempted = 1 AND google_enriched_at IS NOT NULL';
            } elseif ($filters['google_enrichment'] === 'not_found') {
                $where[] = 'google_enrichment_attempted = 1 AND google_enriched_at IS NULL';
            } elseif ($filters['google_enrichment'] === 'not_verified') {
                $where[] = 'google_enrichment_attempted = 0';
            }
        }

        // Filtro de mensagem WA enviada
        if (!empty($filters['wa_sent'])) {
            if ($filters['wa_sent'] === 'sent') {
                $where[] = 'whatsapp_sent_at IS NOT NULL';
            } elseif ($filters['wa_sent'] === 'not_sent') {
                $where[] = 'whatsapp_sent_at IS NULL';
            }
        }

        $whereStr = implode(' AND ', $where);
        $stmt = $db->prepare("SELECT COUNT(*) FROM prospecting_results WHERE {$whereStr}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca resultado por ID
     */
    public static function findResultById(int $id): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * FROM prospecting_results WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Atualiza status de um resultado
     */
    public static function updateResultStatus(int $id, string $status, ?string $notes = null, ?int $userId = null): void
    {
        $db = DB::getConnection();
        $db->prepare("
            UPDATE prospecting_results
            SET status = ?, notes = COALESCE(?, notes), updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$status, $notes, $userId, $id]);
    }

    /**
     * Marca resultado como mensagem WA enviada e faz upgrade para 'qualified' se status for 'new'
     */
    public static function markWaSent(int $id, ?int $userId = null): void
    {
        $db = DB::getConnection();
        $db->prepare("
            UPDATE prospecting_results
            SET whatsapp_sent_at = COALESCE(whatsapp_sent_at, NOW()),
                status = CASE WHEN status != 'discarded' THEN 'qualified' ELSE 'discarded' END,
                updated_by = ?,
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$userId, $id]);
    }

    /**
     * Converte um resultado em Lead
     * 
     * @return int ID do lead criado
     */
    public static function convertToLead(int $resultId, int $userId): int
    {
        $result = self::findResultById($resultId);
        if (!$result) {
            throw new \InvalidArgumentException('Resultado não encontrado');
        }

        if ($result['lead_id']) {
            return $result['lead_id'];
        }

        // Determina source com base na receita
        $recipeStmt = DB::getConnection()->prepare("SELECT source FROM prospecting_recipes WHERE id = ? LIMIT 1");
        $recipeStmt->execute([$result['recipe_id']]);
        $recipeSource = $recipeStmt->fetchColumn() ?: 'google_maps';

        if ($recipeSource === 'minhareceita') {
            $leadSource = 'prospecting_minhareceita';
            $cnpjFormatted = !empty($result['cnpj']) ? preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $result['cnpj']) : '';
            $leadNotes = trim(
                'Empresa encontrada via Prospecção Ativa (Minha Receita).' .
                (!empty($cnpjFormatted) ? "\nCNPJ: " . $cnpjFormatted : '') .
                (!empty($result['address_minhareceita']) ? "\nEndereço: " . $result['address_minhareceita'] : '')
            );
        } elseif ($recipeSource === 'instagram') {
            $leadSource = 'prospecting_instagram';
            $leadNotes = trim(
                'Perfil encontrado via Prospecção Ativa (Instagram).' .
                (!empty($result['instagram_username']) ? "\nInstagram: @" . $result['instagram_username'] : '') .
                (!empty($result['instagram_bio']) ? "\nBio: " . $result['instagram_bio'] : '') .
                (!empty($result['instagram_category']) ? "\nCategoria: " . $result['instagram_category'] : '')
            );
        } else {
            $leadSource = 'prospecting_google_maps';
            $leadNotes = trim(
                'Empresa encontrada via Prospecção Ativa (Google Maps).' .
                (!empty($result['address_google']) ? "\nEndereço: " . $result['address_google'] : '') .
                (!empty($result['website_google']) ? "\nSite: " . $result['website_google'] : '') .
                (!empty($result['rating']) ? "\nAvaliação Google: " . $result['rating'] . '/5' : '')
            );
        }

        // Cria o lead
        // Prioriza phone_instagram, depois phone_minhareceita, fallback phone_google
        $phone = $result['phone_instagram'] ?? $result['phone_minhareceita'] ?? $result['phone_google'] ?? null;
        $email = $result['email'] ?? null;
        
        $leadId = LeadService::create([
            'name'       => $result['name'],
            'company'    => $result['name'],
            'phone'      => $phone,
            'email'      => $email,
            'source'     => $leadSource,
            'notes'      => $leadNotes,
            'created_by' => $userId,
        ]);

        // Vincula o lead ao resultado
        DB::getConnection()->prepare("
            UPDATE prospecting_results
            SET lead_id = ?, status = 'contacted', updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$leadId, $userId, $resultId]);

        return $leadId;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Normaliza array de palavras-chave (string ou array)
     */
    private static function normalizeKeywords(mixed $keywords): array
    {
        if (is_string($keywords)) {
            // Pode vir como string separada por vírgula ou JSON
            $decoded = json_decode($keywords, true);
            if (is_array($decoded)) {
                $keywords = $decoded;
            } else {
                $keywords = array_map('trim', explode(',', $keywords));
            }
        }

        if (!is_array($keywords)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $keywords)));
    }

    /**
     * Busca dados do Google Maps para enriquecer um resultado da Minha Receita
     */
    public static function searchGoogleMapsForEnrichment(int $resultId): array
    {
        $db = DB::getConnection();
        
        // Busca o resultado
        $stmt = $db->prepare("SELECT * FROM prospecting_results WHERE id = ?");
        $stmt->execute([$resultId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$result) {
            throw new \Exception('Resultado não encontrado');
        }
        
        // Marca que tentou enriquecer
        $markStmt = $db->prepare("UPDATE prospecting_results SET google_enrichment_attempted = 1 WHERE id = ?");
        $markStmt->execute([$resultId]);
        
        // Busca no Google Maps usando nome + cidade
        $client = new GooglePlacesClient();
        $query = trim($result['name']) . ' ' . trim($result['city']) . ' ' . trim($result['state']);
        
        $places = $client->textSearch($query, 5); // Busca até 5 resultados para filtrar
        
        if (empty($places)) {
            throw new \Exception('Nenhum resultado encontrado no Google Maps');
        }
        
        // Filtra resultados pela cidade correta
        $expectedCity = self::normalizeString($result['city']);
        $filteredPlaces = array_filter($places, function($place) use ($expectedCity) {
            if (empty($place['city'])) {
                return false;
            }
            $placeCity = self::normalizeString($place['city']);
            // Aceita se cidade é exatamente igual ou se contém a cidade esperada
            return $placeCity === $expectedCity || strpos($placeCity, $expectedCity) !== false;
        });
        
        if (empty($filteredPlaces)) {
            throw new \Exception('Nenhum resultado encontrado no Google Maps na cidade de ' . $result['city']);
        }
        
        // Pega o primeiro resultado filtrado
        $googlePlace = reset($filteredPlaces);
        
        // Calcula score de confiança
        $confidence = self::calculateMatchingConfidence($result, $googlePlace);
        
        return [
            'minha_receita' => [
                'name' => $result['name'],
                'razao_social' => $result['razao_social'],
                'address' => $result['address'],
                'phone' => $result['phone'],
                'email' => $result['email'],
                'website' => $result['website'],
            ],
            'google_maps' => [
                'name' => $googlePlace['name'],
                'address' => $googlePlace['address'],
                'phone' => $googlePlace['phone'],
                'website' => $googlePlace['website'],
                'rating' => $googlePlace['rating'],
                'user_ratings_total' => $googlePlace['user_ratings_total'],
                'google_place_id' => $googlePlace['google_place_id'],
            ],
            'confidence' => $confidence,
            'confidence_label' => self::getConfidenceLabel($confidence),
        ];
    }
    
    /**
     * Calcula score de confiança do matching (0-100)
     */
    private static function calculateMatchingConfidence(array $minhaReceita, array $googlePlace): int
    {
        $score = 0;
        $checks = 0;
        
        // Similaridade de nome (peso 40)
        $nameSimilarity = self::calculateStringSimilarity(
            self::normalizeString($minhaReceita['name']),
            self::normalizeString($googlePlace['name'])
        );
        $score += $nameSimilarity * 40;
        $checks++;
        
        // Endereço (peso 30)
        if (!empty($minhaReceita['address']) && !empty($googlePlace['address'])) {
            $addressSimilarity = self::calculateStringSimilarity(
                self::normalizeString($minhaReceita['address']),
                self::normalizeString($googlePlace['address'])
            );
            $score += $addressSimilarity * 30;
            $checks++;
        }
        
        // Telefone (peso 20)
        if (!empty($minhaReceita['phone']) && !empty($googlePlace['phone'])) {
            $phone1 = preg_replace('/\D/', '', $minhaReceita['phone']);
            $phone2 = preg_replace('/\D/', '', $googlePlace['phone']);
            
            if ($phone1 === $phone2) {
                $score += 20;
            } elseif (substr($phone1, -8) === substr($phone2, -8)) {
                $score += 15; // Últimos 8 dígitos iguais
            }
            $checks++;
        }
        
        // Cidade (peso 30 - CRÍTICO)
        if (!empty($minhaReceita['city']) && !empty($googlePlace['city'])) {
            $city1 = self::normalizeString($minhaReceita['city']);
            $city2 = self::normalizeString($googlePlace['city']);
            
            if ($city1 === $city2) {
                $score += 30; // Cidade exata
            } elseif (strpos($city2, $city1) !== false || strpos($city1, $city2) !== false) {
                $score += 15; // Cidade contém ou está contida
            } else {
                // Cidade diferente = penaliza fortemente
                $score -= 50;
            }
            $checks++;
        }
        
        return min(100, (int) round($score));
    }
    
    /**
     * Calcula similaridade entre duas strings (0.0 a 1.0)
     */
    private static function calculateStringSimilarity(string $str1, string $str2): float
    {
        if (empty($str1) || empty($str2)) {
            return 0.0;
        }
        
        similar_text($str1, $str2, $percent);
        return $percent / 100;
    }
    
    /**
     * Normaliza string para comparação
     */
    private static function normalizeString(string $str): string
    {
        $str = mb_strtolower($str, 'UTF-8');
        $str = preg_replace('/[^a-z0-9\s]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $str));
        $str = preg_replace('/\s+/', ' ', $str);
        return trim($str);
    }
    
    /**
     * Retorna label de confiança baseado no score
     */
    private static function getConfidenceLabel(int $score): string
    {
        if ($score >= 80) return 'ALTA';
        if ($score >= 60) return 'MÉDIA';
        return 'BAIXA';
    }
    
    /**
     * Aplica enriquecimento aprovado pelo usuário
     * 
     * IMPORTANTE: Preserva dados de ambas as fontes (Minha Receita + Google Maps)
     * em vez de sobrescrever. Usa campos separados:
     * - phone_minhareceita / phone_google
     * - website_minhareceita / website_google
     * - address_minhareceita / address_google
     */
    public static function applyGoogleEnrichment(int $resultId, array $googleData): void
    {
        $db = DB::getConnection();
        
        // Atualiza apenas campos específicos do Google Maps
        // Preserva dados da Minha Receita em campos separados
        $stmt = $db->prepare("
            UPDATE prospecting_results SET
                google_place_id = ?,
                phone_google = ?,
                website_google = ?,
                address_google = ?,
                rating = ?,
                user_ratings_total = ?,
                google_enriched_at = NOW(),
                enrichment_confidence = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $googleData['google_place_id'] ?? null,
            $googleData['phone'] ?? null,
            $googleData['website'] ?? null,
            $googleData['address'] ?? null,
            $googleData['rating'] ?? null,
            $googleData['user_ratings_total'] ?? null,
            $googleData['confidence'] ?? null,
            $resultId,
        ]);
    }

    /**
     * Enriquece dados de contato via CNPJ.ws
     * 
     * IMPORTANTE: CNPJ.ws é a FONTE DA VERDADE - SUBSTITUI dados existentes
     * (diferente do Google Maps que apenas adiciona em campos separados)
     * 
     * Atualiza:
     * - Email (sempre substitui se disponível)
     * - Telefone principal (sempre substitui se disponível)
     * - Telefone secundário (sempre substitui se disponível)
     * - Website (sempre substitui se disponível)
     * 
     * Rate limit: 3 requisições/minuto (API pública gratuita)
     */
    public static function enrichWithCnpjWs(int $resultId): array
    {
        $db = DB::getConnection();
        
        // Busca o resultado
        $stmt = $db->prepare("SELECT * FROM prospecting_results WHERE id = ?");
        $stmt->execute([$resultId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$result) {
            throw new \Exception('Resultado não encontrado');
        }
        
        if (empty($result['cnpj'])) {
            throw new \Exception('CNPJ não disponível para esta empresa');
        }
        
        // Busca dados no CNPJ.ws
        $client = new CnpjWsEnrichmentClient();
        $contactData = $client->getContactData($result['cnpj']);
        
        if (!$contactData) {
            throw new \Exception('Dados não encontrados no CNPJ.ws');
        }
        
        // CNPJ.ws é a FONTE DA VERDADE - SUBSTITUI dados existentes
        $updates = [];
        $params = [];
        
        // Email - sempre atualiza se disponível
        if (!empty($contactData['email'])) {
            $updates[] = 'email = ?';
            $params[] = $contactData['email'];
        }
        
        // Telefone principal - sempre atualiza se disponível
        if (!empty($contactData['phone'])) {
            $updates[] = 'phone_minhareceita = ?';
            $params[] = $contactData['phone'];
        }
        
        // Telefone secundário - sempre atualiza se disponível
        if (!empty($contactData['phone_secondary'])) {
            $updates[] = 'telefone_secundario = ?';
            $params[] = $contactData['phone_secondary'];
        }
        
        // Website - sempre atualiza se disponível
        if (!empty($contactData['website'])) {
            $updates[] = 'website_minhareceita = ?';
            $params[] = $contactData['website'];
        }
        
        if (!empty($updates)) {
            $updates[] = 'updated_at = NOW()';
            $params[] = $resultId;
            
            $sql = "UPDATE prospecting_results SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }
        
        return [
            'success' => true,
            'updated_fields' => count($updates) - 1, // -1 para não contar updated_at
            'data' => $contactData,
        ];
    }
    
    /**
     * Tipos de lugares do Google Places mais comuns para prospecção
     */
    public static function getCommonPlaceTypes(): array
    {
        return [
            '' => 'Qualquer tipo',
            'real_estate_agency'    => 'Imobiliária',
            'car_dealer'            => 'Concessionária',
            'car_repair'            => 'Oficina Mecânica',
            'beauty_salon'          => 'Salão de Beleza',
            'dentist'               => 'Dentista / Clínica Odontológica',
            'doctor'                => 'Médico / Clínica',
            'gym'                   => 'Academia',
            'lawyer'                => 'Escritório de Advocacia',
            'accounting'            => 'Contabilidade',
            'insurance_agency'      => 'Seguradora / Corretora de Seguros',
            'travel_agency'         => 'Agência de Viagens',
            'restaurant'            => 'Restaurante',
            'store'                 => 'Loja',
            'clothing_store'        => 'Loja de Roupas',
            'home_goods_store'      => 'Loja de Cama, Mesa e Banho / Casa',
            'furniture_store'       => 'Loja de Móveis',
            'electronics_store'     => 'Loja de Eletrônicos',
            'department_store'      => 'Loja de Departamentos',
            'shopping_mall'         => 'Shopping / Centro Comercial',
            'pet_store'             => 'Pet Shop',
            'school'                => 'Escola / Curso',
            'university'            => 'Faculdade / Universidade',
            'hospital'              => 'Hospital',
            'pharmacy'              => 'Farmácia',
            'supermarket'           => 'Supermercado',
            'bakery'                => 'Padaria',
            'bar'                   => 'Bar',
            'hotel'                 => 'Hotel / Pousada',
            'lodging'               => 'Hospedagem',
            'moving_company'        => 'Transportadora / Mudança',
            'plumber'               => 'Encanador / Hidráulica',
            'electrician'           => 'Eletricista',
            'painter'               => 'Pintor',
            'roofing_contractor'    => 'Telhados / Construção',
            'general_contractor'    => 'Construtora',
            'florist'               => 'Floricultura',
            'photographer'          => 'Fotógrafo / Estúdio',
            'spa'                   => 'Spa / Estética',
            'veterinary_care'       => 'Veterinário',
        ];
    }
}
