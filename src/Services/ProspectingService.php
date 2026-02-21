<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

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
    public static function listTenants(): array
    {
        $db = DB::getConnection();
        $stmt = $db->query("
            SELECT DISTINCT t.id, t.name, t.company
            FROM tenants t
            INNER JOIN prospecting_recipes r ON r.tenant_id = t.id
            WHERE (t.is_archived IS NULL OR t.is_archived = 0)
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
    public static function listRecipes(?int $tenantId = 0): array
    {
        $db = DB::getConnection();

        $where = '';
        $params = [];
        if ($tenantId === null) {
            $where = 'WHERE r.tenant_id IS NULL';
        } elseif ($tenantId > 0) {
            $where = 'WHERE r.tenant_id = ?';
            $params[] = $tenantId;
        }

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
        $city        = trim($data['city'] ?? '');
        $state       = strtoupper(trim($data['state'] ?? ''));
        $productId   = !empty($data['product_id']) ? (int) $data['product_id'] : null;
        $placeType   = trim($data['google_place_type'] ?? '') ?: null;
        $radius      = !empty($data['radius_meters']) ? (int) $data['radius_meters'] : 5000;
        $notes       = trim($data['notes'] ?? '') ?: null;

        // Normaliza keywords
        $keywords = self::normalizeKeywords($data['keywords'] ?? []);

        if (empty($name) || empty($city)) {
            throw new \InvalidArgumentException('Nome e cidade são obrigatórios');
        }

        $tenantId = !empty($data['tenant_id']) ? (int) $data['tenant_id'] : null;

        $stmt = $db->prepare("
            INSERT INTO prospecting_recipes
                (tenant_id, name, product_id, city, state, keywords, google_place_type, radius_meters, status, notes, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $tenantId,
            $name,
            $productId,
            $city,
            $state ?: null,
            json_encode($keywords, JSON_UNESCAPED_UNICODE),
            $placeType,
            $radius,
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

        $tenantId = !empty($data['tenant_id']) ? (int) $data['tenant_id'] : null;

        $stmt = $db->prepare("
            UPDATE prospecting_recipes SET
                tenant_id = ?,
                name = ?,
                product_id = ?,
                city = ?,
                state = ?,
                keywords = ?,
                google_place_type = ?,
                radius_meters = ?,
                notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $tenantId,
            trim($data['name'] ?? ''),
            !empty($data['product_id']) ? (int) $data['product_id'] : null,
            trim($data['city'] ?? ''),
            $state ?: null,
            json_encode($keywords, JSON_UNESCAPED_UNICODE),
            trim($data['google_place_type'] ?? '') ?: null,
            !empty($data['radius_meters']) ? (int) $data['radius_meters'] : 5000,
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
     * 
     * @return array ['found' => int, 'new' => int, 'duplicates' => int, 'errors' => string[]]
     */
    public static function runSearch(int $recipeId, int $maxResults = 60): array
    {
        $recipe = self::findRecipeById($recipeId);
        if (!$recipe) {
            throw new \InvalidArgumentException('Receita não encontrada');
        }

        $client = new GooglePlacesClient();

        // Monta a query de busca
        $query = self::buildSearchQuery($recipe);

        $places = $client->textSearch($query, $maxResults);

        $found      = count($places);
        $new        = 0;
        $duplicates = 0;
        $errors     = [];

        $db = DB::getConnection();

        foreach ($places as $place) {
            if (empty($place['google_place_id'])) {
                continue;
            }

            try {
                // Verifica se já existe (deduplicação global por google_place_id)
                $check = $db->prepare("SELECT id, recipe_id FROM prospecting_results WHERE google_place_id = ?");
                $check->execute([$place['google_place_id']]);
                $existing = $check->fetch(\PDO::FETCH_ASSOC);

                if ($existing) {
                    $duplicates++;
                    // Se é de outra receita, não faz nada (deduplicação global)
                    // Se é da mesma receita, também não duplica
                    continue;
                }

                $stmt = $db->prepare("
                    INSERT INTO prospecting_results
                        (recipe_id, tenant_id, google_place_id, name, address, city, state, phone, website,
                         rating, user_ratings_total, lat, lng, google_types, status, found_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW(), NOW())
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

        // Atualiza last_run_at e total_found
        $db->prepare("
            UPDATE prospecting_recipes
            SET last_run_at = NOW(),
                total_found = (SELECT COUNT(*) FROM prospecting_results WHERE recipe_id = ?),
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$recipeId, $recipeId]);

        return [
            'found'      => $found,
            'new'        => $new,
            'duplicates' => $duplicates,
            'errors'     => $errors,
        ];
    }

    /**
     * Monta a query de busca a partir da receita
     */
    private static function buildSearchQuery(array $recipe): string
    {
        $parts = [];

        // Palavras-chave (usa a primeira como termo principal)
        $keywords = $recipe['keywords'] ?? [];
        if (!empty($keywords)) {
            $parts[] = implode(' ', array_slice($keywords, 0, 3));
        }

        // Cidade + Estado
        $location = trim($recipe['city']);
        if (!empty($recipe['state'])) {
            $location .= ' ' . $recipe['state'];
        }
        $parts[] = $location;

        return implode(' ', $parts);
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
            $where[]  = '(pr.name LIKE ? OR pr.address LIKE ? OR pr.phone LIKE ?)';
            $s        = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
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

        // Cria o lead
        $leadId = LeadService::create([
            'name'       => $result['name'],
            'company'    => $result['name'],
            'phone'      => $result['phone'],
            'email'      => null,
            'source'     => 'prospecting_google_maps',
            'notes'      => trim(
                'Empresa encontrada via Prospecção Ativa (Google Maps).' .
                (!empty($result['address']) ? "\nEndereço: " . $result['address'] : '') .
                (!empty($result['website']) ? "\nSite: " . $result['website'] : '') .
                (!empty($result['rating']) ? "\nAvaliação Google: " . $result['rating'] . '/5' : '')
            ),
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
            'furniture_store'       => 'Loja de Móveis',
            'electronics_store'     => 'Loja de Eletrônicos',
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
