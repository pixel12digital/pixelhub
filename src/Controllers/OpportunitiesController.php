<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Services\OpportunityService;
use PixelHub\Services\ContactService;
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

        // Busca próximos compromissos agendados para esta oportunidade
        $upcomingSchedules = [];
        try {
            $stmt = $db->prepare("
                SELECT id, title, item_date, time_start, time_end, notes
                FROM agenda_manual_items
                WHERE opportunity_id = ?
                AND item_date >= CURDATE()
                ORDER BY item_date ASC, COALESCE(time_start, '00:00:00') ASC
                LIMIT 3
            ");
            $stmt->execute([$id]);
            $upcomingSchedules = $stmt->fetchAll() ?: [];
        } catch (\Exception $e) {
            error_log('[Opportunities] Erro ao buscar compromissos agendados: ' . $e->getMessage());
        }

        $this->view('opportunities.view', [
            'opportunity' => $opportunity,
            'history' => $history,
            'users' => $users,
            'services' => $services,
            'stages' => OpportunityService::STAGES,
            'upcomingSchedules' => $upcomingSchedules,
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

        $leads = ContactService::searchLeads($query, 20);
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
            $duplicates = ContactService::findDuplicatesByPhone($phone);
            $hasDuplicates = !empty($duplicates);

            if ($hasDuplicates && empty($input['force_create'])) {
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
            $id = ContactService::create([
                'name' => $name ?: null,
                'company' => $company ?: null,
                'phone' => $phone,
                'email' => $email,
                'notes' => $notes ?: null,
                'source' => 'crm_manual',
            ], ContactService::TYPE_LEAD);

            $lead = ContactService::findById($id);
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
     * Busca AJAX para auto-search
     * GET /opportunities/search-ajax?q=termo&stage=X&responsible=Y&status=Z
     */
    public function searchAjax(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');
        
        $query = trim($_GET['q'] ?? '');
        if (empty($query)) {
            $this->json(['success' => false, 'error' => 'Termo de busca obrigatório']);
            return;
        }
        
        $filters = [
            'search' => $query,
            'stage' => $_GET['stage'] ?? null,
            'responsible_user_id' => !empty($_GET['responsible']) ? (int) $_GET['responsible'] : null,
            'status' => $_GET['status'] ?? null,
        ];
        
        try {
            $opportunities = OpportunityService::list($filters);
            $this->json(['success' => true, 'opportunities' => $opportunities]);
        } catch (\Exception $e) {
            error_log('[Opportunities] Erro na busca AJAX: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro interno'], 500);
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

        // Gera variações mais completas para busca
        $allVariations = $variations;
        
        // Adiciona variações com sufixos comuns
        $suffixes = ['', '-0', '_0', '@c.us', '@g.us', '@s.whatsapp.net'];
        foreach ($variations as $variation) {
            foreach ($suffixes as $suffix) {
                $allVariations[] = $variation . $suffix;
            }
        }
        
        // Adiciona prefixos possíveis
        $prefixes = ['', 'whatsapp', 'wa'];
        foreach ($variations as $variation) {
            foreach ($prefixes as $prefix) {
                if (!empty($prefix)) {
                    $allVariations[] = $prefix . $variation;
                    $allVariations[] = $variation . '.' . $prefix;
                }
            }
        }
        
        // Remove duplicatas
        $allVariations = array_unique($allVariations);
        
        // Busca na tabela conversations (Inbox) com telefone normalizado e com prefixo '+'
        try {
            $placeholders = implode(',', array_fill(0, count($allVariations), '?'));
            $sql = "
                SELECT id, contact_external_id, contact_name, channel_id
                FROM conversations
                WHERE channel_type = 'whatsapp'
                  AND (
                        contact_external_id IN ({$placeholders})
                     OR REPLACE(REPLACE(contact_external_id, '+', ''), ' ', '') IN ({$placeholders})
                     OR REPLACE(REPLACE(SUBSTRING_INDEX(contact_external_id, '@', 1), '+', ''), ' ', '') IN ({$placeholders})
                     OR REPLACE(REPLACE(contact_external_id, '+', ''), ' ', '') LIKE CONCAT('%', ?, '%')
                  )
                ORDER BY last_message_at DESC, id DESC
                LIMIT 1
            ";
            $stmt = $db->prepare($sql);
            // Executa com variações duplicadas (para os dois IN)
            $params = array_merge($allVariations, $allVariations, $allVariations, [$digits]);
            $stmt->execute($params);
            $thread = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Log para debug mais detalhado
            error_log('[OpportunitiesController] find-conversation - phone: ' . $phone . ' | normalized: ' . $normalized . ' | variations: ' . json_encode($allVariations) . ' | found: ' . ($thread ? 'yes' : 'no'));
            if ($thread) {
                error_log('[OpportunitiesController] find-conversation - FOUND contact_external_id: ' . $thread['contact_external_id']);
            }
        } catch (\Throwable $e) {
            error_log('[OpportunitiesController] find-conversation ERROR: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro interno ao buscar conversa'], 500);
            return;
        }

        if ($thread) {
            // Se veio de uma oportunidade, persiste o vínculo conversation_id → opportunity
            if ($oppId > 0) {
                try {
                    $chk = $db->prepare("SELECT id, conversation_id FROM opportunities WHERE id = ? LIMIT 1");
                    $chk->execute([$oppId]);
                    $opp = $chk->fetch(\PDO::FETCH_ASSOC);
                    if ($opp) {
                        $currentConvId = (int) ($opp['conversation_id'] ?? 0);
                        $newThreadId = (int) $thread['id'];
                        if ($newThreadId > 0 && $currentConvId !== $newThreadId) {
                            $upd = $db->prepare("UPDATE opportunities SET conversation_id = ?, updated_at = NOW() WHERE id = ?");
                            $upd->execute([$newThreadId, $oppId]);
                        }
                    }
                } catch (\Throwable $e) {
                    // Não bloqueia o fluxo
                }
            }

            $this->json([
                'success' => true,
                'found' => true,
                'conversation_id' => (int) $thread['id'],
                'thread_id' => 'whatsapp_' . (int) $thread['id'],
                'channel' => $thread['channel_id'] ?? 'whatsapp',
                'contact_name' => $thread['contact_name'],
            ]);
        } else {
            $this->json([
                'success' => true,
                'found' => false,
            ]);
        }
    }
    
    /**
     * Busca histórico de mensagens da conversa vinculada à oportunidade
     * GET /api/opportunities/conversation-history?id=X
     */
    public function conversationHistory(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');
        
        $oppId = (int) ($_GET['id'] ?? 0);
        if (!$oppId) {
            $this->json(['success' => false, 'error' => 'ID inválido']);
            return;
        }
        
        $db = DB::getConnection();
        
        // Busca conversation_id da oportunidade
        $stmt = $db->prepare("
            SELECT conversation_id, lead_id, tenant_id 
            FROM opportunities 
            WHERE id = ? 
            LIMIT 1
        ");
        $stmt->execute([$oppId]);
        $opp = $stmt->fetch();
        
        if (!$opp) {
            $this->json(['success' => false, 'error' => 'Oportunidade não encontrada']);
            return;
        }
        
        $conversationId = $opp['conversation_id'];
        
        // Se não tem conversation_id, tenta buscar pela lead_id ou tenant_id
        if (!$conversationId) {
            if ($opp['lead_id']) {
                $convStmt = $db->prepare("SELECT id FROM conversations WHERE lead_id = ? ORDER BY updated_at DESC LIMIT 1");
                $convStmt->execute([$opp['lead_id']]);
            } elseif ($opp['tenant_id']) {
                $convStmt = $db->prepare("SELECT id FROM conversations WHERE tenant_id = ? ORDER BY updated_at DESC LIMIT 1");
                $convStmt->execute([$opp['tenant_id']]);
            }
            
            if (isset($convStmt)) {
                $conv = $convStmt->fetch();
                $conversationId = $conv['id'] ?? null;
            }
        }
        
        if (!$conversationId) {
            $this->json(['success' => true, 'messages' => []]);
            return;
        }
        
        // Busca últimas 20 mensagens da conversa
        $msgStmt = $db->prepare("
            SELECT 
                event_type,
                payload,
                created_at
            FROM communication_events
            WHERE conversation_id = ?
            AND event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
            AND status = 'processed'
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $msgStmt->execute([$conversationId]);
        $events = $msgStmt->fetchAll();
        
        $messages = [];
        foreach (array_reverse($events) as $event) {
            $payload = json_decode($event['payload'], true);
            $text = $payload['message']['text'] ?? $payload['text'] ?? '';
            
            if ($text) {
                $messages[] = [
                    'direction' => str_contains($event['event_type'], 'inbound') ? 'inbound' : 'outbound',
                    'text' => $text,
                    'timestamp' => $event['created_at'],
                ];
            }
        }
        
        $this->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    /**
     * Retorna detalhes de um follow-up agendado
     * GET /opportunities/followup-details?id=X
     */
    public function followupDetails(): void
    {
        try {
            Auth::requireInternal();
            
            $itemId = (int) ($_GET['id'] ?? 0);
            if (!$itemId) {
                $this->json(['success' => false, 'error' => 'ID não informado']);
                return;
            }
            
            $db = DB::getConnection();
            
            // Busca dados do agenda item
            $stmt = $db->prepare("
                SELECT id, title, item_date, time_start, time_end, notes, opportunity_id, lead_id, related_type
                FROM agenda_manual_items
                WHERE id = ?
            ");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            
            if (!$item) {
                $this->json(['success' => false, 'error' => 'Follow-up não encontrado']);
                return;
            }
            
            // Verifica se há mensagem agendada
            $scheduledMessage = null;
            $messageStatus = null;
            $sentAt = null;
            
            try {
                $msgStmt = $db->prepare("
                    SELECT message_text, status, sent_at
                    FROM scheduled_messages
                    WHERE agenda_item_id = ?
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $msgStmt->execute([$itemId]);
                $msg = $msgStmt->fetch();
                
                if ($msg) {
                    $scheduledMessage = $msg['message_text'];
                    $messageStatus = $msg['status'];
                    $sentAt = $msg['sent_at'];
                }
            } catch (\PDOException $e) {
                // Tabela pode não existir ou outro erro de DB
                error_log('[Opportunities] Erro ao buscar mensagem agendada: ' . $e->getMessage());
            } catch (\Exception $e) {
                // Outros erros
                error_log('[Opportunities] Erro ao buscar mensagem agendada: ' . $e->getMessage());
            }
            
            $followup = [
                'id' => $item['id'],
                'title' => $item['title'],
                'item_date' => $item['item_date'],
                'time_start' => $item['time_start'],
                'time_end' => $item['time_end'],
                'notes' => $item['notes'],
                'opportunity_id' => $item['opportunity_id'],
                'lead_id' => $item['lead_id'],
                'related_type' => $item['related_type'],
                'scheduled_message' => $scheduledMessage,
                'status' => $messageStatus,
                'sent_at' => $sentAt,
            ];
            
            $this->json([
                'success' => true,
                'followup' => $followup,
            ]);
        } catch (\Exception $e) {
            error_log('[Opportunities] Erro em followupDetails: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * Atualiza um follow-up agendado
     * POST /opportunities/update-followup
     */
    public function updateFollowup(): void
    {
        try {
            Auth::requireInternal();
            
            $itemId = (int) ($_POST['id'] ?? 0);
            if (!$itemId) {
                $this->json(['success' => false, 'error' => 'ID não informado']);
                return;
            }
            
            $title = trim($_POST['title'] ?? '');
            $date = $_POST['item_date'] ?? '';
            $time = $_POST['time_start'] ?? '';
            $notes = trim($_POST['notes'] ?? '');
            $message = trim($_POST['scheduled_message'] ?? '');
            
            // Remove espaços em excesso do início e fim da mensagem
            $message = preg_replace('/^\s+|\s+$/', '', $message);
            
            if (!$title) {
                $this->json(['success' => false, 'error' => 'Título é obrigatório']);
                return;
            }
            if (!$date) {
                $this->json(['success' => false, 'error' => 'Data é obrigatória']);
                return;
            }
            
            $db = DB::getConnection();
            
            // Inicia transação
            $db->beginTransaction();
            
            try {
                // Atualiza o agenda item
                $stmt = $db->prepare("
                    UPDATE agenda_manual_items
                    SET title = ?, item_date = ?, time_start = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$title, $date, $time, $notes, $itemId]);
                
                // Verifica se há mensagem agendada para atualizar
                $msgStmt = $db->prepare("
                    SELECT id FROM scheduled_messages WHERE agenda_item_id = ? ORDER BY created_at DESC LIMIT 1
                ");
                $msgStmt->execute([$itemId]);
                $existingMsg = $msgStmt->fetch();
                
                if (!empty($message)) {
                    // Tem mensagem para salvar
                    if ($existingMsg) {
                        // Atualiza mensagem existente
                        $updateMsg = $db->prepare("
                            UPDATE scheduled_messages
                            SET message_text = ?, scheduled_at = CONCAT(?, ' ', COALESCE(?, '00:00:00')), status = 'pending'
                            WHERE id = ?
                        ");
                        $updateMsg->execute([$message, $date, $time, $existingMsg['id']]);
                    } else {
                        // Cria nova mensagem
                        $insertMsg = $db->prepare("
                            INSERT INTO scheduled_messages
                            (agenda_item_id, message_text, scheduled_at, status, created_at)
                            VALUES (?, ?, CONCAT(?, ' ', COALESCE(?, '00:00:00')), 'pending', CURRENT_TIMESTAMP)
                        ");
                        $insertMsg->execute([$itemId, $message, $date, $time]);
                    }
                } elseif ($existingMsg) {
                    // Remove mensagem se foi limpa
                    $deleteMsg = $db->prepare("DELETE FROM scheduled_messages WHERE id = ?");
                    $deleteMsg->execute([$existingMsg['id']]);
                }
                
                $db->commit();
                
                $this->json(['success' => true]);
            } catch (\Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            error_log('[Opportunities] Erro em updateFollowup: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * Exclui um follow-up agendado
     * POST /opportunities/delete-followup
     */
    public function deleteFollowup(): void
    {
        try {
            Auth::requireInternal();
            
            $itemId = (int) ($_POST['id'] ?? 0);
            if (!$itemId) {
                $this->json(['success' => false, 'error' => 'ID não informado']);
                return;
            }
            
            $db = DB::getConnection();
            
            // Inicia transação
            $db->beginTransaction();
            
            try {
                // Remove mensagens agendadas relacionadas
                $deleteMsg = $db->prepare("DELETE FROM scheduled_messages WHERE agenda_item_id = ?");
                $deleteMsg->execute([$itemId]);
                
                // Remove o agenda item
                $deleteItem = $db->prepare("DELETE FROM agenda_manual_items WHERE id = ?");
                $deleteItem->execute([$itemId]);
                
                $db->commit();
                
                $this->json(['success' => true]);
            } catch (\Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            error_log('[Opportunities] Erro em deleteFollowup: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }
}
