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
        'first_contact'   => 'Primeiro contato',
        'qualify'         => 'Qualificar lead',
        'schedule_call'   => 'Agendar call/reunião',
        'answer_question' => 'Responder dúvida',
        'follow_up'       => 'Follow-up',
        'send_proposal'   => 'Enviar proposta',
        'close_deal'      => 'Fechar negócio',
        'support'         => 'Suporte técnico',
        'billing'         => 'Questão financeira',
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

        // Busca exemplos de aprendizado anteriores
        $learnedExamples = self::getLearnedExamples($contextSlug, $objective, 5);

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

        // Só salva se houve diferença significativa (>10% de mudança)
        $similarity = 0;
        similar_text($aiSuggestion, $humanResponse, $similarity);
        if ($similarity > 90) {
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

        $prompt = <<<PROMPT
{$aiContext['system_prompt']}
{$learningSection}

## Sua tarefa agora
Objetivo do atendente: {$objectiveLabel}
Tom desejado: {$toneLabel}

{$toneInstructions}

{$historyInstruction}

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
     * Monta o user prompt com histórico e observações
     */
    private static function buildUserPrompt(array $history, string $contactName, string $contactPhone, string $attendantNote, string $objective, bool $hasHistory): string
    {
        $parts = [];

        if (!empty($contactName)) {
            $parts[] = "Nome do contato: {$contactName}";
        }
        if (!empty($contactPhone)) {
            $parts[] = "Telefone: {$contactPhone}";
        }

        if ($hasHistory && !empty($history)) {
            $parts[] = "\n--- HISTÓRICO DA CONVERSA (últimas mensagens) ---";
            // Limita a últimas 20 mensagens para não estourar tokens
            $recentHistory = array_slice($history, -20);
            foreach ($recentHistory as $msg) {
                $direction = ($msg['direction'] ?? 'in') === 'out' ? 'Atendente' : 'Contato';
                $text = $msg['text'] ?? $msg['message'] ?? $msg['content'] ?? '';
                if (!empty($text)) {
                    $parts[] = "{$direction}: {$text}";
                }
            }
            $parts[] = "--- FIM DO HISTÓRICO ---";
        } else {
            $parts[] = "\n[Sem histórico de conversa — primeiro contato ou conversa nova]";
        }

        if (!empty($attendantNote)) {
            $parts[] = "\nObservação do atendente: {$attendantNote}";
        }

        $parts[] = "\nGere as 3 sugestões de resposta agora.";

        return implode("\n", $parts);
    }

    /**
     * Chama API do OpenAI
     */
    private static function callOpenAI(string $apiKey, string $systemPrompt, string $userPrompt): array
    {
        $model = Env::get('OPENAI_MODEL', 'gpt-4o-mini');
        $temperature = (float) Env::get('OPENAI_TEMPERATURE', '0.7');
        $maxTokens = (int) Env::get('OPENAI_MAX_TOKENS', '800');

        // Para sugestões, precisamos de mais tokens
        $maxTokens = max($maxTokens, 1000);

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
}
