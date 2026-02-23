<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Services\ProspectingService;
use PixelHub\Services\OpportunityProductService;
use PixelHub\Services\GooglePlacesClient;
use PixelHub\Core\CryptoHelper;

/**
 * Controller para Prospecção Ativa (CRM / Comercial)
 */
class ProspectingController extends Controller
{
    // =========================================================================
    // CONFIGURAÇÕES GOOGLE MAPS (Configurações > Integrações)
    // =========================================================================

    /**
     * GET /settings/google-maps
     */
    public function settingsIndex(): void
    {
        Auth::requireInternal();

        $hasKey    = ProspectingService::hasApiKey();
        $maskedKey = ProspectingService::getMaskedApiKey();

        $this->view('settings.google_maps', [
            'hasKey'    => $hasKey,
            'maskedKey' => $maskedKey,
        ]);
    }

    /**
     * POST /settings/google-maps/save
     */
    public function settingsSave(): void
    {
        Auth::requireInternal();

        $apiKey = trim($_POST['api_key'] ?? '');

        if (empty($apiKey)) {
            $this->redirect('/settings/google-maps?error=empty_key&message=' . urlencode('Informe a chave de API.'));
            return;
        }

        // Validação básica: chaves do Google geralmente começam com "AIza"
        if (strlen($apiKey) < 20) {
            $this->redirect('/settings/google-maps?error=invalid_key&message=' . urlencode('Chave de API inválida.'));
            return;
        }

        try {
            $userId = Auth::user()['id'] ?? 0;
            ProspectingService::saveApiKey($apiKey, $userId);

            // Testa a chave após salvar
            $client = new GooglePlacesClient();
            $test   = $client->testApiKey();

            if ($test['success']) {
                $this->redirect('/settings/google-maps?success=saved&message=' . urlencode('Chave salva e validada com sucesso!'));
            } else {
                $this->redirect('/settings/google-maps?warning=saved_not_validated&message=' . urlencode('Chave salva, mas não foi possível validar: ' . $test['message']));
            }
        } catch (\Exception $e) {
            error_log('[ProspectingController] Erro ao salvar chave: ' . $e->getMessage());
            $this->redirect('/settings/google-maps?error=save_failed&message=' . urlencode($e->getMessage()));
        }
    }

    /**
     * POST /settings/google-maps/test  (AJAX)
     */
    public function settingsTest(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        try {
            $client = new GooglePlacesClient();
            $result = $client->testApiKey();
            $this->json($result);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // RECEITAS DE BUSCA
    // =========================================================================

    /**
     * GET /prospecting/search-tenants?q=xxx
     * Busca tenants por nome/empresa para autocomplete (mínimo 2 chars)
     */
    public function searchTenants(): void
    {
        Auth::requireInternal();
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            $this->json([]);
            return;
        }
        $this->json(ProspectingService::searchTenants($q));
    }

    /**
     * GET /prospecting/search-cnae?q=xxx
     * Busca CNAEs por código ou descrição via API pública CNPJ.ws
     */
    public function searchCnae(): void
    {
        Auth::requireInternal();
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            $this->json([]);
            return;
        }
        $client  = new \PixelHub\Services\CnpjWsClient();
        $results = $client->searchCnae($q, 15);
        $this->json($results);
    }

    /**
     * GET /prospecting
     */
    public function index(): void
    {
        Auth::requireInternal();

        // Filtro de conta: null = agência própria, 0 = todas, N = tenant específico
        $tenantFilter = isset($_GET['tenant_id'])
            ? ($_GET['tenant_id'] === 'own' ? null : (int) $_GET['tenant_id'])
            : 0;

        // Sem ?source= → default google_maps (cada fonte tem sua própria listagem)
        $sourceFilter = (isset($_GET['source']) && in_array($_GET['source'], ['google_maps', 'minhareceita']))
            ? $_GET['source']
            : 'google_maps';

        $recipes         = ProspectingService::listRecipes($tenantFilter, $sourceFilter);
        $hasKey          = ProspectingService::hasApiKey();
        $products        = OpportunityProductService::listActive();
        $tenants         = ProspectingService::listTenants($sourceFilter);

        $this->view('prospecting.recipes', [
            'recipes'      => $recipes,
            'hasKey'       => $hasKey,
            'products'     => $products,
            'tenants'      => $tenants,
            'tenantFilter' => $tenantFilter,
            'sourceFilter' => $sourceFilter,
        ]);
    }

    /**
     * POST /prospecting/store
     */
    public function store(): void
    {
        Auth::requireInternal();

        try {
            $userId = Auth::user()['id'] ?? 0;

            // Normaliza keywords: pode vir como campo separado por vírgula
            $keywordsRaw = trim($_POST['keywords_raw'] ?? '');
            $keywords    = array_values(array_filter(array_map('trim', explode(',', $keywordsRaw))));

            // Parse array de CNAEs se fornecido
            $cnaes = null;
            if (!empty($_POST['cnaes'])) {
                $cnaesDecoded = json_decode($_POST['cnaes'], true);
                if (is_array($cnaesDecoded) && count($cnaesDecoded) > 0) {
                    $cnaes = $cnaesDecoded;
                }
            }

            $data = [
                'tenant_id'         => $_POST['tenant_id'] ?? null,
                'name'              => $_POST['name'] ?? '',
                'source'            => $_POST['source'] ?? 'google_maps',
                'product_id'        => $_POST['product_id'] ?? null,
                'city'              => $_POST['city'] ?? '',
                'state'             => $_POST['state'] ?? '',
                'keywords'          => $keywords,
                'google_place_type' => $_POST['google_place_type'] ?? '',
                'radius_meters'     => $_POST['radius_meters'] ?? 5000,
                'cnae_code'         => $_POST['cnae_code'] ?? '',
                'cnae_description'  => $_POST['cnae_description'] ?? '',
                'cnaes'             => $cnaes,
                'notes'             => $_POST['notes'] ?? '',
            ];

            $id = ProspectingService::createRecipe($data, $userId);
            $source      = $_POST['source'] ?? 'google_maps';
            $sourceParam = in_array($source, ['google_maps','minhareceita']) ? '&source=' . $source : '';
            $this->redirect('/prospecting?success=created&message=' . urlencode('Receita criada com sucesso!') . $sourceParam);
        } catch (\Exception $e) {
            error_log('[ProspectingController] Erro ao criar receita: ' . $e->getMessage());
            $this->redirect('/prospecting?error=create_failed&message=' . urlencode($e->getMessage()));
        }
    }

    /**
     * POST /prospecting/update
     */
    public function update(): void
    {
        Auth::requireInternal();

        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            $this->redirect('/prospecting?error=invalid');
            return;
        }

        try {
            $keywordsRaw = trim($_POST['keywords_raw'] ?? '');
            $keywords    = array_values(array_filter(array_map('trim', explode(',', $keywordsRaw))));

            // Parse array de CNAEs se fornecido
            $cnaes = null;
            if (!empty($_POST['cnaes'])) {
                $cnaesDecoded = json_decode($_POST['cnaes'], true);
                if (is_array($cnaesDecoded) && count($cnaesDecoded) > 0) {
                    $cnaes = $cnaesDecoded;
                }
            }

            $data = [
                'tenant_id'         => $_POST['tenant_id'] ?? null,
                'name'              => $_POST['name'] ?? '',
                'source'            => $_POST['source'] ?? 'google_maps',
                'product_id'        => $_POST['product_id'] ?? null,
                'city'              => $_POST['city'] ?? '',
                'state'             => $_POST['state'] ?? '',
                'keywords'          => $keywords,
                'google_place_type' => $_POST['google_place_type'] ?? '',
                'radius_meters'     => $_POST['radius_meters'] ?? 5000,
                'cnae_code'         => $_POST['cnae_code'] ?? '',
                'cnae_description'  => $_POST['cnae_description'] ?? '',
                'cnaes'             => $cnaes,
                'notes'             => $_POST['notes'] ?? '',
            ];

            ProspectingService::updateRecipe($id, $data);
            $source      = $_POST['source'] ?? 'google_maps';
            $sourceParam = in_array($source, ['google_maps','minhareceita']) ? '&source=' . $source : '';
            $this->redirect('/prospecting?success=updated&message=' . urlencode('Receita atualizada com sucesso!') . $sourceParam);
        } catch (\Exception $e) {
            error_log('[ProspectingController] Erro ao atualizar receita: ' . $e->getMessage());
            $this->redirect('/prospecting?error=update_failed&message=' . urlencode($e->getMessage()));
        }
    }

    /**
     * POST /prospecting/toggle-status
     */
    public function toggleStatus(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            $this->json(['success' => false, 'error' => 'ID inválido'], 400);
            return;
        }

        try {
            $newStatus = ProspectingService::toggleRecipeStatus($id);
            $this->json(['success' => true, 'status' => $newStatus]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /prospecting/delete
     */
    public function delete(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            $this->json(['success' => false, 'error' => 'ID inválido'], 400);
            return;
        }

        try {
            ProspectingService::deleteRecipe($id);
            $this->json(['success' => true]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // EXECUÇÃO DA BUSCA
    // =========================================================================

    /**
     * POST /prospecting/preview  (AJAX)
     * Prévia de resultados antes da busca completa (apenas Minha Receita)
     */
    public function preview(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $recipeId = (int) ($_POST['recipe_id'] ?? 0);

        if (!$recipeId) {
            $this->json(['success' => false, 'error' => 'ID da receita inválido'], 400);
            return;
        }

        try {
            $recipe = ProspectingService::findRecipeById($recipeId);
            $recipeSource = $recipe['source'] ?? 'google_maps';

            if ($recipeSource !== 'minhareceita') {
                $this->json(['success' => false, 'error' => 'Prévia disponível apenas para Minha Receita'], 400);
                return;
            }

            $preview = ProspectingService::previewMinhaReceita($recipeId);
            $this->json(['success' => true, 'preview' => $preview]);
        } catch (\Exception $e) {
            error_log('[ProspectingController] Erro ao gerar prévia: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /prospecting/run  (AJAX)
     * Executa a busca no Google Places para uma receita
     */
    public function run(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $recipeId   = (int) ($_POST['recipe_id'] ?? 0);
        $maxResults = min((int) ($_POST['max_results'] ?? 100), 10000);

        // Aumenta timeout baseado no volume
        // Até 1000: 5 min | 1000-5000: 10 min | 5000+: 15 min
        if ($maxResults > 5000) {
            set_time_limit(900); // 15 minutos
        } elseif ($maxResults > 1000) {
            set_time_limit(600); // 10 minutos
        } else {
            set_time_limit(300); // 5 minutos
        }

        if (!$recipeId) {
            $this->json(['success' => false, 'error' => 'ID da receita inválido'], 400);
            return;
        }

        // Verifica API key apenas para receitas Google Maps
        $recipe = ProspectingService::findRecipeById($recipeId);
        $recipeSource = $recipe['source'] ?? 'google_maps';
        if ($recipe && $recipeSource === 'google_maps' && !ProspectingService::hasApiKey()) {
            $this->json([
                'success' => false,
                'error'   => 'Chave da Google Maps API não configurada. Acesse Configurações > Integrações > Google Maps.',
            ], 400);
            return;
        }

        try {
            $result = ProspectingService::runSearch($recipeId, $maxResults);
            $this->json(['success' => true, 'result' => $result]);
        } catch (\Exception $e) {
            error_log('[ProspectingController] Erro ao executar busca: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // RESULTADOS
    // =========================================================================

    /**
     * GET /prospecting/results?recipe_id=X
     */
    public function results(): void
    {
        Auth::requireInternal();

        $recipeId = (int) ($_GET['recipe_id'] ?? 0);
        if (!$recipeId) {
            $this->redirect('/prospecting?error=invalid_recipe');
            return;
        }

        $recipe = ProspectingService::findRecipeById($recipeId);
        if (!$recipe) {
            $this->redirect('/prospecting?error=recipe_not_found');
            return;
        }

        $filters = [
            'status'        => $_GET['status'] ?? null,
            'search'        => $_GET['search'] ?? null,
            'situacao'      => $_GET['situacao'] ?? null,
            'porte'         => $_GET['porte'] ?? null,
            'mei'           => $_GET['mei'] ?? null,
            'simples'       => $_GET['simples'] ?? null,
            'matriz_filial' => $_GET['matriz_filial'] ?? null,
        ];

        $limit   = 100;
        $offset  = (int) ($_GET['page'] ?? 0) * $limit;
        $results = ProspectingService::listResults($recipeId, $filters, $limit, $offset);
        $total   = ProspectingService::countResults($recipeId, $filters);
        $hasKey  = ProspectingService::hasApiKey();

        $db = DB::getConnection();
        $users    = $db->query("SELECT id, name FROM users WHERE is_internal = 1 ORDER BY name ASC")->fetchAll() ?: [];
        $products = OpportunityProductService::listActive();

        $this->view('prospecting.results', [
            'recipe'   => $recipe,
            'results'  => $results,
            'total'    => $total,
            'filters'  => $filters,
            'hasKey'   => $hasKey,
            'page'     => (int) ($_GET['page'] ?? 0),
            'limit'    => $limit,
            'users'    => $users,
            'products' => $products,
        ]);
    }

    /**
     * POST /prospecting/enrich-google-maps  (AJAX)
     * Busca dados do Google Maps para enriquecer um resultado da Minha Receita
     */
    public function enrichWithGoogleMaps(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $resultId = (int) ($_POST['result_id'] ?? 0);
        if (!$resultId) {
            $this->json(['success' => false, 'error' => 'ID inválido'], 400);
            return;
        }

        try {
            $enrichmentData = ProspectingService::searchGoogleMapsForEnrichment($resultId);
            $this->json(['success' => true, 'data' => $enrichmentData]);
        } catch (\Exception $e) {
            error_log('[ProspectingController] Erro ao enriquecer com Google Maps: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /prospecting/apply-google-enrichment  (AJAX)
     * Aplica enriquecimento aprovado pelo usuário
     */
    public function applyGoogleEnrichment(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $resultId = (int) ($_POST['result_id'] ?? 0);
        $googleDataJson = $_POST['google_data'] ?? null;

        if (!$resultId || !$googleDataJson) {
            $this->json(['success' => false, 'error' => 'Dados inválidos'], 400);
            return;
        }

        // Decodifica JSON
        $googleData = json_decode($googleDataJson, true);
        if (!$googleData) {
            $this->json(['success' => false, 'error' => 'Formato de dados inválido'], 400);
            return;
        }

        try {
            ProspectingService::applyGoogleEnrichment($resultId, $googleData);
            $this->json(['success' => true, 'message' => 'Dados atualizados com sucesso!']);
        } catch (\Exception $e) {
            error_log('[ProspectingController] Erro ao aplicar enriquecimento: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /prospecting/update-result-status  (AJAX)
     */
    public function updateResultStatus(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $id     = (int) ($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $notes  = trim($_POST['notes'] ?? '') ?: null;

        $validStatuses = ['new', 'contacted', 'qualified', 'discarded'];
        if (!$id || !in_array($status, $validStatuses)) {
            $this->json(['success' => false, 'error' => 'Parâmetros inválidos'], 400);
            return;
        }

        try {
            $userId = Auth::user()['id'] ?? null;
            ProspectingService::updateResultStatus($id, $status, $notes, $userId);
            $this->json(['success' => true]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /prospecting/convert-to-lead  (AJAX)
     */
    public function convertToLead(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $resultId = (int) ($_POST['result_id'] ?? 0);
        if (!$resultId) {
            $this->json(['success' => false, 'error' => 'ID inválido'], 400);
            return;
        }

        try {
            $userId = Auth::user()['id'] ?? 0;
            $leadId = ProspectingService::convertToLead($resultId, $userId);

            // Busca dados completos do resultado + receita para montar nome da oportunidade
            $db     = \PixelHub\Core\DB::getConnection();
            $stmt   = $db->prepare("
                SELECT pr.*, rec.keywords, rec.product_id AS recipe_product_id,
                       rec.city AS recipe_city, rec.state AS recipe_state, rec.name AS recipe_name
                FROM prospecting_results pr
                LEFT JOIN prospecting_recipes rec ON rec.id = pr.recipe_id
                WHERE pr.id = ?
                LIMIT 1
            ");
            $stmt->execute([$resultId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Monta nome: [Empresa] — [Cidade/UF] — [Objetivo/Canal]
            $empresa  = trim($result['name'] ?? '');
            $cidade   = trim($result['city'] ?? $result['recipe_city'] ?? '');
            $uf       = trim($result['state'] ?? $result['recipe_state'] ?? '');
            $localStr = $cidade . ($uf ? '/' . strtoupper($uf) : '');

            // Objetivo/Canal: primeira keyword da receita ou nome da receita
            $keywords = $result['keywords'] ?? '[]';
            if (is_string($keywords)) {
                $keywords = json_decode($keywords, true) ?: [];
            }
            $objetivo = !empty($keywords) ? $keywords[0] : ($result['recipe_name'] ?? 'Prospecção');

            $oppName   = trim($empresa . ($localStr ? ' — ' . $localStr : '') . ($objetivo ? ' — ' . $objetivo : ''));
            $productId = !empty($result['recipe_product_id']) ? (int) $result['recipe_product_id'] : null;

            // Determina origin com base na fonte da receita
            $recipeSourceStmt = $db->prepare("SELECT source FROM prospecting_recipes WHERE id = ? LIMIT 1");
            $recipeSourceStmt->execute([$result['recipe_id'] ?? 0]);
            $recipeSource = $recipeSourceStmt->fetchColumn() ?: 'google_maps';
            if ($recipeSource === 'minhareceita') {
                $oppOrigin = 'prospecting_minhareceita';
            } else {
                $oppOrigin = 'prospecting_google_maps';
            }

            // Busca código de rastreamento ativo para prospecção ativa
            $tcStmt = $db->prepare("
                SELECT code FROM tracking_codes
                WHERE channel = ? AND is_active = 1
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $tcStmt->execute([$oppOrigin]);
            $prospectingTrackingCode = $tcStmt->fetchColumn() ?: null;

            // Cria oportunidade automaticamente no estágio "novo"
            $oppId = \PixelHub\Services\OpportunityService::create([
                'name'           => $oppName,
                'lead_id'        => $leadId,
                'tenant_id'      => $result['tenant_id'] ?? null,
                'product_id'     => $productId,
                'stage'          => 'new',
                'origin'         => $oppOrigin,
                'tracking_code'  => $prospectingTrackingCode,
                'tracking_source'=> $prospectingTrackingCode ? 'prospecting' : null,
                'tracking_auto_detected' => $prospectingTrackingCode ? true : false,
            ], $userId);

            // Atualiza opportunity_id no resultado de prospecção
            $db->prepare("UPDATE prospecting_results SET opportunity_id = ? WHERE id = ?")
               ->execute([$oppId, $resultId]);

            $this->json([
                'success'    => true,
                'lead_id'    => $leadId,
                'opp_id'     => $oppId,
                'lead_url'   => pixelhub_url('/leads/edit?id=' . $leadId),
                'opp_url'    => pixelhub_url('/opportunities/view?id=' . $oppId),
                'message'    => 'Lead e oportunidade criados com sucesso!',
            ]);
        } catch (\Exception $e) {
            error_log('[ProspectingController] Erro ao converter para lead: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
