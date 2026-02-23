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
    // CONFIGURAÇÕES CNPJ.ws (Configurações > Integrações)
    // =========================================================================

    /**
     * GET /settings/cnpjws
     */
    public function settingsCnpjWsIndex(): void
    {
        Auth::requireInternal();

        $hasKey    = ProspectingService::hasCnpjWsApiKey();
        $maskedKey = ProspectingService::getMaskedCnpjWsApiKey();

        $this->view('settings.cnpjws', [
            'hasKey'    => $hasKey,
            'maskedKey' => $maskedKey,
        ]);
    }

    /**
     * POST /settings/cnpjws/save
     */
    public function settingsCnpjWsSave(): void
    {
        Auth::requireInternal();

        $apiKey = trim($_POST['api_key'] ?? '');

        if (empty($apiKey)) {
            $this->redirect('/settings/cnpjws?error=empty_key&message=' . urlencode('Informe a chave de API.'));
            return;
        }

        if (strlen($apiKey) < 10) {
            $this->redirect('/settings/cnpjws?error=invalid_key&message=' . urlencode('Chave de API inválida.'));
            return;
        }

        try {
            $userId = Auth::user()['id'] ?? 0;
            ProspectingService::saveCnpjWsApiKey($apiKey, $userId);
            $this->redirect('/settings/cnpjws?success=saved&message=' . urlencode('Chave salva com sucesso! Clique em "Testar Conexão" para validar.'));
        } catch (\Exception $e) {
            error_log('[ProspectingController] Erro ao salvar chave CNPJ.ws: ' . $e->getMessage());
            $this->redirect('/settings/cnpjws?error=save_failed&message=' . urlencode($e->getMessage()));
        }
    }

    /**
     * POST /settings/cnpjws/test  (AJAX)
     */
    public function settingsCnpjWsTest(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        try {
            $apiKey = ProspectingService::getCnpjWsApiKey();
            if (empty($apiKey)) {
                $this->json(['success' => false, 'message' => 'Nenhuma chave configurada.']);
                return;
            }

            $client = new \PixelHub\Services\CnpjWsClient();
            $result = $client->testApiKey($apiKey);
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
        $sourceFilter = (isset($_GET['source']) && in_array($_GET['source'], ['google_maps', 'cnpjws', 'minhareceita']))
            ? $_GET['source']
            : 'google_maps';

        $recipes         = ProspectingService::listRecipes($tenantFilter, $sourceFilter);
        $hasKey          = ProspectingService::hasApiKey();
        $hasCnpjWsKey    = ProspectingService::hasCnpjWsApiKey();
        $products        = OpportunityProductService::listActive();
        $tenants         = ProspectingService::listTenants();

        $this->view('prospecting.recipes', [
            'recipes'      => $recipes,
            'hasKey'       => $hasKey,
            'hasCnpjWsKey' => $hasCnpjWsKey,
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
                'notes'             => $_POST['notes'] ?? '',
            ];

            $id = ProspectingService::createRecipe($data, $userId);
            $tenantParam = !empty($_POST['tenant_id']) ? '&tenant_id=' . (int)$_POST['tenant_id'] : '&tenant_id=own';
            $source      = $_POST['source'] ?? 'google_maps';
            $sourceParam = in_array($source, ['google_maps','cnpjws','minhareceita']) ? '&source=' . $source : '';
            $this->redirect('/prospecting?success=created&message=' . urlencode('Receita criada com sucesso!') . $tenantParam . $sourceParam);
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
                'notes'             => $_POST['notes'] ?? '',
            ];

            ProspectingService::updateRecipe($id, $data);
            $tenantParam = !empty($_POST['tenant_id']) ? '&tenant_id=' . (int)$_POST['tenant_id'] : '&tenant_id=own';
            $source      = $_POST['source'] ?? 'google_maps';
            $sourceParam = in_array($source, ['google_maps','cnpjws','minhareceita']) ? '&source=' . $source : '';
            $this->redirect('/prospecting?success=updated&message=' . urlencode('Receita atualizada com sucesso!') . $tenantParam . $sourceParam);
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
     * POST /prospecting/run  (AJAX)
     * Executa a busca no Google Places para uma receita
     */
    public function run(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $recipeId   = (int) ($_POST['recipe_id'] ?? 0);
        $maxResults = min((int) ($_POST['max_results'] ?? 20), 60);

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
            'status' => $_GET['status'] ?? null,
            'search' => $_GET['search'] ?? null,
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
            if ($recipeSource === 'cnpjws') {
                $oppOrigin = 'prospecting_cnpjws';
            } elseif ($recipeSource === 'minhareceita') {
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
