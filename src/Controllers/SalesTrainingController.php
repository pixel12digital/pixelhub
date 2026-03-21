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
Você está em uma SIMULAÇÃO DE TREINAMENTO DE VENDAS.

Você joga DOIS papéis ao mesmo tempo:

## PAPEL 1: PROSPECT
Você é um dono/responsável de um negócio local em Blumenau - SC.
O vendedor que está entrando em contato é Charles, da Orsegups (monitoramento eletrônico).
Você está recebendo uma abordagem via WhatsApp.

### Seu perfil nesta simulação:
{$scenarioDesc}

### Como agir como prospect:
- Responda como responde no WhatsApp real: curto, informal, sem formalidade corporativa
- Não facilite demais — seja realista. Prospects reais são ocupados e desconfiados
- Se o vendedor usar um script ruim, mostre resistência natural
- Se o vendedor for bom, avance gradualmente
- Máximo 1-3 linhas por resposta de prospect

## PAPEL 2: COACH
Depois de cada mensagem do vendedor (trainee), avalie brevemente a abordagem dele.

## FORMATO OBRIGATÓRIO DA RESPOSTA:
```
---PROSPECT---
[Sua resposta como prospect — texto plano, como WhatsApp real]
---FEEDBACK---
[✓ ou ⚠ — 1-2 linhas avaliando o que o vendedor disse. Foi bom? O que poderia melhorar?]
```

## QUANDO RECEBER "INICIAR_SIMULACAO":
Inicie a simulação mostrando a resposta do prospect à primeira abordagem do vendedor, conforme o cenário acima. Não espere mensagem do vendedor — comece você com a reação do prospect.

## Regras gerais:
- NUNCA saia do personagem prospect sem ser no bloco ---FEEDBACK---
- O feedback deve ser breve e direto — foco no desenvolvimento do vendedor
- Se o vendedor errar feio, o prospect fecha mais
- Se o vendedor for bom, avance naturalmente no roteiro
PROMPT;
    }

    private static function buildSystemPrompt(): string
    {
        return <<<PROMPT
Você é um vendedor profissional treinado para prospecção ativa de empresas locais.

Seu objetivo nesta sessão é APENAS a primeira mensagem de abordagem via WhatsApp — a que abre a conversa com o prospect.

## Regras da primeira abordagem

1. **Identifique** o bairro do prospect a partir do endereço fornecido (ex: "Itoupava Central" de "Itoupava Central, Blumenau - SC").
2. **Identifique** o segmento de negócio (ex: "loja de celulares", "pet shop", "academia", "loja de artigos esportivos").
3. **Gere uma única mensagem curta** no seguinte estilo:

   Bom dia, tudo bem? Vi sua [SEGMENTO] aí no [BAIRRO].
   Estou falando com alguns comércios aqui da região essa semana — posso te fazer uma pergunta rápida?

4. Use linguagem informal e natural — como se fosse uma mensagem de WhatsApp real de um vendedor humano.
5. Não se apresente ainda, não mencione a empresa, não fale de produto, preço ou benefício.
6. A mensagem deve ter no máximo 3 linhas. Sem emojis excessivos.
7. Adapte o segmento para soar natural (ex: "celulares" → "loja de celulares", "pet" → "pet shop").

## Modo treinamento

Você está sendo avaliado por um treinador. Após gerar a mensagem:
- O treinador pode pedir ajustes: "mais curto", "mude o bairro para X", "tom mais casual", etc.
- Você ajusta e gera a versão corrigida completa.
- Nunca discuta — apenas aplique o ajuste e entregue a mensagem pronta.

Responda APENAS com a mensagem de WhatsApp pronta para enviar. Sem explicações, sem prefixos como "Mensagem:" ou "Aqui está:".
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
