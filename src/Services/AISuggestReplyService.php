<?php

namespace PixelHub\Services;

use PixelHub\Core\Env;
use PixelHub\Core\CryptoHelper;
use PixelHub\Core\DB;
use PDO;

/**
 * Serviço para gerar sugestões de resposta via IA (OpenAI)
 * com aprendizado contínuo baseado nas correções dos atendentes.
 * 
 * Fluxo:
 * 1. Recebe contexto, objetivo, histórico e observação do atendente
 * 2. Busca exemplos de aprendizado anteriores (correções humanas)
 * 3. Monta prompt combinando roteiro + histórico + aprendizado
 * 4. Chama OpenAI e retorna 3 sugestões
 * 5. Quando atendente edita e envia, salva a correção para aprendizado futuro
 */
class AISuggestReplyService
{
    public const OBJECTIVES = [
        'first_contact'           => 'Primeiro contato',
        'qualify'                 => 'Qualificar lead',
        'schedule_call'           => 'Agendar call/reunião',
        'answer_question'         => 'Responder dúvida',
        'follow_up'               => 'Follow-up',
        'send_proposal'           => 'Enviar proposta',
        'close_deal'              => 'Fechar negócio',
        'support'                 => 'Suporte técnico',
        'billing'                 => 'Gerar cobrança (automático)',
        'atendimento_financeiro'  => 'Atendimento',
        'cobranca'                => 'Cobrança',
    ];

    // Objetivos internos (não aparecem no dropdown, usados apenas pela análise automática)
    private const INTERNAL_OBJECTIVES = [
        'billing_reminder'   => 'Lembrete de vencimento',
        'billing_collection' => 'Cobrança (1-2 faturas vencidas)',
        'billing_critical'   => 'Cobrança crítica (3+ faturas)',
    ];

    public const TONES = [
        'short'  => 'Curto e direto',
        'normal' => 'Padrão',
        'formal' => 'Mais formal',
    ];

    /**
     * Gera sugestões de resposta
     */
    public static function suggest(array $params): array
    {
        $contextSlug = $params['context_slug'] ?? 'geral';
        $objective = $params['objective'] ?? 'first_contact';
        $tone = $params['tone'] ?? 'normal';
        $attendantNote = $params['attendant_note'] ?? '';
        $conversationHistory = $params['conversation_history'] ?? [];
        $contactName = $params['contact_name'] ?? '';
        $contactPhone = $params['contact_phone'] ?? '';
        $hasHistory = !empty($conversationHistory);

        // Busca contexto de atendimento do banco
        $aiContext = self::getContext($contextSlug);
        if (!$aiContext) {
            $aiContext = self::getContext('geral');
        }

        // Busca exemplos de aprendizado anteriores (reduzido para economizar tokens)
        $learnedExamples = self::getLearnedExamples($contextSlug, $objective, 3);

        // Transcreve áudios nos bastidores para contexto completo
        $conversationHistory = self::transcribeAudiosForContext($conversationHistory);

        // Monta os prompts
        $systemPrompt = self::buildSystemPrompt($aiContext, $objective, $tone, $hasHistory, $learnedExamples);
        $userPrompt = self::buildUserPrompt($conversationHistory, $contactName, $contactPhone, $attendantNote, $objective, $hasHistory);

        // Chama OpenAI
        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            return [
                'success' => false,
                'error' => 'Chave de API OpenAI não configurada. Acesse Configurações > Configurações IA.',
            ];
        }

        try {
            $response = self::callOpenAI($apiKey, $systemPrompt, $userPrompt);
            return [
                'success' => true,
                'suggestions' => $response['suggestions'] ?? [],
                'qualification_questions' => $response['qualification_questions'] ?? [],
                'lead_summary' => $response['lead_summary'] ?? '',
                'has_history' => $hasHistory,
                'context_used' => $aiContext['name'] ?? $contextSlug,
                'learned_examples_count' => count($learnedExamples),
            ];
        } catch (\Exception $e) {
            error_log('[AISuggestReply] Erro: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erro ao gerar sugestões: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Registra aprendizado: salva a correção do atendente
     */
    public static function learn(array $params): array
    {
        $contextSlug = $params['context_slug'] ?? 'geral';
        $objective = $params['objective'] ?? '';
        $situationSummary = $params['situation_summary'] ?? '';
        $aiSuggestion = $params['ai_suggestion'] ?? '';
        $humanResponse = $params['human_response'] ?? '';
        $userId = $params['user_id'] ?? null;
        $conversationId = $params['conversation_id'] ?? null;

        if (empty($aiSuggestion) || empty($humanResponse)) {
            return ['success' => false, 'error' => 'Sugestão da IA e resposta humana são obrigatórias'];
        }

        // Para refinamentos, sempre salva (mesmo que similar) porque contém instruções valiosas
        $isRefinement = !empty($params['is_refinement']) && $params['is_refinement'] === true;
        
        // Só salva se houve diferença significativa (>10% de mudança) OU for refinamento
        $similarity = 0;
        similar_text($aiSuggestion, $humanResponse, $similarity);
        if (!$isRefinement && $similarity > 90) {
            return ['success' => true, 'saved' => false, 'reason' => 'Resposta muito similar à sugestão, não necessita aprendizado'];
        }

        $db = DB::getConnection();
        $stmt = $db->prepare("
            INSERT INTO ai_learned_responses 
            (context_slug, objective, situation_summary, ai_suggestion, human_response, user_id, conversation_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $contextSlug,
            $objective,
            $situationSummary ?: 'Sem resumo disponível',
            $aiSuggestion,
            $humanResponse,
            $userId,
            $conversationId,
        ]);

        return ['success' => true, 'saved' => true, 'id' => (int) $db->lastInsertId()];
    }

    /**
     * Gera 3 sugestões com contexto completo (modo híbrido)
     * Usa o suggest() mas mantém contexto para refinamentos
     */
    public static function suggestChat(array $params): array
    {
        $contextSlug = $params['context_slug'] ?? 'geral';
        $objective = $params['objective'] ?? 'first_contact';
        $tone = $params['tone'] ?? 'normal';
        $attendantNote = $params['attendant_note'] ?? '';
        $conversationHistory = $params['conversation_history'] ?? [];
        $contactName = $params['contact_name'] ?? '';
        $contactPhone = $params['contact_phone'] ?? '';
        $aiChatMessages = $params['ai_chat_messages'] ?? [];
        $hasHistory = !empty($conversationHistory);

        // Busca contexto de atendimento do banco
        $aiContext = self::getContext($contextSlug);
        if (!$aiContext) {
            $aiContext = self::getContext('geral');
        }

        // Busca exemplos de aprendizado anteriores
        $learnedExamples = self::getLearnedExamples($contextSlug, $objective, 3);

        // Transcreve áudios nos bastidores para contexto completo
        $conversationHistory = self::transcribeAudiosForContext($conversationHistory);
        // Propaga histórico transcrito para o chat() se for refinamento
        $params['conversation_history'] = $conversationHistory;

        // Se há histórico de chat IA OU refinamento, usa o método chat
        if (!empty($aiChatMessages) || !empty($params['user_prompt'] ?? '')) {
            // Para refinamentos, usa o método chat com contexto mantido
            return self::chat($params);
        }

        // Primeira geração: usa suggest() para obter 3 sugestões
        $systemPrompt = self::buildSystemPrompt($aiContext, $objective, $tone, $hasHistory, $learnedExamples);
        $userPrompt = self::buildUserPrompt($conversationHistory, $contactName, $contactPhone, $attendantNote, $objective, $hasHistory);

        // Chama OpenAI
        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            return [
                'success' => false,
                'error' => 'Chave de API OpenAI não configurada. Acesse Configurações > Configurações IA.',
            ];
        }

        try {
            $response = self::callOpenAI($apiKey, $systemPrompt, $userPrompt);
            return [
                'success' => true,
                'suggestions' => $response['suggestions'] ?? [],
                'qualification_questions' => $response['qualification_questions'] ?? [],
                'lead_summary' => $response['lead_summary'] ?? '',
                'has_history' => $hasHistory,
                'context_used' => $aiContext['name'] ?? $contextSlug,
                'learned_examples_count' => count($learnedExamples),
                'mode' => '3_suggestions' // Indica que são 3 sugestões
            ];
        } catch (\Exception $e) {
            error_log('[AISuggestReply] Erro: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erro ao gerar sugestões: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Chat conversacional com a IA — gera 1 resposta e permite refinamento
     * Recebe o histórico de mensagens do chat IA (não da conversa WhatsApp)
     */
    public static function chat(array $params): array
    {
        $contextSlug = $params['context_slug'] ?? 'geral';
        $objective = $params['objective'] ?? 'first_contact';
        $attendantNote = $params['attendant_note'] ?? '';
        $conversationHistory = $params['conversation_history'] ?? [];
        $contactName = $params['contact_name'] ?? '';
        $contactPhone = $params['contact_phone'] ?? '';
        $aiChatMessages = $params['ai_chat_messages'] ?? []; // histórico do chat com a IA
        $userPrompt = $params['user_prompt'] ?? ''; // REFINAMENTO DO USUÁRIO
        $currentDatetime = $params['current_datetime'] ?? null;
        $lastContactMessageAt = $params['last_contact_message_at'] ?? null;
        $hasHistory = !empty($conversationHistory);
        
        // Log do refinamento para debug
        if (!empty($userPrompt)) {
            error_log('[AI CHAT] REFINAMENTO DETECTADO: "' . substr($userPrompt, 0, 200) . '..."');
        }

        // Log obrigatório para debug
        error_log('[AI CHAT] conversation_history count: ' . count($conversationHistory));
        if (!empty($conversationHistory)) {
            $firstMsg = $conversationHistory[0]['text'] ?? '';
            error_log('[AI CHAT] first_history_message: "' . substr($firstMsg, 0, 100) . '..."');
        }

        $aiContext = self::getContext($contextSlug);
        if (!$aiContext) {
            $aiContext = self::getContext('geral');
        }

        $learnedExamples = self::getLearnedExamples($contextSlug, $objective, 5);

        // Transcreve áudios automaticamente nos bastidores para contexto completo
        $enhancedHistory = self::transcribeAudiosForContext($conversationHistory);

        $systemPrompt = self::buildChatSystemPrompt($aiContext, $objective, $hasHistory, $learnedExamples);
        $userContext = self::buildUserPrompt($enhancedHistory, $contactName, $contactPhone, $attendantNote, $objective, $hasHistory, $currentDatetime, $lastContactMessageAt);
        
        // Se há refinamento, adiciona ao contexto
        if (!empty($userPrompt)) {
            $userContext .= "\n\n## REFINAMENTO SOLICITADO\n" . $userPrompt;
        }
        
        // Log do contexto gerado para debug
        error_log('[AI CHAT] userContext length: ' . strlen($userContext));
        error_log('[AI CHAT] userContext preview: "' . substr($userContext, 0, 200) . '..."');

        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Chave de API OpenAI não configurada.'];
        }

        try {
            // Monta array de mensagens para OpenAI
            $messages = [['role' => 'system', 'content' => $systemPrompt]];

            if (empty($aiChatMessages)) {
                // Primeira mensagem: envia contexto do lead + histórico + pede resposta
                $fullContext = $userContext . "\n\nGere UMA resposta pronta para enviar via WhatsApp.";
                $messages[] = ['role' => 'user', 'content' => $fullContext];
            } else {
                // Conversa em andamento: MANTÉM o contexto original + adiciona histórico do chat IA
                // Importante: o contexto completo (histórico WhatsApp) sempre vai como primeira mensagem user
                $messages[] = ['role' => 'user', 'content' => $userContext . "\n\nGere UMA resposta pronta para enviar via WhatsApp."];
                
                // Adiciona o histórico do chat IA como mensagens subsequentes
                foreach ($aiChatMessages as $msg) {
                    $role = ($msg['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
                    $messages[] = ['role' => $role, 'content' => $msg['content'] ?? ''];
                }
            }

            $response = self::callOpenAIChat($apiKey, $messages);
            return [
                'success' => true,
                'message' => $response,
                'context_used' => $aiContext['name'] ?? $contextSlug,
                'learned_examples_count' => count($learnedExamples),
            ];
        } catch (\Exception $e) {
            error_log('[AISuggestReply] Chat erro: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erro: ' . $e->getMessage()];
        }
    }

    /**
     * Lista contextos de atendimento ativos
     */
    public static function listContexts(): array
    {
        $db = DB::getConnection();
        return $db->query("
            SELECT id, name, slug, description, sort_order
            FROM ai_contexts
            WHERE is_active = 1
            ORDER BY sort_order ASC, name ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Busca contexto por slug
     */
    private static function getContext(string $slug): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * FROM ai_contexts WHERE slug = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$slug]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Busca exemplos de aprendizado anteriores para enriquecer o prompt
     */
    private static function getLearnedExamples(string $contextSlug, string $objective, int $limit = 5): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT situation_summary, ai_suggestion, human_response
            FROM ai_learned_responses
            WHERE context_slug = ? AND objective = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$contextSlug, $objective, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Monta o system prompt para a IA
     */
    private static function buildSystemPrompt(array $aiContext, string $objective, string $tone, bool $hasHistory, array $learnedExamples): string
    {
        $objectiveLabel = self::OBJECTIVES[$objective] ?? $objective;
        $toneLabel = self::TONES[$tone] ?? $tone;

        $toneInstructions = match ($tone) {
            'short' => "Use frases muito curtas (máx 2-3 linhas por sugestão). Vá direto ao ponto.",
            'formal' => "Use linguagem mais formal e polida. Trate por 'senhor(a)' quando apropriado.",
            default => "Use tom profissional mas acessível. Frases médias, sem formalidade excessiva.",
        };

        $historyInstruction = $hasHistory
            ? "IMPORTANTE: Leia o histórico da conversa ANTES de sugerir. Não repita o que já foi dito. Continue a conversa de forma natural e contextualizada."
            : "Não há histórico de conversa. Gere uma mensagem de abertura inteligente que se apresente e inicie a conversa de forma natural.";

        // Monta seção de aprendizado
        $learningSection = '';
        if (!empty($learnedExamples)) {
            $learningSection = "\n\n## Aprendizado (respostas corrigidas por atendentes anteriormente)\nUse estes exemplos como referência de tom e estilo preferido pela equipe:\n";
            foreach ($learnedExamples as $i => $ex) {
                $num = $i + 1;
                $learningSection .= "\nExemplo {$num}:\n";
                $learningSection .= "Situação: {$ex['situation_summary']}\n";
                $learningSection .= "IA sugeriu: {$ex['ai_suggestion']}\n";
                $learningSection .= "Atendente corrigiu para: {$ex['human_response']}\n";
            }
            $learningSection .= "\nAprenda com estas correções: adapte seu estilo para se aproximar do que os atendentes preferem.";
        }

        // Monta seção de base de conhecimento
        $knowledgeSection = '';
        if (!empty($aiContext['knowledge_base'])) {
            $kb = $aiContext['knowledge_base'];
            // Limita a 3000 chars para não estourar tokens
            if (mb_strlen($kb) > 1500) {
                $kb = mb_substr($kb, 0, 1500) . '... [truncado]';
            }
            $knowledgeSection = "\n\n## Base de Conhecimento (informações do produto/serviço)\nUse estas informações para responder com precisão sobre o que oferecemos:\n\n{$kb}";
        }

        // Instruções específicas por objetivo
        $objectiveInstructions = match ($objective) {
            'follow_up' => "REGRAS OBRIGATÓRIAS PARA FOLLOW-UP: Não mencione valores, preços ou condições de pagamento — isso é exclusivo de 'Enviar proposta'. Não envie proposta comercial. Leia o histórico: qual pergunta ficou sem resposta? qual conteúdo foi enviado sem retorno? Foque nisso. Mensagens CURTAS (máx 3-4 linhas). Sem redundância — não repita a mesma ideia duas vezes.",
            'send_proposal' => "REGRAS PARA PROPOSTA: Apresente valores, condições e detalhes comerciais com clareza. Inclua um CTA claro.",
            'first_contact' => "REGRAS OBRIGATÓRIAS PARA PRIMEIRO CONTATO:\n1. SEMPRE se apresente como 'Charles da Pixel12 Digital' (ou variação natural como 'Oi, sou o Charles da Pixel12')\n2. Demonstre que ENTENDEU o negócio do cliente (mencione 2-3 pontos relevantes do segmento dele)\n3. Apresente o valor de forma CLARA e CORRETA (ex: 12x de R$ 97 significa DOZE parcelas de noventa e sete reais)\n4. Crie uma mensagem ENVOLVENTE que diferencie da concorrência (foque no que o cliente QUER alcançar, não em features genéricas)\n5. Seja CONVERSACIONAL e HUMANO, não robótico\n6. Termine com próximo passo claro (chamada ou pergunta de qualificação)",
            'qualify' => "REGRAS PARA QUALIFICAÇÃO: Faça perguntas abertas. Máximo 1-2 perguntas por mensagem. Não mencione valores ainda.",
            'close_deal' => "REGRAS OBRIGATÓRIAS PARA FECHAR NEGÓCIO: LEIA O HISTÓRICO — identifique o que JÁ foi enviado (proposta, valores, condições). NUNCA repita o que já foi enviado. Sonde o fechamento: pergunte se o valor está dentro do esperado, se ficou alguma dúvida, o que falta para decidir. Mensagem CURTA (2-4 linhas). A observação do atendente é a instrução principal — siga-a à risca.",
            'answer_question' => "REGRAS PARA RESPONDER DÚVIDA: Leia o histórico e identifique a pergunta específica. Responda de forma direta. Após responder, faça UMA pergunta para manter o engajamento.",
            default => '',
        };

        $prompt = <<<PROMPT
{$aiContext['system_prompt']}
{$knowledgeSection}
{$learningSection}

## Sua tarefa agora
Objetivo do atendente: {$objectiveLabel}
Tom desejado: {$toneLabel}

{$toneInstructions}

{$historyInstruction}

{$objectiveInstructions}

## Formato de resposta (OBRIGATÓRIO: retorne APENAS JSON válido)
{
  "suggestions": [
    {"label": "Curta", "text": "mensagem curta e direta"},
    {"label": "Padrão", "text": "mensagem com tom padrão"},
    {"label": "Persuasiva", "text": "mensagem mais elaborada e persuasiva"}
  ],
  "qualification_questions": ["pergunta 1", "pergunta 2", "pergunta 3"],
  "lead_summary": "resumo breve do que o contato quer/precisa"
}

Regras:
- Sempre gere exatamente 3 sugestões
- As sugestões devem ser mensagens prontas para enviar via WhatsApp
- Não use markdown (sem **, ##, etc.) — apenas texto plano com quebras de linha
- As perguntas de qualificação devem ser relevantes ao contexto
- Se não houver histórico, o lead_summary deve dizer "Primeiro contato — sem histórico disponível"
PROMPT;

        return $prompt;
    }

    /**
     * Monta system prompt para modo chat conversacional (1 resposta + refinamento)
     */
    private static function buildChatSystemPrompt(array $aiContext, string $objective, bool $hasHistory, array $learnedExamples): string
    {
        $objectiveLabel = self::OBJECTIVES[$objective] ?? $objective;

        $learningSection = '';
        if (!empty($learnedExamples)) {
            $learningSection = "\n\n## Aprendizado (estilo preferido pela equipe)\n";
            foreach ($learnedExamples as $i => $ex) {
                $num = $i + 1;
                $learningSection .= "\nExemplo {$num}:\n";
                $learningSection .= "IA sugeriu: {$ex['ai_suggestion']}\n";
                $learningSection .= "Atendente preferiu: {$ex['human_response']}\n";
            }
            $learningSection .= "\nAdapte seu estilo para se aproximar do que os atendentes preferem.";
        }

        $knowledgeSection = '';
        if (!empty($aiContext['knowledge_base'])) {
            $kb = $aiContext['knowledge_base'];
            if (mb_strlen($kb) > 1500) {
                $kb = mb_substr($kb, 0, 1500) . '... [truncado]';
            }
            $knowledgeSection = "\n\n## Base de Conhecimento\n{$kb}";
        }

        $historyInstruction = $hasHistory
            ? "Há histórico de conversa com o contato. LEIA O HISTÓRICO COMPLETO antes de escrever qualquer coisa — especialmente a ÚLTIMA mensagem do contato, pois é o ponto de partida obrigatório da sua resposta.\n\n🔴 ATENÇÃO CRÍTICA PARA CONVERSAS FLUÍDAS:\n- Se a última mensagem do contato foi enviada há MINUTOS (não horas/dias), trata-se de uma conversa em TEMPO REAL\n- Em conversas fluídas, o contato está AGUARDANDO sua resposta AGORA\n- NÃO faça follow-up genérico ('ainda tem interesse?', 'viu minha mensagem?') — isso é INADEQUADO\n- Responda DIRETAMENTE ao que o contato disse, como se fosse uma conversa presencial\n- Se o contato fez uma pergunta, RESPONDA a pergunta\n- Se o contato deu uma informação, RECONHEÇA e avance a conversa\n- Verifique os timestamps: se há mensagens recentes do atendente (últimos minutos), o cliente JÁ recebeu essas informações — NÃO as repita"
            : "Primeiro contato — gere uma mensagem de abertura.";

        // Instruções específicas por objetivo
        $objectiveInstructions = match ($objective) {
            'follow_up' => <<<OBJ
## Regras OBRIGATÓRIAS para Follow-up
- PASSO 1 OBRIGATÓRIO: Leia o histórico do início ao fim. Identifique a ÚLTIMA mensagem do contato — é ela que define o que você deve responder.
- PASSO 2: Identifique o estado atual da conversa: o cliente disse que vai verificar algo? Pediu um prazo? Disse que vai retornar? Ficou com dúvida?
- PASSO 3: Gere uma mensagem que dê continuidade EXATA a esse estado — não ignore o que o cliente disse por último.
- Se o cliente disse "vou verificar e te chamo": pergunte se já conseguiu verificar, se precisa de mais informações para decidir.
- Se o cliente fez uma pergunta que ficou sem resposta: retome essa pergunta.
- Se o cliente demonstrou interesse mas não avançou: pergunte o que falta para avançar.
- NUNCA gere uma mensagem genérica de "só queria saber se ainda tem interesse" se o histórico mostra uma conversa em andamento com contexto claro.
- Mensagens CURTAS: máximo 3-4 linhas. Sem redundância.
OBJ,
            'send_proposal' => <<<OBJ
## Regras para Enviar Proposta
- Aqui SIM é o momento de apresentar valores, condições e detalhes comerciais
- Seja claro sobre o que está sendo oferecido, o valor e as condições
- Inclua um CTA claro (próximo passo)
OBJ,
            'first_contact' => <<<OBJ
## Regras OBRIGATÓRIAS para Primeiro Contato
1. **APRESENTAÇÃO OBRIGATÓRIA**: SEMPRE se apresente como "Charles da Pixel12 Digital" (ou variação natural)
2. **ENTENDIMENTO DO NEGÓCIO**: Demonstre que você ENTENDEU o negócio do cliente:
   - Mencione 2-3 pontos relevantes do segmento (ex: para moda = mostrar produtos, para alimentos = controle de estoque)
   - Foque no que o cliente QUER alcançar (vender, alcançar clientes, facilitar pedidos)
   - Seja ESPECÍFICO ao negócio dele, não genérico
3. **VALORES CORRETOS**: Se mencionar preço, seja PRECISO:
   - "12x de R$ 97" = DOZE parcelas de NOVENTA E SETE reais cada
   - "R$ 197 em 12x" = valor total de R$ 197 parcelado em 12 vezes
   - NUNCA inverta ou confunda os valores
4. **MENSAGEM ENVOLVENTE**: Diferencie-se da concorrência:
   - Não use frases genéricas ("aumente suas vendas", "solução completa")
   - Foque em BENEFÍCIOS PRÁTICOS para o negócio específico
   - Seja CONVERSACIONAL, não corporativo
5. **PRÓXIMO PASSO**: Termine com CTA claro (agendar chamada OU pergunta de qualificação)
OBJ,
            'qualify' => <<<OBJ
## Regras para Qualificação
- Faça perguntas abertas para entender a necessidade real do cliente
- Máximo 1-2 perguntas por mensagem — não sobrecarregue
- Não mencione valores ainda
OBJ,
            'close_deal' => <<<OBJ
## Regras OBRIGATÓRIAS para Fechar Negócio
- LEIA O HISTÓRICO COMPLETO antes de escrever qualquer coisa. Identifique o que JÁ foi enviado (proposta, valores, condições, links) e o que o cliente respondeu
- NUNCA repita informações que já foram enviadas na conversa (valores, condições, detalhes do produto) — o cliente já as recebeu
- Seu papel agora é SONDAR e QUALIFICAR o fechamento: perguntar se o valor está dentro do esperado, se ficou alguma dúvida, o que falta para decidir
- Se o atendente deixou uma observação, essa é sua INSTRUÇÃO PRINCIPAL — siga-a à risca
- Mensagem CURTA e DIRETA: 2-4 linhas. Uma pergunta de qualificação de fechamento por vez
- Exemplos de perguntas de fechamento: "O valor ficou dentro do que você pretendia investir?", "Ficou alguma dúvida sobre o que está incluído?", "O que falta para você tomar a decisão?"
- Nunca reapresente a proposta inteira — ela já foi enviada
OBJ,
            'answer_question' => <<<OBJ
## Regras OBRIGATÓRIAS para Responder Dúvida

🎯 IDENTIFICAÇÃO DA PERGUNTA CENTRAL:
1. Leia a última mensagem do contato COMPLETAMENTE
2. Identifique qual é a PERGUNTA CENTRAL (geralmente vem com "?", "como funciona", "qual é", "quanto custa")
3. Separe PERGUNTAS de COMENTÁRIOS SECUNDÁRIOS:
   - Pergunta central: "Em relação a frete, como funciona?"
   - Comentário secundário: "Vou ver com meu gerente", "Vou pensar", "Depois te falo"
4. SEMPRE responda a pergunta central PRIMEIRO, mesmo que haja comentários secundários

💡 RESPOSTA TÉCNICA COM CONHECIMENTO PRÁTICO:
Se a pergunta é sobre funcionalidades/processos (frete, pagamento, integração, etc):
- NÃO fique vago ("podemos personalizar", "vamos discutir")
- Responda com INFORMAÇÕES PRÁTICAS e EXEMPLOS CONCRETOS
- Use conhecimento de MELHORES PRÁTICAS do segmento (e-commerce, SaaS, etc)
- Explique COMO FUNCIONA na prática, com opções disponíveis

Exemplo CORRETO para "como funciona o frete?":
✅ "Sobre o frete: você tem total flexibilidade! Fazemos a integração com qualquer transportadora (Correios, Jadlog, etc). Você pode:
- Oferecer frete grátis (absorve o custo)
- Repassar o valor real ao cliente
- Criar regras (ex: frete grátis acima de R$ 200)
- Combinar: frete grátis em algumas regiões, pago em outras

O que faz mais sentido pro seu negócio?"

Exemplo ERRADO:
❌ "Podemos discutir mais sobre como personalizar o frete para suas necessidades. Vou aguardar!"

⚠️ NUNCA ignore a pergunta central para focar em comentários secundários!

Após responder a pergunta:
- Reconheça brevemente o comentário secundário se houver ("Perfeito, converse com seu gerente!")
- Faça UMA pergunta de qualificação relacionada à resposta dada
OBJ,
            default => "Objetivo atual: {$objectiveLabel}. Adapte a mensagem ao contexto da conversa.",
        };

        return <<<PROMPT
{$aiContext['system_prompt']}
{$knowledgeSection}
{$learningSection}

## Modo de operação
Você está em modo CHAT com o atendente. Seu trabalho:
1. Gere UMA resposta pronta para enviar ao cliente via WhatsApp
2. O atendente pode pedir ajustes: "mude o tom", "mais curto", "mencione X", "mais informal"
3. O atendente PODE CORRIGIR PREMISSAS: "Eu ainda não enviei o projeto", "Não tem link na conversa", "Mude para primeiro contato"
4. Você ajusta e gera uma nova versão COMPLETAMENTE corrigida
5. Quando o atendente aprovar, ele usa a resposta

## ANÁLISE TEMPORAL OBRIGATÓRIA (antes de gerar qualquer resposta)
1. Verifique os timestamps de TODAS as mensagens no histórico
2. Identifique a ÚLTIMA mensagem do contato e quando foi enviada
3. Identifique a ÚLTIMA mensagem do atendente (se houver) e quando foi enviada
4. Calcule o tempo decorrido entre a última mensagem do contato e o momento atual
5. Se a última mensagem do contato foi há MINUTOS (< 60 min):
   - Trata-se de conversa FLUÍDA em tempo real
   - O contato está ATIVO e aguardando resposta
   - Responda DIRETAMENTE ao que ele disse, sem rodeios
   - NÃO pergunte se ele viu algo ou se ainda tem interesse
6. Se o atendente enviou mensagens APÓS a última do contato:
   - O cliente JÁ recebeu essas informações
   - NÃO repita valores, links, detalhes que já foram enviados
   - Avance a conversa para o próximo passo

## ANTI-REPETIÇÃO: VARIAÇÃO DE FRASES DE ENCERRAMENTO
🚫 PROBLEMA CRÍTICO: Frases repetitivas cansam o cliente e soam robóticas.

FRASES PROIBIDAS DE REPETIR em conversas fluídas (< 60 min entre mensagens):
- "Estou à disposição" / "À disposição"
- "Se precisar de mais detalhes" / "Se precisar de algo"
- "Se tiver dúvida" / "Qualquer dúvida"
- "Pode me chamar" / "Qualquer coisa, me chama"
- "Podemos agendar uma chamada"
- "Fico no aguardo" / "Aguardo retorno"

✅ REGRAS DE VARIAÇÃO:
1. Analise as últimas 5 mensagens do atendente
2. Se uma frase de encerramento foi usada 2+ vezes → VARIE ou OMITA
3. Em conversas fluídas, muitas vezes NÃO é necessário encerramento formal
4. Termine direto após responder a pergunta/dar a informação
5. Se precisar encerrar, use variações naturais:
   - "Vou aguardar sua decisão"
   - "Me avise o que achar melhor"
   - "Fico por aqui então"
   - "Qualquer coisa, só chamar"
   - Ou simplesmente termine sem frase de encerramento

🎯 OBJETIVO: Soar HUMANO, não robótico. Conversas naturais não repetem as mesmas frases a cada mensagem.

Objetivo atual: {$objectiveLabel}
{$historyInstruction}

🎭 TOM E NATURALIDADE:
- Seja conversacional, não formal demais
- Evite soar como chatbot com frases padronizadas
- Em conversas fluídas, seja direto e natural como em uma conversa presencial
- Não force encerramentos educados a cada mensagem — isso cansa

## 🎯 ANÁLISE CRÍTICA DE PERGUNTAS (OBRIGATÓRIO)
Antes de gerar qualquer resposta, execute este checklist:

1️⃣ IDENTIFICAR PERGUNTA CENTRAL:
   - Procure por "?", "como funciona", "qual é", "quanto custa", "como faz"
   - A pergunta central é o que o cliente QUER SABER AGORA
   - Exemplo: "Em relação a frete, como funciona?" ← PERGUNTA CENTRAL

2️⃣ SEPARAR COMENTÁRIOS SECUNDÁRIOS:
   - "Vou ver com meu gerente" ← comentário, NÃO é a pergunta
   - "Vou pensar" ← comentário, NÃO é a pergunta
   - "Depois te falo" ← comentário, NÃO é a pergunta
   - Estes são apenas sinalizações de processo interno do cliente

3️⃣ PRIORIDADE ABSOLUTA:
   - SEMPRE responda a pergunta central PRIMEIRO
   - NUNCA ignore a pergunta para focar no comentário
   - Comentários secundários podem ser reconhecidos BREVEMENTE após responder

4️⃣ RESPOSTA TÉCNICA COM CONHECIMENTO:
   - Se a pergunta é sobre COMO FUNCIONA algo (frete, pagamento, integração):
     ✅ Responda com INFORMAÇÕES PRÁTICAS e OPÇÕES CONCRETAS
     ✅ Use conhecimento de MELHORES PRÁTICAS do segmento
     ✅ Dê EXEMPLOS REAIS de como funciona
     ❌ NÃO seja vago ("podemos personalizar", "vamos discutir depois")
     ❌ NÃO deixe para depois se pode responder agora

5️⃣ ESTRUTURA DA RESPOSTA:
   a) Responda a pergunta central com informações práticas
   b) Se houver comentário secundário, reconheça brevemente
   c) Faça UMA pergunta de qualificação relacionada

EXEMPLO REAL:
Cliente: "Em relação a frete, como funciona? Vou ver com meu gerente"

❌ ERRADO: "Enquanto você verifica com seu gerente, podemos discutir o frete depois"

✅ CORRETO: "Sobre o frete: você tem total flexibilidade! Fazemos integração com qualquer transportadora (Correios, Jadlog, etc). Você pode:
- Oferecer frete grátis (você absorve o custo)
- Repassar o valor real ao cliente
- Criar regras (ex: frete grátis acima de R$ 200)
- Combinar estratégias por região

Perfeito conversar com seu gerente! O que faz mais sentido pro negócio de vocês?"

{$objectiveInstructions}

## OBSERVAÇÃO DO ATENDENTE = PRIORIDADE MÁXIMA
- A observação do atendente é sua instrução principal. Ela sobrepõe qualquer inferência que você faria sozinho
- Se o atendente disse "perguntar se ficou dúvida e se o valor está dentro do esperado": faça EXATAMENTE isso, nada mais
- Se o atendente disser "não enviei", "ainda não", "não tem link": IGNORE a premissa anterior
- Se o atendente corrigir informações: APLIQUE a correção na nova mensagem
- NUNCA repita um erro que o atendente corrigiu

Regras gerais:
- Responda SEMPRE com a mensagem pronta para enviar (texto plano, sem markdown)
- Se o atendente pedir ajuste, gere a versão corrigida completa (não apenas o trecho alterado)
- Se o atendente corrigir uma premissa, REESCREVA a mensagem sem o erro
- Seja conciso nas explicações — o foco é a mensagem para o cliente
- Se o atendente perguntar algo, responda brevemente e inclua a mensagem atualizada
- Não use **, ##, ``` ou qualquer formatação markdown — apenas texto plano com quebras de linha
PROMPT;
    }

    /**
     * Chama OpenAI com array de mensagens (multi-turn)
     */
    private static function callOpenAIChat(string $apiKey, array $messages): string
    {
        $model = Env::get('OPENAI_MODEL', 'gpt-4.1-mini');
        $temperature = (float) Env::get('OPENAI_TEMPERATURE', '0.8');
        $maxTokens = (int) Env::get('OPENAI_MAX_TOKENS', '1000');
        $maxTokens = max($maxTokens, 600);

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('Erro de conexão: ' . $error);
        }
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            throw new \Exception('Erro OpenAI: ' . ($errorData['error']['message'] ?? "HTTP {$httpCode}"));
        }

        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        if (empty($content)) {
            throw new \Exception('Resposta vazia da OpenAI');
        }

        return $content;
    }

    /**
     * Monta o user prompt com histórico e observações
     */
    private static function buildUserPrompt(array $history, string $contactName, string $contactPhone, string $attendantNote, string $objective, bool $hasHistory, ?string $currentDatetime = null, ?string $lastContactMessageAt = null): string
    {
        error_log('[AI PROMPT] ========== INÍCIO MONTAGEM DE PROMPT ==========');
        error_log('[AI PROMPT] hasHistory: ' . ($hasHistory ? 'SIM' : 'NÃO'));
        error_log('[AI PROMPT] history count: ' . count($history));
        error_log('[AI PROMPT] contactName: ' . ($contactName ?: 'vazio'));
        error_log('[AI PROMPT] attendantNote: ' . ($attendantNote ? substr($attendantNote, 0, 100) . '...' : 'vazio'));
        
        $parts = [];

        if (!empty($contactName)) {
            $parts[] = "Nome do contato: {$contactName}";
        }
        if (!empty($contactPhone)) {
            $parts[] = "Telefone: {$contactPhone}";
        }

        // Contexto temporal — quanto tempo passou desde a última mensagem do contato
        if (!empty($lastContactMessageAt)) {
            try {
                $now = !empty($currentDatetime) ? new \DateTime($currentDatetime) : new \DateTime();
                $lastMsg = new \DateTime($lastContactMessageAt);
                $diff = $now->getTimestamp() - $lastMsg->getTimestamp();

                if ($diff < 0) $diff = 0;

                if ($diff < 3600) {
                    // Menos de 1 hora
                    $minutes = (int) round($diff / 60);
                    $minutes = max($minutes, 1);
                    $elapsedLabel = "há {$minutes} minuto" . ($minutes > 1 ? 's' : '');
                    $temporalNote = "⏱️ CONTEXTO TEMPORAL: A última mensagem do contato foi enviada {$elapsedLabel} (conversa em andamento no mesmo dia, minutos atrás). NÃO faça follow-up genérico — o contato acabou de responder. Dê continuidade direta ao que ele disse, sem perguntar 'se ainda tem interesse' ou 'se viu algo'.";
                } elseif ($diff < 86400) {
                    // Menos de 24 horas
                    $hours = (int) round($diff / 3600);
                    $hours = max($hours, 1);
                    $elapsedLabel = "há {$hours} hora" . ($hours > 1 ? 's' : '');
                    $temporalNote = "⏱️ CONTEXTO TEMPORAL: A última mensagem do contato foi enviada {$elapsedLabel} (mesmo dia). Retome a conversa de forma natural, sem ser invasivo. Dê continuidade ao que foi discutido.";
                } elseif ($diff < 172800) {
                    // Entre 1 e 2 dias
                    $temporalNote = "⏱️ CONTEXTO TEMPORAL: A última mensagem do contato foi enviada ontem. Retome a conversa de forma amigável, lembrando brevemente o contexto se necessário.";
                } elseif ($diff < 604800) {
                    // Entre 2 e 7 dias
                    $days = (int) round($diff / 86400);
                    $temporalNote = "⏱️ CONTEXTO TEMPORAL: A última mensagem do contato foi enviada há {$days} dias. É um follow-up legítimo — retome com leveza, sem pressão.";
                } else {
                    // Mais de 7 dias
                    $days = (int) round($diff / 86400);
                    $temporalNote = "⏱️ CONTEXTO TEMPORAL: A última mensagem do contato foi enviada há {$days} dias. Reative a conversa com contexto — relembre brevemente o que foi discutido antes de perguntar sobre o andamento.";
                }

                $parts[] = "\n{$temporalNote}";
            } catch (\Exception $e) {
                // Ignora erro de parsing de data
            }
        }

        // Observação do atendente vem ANTES do histórico para ter peso máximo
        if (!empty($attendantNote)) {
            $parts[] = "\n🔴 INSTRUÇÃO PRIORITÁRIA DO ATENDENTE - PRIORIDADE ABSOLUTA 🔴";
            $parts[] = "Esta é a VERDADE ABSOLUTA que você DEVE seguir. Sobrepõe QUALQUER inferência ou sugestão.";
            $parts[] = "Use EXATAMENTE as informações, valores e CTAs especificados abaixo:";
            $parts[] = "\n{$attendantNote}";
            $parts[] = "\n⚠️ REGRAS OBRIGATÓRIAS:";
            $parts[] = "- Se o atendente especificou um valor (ex: '12 vezes de 97'), use EXATAMENTE esse formato";
            $parts[] = "- Se o atendente especificou um CTA (ex: 'próximo passo seria fazermos uma chamada'), use EXATAMENTE esse CTA";
            $parts[] = "- Se o atendente especificou informações sobre o cliente, use EXATAMENTE essas informações";
            $parts[] = "- NUNCA altere, interprete ou 'melhore' o que o atendente especificou";
            $parts[] = "- A observação é sua INSTRUÇÃO PRINCIPAL - siga à risca\n";
        }

        if ($hasHistory && !empty($history)) {
            $parts[] = "\n--- HISTÓRICO DA CONVERSA (leia com atenção — não repita o que já foi dito) ---";
            $parts[] = "⚠️ REGRA CRÍTICA: A ÚLTIMA MENSAGEM DO CONTATO é o ponto de partida OBRIGATÓRIO da sua resposta. Responda DIRETAMENTE ao que o contato disse por último.";
            
            // Limita a últimas 15 mensagens para ter contexto suficiente
            $recentHistory = array_slice($history, -15);
            
            // Detecta padrões repetitivos nas últimas mensagens do atendente
            $repetitivePhrases = [];
            $recentAgentMessages = [];
            foreach ($recentHistory as $msg) {
                if (($msg['direction'] ?? 'in') === 'out') {
                    $text = $msg['text'] ?? $msg['message'] ?? $msg['content'] ?? '';
                    if (!empty($text)) {
                        $recentAgentMessages[] = mb_strtolower($text);
                    }
                }
            }
            
            // Padrões comuns de encerramento/disponibilidade
            $commonPatterns = [
                'estou à disposição',
                'à disposição',
                'se precisar de mais',
                'se precisar de algo',
                'se tiver dúvida',
                'se tiver alguma dúvida',
                'qualquer dúvida',
                'qualquer coisa',
                'pode me chamar',
                'pode chamar',
                'agendar uma chamada',
                'podemos agendar',
                'quiser saber mais',
                'se quiser',
                'fico no aguardo',
                'aguardo retorno',
                'aguardo seu retorno'
            ];
            
            foreach ($commonPatterns as $pattern) {
                $count = 0;
                foreach ($recentAgentMessages as $agentMsg) {
                    if (mb_strpos($agentMsg, $pattern) !== false) {
                        $count++;
                    }
                }
                if ($count >= 2) {
                    $repetitivePhrases[] = $pattern;
                }
            }
            
            // Se detectou frases repetitivas, adiciona aviso
            if (!empty($repetitivePhrases)) {
                $phrasesList = implode('", "', $repetitivePhrases);
                $parts[] = "";
                $parts[] = "🚫 ALERTA DE REPETIÇÃO DETECTADA:";
                $parts[] = "As seguintes frases foram usadas 2+ vezes nas últimas mensagens do atendente: \"{$phrasesList}\"";
                $parts[] = "Em conversas FLUÍDAS (mensagens com minutos de diferença), NÃO use essas frases novamente.";
                $parts[] = "VARIE o encerramento ou OMITA completamente se não for necessário.";
                $parts[] = "Exemplos de variação: 'Vou aguardar', 'Me avise quando decidir', 'Fico por aqui', ou simplesmente termine direto após responder.";
                $parts[] = "";
            }
            
            // Identifica a última mensagem do contato para destacar
            $lastContactIndex = -1;
            for ($i = count($recentHistory) - 1; $i >= 0; $i--) {
                if (($recentHistory[$i]['direction'] ?? 'in') !== 'out') {
                    $lastContactIndex = $i;
                    break;
                }
            }
            
            foreach ($recentHistory as $idx => $msg) {
                $direction = ($msg['direction'] ?? 'in') === 'out' ? 'Atendente' : 'Contato';
                $text = $msg['text'] ?? $msg['message'] ?? $msg['content'] ?? '';
                $timestamp = $msg['created_at'] ?? '';
                
                error_log('[AI PROMPT] Mensagem #' . $idx . ' - direction: ' . $direction . ', hasText: ' . (!empty($text) ? 'SIM' : 'NÃO') . ', hasMedia: ' . (!empty($msg['media']) ? 'SIM' : 'NÃO'));

                // Se não há texto, tenta extrair transcrição do campo media
                if (empty($text) && !empty($msg['media']) && is_array($msg['media'])) {
                    error_log('[AI PROMPT] Tentando extrair transcrição da mídia...');
                    foreach ($msg['media'] as $mediaIdx => $media) {
                        $mediaType = $media['media_type'] ?? $media['type'] ?? '';
                        $transcription = $media['transcription'] ?? '';
                        error_log('[AI PROMPT] Mídia #' . $mediaIdx . ' - type: ' . $mediaType . ', hasTranscription: ' . (!empty($transcription) ? 'SIM' : 'NÃO'));
                        if (!empty($transcription)) {
                            $text = "[Áudio: {$transcription}]";
                            error_log('[AI PROMPT] ✅ Transcrição incluída no prompt: "' . substr($transcription, 0, 100) . '..."');
                            break;
                        } elseif (in_array($mediaType, ['audio', 'ptt', 'voice'])) {
                            $text = "[Áudio sem transcrição disponível]";
                            error_log('[AI PROMPT] ⚠️ Áudio sem transcrição');
                            break;
                        }
                    }
                }

                if (!empty($text)) {
                    // Formata timestamp se disponível
                    $timeStr = '';
                    if (!empty($timestamp)) {
                        try {
                            $dt = new \DateTime($timestamp);
                            $dt->setTimezone(new \DateTimeZone('America/Sao_Paulo'));
                            $timeStr = ' [' . $dt->format('d/m H:i') . ']';
                        } catch (\Exception $e) {
                            // Ignora erro de parsing
                        }
                    }
                    
                    // Destaca a última mensagem do contato
                    if ($idx === $lastContactIndex) {
                        $parts[] = "";
                        $parts[] = ">>> ÚLTIMA MENSAGEM DO CONTATO (RESPONDA A ESTA){$timeStr}:";
                        $parts[] = "{$direction}: {$text}";
                        $parts[] = ">>> Esta é a mensagem mais recente do contato. Sua resposta DEVE dar continuidade direta a ela.";
                        $parts[] = "";
                    } else {
                        $parts[] = "{$direction}{$timeStr}: {$text}";
                    }
                }
            }
            $parts[] = "--- FIM DO HISTÓRICO ---";
            $parts[] = "ATENÇÃO CRÍTICA: Tudo que o Atendente enviou acima JÁ foi recebido pelo cliente. NÃO repita essas informações. Responda APENAS baseado na última mensagem do contato.";
        } else {
            $parts[] = "\n[Sem histórico de conversa — primeiro contato ou conversa nova]";
        }

        $parts[] = "\nGere as 3 sugestões de resposta agora.";

        return implode("\n", $parts);
    }

    /**
     * Chama API do OpenAI
     */
    private static function callOpenAI(string $apiKey, string $systemPrompt, string $userPrompt): array
    {
        $model = Env::get('OPENAI_MODEL', 'gpt-4.1-mini');
        $temperature = (float) Env::get('OPENAI_TEMPERATURE', '0.8');
        $maxTokens = (int) Env::get('OPENAI_MAX_TOKENS', '1000');

        // Otimizado para economizar tokens
        $maxTokens = max($maxTokens, 600);

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'response_format' => ['type' => 'json_object'],
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('Erro de conexão com OpenAI: ' . $error);
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error']['message'] ?? "HTTP {$httpCode}";
            throw new \Exception('Erro OpenAI: ' . $errorMsg);
        }

        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '';

        if (empty($content)) {
            throw new \Exception('Resposta vazia da OpenAI');
        }

        $parsed = json_decode($content, true);
        if (!$parsed || !isset($parsed['suggestions'])) {
            throw new \Exception('Resposta da IA não está no formato esperado');
        }

        return $parsed;
    }

    /**
     * Obtém e descriptografa a API key
     */
    private static function getApiKey(): string
    {
        $apiKeyRaw = Env::get('OPENAI_API_KEY');
        if (empty($apiKeyRaw)) {
            return '';
        }

        $apiKeyRaw = trim($apiKeyRaw);

        if (strpos($apiKeyRaw, 'sk-') === 0 || strpos($apiKeyRaw, 'pk-') === 0) {
            return $apiKeyRaw;
        }

        if (strlen($apiKeyRaw) > 100) {
            try {
                $decrypted = CryptoHelper::decrypt($apiKeyRaw);
                if (!empty($decrypted) && (strpos($decrypted, 'sk-') === 0 || strpos($decrypted, 'pk-') === 0)) {
                    return $decrypted;
                }
            } catch (\Exception $e) {
                error_log('[AISuggestReply] Erro ao descriptografar API key: ' . $e->getMessage());
                return '';
            }
        }

        return $apiKeyRaw;
    }

    /**
     * Transcreve áudios automaticamente nos bastidores para contexto da IA
     * 
     * @param array $conversationHistory Histórico da conversa
     * @return array Histórico com transcrições incluídas
     */
    private static function transcribeAudiosForContext(array $conversationHistory): array
    {
        error_log('[AI TRANSCRIBE] ========== INÍCIO TRANSCRIÇÃO DE ÁUDIOS ==========');
        error_log('[AI TRANSCRIBE] Total de mensagens no histórico: ' . count($conversationHistory));
        
        $enhancedHistory = [];
        $audioCount = 0;
        $transcribedCount = 0;
        
        foreach ($conversationHistory as $index => $message) {
            $enhancedMessage = $message;
            
            // Verifica se há mídia/áudio na mensagem
            if (isset($message['media']) && is_array($message['media'])) {
                error_log('[AI TRANSCRIBE] Mensagem #' . $index . ' tem mídia: ' . count($message['media']) . ' item(s)');
                $transcribedTexts = [];
                
                foreach ($message['media'] as $mediaIndex => $media) {
                    $mediaType = $media['media_type'] ?? 'unknown';
                    error_log('[AI TRANSCRIBE] Mídia #' . $mediaIndex . ' - Tipo: ' . $mediaType);
                    
                    // Se é áudio e não tem transcrição
                    if (in_array($mediaType, ['audio', 'ptt', 'voice'])) {
                        $audioCount++;
                        $hasTranscription = isset($media['transcription']) && !empty($media['transcription']);
                        $hasEventId = isset($media['event_id']);
                        
                        error_log('[AI TRANSCRIBE] Áudio detectado! hasTranscription: ' . ($hasTranscription ? 'SIM' : 'NÃO') . ', hasEventId: ' . ($hasEventId ? 'SIM' : 'NÃO'));
                        
                        if (!$hasTranscription && $hasEventId) {
                            error_log('[AI TRANSCRIBE] Tentando transcrever event_id: ' . $media['event_id']);
                            try {
                                // Transcreve nos bastidores
                                $result = \PixelHub\Services\AudioTranscriptionService::transcribeByEventId($media['event_id']);
                                
                                if ($result['success'] && !empty($result['transcription'])) {
                                    $transcribedTexts[] = $result['transcription'];
                                    $transcribedCount++;
                                    
                                    // Atualiza a mídia com a transcrição
                                    $media['transcription'] = $result['transcription'];
                                    $media['transcription_status'] = 'completed';
                                    
                                    error_log('[AI TRANSCRIBE] ✅ Transcrição OK: "' . substr($result['transcription'], 0, 100) . '..."');
                                } else {
                                    error_log('[AI TRANSCRIBE] ❌ Transcrição falhou: ' . ($result['error'] ?? 'Erro desconhecido'));
                                }
                            } catch (\Exception $e) {
                                error_log('[AI TRANSCRIBE] ❌ Erro na transcrição: ' . $e->getMessage());
                            }
                        } elseif ($hasTranscription) {
                            // Já tem transcrição
                            $transcribedTexts[] = $media['transcription'];
                            error_log('[AI TRANSCRIBE] ✅ Usando transcrição existente: "' . substr($media['transcription'], 0, 100) . '..."');
                        }
                    }
                }
                
                // Adiciona transcrições ao conteúdo da mensagem
                if (!empty($transcribedTexts)) {
                    $originalContent = $message['message'] ?? '';
                    $transcriptionText = implode(' | ', $transcribedTexts);
                    
                    if (!empty($originalContent)) {
                        $enhancedMessage['message'] = $originalContent . ' [Áudio: ' . $transcriptionText . ']';
                    } else {
                        $enhancedMessage['message'] = '[Áudio: ' . $transcriptionText . ']';
                    }
                    
                    error_log('[AI TRANSCRIBE] Mensagem enriquecida: "' . substr($enhancedMessage['message'], 0, 150) . '..."');
                    
                    // Atualiza a mídia na mensagem
                    $enhancedMessage['media'] = $message['media'];
                }
            }
            
            $enhancedHistory[] = $enhancedMessage;
        }
        
        error_log('[AI TRANSCRIBE] ========== FIM TRANSCRIÇÃO ==========');
        error_log('[AI TRANSCRIBE] Áudios encontrados: ' . $audioCount);
        error_log('[AI TRANSCRIBE] Áudios transcritos: ' . $transcribedCount);
        
        return $enhancedHistory;
    }

    /**
     * Analisa situação de cobrança de um tenant e retorna contexto estruturado
     * Reutiliza infraestrutura existente de billing_invoices
     * 
     * @param int $tenantId ID do tenant
     * @return array ['objective' => string, 'context' => string, 'invoices_data' => array]
     */
    public static function analyzeBillingContext(int $tenantId): array
    {
        error_log('[AI BILLING] ========== INÍCIO ANÁLISE DE COBRANÇA ==========');
        error_log('[AI BILLING] tenant_id recebido: ' . $tenantId);
        
        $db = DB::getConnection();

        // Sincroniza com Asaas ANTES de buscar faturas para obter valores atualizados com juros/multas
        error_log('[AI BILLING] Sincronizando com Asaas...');
        try {
            $syncResult = \PixelHub\Services\AsaasBillingService::syncInvoicesForTenant($tenantId);
            error_log('[AI BILLING] Sincronização OK! Criadas: ' . ($syncResult['created'] ?? 0) . ', Atualizadas: ' . ($syncResult['updated'] ?? 0));
        } catch (\Exception $e) {
            error_log('[AI BILLING] ERRO na sincronização: ' . $e->getMessage());
            // Continua mesmo se sincronização falhar - usa dados do banco
        }

        // Busca faturas pendentes e vencidas do tenant com detalhes de serviços
        $stmt = $db->prepare("
            SELECT 
                bi.id,
                bi.amount,
                bi.due_date,
                bi.status,
                bi.invoice_url,
                bi.asaas_payment_id,
                bi.description,
                bi.billing_type,
                DATEDIFF(CURDATE(), bi.due_date) as days_overdue,
                s.name as service_name
            FROM billing_invoices bi
            LEFT JOIN services s ON bi.project_id = s.id
            WHERE bi.tenant_id = ?
            AND bi.status IN ('pending', 'overdue')
            AND (bi.is_deleted IS NULL OR bi.is_deleted = 0)
            ORDER BY bi.due_date ASC
        ");
        $stmt->execute([$tenantId]);
        $invoices = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if (empty($invoices)) {
            return [
                'objective' => 'answer_question',
                'context' => 'Cliente não possui faturas pendentes ou vencidas.',
                'invoices_data' => [],
            ];
        }

        // Separa vencidas e a vencer
        $overdue = array_filter($invoices, fn($inv) => $inv['status'] === 'overdue');
        $pending = array_filter($invoices, fn($inv) => $inv['status'] === 'pending');

        $overdueCount = count($overdue);
        $pendingCount = count($pending);

        // Calcula total
        $totalAmount = array_sum(array_column($invoices, 'amount'));

        // Agrupa por tipo de serviço
        $servicesSummary = [];
        foreach ($invoices as $inv) {
            $serviceName = $inv['service_name'] ?: 'Serviço';
            if (!isset($servicesSummary[$serviceName])) {
                $servicesSummary[$serviceName] = ['count' => 0, 'amount' => 0];
            }
            $servicesSummary[$serviceName]['count']++;
            $servicesSummary[$serviceName]['amount'] += $inv['amount'];
        }

        // Monta resumo de serviços
        $servicesText = [];
        foreach ($servicesSummary as $name => $data) {
            $servicesText[] = "• {$data['count']}x {$name} - R$ " . number_format($data['amount'], 2, ',', '.');
        }

        // Monta lista de links de pagamento (apenas para cobrança e lembrete)
        $paymentLinks = [];
        foreach ($invoices as $inv) {
            $link = $inv['invoice_url'] ?: null;
            if ($link) {
                $paymentLinks[] = "• Fatura #{$inv['id']} - R$ " . number_format($inv['amount'], 2, ',', '.') . 
                                  " (Venc: " . date('d/m/Y', strtotime($inv['due_date'])) . "): {$link}";
            }
        }

        // Determina objetivo e monta contexto baseado na situação
        if ($overdueCount >= 3) {
            // CRÍTICO: 3+ faturas vencidas - RENEGOCIAÇÃO (SEM LINKS)
            $objective = 'billing_critical';
            $context = "SITUAÇÃO CRÍTICA DE COBRANÇA - RENEGOCIAÇÃO\n\n";
            $context .= "O cliente possui {$overdueCount} faturas vencidas";
            if ($pendingCount > 0) {
                $context .= " e {$pendingCount} a vencer";
            }
            $context .= ".\n";
            $context .= "Total em aberto: R$ " . number_format($totalAmount, 2, ',', '.') . "\n\n";
            
            $context .= "Resumo dos serviços em aberto:\n";
            $context .= implode("\n", $servicesText) . "\n\n";
            
            $context .= "INSTRUÇÕES PARA A MENSAGEM:\n";
            $context .= "1. Ser DIRETA e CLARA - sem formalidades excessivas\n";
            $context .= "2. Informar: '{$overdueCount} faturas vencidas, total R$ " . number_format($totalAmount, 2, ',', '.') . "'\n";
            $context .= "3. Listar serviços de forma simples: '{$overdueCount} mensalidades de hospedagem'\n";
            $context .= "4. **NÃO ENVIAR LINKS DE PAGAMENTO** (é renegociação, não cobrança simples)\n";
            $context .= "5. PRAZO CLARO: '48 horas para retorno ou regularização'\n";
            $context .= "6. CONSEQUÊNCIA CLARA: 'Após 48h, o site será removido da hospedagem'\n";
            $context .= "7. Mencionar: 'Haverá custos de reinstalação se quiser reativar depois'\n";
            $context .= "8. PERGUNTA DIRETA: 'Você quer regularizar ou prefere que a gente remova o projeto?'\n";
            $context .= "9. Tom: FIRME mas RESPEITOSO - sem ser agressivo\n";
            $context .= "10. Oferecer: 'Podemos conversar sobre formas de pagamento'\n";
            $context .= "11. IMPORTANTE: Mensagem CURTA e OBJETIVA - máximo 5-6 linhas\n";

        } elseif ($overdueCount > 0) {
            // COBRANÇA: 1-2 faturas vencidas - COM LINKS
            $objective = 'billing_collection';
            $context = "COBRANÇA DE FATURAS VENCIDAS\n\n";
            $context .= "O cliente possui {$overdueCount} fatura(s) vencida(s)";
            if ($pendingCount > 0) {
                $context .= " e {$pendingCount} a vencer";
            }
            $context .= ".\n";
            $context .= "Total em aberto: R$ " . number_format($totalAmount, 2, ',', '.') . "\n\n";
            
            $context .= "Resumo dos serviços:\n";
            $context .= implode("\n", $servicesText) . "\n\n";
            
            $context .= "INSTRUÇÕES PARA A MENSAGEM:\n";
            $context .= "1. Informar sobre as cobranças vencidas de forma clara\n";
            $context .= "2. Apresentar valor total atualizado: R$ " . number_format($totalAmount, 2, ',', '.') . "\n";
            $context .= "3. Solicitar regularização\n";
            $context .= "4. **ENVIAR os links de pagamento abaixo**\n";
            $context .= "5. Tom cordial mas direto\n\n";
            $context .= "Links de pagamento:\n" . implode("\n", $paymentLinks);

        } else {
            // LEMBRETE: apenas faturas a vencer - COM LINKS
            $objective = 'billing_reminder';
            $context = "LEMBRETE DE VENCIMENTO\n\n";
            $context .= "O cliente possui {$pendingCount} fatura(s) a vencer.\n";
            $context .= "Total: R$ " . number_format($totalAmount, 2, ',', '.') . "\n\n";
            
            $context .= "Serviços:\n";
            $context .= implode("\n", $servicesText) . "\n\n";
            
            $context .= "INSTRUÇÕES PARA A MENSAGEM:\n";
            $context .= "1. Lembrete amigável sobre as faturas que vencerão em breve\n";
            $context .= "2. Informar valor total e datas de vencimento\n";
            $context .= "3. **ENVIAR os links de pagamento abaixo**\n";
            $context .= "4. Tom cordial e prestativo\n\n";
            $context .= "Links de pagamento:\n" . implode("\n", $paymentLinks);
        }

        return [
            'objective' => $objective,
            'context' => $context,
            'invoices_data' => [
                'total_amount' => $totalAmount,
                'overdue_count' => $overdueCount,
                'pending_count' => $pendingCount,
                'services_summary' => $servicesSummary,
                'invoices' => $invoices,
            ],
        ];
    }
}
