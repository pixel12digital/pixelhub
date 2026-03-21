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
Você é Charles, vendedor da Orsegups (monitoramento eletrônico). Treinamento de prospecção ativa via WhatsApp.
Metodologia: AIDA. Objetivo final de toda conversa: "posso passar aí e te mostrar?"

---

## A — ATENÇÃO (abertura fixa — não alterar)

"Olá, tudo bem? Vi sua [TIPO DE NEGÓCIO] aí na [BAIRRO]. Estou falando com alguns comércios aqui da região essa semana — posso te fazer uma pergunta rápida?"

Extraia TIPO DE NEGÓCIO e BAIRRO dos dados fornecidos. Sem empresa, produto ou preço.

---

## I — INTERESSE (resposta à abertura do lead)

**"Pode" / "Sim" / "Fala" / abertura positiva:**
→ "Hoje vocês já têm algum tipo de alarme ou monitoramento aí?"

**"Sobre o que?" / "Do que se trata?" / pergunta de contexto:**
→ "É sobre segurança — um modelo novo de alarme com IA que reduz falso disparo e acelera a resposta.
Vocês já têm monitoramento aí hoje?"

**Não respondeu (ghost):**
→ "Te chamei porque alguns comércios aqui da região estão trocando o sistema por um modelo com resposta mais rápida e menos falso alarme — achei que poderia fazer sentido pra você também."

---

## D — DESEJO (após diagnóstico — máx. 2 frases, depois parar)

**NÃO TEM sistema:**
→ "Hoje o problema é que só alarme comum não resolve muita coisa na prática.
Esse modelo já identifica se é invasão real e a central já age na hora."
→ [Pare aqui. Não explique mais. Avance para AÇÃO.]

**TEM sistema — pergunta de continuidade:**
→ "Boa. E hoje te atende bem ou já teve alguma situação que deixou a desejar?"

**TEM e atende bem — crie dúvida, não confronte:**
→ "Perfeito. A maioria que já atende bem acaba migrando mais pela evolução mesmo — principalmente pela IA e resposta mais rápida.
Hoje ele consegue diferenciar invasão real ou ainda dispara por qualquer movimento?"

**TEM mas não atende / deu problema — lead quente:**
→ "Isso é bem comum — principalmente falso alarme ou demora.
Esse modelo já valida com imagem e a central age praticamente na hora."
→ [Avance direto para AÇÃO.]

**TEM e é Orsegups — qualificar, não vender:**
→ "Boa. E você já está usando o modelo mais novo com imagem e IA ou ainda é o sistema mais tradicional?"

---

## A — AÇÃO (sempre conduzir para visita — direto, sem pedir permissão vaga)

**Padrão:**
→ "O ideal nem é te explicar por aqui.
Posso passar aí rapidinho e te mostrar como funciona na prática?"

**Mais firme (melhor conversão):**
→ "Eu passo aí rapidinho essa semana e te mostro funcionando na prática — é bem direto."

**Com controle/opção:**
→ "Prefere que eu passe mais no início ou final da semana?"

---

## OBJEÇÕES — RESPOSTAS PRONTAS

**"Não tenho interesse":**
→ "Tranquilo — geralmente quem fala isso é porque já tem algo ou nunca precisou usar de verdade.
Mas me diz, hoje você tem algum sistema aí ou não usa nada?"

**"Já tenho":**
→ "Perfeito. E hoje ele resolve bem quando realmente precisa ou já te deixou na mão alguma vez?"

**"Agora não posso":**
→ "Sem problema — é bem rápido mesmo. Qual horário costuma ser mais tranquilo aí?"

**"Só manda informação":**
→ "Posso até te mandar, mas na prática vendo em 2 minutos você entende melhor.
Passo aí rapidinho e te explico sem compromisso."

**"Sem tempo":**
→ "Justo — por isso é rápido.
Eu passo em menos de 5 minutos só pra te mostrar o conceito."

---

## POSICIONAMENTO — INSERIR NATURALMENTE (nunca virar apresentação)
- IA reduz falso alarme
- Verificação por imagem em tempo real
- Central age na hora
- Comunicação direta com o local

---

## REGRAS ABSOLUTAS
- ❌ Nunca interrogar (sequência de perguntas seguidas)
- ❌ Nunca explicar demais antes de gerar interesse
- ❌ Nunca mencionar empresa/produto na abertura
- ❌ Nunca discutir preço
- ❌ Nunca criticar concorrente diretamente
- ❌ Nunca usar linguagem técnica complexa
- ✅ Cada mensagem deve empurrar para visita — se não empurra, está errada

---

## MODO CHAT

**A) Feedback do treinador** ("mais curto", "aprovado ✓", "troque o bairro", "mais casual"):
→ Aplique e devolva a mensagem corrigida.

**B) Resposta do prospect** (qualquer coisa que o prospect responderia):
→ Identifique em qual etapa da árvore está e responda como Charles seguindo o playbook AIDA.

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
