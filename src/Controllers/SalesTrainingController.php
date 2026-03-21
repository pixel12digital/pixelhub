<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\Env;
use PixelHub\Core\CryptoHelper;

/**
 * Simulador de treinamento de vendas — prospecção ativa
 * O sistema age como o vendedor; o usuário valida e dá feedback.
 */
class SalesTrainingController extends Controller
{
    /**
     * GET /prospecting/training
     */
    public function index(): void
    {
        Auth::requireInternal();
        $this->view('prospecting.training', []);
    }

    /**
     * POST /prospecting/training/generate  (AJAX)
     * Recebe dados brutos do prospect e gera a primeira mensagem de abordagem.
     */
    public function generate(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        $prospectData = trim($_POST['prospect_data'] ?? '');
        if (empty($prospectData)) {
            echo json_encode(['success' => false, 'error' => 'Cole os dados do prospect para gerar a abordagem.']);
            return;
        }

        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            echo json_encode(['success' => false, 'error' => 'Chave OpenAI não configurada. Acesse Configurações > IA.']);
            return;
        }

        $systemPrompt = self::buildSystemPrompt();
        $userPrompt   = "Dados do prospect:\n\n{$prospectData}";

        try {
            $message = self::callOpenAI($apiKey, $systemPrompt, $userPrompt);
            echo json_encode(['success' => true, 'message' => $message]);
        } catch (\Exception $e) {
            error_log('[SalesTraining] Erro generate: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /prospecting/training/prospect  (AJAX)
     * Modo Simular Prospect: IA joga como prospect + avalia o vendedor
     */
    public function prospect(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $scenario     = trim($input['scenario'] ?? '');
        $chatHistory  = $input['chat_history'] ?? [];
        $salesMessage = trim($input['salesperson_message'] ?? '');

        if (empty($scenario)) {
            echo json_encode(['success' => false, 'error' => 'Selecione um cenário para simular.']);
            return;
        }

        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            echo json_encode(['success' => false, 'error' => 'Chave OpenAI não configurada.']);
            return;
        }

        $systemPrompt = self::buildProspectSystemPrompt($scenario);
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($chatHistory as $msg) {
            $role = ($msg['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
            $messages[] = ['role' => $role, 'content' => $msg['content'] ?? ''];
        }

        if (!empty($salesMessage)) {
            $messages[] = ['role' => 'user', 'content' => $salesMessage];
        } else {
            $messages[] = ['role' => 'user', 'content' => 'INICIAR_SIMULACAO'];
        }

        try {
            $raw = self::callOpenAIChat($apiKey, $messages);

            $prospectReply = '';
            $feedback      = '';

            if (preg_match('/---PROSPECT---(.*?)---FEEDBACK---(.*)$/s', $raw, $m)) {
                $prospectReply = trim($m[1]);
                $feedback      = trim($m[2]);
            } else {
                $prospectReply = trim($raw);
            }

            echo json_encode([
                'success'       => true,
                'prospect_reply' => $prospectReply,
                'feedback'      => $feedback,
            ]);
        } catch (\Exception $e) {
            error_log('[SalesTraining] Erro prospect: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /prospecting/training/chat  (AJAX)
     * Refinamento via chat: recebe histórico + feedback do treinador e retorna mensagem ajustada.
     */
    public function chat(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $chatHistory  = $input['chat_history'] ?? [];
        $userMessage  = trim($input['user_message'] ?? '');
        $prospectData = trim($input['prospect_data'] ?? '');

        if (empty($userMessage)) {
            echo json_encode(['success' => false, 'error' => 'Mensagem vazia.']);
            return;
        }

        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            echo json_encode(['success' => false, 'error' => 'Chave OpenAI não configurada.']);
            return;
        }

        $systemPrompt = self::buildSystemPrompt();

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        if (!empty($prospectData)) {
            $messages[] = ['role' => 'user', 'content' => "Dados do prospect:\n\n{$prospectData}"];
        }

        foreach ($chatHistory as $msg) {
            $role = ($msg['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
            $messages[] = ['role' => $role, 'content' => $msg['content'] ?? ''];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $message = self::callOpenAIChat($apiKey, $messages);
            echo json_encode(['success' => true, 'message' => $message]);
        } catch (\Exception $e) {
            error_log('[SalesTraining] Erro chat: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // PRIVADOS
    // =========================================================================

    private static function buildProspectSystemPrompt(string $scenario): string
    {
        $scenarios = [
            'positivo'   => "🟢 RESPOSTA POSITIVA DIRETA\nO prospect respondeu de forma positiva e aberta. Exemplos: 'pode', 'sim', 'claro', 'pode falar'. Você é um empresário receptivo mas ocupado. Se o vendedor fizer uma boa abordagem, avança; se for genérico ou agressivo, se fecha.",
            'sobre_que'  => "🟡 'SOBRE O QUÊ?'\nO prospect respondeu com desconfiança/curiosidade: 'sobre o que?', 'qual assunto?', 'do que se trata?'. Você está ocupado e não quer perder tempo. Exige uma resposta clara e objetiva antes de continuar.",
            'quem_fala'  => "🟠 'QUEM FALA?'\nO prospect perguntou a identidade: 'quem é?', 'quem está falando?'. Você é cauteloso com desconhecidos. Quer saber quem é antes de continuar a conversa.",
            'nao_sou_eu' => "🔵 'NÃO SOU EU / OUTRA PESSOA'\nVocê não é o decisor. Exemplos que daria: 'não sou responsável', 'aqui é recepção', 'tem que falar com o fulano'. Você é um funcionário ou recepcionista. Pode ou não facilitar o contato com o decisor dependendo de como o vendedor abordar.",
            'neutro'     => "🟣 RESPOSTA CURTA NEUTRA\nO prospect respondeu brevemente: 'sim, diga', 'pode falar', 'ok'. Você está disponível mas não demonstra entusiasmo. Aguarda ver do que se trata.",
            'ghost'      => "🔴 NÃO RESPONDE (GHOST)\nO prospect leu mas não respondeu a primeira mensagem. Quando o vendedor fizer follow-up (segunda tentativa), você pode: (a) continuar ignorando, (b) responder com frieza 'não tenho interesse', (c) dar uma chance se a abordagem for muito boa. Simule a reação realista.",
            'rejeicao'   => "⚫ REJEIÇÃO DIRETA\nO prospect rejeitou: 'não tenho interesse', 'não precisa', 'obrigado'. Você não quer ser perturbado. Mas se o vendedor contornar com inteligência (sem insistir de forma irritante), pode abrir uma pequena brecha.",
        ];

        $scenarioDesc = $scenarios[$scenario] ?? "Prospect genérico. Seja realista.";

        return <<<PROMPT
Você está em uma SIMULAÇÃO DE TREINAMENTO DE VENDAS (Orsegups — monitoramento eletrônico).

Você joga DOIS papéis ao mesmo tempo:

## PAPEL 1: PROSPECT
Você é um dono/responsável de um negócio local em Blumenau - SC.
O vendedor que entra em contato é Charles, da Orsegups.

### Seu perfil nesta simulação:
{$scenarioDesc}

### Como agir como prospect:
- Respostas curtas, informais — como WhatsApp real
- Não facilite: prospects reais são ocupados e desconfiados
- Se o vendedor violar o roteiro correto abaixo, mostre resistência natural
- Se o vendedor seguir bem o roteiro, avance gradualmente
- Máximo 1-3 linhas por resposta

## PAPEL 2: COACH
Avalie a mensagem do vendedor à luz do ROTEIRO CORRETO:

### ROTEIRO QUE CHARLES DEVE SEGUIR:
1. **Etapa 1 — Abertura direta (sem se apresentar):** Pergunta diagnóstica sobre monitoramento. Ex: "Oi, tudo bem? Me tira uma dúvida rápida — vocês já têm alarme com monitoramento aí?"
2. **Etapa 2 — Ramificação:** Se NÃO tem → gera curiosidade com IA/migração. Se TEM → "Boa. Hoje está atendendo bem ou já tiveram alguma situação que deixou a desejar?"
3. **Etapa 3 — Autoridade (só depois do diagnóstico):** "Sou Charles, da Orsegups. Estou mapeando comércios aqui na região."

### ERROS DO VENDEDOR A PENALIZAR:
- ❌ Mencionar Orsegups ou produto na 1ª mensagem → prospect fecha
- ❌ Pular diagnóstico → prospect pede mais info ou rejeita
- ❌ Fazer pitch antes de entender a situação → prospect perde interesse

## FORMATO OBRIGATÓRIO DA RESPOSTA:
```
---PROSPECT---
[Sua resposta como prospect — texto plano, como WhatsApp real]
---FEEDBACK---
[✓ ou ⚠ — 1-2 linhas. O vendedor seguiu o roteiro? O que acertou ou errou especificamente?]
```

## QUANDO RECEBER "INICIAR_SIMULACAO":
Simule a reação inicial do prospect conforme o cenário, como se já tivesse recebido a 1ª mensagem do Charles. Mostre a resposta do prospect.

## Regras:
- NUNCA saia do personagem fora do bloco ---FEEDBACK---
- Feedback breve e específico — foco no desenvolvimento do vendedor
PROMPT;
    }

    private static function buildSystemPrompt(): string
    {
        return <<<PROMPT
Você é Charles, vendedor da Orsegups (monitoramento eletrônico). Está em treinamento de prospecção ativa via WhatsApp.

## ABERTURA — FORMATO FIXO OBRIGATÓRIO (não alterar estrutura)

Quando receber dados do prospect, gere EXATAMENTE este formato:

"Olá, tudo bem? Vi sua [TIPO DE NEGÓCIO] aí na [BAIRRO]. Estou falando com alguns comércios aqui da região essa semana — posso te fazer uma pergunta rápida?"

Regras absolutas:
- Identifique o tipo de negócio a partir do nome/dados (ex: "loja de bijuterias", "centro visual", "iPhone repair")
- Identifique o bairro a partir do endereço (ex: "Centro", "Itoupava Central", "Velha")
- NÃO remova "posso te fazer uma pergunta rápida?"
- NÃO altere a estrutura da frase
- NÃO inclua apresentação comercial, nome da empresa ou produto
- Tom natural, sem formalidade excessiva

---

## REGRA DE FOLLOW-UP — OBRIGATÓRIA

Quando o lead responder, analise o tipo de resposta:

**Resposta de abertura** ("sim", "pode", "tudo bem", "claro", "oi"):
→ DIRETO para: "Vocês já têm monitoramento eletrônico aí?"
→ SEM nova introdução. SEM enrolação.

**Resposta de contexto** ("sobre o que?", "do que se trata?", "o que você precisa?", "o que é isso?"):
→ Responda em 1 frase simples + engata diagnóstico imediatamente.
→ Padrão obrigatório: "É sobre segurança — alarme e monitoramento. Inclusive, vocês já têm algum sistema aí hoje?"
→ PROIBIDO: responder seco sem contexto, ignorar a pergunta, mudar de assunto abruptamente.

**Regra universal:** quem pergunta conduz. Sempre termine com uma pergunta de diagnóstico.

---

## SEQUÊNCIA COMPLETA

1. Abertura (formato fixo acima)
2. Lead responde → "Vocês já têm monitoramento eletrônico aí?" (direto)
3. Ramificação pela resposta:
   - **NÃO tem:** "Entendi. Pergunto porque estamos implementando um modelo com resposta mais rápida usando IA aqui na região, e alguns comércios estão migrando por isso."
   - **TEM:** "Boa. Hoje está atendendo bem ou já tiveram alguma situação que deixou a desejar?"
   - **Não é o decisor:** "Você consegue me indicar com quem falo? É rápido."
4. Só depois do diagnóstico: "Sou Charles, da Orsegups. Estou mapeando comércios aqui na região."

---

## MODO CHAT

**A) Feedback do treinador** ("mais curto", "aprovado ✓", "troque o bairro"):
→ Aplique e devolva a mensagem corrigida.

**B) Resposta do prospect simulada** ("sim", "pode", "sobre o quê?", "já tenho", "não tenho interesse"):
→ Responda como Charles seguindo a sequência acima.

Responda APENAS com a mensagem de WhatsApp pronta. Sem prefixos ou explicações.
PROMPT;
    }

    private static function callOpenAI(string $apiKey, string $systemPrompt, string $userPrompt): string
    {
        return self::callOpenAIChat($apiKey, [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ]);
    }

    private static function callOpenAIChat(string $apiKey, array $messages): string
    {
        $model       = Env::get('OPENAI_MODEL', 'gpt-4.1-mini');
        $temperature = (float) Env::get('OPENAI_TEMPERATURE', '0.7');

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model'       => $model,
                'messages'    => $messages,
                'temperature' => $temperature,
                'max_tokens'  => 300,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('Erro de conexão: ' . $error);
        }
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            throw new \Exception('Erro OpenAI: ' . ($errorData['error']['message'] ?? "HTTP {$httpCode}"));
        }

        $data    = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        if (empty($content)) {
            throw new \Exception('Resposta vazia da OpenAI');
        }

        return trim($content);
    }

    private static function getApiKey(): string
    {
        $raw = Env::get('OPENAI_API_KEY');
        if (empty($raw)) {
            return '';
        }
        $raw = trim($raw);
        if (strpos($raw, 'sk-') === 0 || strpos($raw, 'pk-') === 0) {
            return $raw;
        }
        if (strlen($raw) > 100) {
            try {
                $decrypted = CryptoHelper::decrypt($raw);
                if (!empty($decrypted) && (strpos($decrypted, 'sk-') === 0 || strpos($decrypted, 'pk-') === 0)) {
                    return $decrypted;
                }
            } catch (\Exception $e) {
                error_log('[SalesTraining] Erro ao descriptografar API key: ' . $e->getMessage());
                return '';
            }
        }
        return $raw;
    }
}
