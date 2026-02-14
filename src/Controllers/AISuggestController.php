<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Services\AISuggestReplyService;
use PDO;

/**
 * Controller para endpoints da IA assistente de respostas
 */
class AISuggestController extends Controller
{
    /**
     * Lista contextos disponíveis
     * GET /api/ai/contexts
     */
    public function contexts(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $contexts = AISuggestReplyService::listContexts();
            $this->json([
                'success' => true,
                'contexts' => $contexts,
                'objectives' => AISuggestReplyService::OBJECTIVES,
                'tones' => AISuggestReplyService::TONES,
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Gera sugestões de resposta
     * POST /api/ai/suggest-reply
     */
    public function suggestReply(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];

        $contextSlug = trim($input['context_slug'] ?? 'geral');
        $objective = trim($input['objective'] ?? 'first_contact');
        $tone = trim($input['tone'] ?? 'normal');
        $attendantNote = trim($input['attendant_note'] ?? '');
        $conversationId = $input['conversation_id'] ?? null;
        $contactName = trim($input['contact_name'] ?? '');
        $contactPhone = trim($input['contact_phone'] ?? '');

        // Busca histórico da conversa se tiver conversation_id
        $conversationHistory = [];
        if (!empty($conversationId)) {
            $conversationHistory = $this->getConversationHistory((int) $conversationId);
            // Tenta pegar nome/telefone da conversa se não foram fornecidos
            if (empty($contactName) || empty($contactPhone)) {
                $convInfo = $this->getConversationInfo((int) $conversationId);
                if (empty($contactName) && !empty($convInfo['contact_name'])) {
                    $contactName = $convInfo['contact_name'];
                }
                if (empty($contactPhone) && !empty($convInfo['contact_phone'])) {
                    $contactPhone = $convInfo['contact_phone'];
                }
            }
        }

        // Se veio histórico inline (do modal nova mensagem, por exemplo)
        if (empty($conversationHistory) && !empty($input['history'])) {
            $conversationHistory = $input['history'];
        }

        $result = AISuggestReplyService::suggest([
            'context_slug' => $contextSlug,
            'objective' => $objective,
            'tone' => $tone,
            'attendant_note' => $attendantNote,
            'conversation_history' => $conversationHistory,
            'contact_name' => $contactName,
            'contact_phone' => $contactPhone,
        ]);

        $this->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Registra aprendizado (quando atendente edita sugestão e envia)
     * POST /api/ai/learn
     */
    public function learn(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
        $user = Auth::user();

        $result = AISuggestReplyService::learn([
            'context_slug' => trim($input['context_slug'] ?? 'geral'),
            'objective' => trim($input['objective'] ?? ''),
            'situation_summary' => trim($input['situation_summary'] ?? ''),
            'ai_suggestion' => trim($input['ai_suggestion'] ?? ''),
            'human_response' => trim($input['human_response'] ?? ''),
            'user_id' => $user ? (int) $user['id'] : null,
            'conversation_id' => $input['conversation_id'] ?? null,
        ]);

        $this->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Página admin de contextos IA
     * GET /settings/ai-contexts
     */
    public function adminContexts(): void
    {
        Auth::requireInternal();
        $this->view('settings.ai_contexts');
    }

    /**
     * Lista todos os contextos (admin, inclui inativos)
     * GET /api/ai/contexts/all
     */
    public function allContexts(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        $db = DB::getConnection();
        $contexts = $db->query("
            SELECT id, name, slug, description, system_prompt, knowledge_base, is_active, sort_order, created_at, updated_at
            FROM ai_contexts
            ORDER BY sort_order ASC, name ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->json(['success' => true, 'contexts' => $contexts]);
    }

    /**
     * Salva contexto (create ou update)
     * POST /api/ai/contexts/save
     */
    public function saveContext(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
        $id = $input['id'] ?? null;
        $name = trim($input['name'] ?? '');
        $slug = trim($input['slug'] ?? '');
        $description = trim($input['description'] ?? '');
        $systemPrompt = trim($input['system_prompt'] ?? '');
        $knowledgeBase = trim($input['knowledge_base'] ?? '');
        $isActive = (int) ($input['is_active'] ?? 1);
        $sortOrder = (int) ($input['sort_order'] ?? 0);

        if (empty($name) || empty($slug) || empty($systemPrompt)) {
            $this->json(['success' => false, 'error' => 'Nome, slug e prompt do sistema são obrigatórios'], 400);
            return;
        }

        $db = DB::getConnection();
        $now = date('Y-m-d H:i:s');

        if ($id) {
            $stmt = $db->prepare("
                UPDATE ai_contexts SET name = ?, slug = ?, description = ?, system_prompt = ?, knowledge_base = ?, is_active = ?, sort_order = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $slug, $description, $systemPrompt, $knowledgeBase ?: null, $isActive, $sortOrder, $now, $id]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO ai_contexts (name, slug, description, system_prompt, knowledge_base, is_active, sort_order, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $slug, $description, $systemPrompt, $knowledgeBase ?: null, $isActive, $sortOrder, $now, $now]);
            $id = (int) $db->lastInsertId();
        }

        $this->json(['success' => true, 'id' => (int) $id]);
    }

    /**
     * Busca histórico de mensagens de uma conversa
     */
    private function getConversationHistory(int $conversationId): array
    {
        $db = DB::getConnection();

        // Busca últimas 20 mensagens da conversa
        $stmt = $db->prepare("
            SELECT 
                direction,
                COALESCE(body, '') as text,
                created_at
            FROM communication_messages
            WHERE conversation_id = ?
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$conversationId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Inverte para ordem cronológica
        return array_reverse($messages);
    }

    /**
     * Busca informações do contato da conversa
     */
    private function getConversationInfo(int $conversationId): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT 
                c.contact_name,
                c.contact_external_id as contact_phone,
                c.tenant_id,
                t.name as tenant_name,
                t.phone as tenant_phone
            FROM conversations c
            LEFT JOIN tenants t ON t.id = c.tenant_id
            WHERE c.id = ?
            LIMIT 1
        ");
        $stmt->execute([$conversationId]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$conv) {
            return [];
        }

        return [
            'contact_name' => $conv['contact_name'] ?: $conv['tenant_name'] ?: '',
            'contact_phone' => $conv['contact_phone'] ?: $conv['tenant_phone'] ?: '',
        ];
    }
}
