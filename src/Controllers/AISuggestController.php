<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Services\AISuggestReplyService;
use PixelHub\Services\OpportunityService;
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
            $db = DB::getConnection();
            
            // Busca contextos com allowed_objectives
            $stmt = $db->query("
                SELECT id, name, slug, description, allowed_objectives, is_active, sort_order
                FROM ai_contexts
                WHERE is_active = 1
                ORDER BY sort_order ASC, name ASC
            ");
            $contexts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            // Decodifica allowed_objectives JSON
            foreach ($contexts as &$ctx) {
                if (!empty($ctx['allowed_objectives'])) {
                    $ctx['allowed_objectives'] = json_decode($ctx['allowed_objectives'], true) ?: null;
                } else {
                    $ctx['allowed_objectives'] = null; // null = todos os objetivos
                }
            }
            
            $this->json([
                'success' => true,
                'contexts' => $contexts,
                'all_objectives' => AISuggestReplyService::OBJECTIVES,
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
        $opportunityId = $input['opportunity_id'] ?? null;

        // Se tem opportunity_id, busca dados completos da oportunidade
        $opportunityContext = '';
        if (!empty($opportunityId)) {
            $opportunityData = $this->getOpportunityContext((int) $opportunityId);
            if ($opportunityData) {
                $opportunityContext = $opportunityData['context'];
                // Se não tem nome/telefone, usa da oportunidade
                if (empty($contactName) && !empty($opportunityData['contact_name'])) {
                    $contactName = $opportunityData['contact_name'];
                }
                if (empty($contactPhone) && !empty($opportunityData['contact_phone'])) {
                    $contactPhone = $opportunityData['contact_phone'];
                }
            }
        }

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

        // Combina observação do atendente com contexto da oportunidade
        $fullAttendantNote = $attendantNote;
        if (!empty($opportunityContext)) {
            if (!empty($fullAttendantNote)) {
                $fullAttendantNote .= "\n\n";
            }
            $fullAttendantNote .= "[CONTEXTO DA OPORTUNIDADE]\n" . $opportunityContext;
        }

        $result = AISuggestReplyService::suggest([
            'context_slug' => $contextSlug,
            'objective' => $objective,
            'tone' => $tone,
            'attendant_note' => $fullAttendantNote,
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
     * Gera 3 sugestões com contexto completo (modo híbrido)
     * POST /api/ai/suggest-chat
     */
    public function suggestChat(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];

            $contextSlug = trim($input['context_slug'] ?? 'geral');
            $objective = trim($input['objective'] ?? 'first_contact');
            $attendantNote = trim($input['attendant_note'] ?? '');
            $conversationId = $input['conversation_id'] ?? null;
            $contactName = trim($input['contact_name'] ?? '');
            $contactPhone = trim($input['contact_phone'] ?? '');
            $opportunityId = $input['opportunity_id'] ?? null;
            $aiChatMessages = $input['ai_chat_messages'] ?? [];
            $userPrompt = $input['user_prompt'] ?? ''; // REFINAMENTO DO USUÁRIO
            
            // Dados do thread (novo)
            $threadId = $input['thread_id'] ?? null;
            $leadId = $input['lead_id'] ?? null;
            $contactId = $input['contact_id'] ?? null;
            $threadMessages = $input['thread_messages'] ?? [];

            // Contexto temporal
            $currentDatetime = $input['current_datetime'] ?? null;
            $lastContactMessageAt = $input['last_contact_message_at'] ?? null;

            // Log obrigatório para debug
            error_log('[AI SUGGEST-CHAT] thread_id: ' . ($threadId ?: 'null') . 
                     ' | messages_count: ' . count($threadMessages) . 
                     ' | conversation_id: ' . ($conversationId ?: 'null') .
                     ' | opportunity_id: ' . ($opportunityId ?: 'null'));
            
            if (!empty($threadMessages)) {
                $firstMsg = $threadMessages[0]['message_text'] ?? '';
                error_log('[AI SUGGEST-CHAT] first_message: "' . substr($firstMsg, 0, 100) . '..."');
            }

            // Se tem opportunity_id, busca dados completos da oportunidade
            $opportunityContext = '';
            if (!empty($opportunityId)) {
                try {
                    $opportunityData = $this->getOpportunityContext((int) $opportunityId);
                    if ($opportunityData) {
                        $opportunityContext = $opportunityData['context'];
                        // Se não tem nome/telefone, usa da oportunidade
                        if (empty($contactName) && !empty($opportunityData['contact_name'])) {
                            $contactName = $opportunityData['contact_name'];
                        }
                        if (empty($contactPhone) && !empty($opportunityData['contact_phone'])) {
                            $contactPhone = $opportunityData['contact_phone'];
                        }
                    }
                } catch (Exception $e) {
                    error_log('[AI SUGGEST-CHAT] Erro ao buscar contexto da oportunidade ' . $opportunityId . ': ' . $e->getMessage());
                    // Continua sem contexto da oportunidade
                }
            }

            // Prioriza thread_messages sobre conversation_history do banco
            $conversationHistory = [];
            if (!empty($threadMessages)) {
                // Converte thread_messages para formato conversation_history, preservando mídia e transcrições
                $conversationHistory = array_map(function($msg) {
                    $text = $msg['message_text'] ?? '';
                    $transcription = $msg['transcription'] ?? null;
                    $mediaType = $msg['media_type'] ?? null;
                    $eventId = $msg['event_id'] ?? null;

                    // Monta campo media para que transcribeAudiosForContext possa processar
                    $mediaField = null;
                    if ($mediaType || $transcription || $eventId) {
                        $mediaField = [[
                            'media_type' => $mediaType,
                            'transcription' => $transcription,
                            'event_id' => $eventId,
                        ]];
                    }

                    return [
                        'direction' => ($msg['sender_type'] ?? '') === 'agent' ? 'out' : 'in',
                        'text' => $text,
                        'message' => $text,
                        'media' => $mediaField,
                        'created_at' => $msg['created_at'] ?? ''
                    ];
                }, $threadMessages);
                // Remove mensagens completamente vazias (sem texto e sem mídia)
                $conversationHistory = array_filter($conversationHistory, function($msg) {
                    return !empty($msg['text']) || !empty($msg['media']);
                });
                $conversationHistory = array_values($conversationHistory);
            } elseif (!empty($conversationId)) {
                // Fallback: busca do banco como antes
                $conversationHistory = $this->getConversationHistory((int) $conversationId);
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

            // Combina observação do atendente com contexto da oportunidade
            $fullAttendantNote = $attendantNote;
            if (!empty($opportunityContext)) {
                if (!empty($fullAttendantNote)) {
                    $fullAttendantNote .= "\n\n";
                }
                $fullAttendantNote .= "[CONTEXTO DA OPORTUNIDADE]\n" . $opportunityContext;
            }

            $result = AISuggestReplyService::suggestChat([
                'context_slug' => $contextSlug,
                'objective' => $objective,
                'attendant_note' => $fullAttendantNote,
                'conversation_history' => $conversationHistory,
                'contact_name' => $contactName,
                'contact_phone' => $contactPhone,
                'ai_chat_messages' => $aiChatMessages,
                'user_prompt' => $userPrompt,
                'current_datetime' => $currentDatetime,
                'last_contact_message_at' => $lastContactMessageAt,
            ]);

            $this->json($result, $result['success'] ? 200 : 400);
            
        } catch (Exception $e) {
            error_log('[AI SUGGEST-CHAT] Erro: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
            $this->json([
                'success' => false,
                'error' => 'Erro interno do servidor: ' . $e->getMessage()
            ], 500);
        } catch (Error $e) {
            error_log('[AI SUGGEST-CHAT] Error Fatal: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
            $this->json([
                'success' => false,
                'error' => 'Erro interno do servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Chat conversacional com a IA
     * POST /api/ai/chat
     */
    public function chat(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];

            $contextSlug = trim($input['context_slug'] ?? 'geral');
            $objective = trim($input['objective'] ?? 'first_contact');
            $attendantNote = trim($input['attendant_note'] ?? '');
            $conversationId = $input['conversation_id'] ?? null;
            $contactName = trim($input['contact_name'] ?? '');
            $contactPhone = trim($input['contact_phone'] ?? '');
            $aiChatMessages = $input['ai_chat_messages'] ?? [];
            $opportunityId = $input['opportunity_id'] ?? null;
            $userPrompt = $input['user_prompt'] ?? ''; // REFINAMENTO DO USUÁRIO
            
            // Dados do thread (novo)
            $threadId = $input['thread_id'] ?? null;
            $leadId = $input['lead_id'] ?? null;
            $contactId = $input['contact_id'] ?? null;
            $threadMessages = $input['thread_messages'] ?? [];

            // Contexto temporal
            $currentDatetime = $input['current_datetime'] ?? null;
            $lastContactMessageAt = $input['last_contact_message_at'] ?? null;

            // Log obrigatório para debug
            error_log('[AI DRAFT REQUEST] thread_id: ' . ($threadId ?: 'null') . 
                     ' | messages_count: ' . count($threadMessages) . 
                     ' | conversation_id: ' . ($conversationId ?: 'null') .
                     ' | opportunity_id: ' . ($opportunityId ?: 'null'));
            
            if (!empty($threadMessages)) {
                $firstMsg = $threadMessages[0]['message_text'] ?? '';
                error_log('[AI DRAFT REQUEST] first_message: "' . substr($firstMsg, 0, 100) . '..."');
            }

            // Se tem opportunity_id, busca dados completos da oportunidade
            $opportunityContext = '';
            if (!empty($opportunityId)) {
                try {
                    $opportunityData = $this->getOpportunityContext((int) $opportunityId);
                    if ($opportunityData) {
                        $opportunityContext = $opportunityData['context'];
                        // Se não tem nome/telefone, usa da oportunidade
                        if (empty($contactName) && !empty($opportunityData['contact_name'])) {
                            $contactName = $opportunityData['contact_name'];
                        }
                        if (empty($contactPhone) && !empty($opportunityData['contact_phone'])) {
                            $contactPhone = $opportunityData['contact_phone'];
                        }
                    }
                } catch (Exception $e) {
                    error_log('[AI DRAFT] Erro ao buscar contexto da oportunidade ' . $opportunityId . ': ' . $e->getMessage());
                    // Continua sem contexto da oportunidade
                }
            }

            // Prioriza thread_messages sobre conversation_history do banco
            $conversationHistory = [];
            if (!empty($threadMessages)) {
                // Converte thread_messages para formato conversation_history, preservando mídia e transcrições
                $conversationHistory = array_map(function($msg) {
                    $text = $msg['message_text'] ?? '';
                    $transcription = $msg['transcription'] ?? null;
                    $mediaType = $msg['media_type'] ?? null;
                    $eventId = $msg['event_id'] ?? null;

                    // Monta campo media para que transcribeAudiosForContext possa processar
                    $mediaField = null;
                    if ($mediaType || $transcription || $eventId) {
                        $mediaField = [[
                            'media_type' => $mediaType,
                            'transcription' => $transcription,
                            'event_id' => $eventId,
                        ]];
                    }

                    return [
                        'direction' => ($msg['sender_type'] ?? '') === 'agent' ? 'out' : 'in',
                        'text' => $text,
                        'message' => $text,
                        'media' => $mediaField,
                        'created_at' => $msg['created_at'] ?? ''
                    ];
                }, $threadMessages);
                // Remove mensagens completamente vazias (sem texto e sem mídia)
                $conversationHistory = array_filter($conversationHistory, function($msg) {
                    return !empty($msg['text']) || !empty($msg['media']);
                });
                $conversationHistory = array_values($conversationHistory);
            } elseif (!empty($conversationId)) {
                // Fallback: busca do banco como antes
                $conversationHistory = $this->getConversationHistory((int) $conversationId);
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

            // Análise automática de cobranças se contexto é financeiro E objetivo é cobranca
            $billingAnalysis = null;
            if ($contextSlug === 'financeiro' && $objective === 'cobranca') {
                // Tenta identificar tenant_id: primeiro do payload, depois da conversa/oportunidade
                $tenantId = $input['tenant_id'] ?? null;
                if (!$tenantId) {
                    $tenantId = $this->extractTenantIdFromConversation($conversationId, $opportunityId);
                }
                
                if ($tenantId) {
                    error_log('[AI CHAT] Iniciando análise de cobrança para tenant_id: ' . $tenantId);
                    $billingAnalysis = AISuggestReplyService::analyzeBillingContext($tenantId);
                    error_log('[AI CHAT] Análise retornou objetivo: ' . ($billingAnalysis['objective'] ?? 'null'));
                    
                    // Sobrescreve objetivo com o específico (critical/collection/reminder)
                    if (!empty($billingAnalysis['objective']) && $billingAnalysis['objective'] !== 'answer_question') {
                        $objective = $billingAnalysis['objective'];
                        error_log('[AI CHAT] Objetivo sobrescrito para: ' . $objective);
                    }
                } else {
                    error_log('[AI CHAT] ERRO: Contexto financeiro + objetivo cobranca, mas tenant_id não identificado');
                    error_log('[AI CHAT] conversation_id: ' . ($conversationId ?: 'null') . ', opportunity_id: ' . ($opportunityId ?: 'null'));
                }
            }

            // Combina observação do atendente com contexto da oportunidade e análise de cobrança
            $fullAttendantNote = $attendantNote;
            if (!empty($opportunityContext)) {
                if (!empty($fullAttendantNote)) {
                    $fullAttendantNote .= "\n\n";
                }
                $fullAttendantNote .= "[CONTEXTO DA OPORTUNIDADE]\n" . $opportunityContext;
            }
            if (!empty($billingAnalysis['context'])) {
                if (!empty($fullAttendantNote)) {
                    $fullAttendantNote .= "\n\n";
                }
                $fullAttendantNote .= $billingAnalysis['context'];
            }

            $result = AISuggestReplyService::chat([
                'context_slug' => $contextSlug,
                'objective' => $objective,
                'attendant_note' => $fullAttendantNote,
                'conversation_history' => $conversationHistory,
                'contact_name' => $contactName,
                'contact_phone' => $contactPhone,
                'ai_chat_messages' => $aiChatMessages,
                'user_prompt' => $userPrompt,
                'current_datetime' => $currentDatetime,
                'last_contact_message_at' => $lastContactMessageAt,
            ]);

            $this->json($result, $result['success'] ? 200 : 400);
            
        } catch (Exception $e) {
            error_log('[AI CHAT] Erro: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
            $this->json([
                'success' => false,
                'error' => 'Erro interno do servidor: ' . $e->getMessage()
            ], 500);
        } catch (Error $e) {
            error_log('[AI CHAT] Error Fatal: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
            $this->json([
                'success' => false,
                'error' => 'Erro interno do servidor: ' . $e->getMessage()
            ], 500);
        }
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

        // Busca últimas 20 mensagens com transcrições de áudio via JOIN
        $stmt = $db->prepare("
            SELECT 
                ce.event_id,
                CASE 
                    WHEN ce.event_type LIKE '%outbound%' THEN 'out'
                    WHEN ce.event_type LIKE '%inbound%' THEN 'in'
                    ELSE 'in'
                END as direction,
                COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.content')),
                    JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.content')),
                    JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.data.content')),
                    ''
                ) as text,
                cm.media_type,
                cm.transcription,
                cm.transcription_status,
                ce.created_at
            FROM communication_events ce
            LEFT JOIN communication_media cm ON cm.event_id = ce.event_id
            WHERE ce.conversation_id = ?
            AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
            ORDER BY ce.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$conversationId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $messages = [];
        foreach ($rows as $row) {
            $text = $row['text'] ?? '';
            $mediaType = $row['media_type'] ?? null;
            $transcription = $row['transcription'] ?? null;

            // Monta campo media se houver mídia associada
            $mediaField = null;
            if ($mediaType || $transcription) {
                $mediaField = [[
                    'media_type' => $mediaType,
                    'transcription' => $transcription,
                    'transcription_status' => $row['transcription_status'] ?? null,
                    'event_id' => $row['event_id'],
                ]];
            }

            // Inclui mensagem se tem texto OU tem mídia (áudio, imagem, etc.)
            if (!empty($text) || $mediaField) {
                $messages[] = [
                    'direction' => $row['direction'],
                    'text' => $text,
                    'message' => $text,
                    'media' => $mediaField,
                    'created_at' => $row['created_at'],
                ];
            }
        }

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

    /**
     * Extrai tenant_id de uma conversa ou oportunidade
     */
    private function extractTenantIdFromConversation(?int $conversationId, ?int $opportunityId): ?int
    {
        $db = DB::getConnection();

        // Tenta pela oportunidade primeiro
        if ($opportunityId) {
            $stmt = $db->prepare("SELECT tenant_id FROM opportunities WHERE id = ? LIMIT 1");
            $stmt->execute([$opportunityId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && !empty($result['tenant_id'])) {
                return (int) $result['tenant_id'];
            }
        }

        // Tenta pela conversa
        if ($conversationId) {
            $stmt = $db->prepare("SELECT tenant_id FROM conversations WHERE id = ? LIMIT 1");
            $stmt->execute([$conversationId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && !empty($result['tenant_id'])) {
                return (int) $result['tenant_id'];
            }
        }

        return null;
    }

    /**
     * Busca contexto completo da oportunidade para IA
     * Inclui dados da oportunidade, histórico e informações do lead/cliente
     */
    private function getOpportunityContext(int $opportunityId): ?array
    {
        $db = DB::getConnection();

        // Busca dados da oportunidade
        $stmt = $db->prepare("
            SELECT o.*,
                   l.name as lead_name, l.phone as lead_phone, l.email as lead_email, l.notes as lead_notes,
                   t.name as tenant_name, t.phone as tenant_phone, t.email as tenant_email,
                   u.name as responsible_name,
                   s.name as service_name
            FROM opportunities o
            LEFT JOIN leads l ON o.lead_id = l.id
            LEFT JOIN tenants t ON o.tenant_id = t.id
            LEFT JOIN users u ON o.responsible_user_id = u.id
            LEFT JOIN services s ON o.service_id = s.id
            WHERE o.id = ?
            LIMIT 1
        ");
        $stmt->execute([$opportunityId]);
        $opportunity = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$opportunity) {
            return null;
        }

        // Busca histórico da oportunidade
        $stmt = $db->prepare("
            SELECT oh.description, oh.created_at, u.name as user_name
            FROM opportunity_history oh
            LEFT JOIN users u ON oh.user_id = u.id
            WHERE oh.opportunity_id = ?
            ORDER BY oh.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$opportunityId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Monta contexto estruturado para a IA
        $contextParts = [];

        // Dados principais da oportunidade
        $contextParts[] = "**Oportunidade**: " . $opportunity['name'];
        if (!empty($opportunity['estimated_value'])) {
            $contextParts[] = "**Valor estimado**: R$ " . number_format($opportunity['estimated_value'], 2, ',', '.');
        }
        if (!empty($opportunity['stage'])) {
            $stages = OpportunityService::STAGES;
            $stageLabel = $stages[$opportunity['stage']] ?? $opportunity['stage'];
            $contextParts[] = "**Etapa atual**: {$stageLabel}";
        }
        if (!empty($opportunity['responsible_name'])) {
            $contextParts[] = "**Responsável**: " . $opportunity['responsible_name'];
        }

        // Informações do contato (lead ou cliente)
        $contactName = $opportunity['lead_name'] ?: $opportunity['tenant_name'] ?: '';
        $contactPhone = $opportunity['lead_phone'] ?: $opportunity['tenant_phone'] ?: '';
        $contactEmail = $opportunity['lead_email'] ?: $opportunity['tenant_email'] ?: '';

        if (!empty($contactName)) {
            $contextParts[] = "**Contato**: {$contactName}";
        }
        if (!empty($contactPhone)) {
            $contextParts[] = "**Telefone**: {$contactPhone}";
        }
        if (!empty($contactEmail)) {
            $contextParts[] = "**E-mail**: {$contactEmail}";
        }

        // Serviço relacionado
        if (!empty($opportunity['service_name'])) {
            $contextParts[] = "**Serviço**: " . $opportunity['service_name'];
        }

        // Observações da oportunidade
        if (!empty($opportunity['notes'])) {
            $contextParts[] = "**Observações**: " . $opportunity['notes'];
        }

        // Observações do lead (se houver)
        if (!empty($opportunity['lead_notes'])) {
            $contextParts[] = "**Observações do lead**: " . $opportunity['lead_notes'];
        }

        // Histórico relevante
        if (!empty($history)) {
            $contextParts[] = "\n**Histórico recente**:";
            foreach ($history as $item) {
                $date = date('d/m/Y H:i', strtotime($item['created_at']));
                $user = $item['user_name'] ?: 'Sistema';
                $contextParts[] = "- {$date} ({$user}): " . $item['description'];
            }
        }

        return [
            'context' => implode("\n", $contextParts),
            'contact_name' => $contactName,
            'contact_phone' => $contactPhone,
        ];
    }
}
