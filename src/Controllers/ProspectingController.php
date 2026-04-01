<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Services\ProspectingService;
use PixelHub\Services\OpportunityProductService;
use PixelHub\Services\GooglePlacesClient;
use PixelHub\Services\ApifyClient;
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
    // CONFIGURAÇÕES APIFY (Configurações > Integrações > Apify)
    // =========================================================================

    /**
     * GET /settings/apify
     */
    public function settingsApify(): void
    {
        Auth::requireInternal();

        $hasKey    = ProspectingService::hasApifyApiKey();
        $maskedKey = ProspectingService::getMaskedApifyApiKey();

        $this->view('settings.apify', [
            'hasKey'    => $hasKey,
            'maskedKey' => $maskedKey,
        ]);
    }

    /**
     * POST /settings/apify/save
     */
    public function settingsApifySave(): void
    {
        Auth::requireInternal();

        $apiKey = trim($_POST['api_key'] ?? '');

        if (empty($apiKey)) {
            $this->redirect('/settings/apify?error=empty_key&message=' . urlencode('Informe a chave de API Apify.'));
            return;
        }

        try {
            $userId = Auth::user()['id'] ?? 0;
            ProspectingService::saveApifyApiKey($apiKey, $userId);

            $client = new ApifyClient();
            $test   = $client->testApiKey($apiKey);

            if ($test['success']) {
                $this->redirect('/settings/apify?success=saved&message=' . urlencode('Chave Apify salva e validada com sucesso!'));
            } else {
                $this->redirect('/settings/apify?warning=saved_not_validated&message=' . urlencode('Chave salva, mas não foi possível validar: ' . $test['message']));
            }
        } catch (\Exception $e) {
            $this->redirect('/settings/apify?error=save_failed&message=' . urlencode($e->getMessage()));
        }
    }

    /**
     * POST /settings/apify/test  (AJAX)
     */
    public function settingsApifyTest(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        try {
            $apiKey = ApifyClient::resolveApiKey();
            $client = new ApifyClient();
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
        $sourceFilter = (isset($_GET['source']) && in_array($_GET['source'], ['google_maps', 'minhareceita', 'instagram']))
            ? $_GET['source']
            : 'google_maps';

        $recipes      = ProspectingService::listRecipes($tenantFilter, $sourceFilter);
        $hasKey       = ProspectingService::hasApiKey();
        $hasApifyKey  = ProspectingService::hasApifyApiKey();
        $products     = OpportunityProductService::listActive();
        $tenants      = ProspectingService::listTenants($sourceFilter);

        $this->view('prospecting.recipes', [
            'recipes'      => $recipes,
            'hasKey'       => $hasKey,
            'hasApifyKey'  => $hasApifyKey,
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
            $sourceParam = in_array($source, ['google_maps','minhareceita','instagram']) ? '&source=' . $source : '';
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
            $sourceParam = in_array($source, ['google_maps','minhareceita','instagram']) ? '&source=' . $source : '';
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

        // Verifica API key conforme a fonte da receita
        $recipe = ProspectingService::findRecipeById($recipeId);
        $recipeSource = $recipe['source'] ?? 'google_maps';
        if ($recipe && $recipeSource === 'google_maps' && !ProspectingService::hasApiKey()) {
            $this->json([
                'success' => false,
                'error'   => 'Chave da Google Maps API não configurada. Acesse Configurações > Integrações > Google Maps.',
            ], 400);
            return;
        }
        if ($recipe && $recipeSource === 'instagram' && !ProspectingService::hasApifyApiKey()) {
            $this->json([
                'success' => false,
                'error'   => 'Chave da API Apify não configurada. Acesse Configurações > Integrações > Apify.',
            ], 400);
            return;
        }

        try {
            $result = ProspectingService::runSearch($recipeId, $maxResults);
            $this->json(['success' => true, 'result' => $result]);
        } catch (\Throwable $e) {
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
            'status'           => $_GET['status'] ?? null,
            'search'           => $_GET['search'] ?? null,
            'situacao'         => $_GET['situacao'] ?? null,
            'porte'            => $_GET['porte'] ?? null,
            'mei'              => $_GET['mei'] ?? null,
            'simples'          => $_GET['simples'] ?? null,
            'matriz_filial'    => $_GET['matriz_filial'] ?? null,
            'tem_contato'      => $_GET['tem_contato'] ?? null,
            'google_enrichment'=> $_GET['google_enrichment'] ?? null,
            'wa_sent'          => $_GET['wa_sent'] ?? null,
        ];

        $limit   = 100;
        $offset  = (int) ($_GET['page'] ?? 0) * $limit;
        $results = ProspectingService::listResults($recipeId, $filters, $limit, $offset);
        $total   = ProspectingService::countResults($recipeId, $filters);
        $hasKey      = ProspectingService::hasApiKey();
        $hasApifyKey = ApifyClient::hasApiKey();

        $db = DB::getConnection();
        $users    = $db->query("SELECT id, name FROM users WHERE is_internal = 1 ORDER BY name ASC")->fetchAll() ?: [];
        $products = OpportunityProductService::listActive();

        $this->view('prospecting.results', [
            'recipe'      => $recipe,
            'results'     => $results,
            'total'       => $total,
            'filters'     => $filters,
            'hasKey'      => $hasKey,
            'hasApifyKey' => $hasApifyKey,
            'page'        => (int) ($_GET['page'] ?? 0),
            'limit'       => $limit,
            'users'       => $users,
            'products'    => $products,
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
     * POST /prospecting/enrich-cnpjws  (AJAX)
     * Enriquece dados de contato via CNPJ.ws
     */
    public function enrichWithCnpjWs(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $resultId = (int) ($_POST['result_id'] ?? 0);
        
        if (!$resultId) {
            $this->json(['success' => false, 'error' => 'ID inválido'], 400);
            return;
        }

        try {
            $result = ProspectingService::enrichWithCnpjWs($resultId);
            $this->json($result);
        } catch (\Exception $e) {
            error_log('[ProspectingController] Erro ao enriquecer com CNPJ.ws: ' . $e->getMessage());
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

        $validStatuses = ['new', 'qualified', 'discarded'];
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
     * POST /prospecting/mark-wa-sent  (AJAX)
     * Marca resultado como mensagem WA enviada e faz upgrade de status para 'qualified' se ainda for 'new'
     */
    public function markWaSent(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $id = (int) ($_POST['result_id'] ?? 0);
        if (!$id) {
            $this->json(['success' => false, 'error' => 'ID inválido'], 400);
            return;
        }

        try {
            $userId = Auth::user()['id'] ?? null;
            ProspectingService::markWaSent($id, $userId);
            $this->json(['success' => true]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /prospecting/enrich-apify-phone  (AJAX)
     * Busca telefone business do perfil Instagram via Apify (Fase 2)
     */
    public function enrichWithApifyPhone(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $resultId = (int) ($_POST['result_id'] ?? 0);
        if (!$resultId) {
            $this->json(['success' => false, 'error' => 'ID inválido'], 400);
            return;
        }

        try {
            $result = ProspectingService::enrichWithApifyPhone($resultId);
            $this->json($result);
        } catch (\Exception $e) {
            error_log('[ProspectingController] Erro ao enriquecer phone Apify: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /prospecting/save-phone  (AJAX)
     * Salva telefone inserido manualmente para um resultado
     */
    public function savePhone(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $resultId = (int) ($_POST['result_id'] ?? 0);
        $phone    = trim($_POST['phone'] ?? '');

        if (!$resultId || empty($phone)) {
            $this->json(['success' => false, 'error' => 'Parâmetros inválidos'], 400);
            return;
        }

        try {
            $result = ProspectingService::savePhone($resultId, $phone);
            $this->json($result);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /prospecting/whatsapp-sessions  (AJAX)
     * Retorna sessões WhatsApp disponíveis para o modal de envio
     */
    public function whatsappSessions(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        try {
            $db   = \PixelHub\Core\DB::getConnection();
            $col  = $db->query("SHOW COLUMNS FROM tenant_message_channels LIKE 'session_id'")->fetch() ? 'session_id' : 'channel_id';
            $stmt = $db->query("SELECT DISTINCT {$col} as session_id, channel_id FROM tenant_message_channels WHERE provider = 'wpp_gateway' AND is_enabled = 1 ORDER BY channel_id");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $sessions = [];
            foreach ($rows as $r) {
                $id = $r['session_id'] ?: $r['channel_id'];
                if ($id) $sessions[] = ['id' => $id, 'name' => $r['channel_id']];
            }
            $this->json(['success' => true, 'sessions' => $sessions]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'sessions' => [], 'error' => $e->getMessage()]);
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

    // =========================================================================
    // SDR — Sales Development Representative
    // =========================================================================

    /**
     * POST /prospecting/sdr/dispatch  (AJAX)
     * Enfileira todos os leads de uma receita para disparo SDR no dia atual.
     */
    public function sdrDispatch(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $recipeId  = (int) ($_POST['recipe_id'] ?? 0);
        $maxPerDay = min((int) ($_POST['max_per_day'] ?? 80), 120);

        if (!$recipeId) {
            $this->json(['success' => false, 'error' => 'ID da receita inválido'], 400);
            return;
        }

        $sessionName = trim($_POST['session_name'] ?? '');

        try {
            $stats = \PixelHub\Services\SdrDispatchService::planDay($recipeId, $maxPerDay, $sessionName);
            $this->json([
                'success'          => true,
                'enqueued'         => $stats['enqueued'],
                'skipped_no_phone' => $stats['skipped_no_phone'],
                'message'          => "Enfileirados: {$stats['enqueued']} leads para disparo SDR.",
            ]);
        } catch (\Exception $e) {
            error_log('[ProspectingController] sdrDispatch error: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /prospecting/sdr/dispatch-selection  (AJAX)
     * Enfileira apenas os result_ids selecionados pelo usuário.
     */
    public function sdrDispatchSelection(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $resultIds = array_map('intval', (array) ($_POST['result_ids'] ?? []));
        $resultIds = array_filter($resultIds, fn($id) => $id > 0);
        $resultIds = array_values($resultIds);

        if (empty($resultIds)) {
            $this->json(['success' => false, 'error' => 'Nenhum contato selecionado'], 400);
            return;
        }

        $sessionName = trim($_POST['session_name'] ?? '');

        try {
            $stats = \PixelHub\Services\SdrDispatchService::planSelection($resultIds, $sessionName);
            $this->json([
                'success'          => true,
                'enqueued'         => $stats['enqueued'],
                'skipped_no_phone' => $stats['skipped_no_phone'],
                'message'          => "Enfileirados: {$stats['enqueued']} de " . count($resultIds) . " selecionados.",
            ]);
        } catch (\Exception $e) {
            error_log('[ProspectingController] sdrDispatchSelection error: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /prospecting/sdr/sessions  (AJAX)
     * Lista sessões Whapi disponíveis para uso no SDR.
     */
    public function sdrSessions(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $configs = \PixelHub\Services\WhatsAppProviderFactory::getAllWhapiConfigs();
        $sessions = [];
        foreach ($configs as $c) {
            if (empty($c['session_name']) || !$c['is_active'] || !$c['has_token']) continue;
            $meta = json_decode($c['config_metadata'] ?? '{}', true) ?: [];
            $sessions[] = [
                'session_name' => $c['session_name'],
                'display_name' => $meta['display_name'] ?? ucwords(str_replace(['_', '-'], ' ', $c['session_name'])),
                'is_active'    => true,
                'has_token'    => true,
            ];
        }

        $this->json(['success' => true, 'sessions' => $sessions]);
    }

    /**
     * GET /prospecting/sdr/queue  (AJAX)
     * Lista jobs enfileirados para uma receita (acompanhamento).
     */
    public function sdrQueue(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $recipeId = (int) ($_GET['recipe_id'] ?? 0);
        if (!$recipeId) {
            $this->json(['success' => false, 'error' => 'ID da receita inválido'], 400);
            return;
        }

        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("
                SELECT dq.id, dq.result_id, dq.session_name, dq.phone, dq.establishment_name,
                       dq.message, dq.scheduled_at, dq.status, dq.created_at,
                       dq.phone_validation_status, dq.error,
                       pr.name as result_name
                FROM sdr_dispatch_queue dq
                LEFT JOIN prospecting_results pr ON pr.id = dq.result_id
                WHERE dq.recipe_id = ?
                ORDER BY dq.scheduled_at ASC, dq.created_at ASC
            ");
            $stmt->execute([$recipeId]);
            $jobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Formata horários e adiciona status legível
            foreach ($jobs as &$j) {
                $j['scheduled_at_br'] = (new \DateTime($j['scheduled_at']))->format('d/m/Y H:i');
                $isInvalidPhone = $j['status'] === 'failed'
                    && ($j['phone_validation_status'] === 'invalid'
                        || str_contains($j['error'] ?? '', 'Sem WhatsApp')
                        || str_contains($j['error'] ?? '', 'sem WhatsApp'));
                $j['no_whatsapp'] = $isInvalidPhone;
                $j['status_label'] = match (true) {
                    $isInvalidPhone => 'Sem WhatsApp',
                    $j['status'] === 'queued' => 'Aguardando',
                    $j['status'] === 'processing' => 'Enviando',
                    $j['status'] === 'sent' => 'Enviado',
                    $j['status'] === 'failed' => 'Falhou',
                    default => $j['status']
                };
            }

            $this->json(['success' => true, 'jobs' => $jobs]);
        } catch (\Exception $e) {
            error_log('[ProspectingController] sdrQueue error: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /prospecting/sdr/cancel  (AJAX)
     * Cancela um job específico da fila (se ainda não enviado).
     */
    public function sdrCancel(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $jobId = (int) ($_POST['job_id'] ?? 0);
        if (!$jobId) {
            $this->json(['success' => false, 'error' => 'ID do job inválido'], 400);
            return;
        }

        try {
            $db = DB::getConnection();
            // Só permite cancelar se status = queued
            $stmt = $db->prepare("UPDATE sdr_dispatch_queue SET status='cancelled' WHERE id=? AND status='queued'");
            $affected = $stmt->execute([$jobId]);

            if ($affected) {
                $this->json(['success' => true, 'message' => 'Envio cancelado.']);
            } else {
                $this->json(['success' => false, 'error' => 'Job não encontrado ou já enviado.'], 404);
            }
        } catch (\Exception $e) {
            error_log('[ProspectingController] sdrCancel error: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /prospecting/sdr/takeover  (AJAX)
     * Ativa ou desativa modo humano para uma conversa SDR.
     */
    public function sdrTakeover(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $sdrConvId = (int) ($_POST['sdr_conv_id'] ?? 0);
        $mode      = (int) ($_POST['human_mode'] ?? 1); // 1=assumir, 0=devolver

        if (!$sdrConvId) {
            $this->json(['success' => false, 'error' => 'ID da conversa SDR inválido'], 400);
            return;
        }

        try {
            $userId = Auth::user()['id'] ?? null;
            \PixelHub\Services\SdrDispatchService::setHumanMode($sdrConvId, (bool) $mode, $userId);
            $label = $mode ? 'Conversa assumida por humano. IA pausada.' : 'Conversa devolvida à IA.';
            $this->json(['success' => true, 'message' => $label]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /prospecting/sdr/status  (AJAX)
     * Retorna estatísticas diárias do SDR.
     */
    public function sdrStatus(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        try {
            $stats = \PixelHub\Services\SdrDispatchService::getDailyStats();
            $this->json(['success' => true, 'stats' => $stats]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /prospecting/sdr/conversations  (AJAX)
     * Lista conversas SDR ativas para exibição na tela de resultados.
     */
    public function sdrConversations(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $recipeId = (int) ($_GET['recipe_id'] ?? 0);

        try {
            $db = \PixelHub\Core\DB::getConnection();
            $where = $recipeId ? 'AND dq.recipe_id = ' . $recipeId : '';
            $stmt  = $db->query("
                SELECT sc.id, sc.result_id, sc.phone, sc.establishment_name,
                       sc.stage, sc.human_mode, sc.scheduled_at,
                       sc.last_inbound_at, sc.last_ai_reply_at,
                       dq.status AS queue_status, dq.sent_at, dq.scheduled_at AS dispatch_at
                FROM sdr_conversations sc
                LEFT JOIN sdr_dispatch_queue dq ON dq.result_id = sc.result_id
                WHERE 1=1 {$where}
                ORDER BY sc.updated_at DESC
                LIMIT 200
            ");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->json(['success' => true, 'conversations' => $rows]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /prospecting/poll-status
     * Retorna status atual e whatsapp_sent_at para uma lista de IDs de resultados.
     * Usado pelo polling JS da página de resultados para atualizar sem recarregar.
     */
    public function pollStatus(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $ids = array_filter(array_map('intval', (array)($_GET['ids'] ?? [])));
            if (empty($ids)) {
                $this->json(['success' => true, 'results' => []]);
                return;
            }

            $db = DB::getConnection();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("
                SELECT id, status, whatsapp_sent_at
                FROM prospecting_results
                WHERE id IN ({$placeholders})
            ");
            $stmt->execute($ids);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $results = [];
            foreach ($rows as $row) {
                $results[(string)$row['id']] = [
                    'status'          => $row['status'],
                    'whatsapp_sent_at' => $row['whatsapp_sent_at'],
                ];
            }

            $this->json(['success' => true, 'results' => $results]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'results' => []], 500);
        }
    }
}
