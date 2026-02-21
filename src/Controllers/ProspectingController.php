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
     * GET /prospecting
     */
    public function index(): void
    {
        Auth::requireInternal();

        // Filtro de conta: null = agência própria, 0 = todas, N = tenant específico
        $tenantFilter = isset($_GET['tenant_id'])
            ? ($_GET['tenant_id'] === 'own' ? null : (int) $_GET['tenant_id'])
            : 0;

        $recipes  = ProspectingService::listRecipes($tenantFilter);
        $hasKey   = ProspectingService::hasApiKey();
        $products = OpportunityProductService::listActive();
        $tenants  = ProspectingService::listTenants();

        $this->view('prospecting.recipes', [
            'recipes'      => $recipes,
            'hasKey'       => $hasKey,
            'products'     => $products,
            'tenants'      => $tenants,
            'tenantFilter' => $tenantFilter,
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
                'product_id'        => $_POST['product_id'] ?? null,
                'city'              => $_POST['city'] ?? '',
                'state'             => $_POST['state'] ?? '',
                'keywords'          => $keywords,
                'google_place_type' => $_POST['google_place_type'] ?? '',
                'radius_meters'     => $_POST['radius_meters'] ?? 5000,
                'notes'             => $_POST['notes'] ?? '',
            ];

            $id = ProspectingService::createRecipe($data, $userId);
            // Redireciona mantendo o filtro de tenant
            $tenantParam = !empty($_POST['tenant_id']) ? '&tenant_id=' . (int)$_POST['tenant_id'] : '&tenant_id=own';
            $this->redirect('/prospecting?success=created&message=' . urlencode('Receita criada com sucesso!') . $tenantParam);
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
                'product_id'        => $_POST['product_id'] ?? null,
                'city'              => $_POST['city'] ?? '',
                'state'             => $_POST['state'] ?? '',
                'keywords'          => $keywords,
                'google_place_type' => $_POST['google_place_type'] ?? '',
                'radius_meters'     => $_POST['radius_meters'] ?? 5000,
                'notes'             => $_POST['notes'] ?? '',
            ];

            ProspectingService::updateRecipe($id, $data);
            $tenantParam = !empty($_POST['tenant_id']) ? '&tenant_id=' . (int)$_POST['tenant_id'] : '&tenant_id=own';
            $this->redirect('/prospecting?success=updated&message=' . urlencode('Receita atualizada com sucesso!') . $tenantParam);
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

        if (!ProspectingService::hasApiKey()) {
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

            // Cria oportunidade vinculada se dados do modal foram enviados
            $oppName    = trim($_POST['opp_name'] ?? '');
            $productId  = !empty($_POST['product_id']) ? (int) $_POST['product_id'] : null;
            $value      = !empty($_POST['estimated_value']) ? (float) str_replace(['.', ','], ['', '.'], $_POST['estimated_value']) : null;
            $responsible = !empty($_POST['responsible_user_id']) ? (int) $_POST['responsible_user_id'] : null;
            $notes      = trim($_POST['notes'] ?? '');

            $oppId = null;
            if ($oppName) {
                $result = ProspectingService::findResultById($resultId);
                $oppId = \PixelHub\Services\OpportunityService::create([
                    'name'                => $oppName,
                    'lead_id'             => $leadId,
                    'tenant_id'           => $result['tenant_id'] ?? null,
                    'product_id'          => $productId,
                    'estimated_value'     => $value,
                    'responsible_user_id' => $responsible,
                    'notes'               => $notes,
                    'stage'               => 'new',
                    'origin'              => 'prospecting_google_maps',
                ], $userId);
            }

            $this->json([
                'success'    => true,
                'lead_id'    => $leadId,
                'opp_id'     => $oppId,
                'lead_url'   => pixelhub_url('/leads/edit?id=' . $leadId),
                'opp_url'    => $oppId ? pixelhub_url('/opportunities/view?id=' . $oppId) : null,
                'message'    => 'Lead e oportunidade criados com sucesso!',
            ]);
        } catch (\Exception $e) {
            error_log('[ProspectingController] Erro ao converter para lead: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
