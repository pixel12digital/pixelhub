<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Services\OpportunityService;
use PixelHub\Services\LeadService;
use PixelHub\Services\PhoneNormalizer;

/**
 * Controller para o módulo CRM / Comercial — Oportunidades
 */
class OpportunitiesController extends Controller
{
    /**
     * Lista de oportunidades
     * GET /opportunities
     */
    public function index(): void
    {
        Auth::requireInternal();

        $filters = [
            'status' => $_GET['status'] ?? null,
            'stage' => $_GET['stage'] ?? null,
            'responsible_user_id' => !empty($_GET['responsible']) ? (int) $_GET['responsible'] : null,
            'search' => $_GET['search'] ?? null,
        ];

        $opportunities = OpportunityService::list($filters);
        $counts = OpportunityService::countByStatus();

        // Busca usuários para filtro de responsável
        $db = DB::getConnection();
        $users = $db->query("SELECT id, name FROM users WHERE is_internal = 1 ORDER BY name ASC")->fetchAll() ?: [];

        $this->view('opportunities.index', [
            'opportunities' => $opportunities,
            'counts' => $counts,
            'filters' => $filters,
            'users' => $users,
            'stages' => OpportunityService::STAGES,
        ]);
    }

    /**
     * Ficha da oportunidade
     * GET /opportunities/view?id=X
     */
    public function show(): void
    {
        Auth::requireInternal();

        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            $this->redirect('/opportunities');
            return;
        }

        $opportunity = OpportunityService::findById($id);
        if (!$opportunity) {
            $this->redirect('/opportunities?error=not_found');
            return;
        }

        $history = OpportunityService::getHistory($id);

        // Busca usuários para select de responsável
        $db = DB::getConnection();
        $users = $db->query("SELECT id, name FROM users WHERE is_internal = 1 ORDER BY name ASC")->fetchAll() ?: [];

        // Busca serviços para select (opcional)
        $services = [];
        try {
            $services = $db->query("SELECT id, name FROM services WHERE is_active = 1 ORDER BY name ASC")->fetchAll() ?: [];
        } catch (\Exception $e) {
            // Tabela pode não existir
        }

        $this->view('opportunities.view', [
            'opportunity' => $opportunity,
            'history' => $history,
            'users' => $users,
            'services' => $services,
            'stages' => OpportunityService::STAGES,
        ]);
    }

    /**
     * Abre a oportunidade mais recente vinculada a um lead
     * GET /opportunities/view-by-lead?lead_id=X
     */
    public function viewByLead(): void
    {
        Auth::requireInternal();

        $leadId = (int) ($_GET['lead_id'] ?? 0);
        if (!$leadId) {
            $this->redirect('/opportunities?error=invalid_lead');
            return;
        }

        $db = DB::getConnection();

        try {
            $stmt = $db->prepare("SELECT id FROM opportunities WHERE lead_id = ? ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT 1");
            $stmt->execute([$leadId]);
            $row = $stmt->fetch();
        } catch (\Exception $e) {
            error_log('[Opportunities] Erro ao buscar oportunidade por lead_id=' . $leadId . ': ' . $e->getMessage());
            $this->redirect('/opportunities?error=database_error');
            return;
        }

        if (empty($row['id'])) {
            $this->redirect('/opportunities?error=no_opportunity_for_lead&lead_id=' . $leadId);
            return;
        }

        $this->redirect('/opportunities/view?id=' . (int) $row['id']);
    }

    /**
     * Salva nova oportunidade (form submit)
     * POST /opportunities/store
     */
    public function store(): void
    {
        Auth::requireInternal();
        $user = Auth::user();

        try {
            $id = OpportunityService::create($_POST, $user['id'] ?? null);
            $this->redirect('/opportunities/view?id=' . $id . '&success=created');
        } catch (\InvalidArgumentException $e) {
            $this->redirect('/opportunities?error=' . urlencode($e->getMessage()));
        } catch (\Exception $e) {
            error_log("[Opportunities] Erro ao criar: " . $e->getMessage());
            $this->redirect('/opportunities?error=database_error');
        }
    }

    /**
     * Atualiza oportunidade (form submit)
     * POST /opportunities/update
     */
    public function update(): void
    {
        Auth::requireInternal();
        $user = Auth::user();

        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            $this->redirect('/opportunities');
            return;
        }

        try {
            OpportunityService::update($id, $_POST, $user['id'] ?? null);
            $this->redirect('/opportunities/view?id=' . $id . '&success=updated');
        } catch (\Exception $e) {
            error_log("[Opportunities] Erro ao atualizar #{$id}: " . $e->getMessage());
            $this->redirect('/opportunities/view?id=' . $id . '&error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Muda etapa (AJAX)
     * POST /opportunities/change-stage
     */
    public function changeStage(): void
    {
        Auth::requireInternal();
        $user = Auth::user();

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $id = (int) ($input['id'] ?? 0);
        $stage = $input['stage'] ?? '';

        if (!$id || !$stage) {
            $this->json(['success' => false, 'error' => 'ID e etapa são obrigatórios'], 400);
            return;
        }

        try {
            $result = OpportunityService::changeStage($id, $stage, $user['id'] ?? null);
            
            if ($stage === 'won') {
                $opp = OpportunityService::findById($id);
                $this->json([
                    'success' => true,
                    'message' => 'Oportunidade marcada como ganha!',
                    'service_order_id' => $opp['service_order_id'] ?? null,
                ]);
            } else {
                $this->json(['success' => $result]);
            }
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Marca como perdida (AJAX)
     * POST /opportunities/mark-lost
     */
    public function markLost(): void
    {
        Auth::requireInternal();
        $user = Auth::user();

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $id = (int) ($input['id'] ?? 0);
        $reason = $input['reason'] ?? null;

        if (!$id) {
            $this->json(['success' => false, 'error' => 'ID é obrigatório'], 400);
            return;
        }

        $result = OpportunityService::markAsLost($id, $reason, $user['id'] ?? null);
        $this->json(['success' => $result]);
    }

    /**
     * Reabrir oportunidade (AJAX)
     * POST /opportunities/reopen
     */
    public function reopen(): void
    {
        Auth::requireInternal();
        $user = Auth::user();

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $id = (int) ($input['id'] ?? 0);

        if (!$id) {
            $this->json(['success' => false, 'error' => 'ID é obrigatório'], 400);
            return;
        }

        $result = OpportunityService::reopen($id, $user['id'] ?? null);
        $this->json(['success' => $result]);
    }

    /**
     * Criar oportunidade via AJAX (do Inbox)
     * POST /opportunities/create-ajax
     */
    public function createAjax(): void
    {
        Auth::requireInternal();
        $user = Auth::user();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $this->json(['success' => false, 'error' => 'Dados inválidos'], 400);
            return;
        }

        try {
            $id = OpportunityService::create($input, $user['id'] ?? null);
            $opp = OpportunityService::findById($id);
            $this->json([
                'success' => true,
                'opportunity_id' => $id,
                'opportunity' => $opp,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            error_log("[Opportunities] Erro AJAX ao criar: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro interno'], 500);
        }
    }

    /**
     * Adiciona nota/anotação (AJAX)
     * POST /opportunities/add-note
     */
    public function addNote(): void
    {
        Auth::requireInternal();
        $user = Auth::user();

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $id = (int) ($input['id'] ?? 0);
        $note = trim($input['note'] ?? '');

        if (!$id || !$note) {
            $this->json(['success' => false, 'error' => 'ID e nota são obrigatórios'], 400);
            return;
        }

        $db = DB::getConnection();

        // Append na nota existente
        $opp = OpportunityService::findById($id);
        if (!$opp) {
            $this->json(['success' => false, 'error' => 'Oportunidade não encontrada'], 404);
            return;
        }

        $currentNotes = $opp['notes'] ?? '';
        $timestamp = date('d/m/Y H:i');
        $userName = $user['name'] ?? 'Sistema';
        $newNotes = ($currentNotes ? $currentNotes . "\n\n" : '') . "[{$timestamp} - {$userName}] {$note}";

        $stmt = $db->prepare("UPDATE opportunities SET notes = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newNotes, $id]);

        // Registra no histórico
        $db2 = DB::getConnection();
        $stmt2 = $db2->prepare("
            INSERT INTO opportunity_history (opportunity_id, action, description, user_id, created_at)
            VALUES (?, 'note_added', ?, ?, NOW())
        ");
        $stmt2->execute([$id, $note, $user['id'] ?? null]);

        $this->json(['success' => true, 'notes' => $newNotes]);
    }

    /**
     * Busca leads via AJAX (autocomplete)
     * GET /leads/search-ajax?q=termo
     */
    public function searchLeads(): void
    {
        Auth::requireInternal();

        $query = trim($_GET['q'] ?? '');
        if (strlen($query) < 3) {
            $this->json(['success' => true, 'leads' => []]);
            return;
        }

        $leads = LeadService::list($query, 20);
        $this->json(['success' => true, 'leads' => $leads]);
    }

    /**
     * Busca clientes/tenants via AJAX (autocomplete)
     * GET /tenants/search-opp?q=termo
     */
    public function searchTenants(): void
    {
        Auth::requireInternal();

        $query = trim($_GET['q'] ?? '');
        if (strlen($query) < 3) {
            $this->json(['success' => true, 'tenants' => []]);
            return;
        }

        $db = DB::getConnection();
        $searchTerm = '%' . $query . '%';
        $searchDigits = preg_replace('/[^0-9]/', '', $query);

        if (!empty($searchDigits)) {
            $stmt = $db->prepare("
                SELECT id, name, phone, email
                FROM tenants
                WHERE status = 'active'
                AND (name LIKE ? OR email LIKE ? OR REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ?)
                ORDER BY name ASC
                LIMIT 20
            ");
            $stmt->execute([$searchTerm, $searchTerm, '%' . $searchDigits . '%']);
        } else {
            $stmt = $db->prepare("
                SELECT id, name, phone, email
                FROM tenants
                WHERE status = 'active'
                AND (name LIKE ? OR email LIKE ?)
                ORDER BY name ASC
                LIMIT 20
            ");
            $stmt->execute([$searchTerm, $searchTerm]);
        }

        $tenants = $stmt->fetchAll() ?: [];
        $this->json(['success' => true, 'tenants' => $tenants]);
    }

    /**
     * Cria lead rápido via AJAX (do modal de oportunidade)
     * POST /leads/store-ajax
     */
    public function storeLeadAjax(): void
    {
        Auth::requireInternal();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $this->json(['success' => false, 'error' => 'Dados inválidos'], 400);
            return;
        }

        $name = trim($input['name'] ?? '');
        $company = trim($input['company'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $email = trim($input['email'] ?? '');
        $notes = trim($input['notes'] ?? '');

        if (empty($phone) && empty($email)) {
            $this->json(['success' => false, 'error' => 'Informe pelo menos um telefone ou e-mail'], 400);
            return;
        }

        // Verifica duplicidade por telefone
        if (!empty($phone)) {
            $duplicates = LeadService::findDuplicatesByPhone($phone);
            $hasLeadDuplicates = !empty($duplicates['leads']);
            $hasTenantDuplicates = !empty($duplicates['tenants']);

            if (($hasLeadDuplicates || $hasTenantDuplicates) && empty($input['force_create'])) {
                $this->json([
                    'success' => false,
                    'duplicate' => true,
                    'duplicates' => $duplicates,
                    'message' => 'Já existe um cadastro com este telefone. Deseja usar o existente ou criar mesmo assim?',
                ]);
                return;
            }
        }

        try {
            $id = LeadService::create([
                'name' => $name ?: null,
                'company' => $company ?: null,
                'phone' => $phone,
                'email' => $email,
                'notes' => $notes ?: null,
                'source' => 'crm_manual',
            ]);

            $lead = LeadService::findById($id);
            $this->json([
                'success' => true,
                'lead' => $lead,
            ]);
        } catch (\Exception $e) {
            error_log("[Opportunities] Erro ao criar lead: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Busca conversa existente pelo telefone (para botão WhatsApp)
     * GET /opportunities/find-conversation?phone=47999999999
     */
    public function findConversation(): void
    {
        Auth::requireInternal();

        $phone = trim($_GET['phone'] ?? '');
        $oppId = (int) ($_GET['opp_id'] ?? 0);
        if (empty($phone)) {
            $this->json(['success' => false, 'error' => 'Telefone não informado'], 400);
            return;
        }

        $normalized = PhoneNormalizer::toE164OrNull($phone);
        if (!$normalized) {
            $this->json(['success' => false, 'found' => false]);
            return;
        }

        $db = DB::getConnection();

        // Busca conversa pelo contact_external_id normalizado (com e sem 9º dígito)
        $digits = preg_replace('/[^0-9]/', '', $normalized);
        $variations = [$digits];

        // Variação do 9º dígito BR
        if (strlen($digits) === 13 && substr($digits, 0, 2) === '55') {
            $ddd = substr($digits, 2, 2);
            $number = substr($digits, 4);
            if (strlen($number) === 9 && $number[0] === '9') {
                $variations[] = '55' . $ddd . substr($number, 1);
            }
        } elseif (strlen($digits) === 12 && substr($digits, 0, 2) === '55') {
            $ddd = substr($digits, 2, 2);
            $number = substr($digits, 4);
            if (strlen($number) === 8) {
                $variations[] = '55' . $ddd . '9' . $number;
            }
        }

        $placeholders = implode(',', array_fill(0, count($variations), '?'));
        $stmt = $db->prepare("
            SELECT id, contact_external_id, contact_name, channel_type
            FROM conversations
            WHERE REPLACE(contact_external_id, '+', '') IN ({$placeholders})
            ORDER BY updated_at DESC
            LIMIT 1
        ");
        $stmt->execute($variations);
        $conversation = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($conversation) {
            // Se veio de uma oportunidade, persiste o vínculo conversation_id → opportunity
            if ($oppId > 0) {
                try {
                    $chk = $db->prepare("SELECT id, conversation_id FROM opportunities WHERE id = ? LIMIT 1");
                    $chk->execute([$oppId]);
                    $opp = $chk->fetch(\PDO::FETCH_ASSOC);
                    if ($opp) {
                        $currentConvId = (int) ($opp['conversation_id'] ?? 0);
                        $newConvId = (int) $conversation['id'];
                        if ($newConvId > 0 && $currentConvId !== $newConvId) {
                            $upd = $db->prepare("UPDATE opportunities SET conversation_id = ?, updated_at = NOW() WHERE id = ?");
                            $upd->execute([$newConvId, $oppId]);
                        }
                    }
                } catch (\Throwable $e) {
                    // Não bloqueia o fluxo
                }
            }

            $this->json([
                'success' => true,
                'found' => true,
                'conversation_id' => (int) $conversation['id'],
                'thread_id' => 'whatsapp_' . (int) $conversation['id'],
                'channel' => $conversation['channel_type'] ?? 'whatsapp',
                'contact_name' => $conversation['contact_name'],
            ]);
        } else {
            $this->json([
                'success' => true,
                'found' => false,
            ]);
        }
    }
}
