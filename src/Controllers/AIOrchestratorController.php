<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Env;
use PixelHub\Core\Response;
use PixelHub\Services\IntelligentDataCollector;
use PixelHub\Core\CryptoHelper;

// Garante que .env está carregado
if (empty($_ENV)) {
    Env::load();
}

/**
 * Controller para orquestração inteligente do chat usando IA
 */
class AIOrchestratorController
{
    /**
     * Processa mensagem do usuário usando IA para entender intenção e contexto
     */
    public function processMessage()
    {
        header('Content-Type: application/json');
        
        error_log('[AI Orchestrator] ===== NOVA REQUISIÇÃO =====');
        
        $rawInput = file_get_contents('php://input');
        error_log('[AI Orchestrator] Input raw recebido: ' . substr($rawInput, 0, 500));
        
        $input = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[AI Orchestrator] ERRO ao parsear JSON: ' . json_last_error_msg());
            echo json_encode([
                'success' => false,
                'error' => 'Erro ao parsear JSON: ' . json_last_error_msg()
            ]);
            return;
        }
        
        $userMessage = $input['message'] ?? '';
        $conversationHistory = $input['history'] ?? [];
        $formData = $input['formData'] ?? [];
        $currentStep = $input['currentStep'] ?? 'greeting';
        $currentQuestion = $input['currentQuestion'] ?? null;
        $serviceType = $input['serviceType'] ?? 'business_card';
        
        error_log('[AI Orchestrator] Dados recebidos:');
        error_log('  - Mensagem: ' . substr($userMessage, 0, 100));
        error_log('  - Histórico: ' . count($conversationHistory) . ' mensagens');
        error_log('  - FormData keys: ' . implode(', ', array_keys($formData)));
        error_log('  - Step: ' . $currentStep);
        error_log('  - ServiceType: ' . $serviceType);
        
        if (empty($userMessage)) {
            error_log('[AI Orchestrator] ERRO: Mensagem vazia');
            echo json_encode([
                'success' => false,
                'error' => 'Mensagem vazia'
            ]);
            return;
        }
        
        try {
            // Chama IA para analisar a mensagem
            $analysis = $this->analyzeWithAI($userMessage, $conversationHistory, $formData, $currentStep, $currentQuestion, $serviceType);
            
            echo json_encode([
                'success' => true,
                'analysis' => $analysis
            ]);
        } catch (\Exception $e) {
            error_log('[AI Orchestrator] Erro: ' . $e->getMessage());
            error_log('[AI Orchestrator] Stack trace: ' . $e->getTraceAsString());
            
            // Tenta usar fallback inteligente
            try {
                $missingFields = IntelligentDataCollector::getMissingFields($formData, $serviceType ?? 'business_card');
                $fallback = $this->intelligentFallback($userMessage, $formData, $currentStep, $missingFields);
                
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'fallback' => $fallback
                ]);
            } catch (\Exception $fallbackError) {
                error_log('[AI Orchestrator] Erro no fallback: ' . $fallbackError->getMessage());
                // Fallback básico
                $fallback = $this->fallbackAnalysis($userMessage, $formData, $currentStep);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'fallback' => $fallback
                ]);
            }
        }
    }
    
    /**
     * Analisa mensagem usando OpenAI ou serviço de IA
     */
    private function analyzeWithAI(string $userMessage, array $history, array $formData, string $currentStep, ?string $currentQuestion, string $serviceType = 'business_card'): array
    {
        error_log('[AI Orchestrator] Iniciando análise com IA');
        error_log('[AI Orchestrator] Mensagem: ' . substr($userMessage, 0, 100));
        
        // Verifica se cadastro está completo antes de buscar campos de briefing
        $cadastroComplete = self::isCadastroComplete($formData);
        error_log('[AI Orchestrator] Cadastro completo: ' . ($cadastroComplete ? 'SIM' : 'NÃO'));
        
        // Usa IntelligentDataCollector para saber o que falta
        // Se cadastro não completo, retorna apenas campos de cadastro
        // Se completo, retorna campos do briefing do serviço
        $missingFields = IntelligentDataCollector::getMissingFields($formData, $serviceType, $cadastroComplete);
        error_log('[AI Orchestrator] Campos faltantes: ' . count($missingFields));
        
        // Carrega e descriptografa API key
        $apiKeyRaw = Env::get('OPENAI_API_KEY');
        error_log('[AI Orchestrator] API Key raw existe: ' . (!empty($apiKeyRaw) ? 'SIM (' . strlen($apiKeyRaw) . ' chars)' : 'NÃO'));
        
        $apiKey = $this->decryptApiKey($apiKeyRaw);
        error_log('[AI Orchestrator] API Key descriptografada existe: ' . (!empty($apiKey) ? 'SIM (inicia com: ' . substr($apiKey, 0, 7) . '...)' : 'NÃO'));
        
        // Se não tem API key configurada, usa fallback inteligente
        if (empty($apiKey)) {
            error_log('[AI Orchestrator] Sem API key, usando fallback inteligente');
            return $this->intelligentFallback($userMessage, $formData, $currentStep, $missingFields);
        }
        
        // Prepara contexto inteligente
        $context = $this->buildIntelligentContext($formData, $missingFields, $currentStep, $currentQuestion);
        $historyText = $this->formatHistory($history);
        
        $prompt = $this->buildIntelligentPrompt($userMessage, $context, $historyText, $currentStep, $missingFields, $serviceType, $formData, $cadastroComplete);
        
        try {
            error_log('[AI Orchestrator] Chamando OpenAI API...');
            $response = $this->callOpenAI($apiKey, $prompt);
            error_log('[AI Orchestrator] Resposta recebida da OpenAI');
            
            $analysis = $this->parseAIResponse($response);
            error_log('[AI Orchestrator] Análise parseada: ' . json_encode($analysis));
            
            // Enriquece análise com dados do IntelligentDataCollector
            $enriched = $this->enrichAnalysis($analysis, $missingFields, $formData);
            error_log('[AI Orchestrator] Análise enriquecida: intenção=' . ($enriched['intention'] ?? 'null'));
            return $enriched;
        } catch (\Exception $e) {
            error_log('[AI Orchestrator] ERRO ao chamar OpenAI: ' . $e->getMessage());
            error_log('[AI Orchestrator] Stack trace: ' . $e->getTraceAsString());
            return $this->intelligentFallback($userMessage, $formData, $currentStep, $missingFields);
        }
    }
    
    /**
     * Chama API do OpenAI
     */
    private function callOpenAI(string $apiKey, string $prompt): array
    {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => Env::get('OPENAI_MODEL', 'gpt-4o-mini'), // Usa modelo configurado ou padrão
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você é um assistente virtual profissional que coleta dados para pedidos de serviços. Seja amigável, preciso e sempre tente entender a intenção real do usuário, mesmo que ele não diga explicitamente. SEMPRE retorne APENAS JSON válido, sem texto adicional antes ou depois.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => (float) Env::get('OPENAI_TEMPERATURE', '0.3'), // Usa temperatura configurada ou padrão
                'max_tokens' => (int) Env::get('OPENAI_MAX_TOKENS', '500'), // Usa max_tokens configurado ou padrão
                'response_format' => ['type' => 'json_object'] // Força resposta em JSON
            ])
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception('Erro cURL: ' . $error);
        }
        
        if ($httpCode !== 200) {
            throw new \Exception('Erro HTTP ' . $httpCode . ': ' . $response);
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \Exception('Resposta inválida da API');
        }
        
        return $data;
    }
    
    /**
     * Constrói contexto inteligente baseado no que falta
     */
    private function buildIntelligentContext(array $formData, array $missingFields, string $currentStep, ?string $currentQuestion): string
    {
        $context = "DADOS JÁ COLETADOS:\n";
        
        if (!empty($formData['client']['name'])) {
            $context .= "✓ Nome: {$formData['client']['name']}\n";
        }
        if (!empty($formData['client']['email'])) {
            $context .= "✓ Email: {$formData['client']['email']}\n";
        }
        if (!empty($formData['client']['phone'])) {
            $context .= "✓ Telefone: {$formData['client']['phone']}\n";
        }
        if (!empty($formData['client']['cpf_cnpj'])) {
            $context .= "✓ CPF/CNPJ: {$formData['client']['cpf_cnpj']}\n";
        }
        if (!empty($formData['address']['cep'])) {
            $context .= "✓ CEP: {$formData['address']['cep']}\n";
        }
        
        $context .= "\nCAMPOS QUE AINDA FALTAM (em ordem de prioridade):\n";
        $priority = 1;
        foreach (array_slice($missingFields, 0, 3) as $fieldKey => $fieldConfig) {
            $context .= "{$priority}. {$fieldConfig['label']} - {$fieldConfig['hint']}\n";
            $priority++;
        }
        
        return $context;
    }
    
    /**
     * Constrói prompt inteligente para a IA
     */
    private function buildIntelligentPrompt(
        string $userMessage, 
        string $context, 
        string $historyText, 
        string $currentStep,
        array $missingFields,
        string $serviceType = 'business_card',
        array $formData = [],
        bool $cadastroComplete = false
    ): string {
        $nextField = reset($missingFields);
        $nextFieldKey = key($missingFields);
        
        $nextFieldInfo = $nextField 
            ? "PRÓXIMA INFORMAÇÃO NECESSÁRIA: {$nextField['label']} (campo: {$nextFieldKey})\nDica: {$nextField['hint']}"
            : "Todas as informações foram coletadas. Aguardando confirmação.";
        
        // Lista campos válidos para a IA
        $validFieldsList = [];
        foreach ($missingFields as $key => $config) {
            $validFieldsList[] = "  - '{$key}': {$config['label']}";
        }
        $validFieldsText = !empty($validFieldsList) 
            ? "CAMPOS VÁLIDOS DISPONÍVEIS:\n" . implode("\n", $validFieldsList) . "\n\nCAMPOS VÁLIDOS GLOBAIS (sempre podem ser usados):\n  - 'name': Nome completo (ou 'nome_completo')\n  - 'email': Email\n  - 'phone': Telefone/Celular\n  - 'cpf_cnpj': CPF ou CNPJ\n  - 'cep': CEP"
            : "CAMPOS VÁLIDOS GLOBAIS:\n  - 'name': Nome completo (ou 'nome_completo')\n  - 'email': Email\n  - 'phone': Telefone/Celular\n  - 'cpf_cnpj': CPF ou CNPJ\n  - 'cep': CEP";
        
        // Determina se estamos coletando cadastro ou briefing
        $collectionPhase = $cadastroComplete ? 'briefing' : 'cadastro';
        
        $serviceContext = '';
        if ($collectionPhase === 'briefing') {
            // Estamos coletando briefing do serviço
            if ($serviceType === 'business_card') {
                $serviceContext = "\nCONTEXTO DO SERVIÇO: Você está coletando BRIEFING para criação de um CARTÃO DE VISITA PROFISSIONAL.\n";
                $serviceContext .= "- O cadastro do cliente já foi concluído\n";
                $serviceContext .= "- Agora precisamos das informações específicas do cartão\n";
                $serviceContext .= "- O nome informado será o que aparecerá no cartão\n";
                $serviceContext .= "- Se o usuário perguntar sobre o cartão, responda de forma útil e confirme\n";
            }
        } else {
            // Estamos coletando dados de cadastro
            $serviceContext = "\nCONTEXTO: Você está coletando DADOS DE CADASTRO (necessários para nota fiscal e pagamento).\n";
            $serviceContext .= "- Estes dados são obrigatórios para qualquer pedido de serviço\n";
            $serviceContext .= "- Após o cadastro, coletaremos o briefing específico do serviço\n";
            $serviceContext .= "- Se o usuário perguntar sobre o serviço, explique que primeiro precisamos do cadastro\n";
        }
        
        return <<<PROMPT
Você é um assistente virtual PROFISSIONAL e EFICIENTE que coleta dados para pedidos de serviços.

{$serviceContext}

REGRAS IMPORTANTES:
- Seja DIRETO e OBJETIVO - usuários não têm paciência
- Não faça perguntas desnecessárias
- Se o usuário der múltiplas informações de uma vez, extraia todas
- Se o usuário quiser corrigir algo, ajude IMEDIATAMENTE
- Se o dado estiver incompleto, peça APENAS o que falta

{$validFieldsText}

REGRAS DE MAPEAMENTO DE CAMPOS:
- Use APENAS os nomes de campos listados acima
- Para nome completo, use 'name' ou 'nome_completo' (não 'nome')
- Para email, use 'email'
- Para telefone, use 'phone'
- Para CPF/CNPJ, use 'cpf_cnpj'
- Para CEP, use 'cep'
- NUNCA use valores genéricos como "nome_do_campo_ou_null" - sempre use o nome exato do campo

QUANDO USAR "field" vs "extractedFields":
- Use "field" quando o usuário informou UM ÚNICO campo específico (ex: apenas o nome)
- Use "extractedFields" quando o usuário informou MÚLTIPLOS campos de uma vez (ex: "Meu nome é João, email joao@email.com")
- Se usar "extractedFields", o campo "field" pode ser null ou o campo principal extraído
- PRIORIZE "extractedFields" quando múltiplos dados forem identificados

CRÍTICO - DETECÇÃO DE PERGUNTAS:
- Se a mensagem do usuário for uma PERGUNTA (contém "?", ou palavras como "vai", "será", "precisa", "pode", "deve", etc.), 
  você DEVE responder imediatamente com:
  * intention: "fazer_pergunta"
  * action: "answer_question"
  * field: null (não é um dado sendo informado)
  * extractedFields: {} (vazio, pois é uma pergunta)
  * response: "Sim, [explicação breve e útil]" ou resposta direta à pergunta
  * Se a pergunta for sobre o campo atual que está sendo solicitado, mantenha o contexto (não mude de campo)
  * Se a pergunta for sobre outro campo já coletado, responda mas mantenha o foco no campo atual
- Exemplos de perguntas: "vai no cartão esse no nome?", "esse email é obrigatório?", "preciso informar telefone?"
- SEMPRE responda perguntas antes de continuar coletando dados
- Após responder, se o usuário ainda precisa informar o campo atual, mantenha o foco nele (não avance para próximo campo)

{$context}

{$nextFieldInfo}

Histórico recente:
{$historyText}

ÚLTIMA MENSAGEM DO USUÁRIO:
"{$userMessage}"

Analise e responda em JSON válido (SEM texto adicional antes ou depois):
{
    "intention": "informar_dado|corrigir_campo|ver_resumo|confirmar|fazer_pergunta|outro",
    "field": "name|email|phone|cpf_cnpj|cep|null ou campo válido da lista acima",
    "value": "valor extraído da mensagem ou null",
    "extractedFields": {
        "name": "valor se nome foi informado",
        "email": "valor se email foi informado",
        "phone": "valor se telefone foi informado",
        "cpf_cnpj": "valor se CPF/CNPJ foi informado"
    },
    "isValidData": true|false,
    "validation": {
        "valid": true|false,
        "error": "mensagem de erro se inválido, ou null",
        "suggestion": "sugestão de correção se houver, ou null"
    },
    "action": "accept|ask_correction|show_summary|ask_next|clarify|extract_multiple|answer_question",
    "response": "mensagem curta e direta para o usuário (em português)",
    "needsConfirmation": true|false
}

EXEMPLOS DE RESPOSTAS CORRETAS:

1. Usuário informa apenas nome: "João Silva"
{
    "intention": "informar_dado",
    "field": "name",
    "value": "João Silva",
    "extractedFields": {"name": "João Silva"},
    "isValidData": true,
    "validation": {"valid": true, "error": null, "suggestion": null},
    "action": "ask_next",
    "response": "Obrigado, João Silva! Agora, por favor, informe seu CPF ou CNPJ.",
    "needsConfirmation": false
}

2. Usuário faz pergunta: "é o que vai no cartão?"
{
    "intention": "fazer_pergunta",
    "field": null,
    "value": null,
    "extractedFields": {},
    "isValidData": false,
    "validation": {"valid": false, "error": null, "suggestion": null},
    "action": "answer_question",
    "response": "Sim, é o nome que aparecerá no cartão.",
    "needsConfirmation": false
}
PROMPT;
    }
    
    
    /**
     * Enriquece análise com contexto do IntelligentDataCollector
     */
    private function enrichAnalysis(array $analysis, array $missingFields, array $formData): array
    {
        // Se a análise não especificou campo mas há campos faltando, sugere o próximo
        if (empty($analysis['field']) && !empty($missingFields)) {
            $nextField = reset($missingFields);
            $nextFieldKey = key($missingFields);
            $analysis['suggestedField'] = $nextFieldKey;
        }
        
        // Adiciona informação sobre o que falta
        $analysis['missingFieldsCount'] = count($missingFields);
        $analysis['isComplete'] = empty($missingFields);
        
        return $analysis;
    }
    
    /**
     * Fallback inteligente sem IA
     */
    private function intelligentFallback(string $userMessage, array $formData, string $currentStep, array $missingFields): array
    {
        // Usa IntelligentDataCollector para extração básica
        $extracted = IntelligentDataCollector::extractDataFromMessage($userMessage, $missingFields);
        
        // Detecta intenção básica
        $lowerMessage = strtolower($userMessage);
        
        if (preg_match('/(corrigir|alterar|mudar|trocar|quero|preciso).*(nome|email|telefone|phone|cpf|cnpj|cep)/i', $lowerMessage)) {
            $field = null;
            if (stripos($lowerMessage, 'nome') !== false) $field = 'name';
            elseif (stripos($lowerMessage, 'email') !== false) $field = 'email';
            elseif (stripos($lowerMessage, 'telefone') !== false || stripos($lowerMessage, 'phone') !== false) $field = 'phone';
            elseif (stripos($lowerMessage, 'cpf') !== false || stripos($lowerMessage, 'cnpj') !== false) $field = 'cpf_cnpj';
            elseif (stripos($lowerMessage, 'cep') !== false) $field = 'cep';
            
            return [
                'intention' => 'corrigir_campo',
                'field' => $field,
                'value' => null,
                'extractedFields' => $extracted,
                'isValidData' => false,
                'validation' => ['valid' => false],
                'action' => $field ? 'ask_correction' : 'show_summary',
                'response' => $field ? "Vou te ajudar a corrigir esse campo." : "Vou mostrar o resumo para você escolher.",
                'needsConfirmation' => false,
                'missingFieldsCount' => count($missingFields),
                'isComplete' => empty($missingFields)
            ];
        }
        
        // Se extraiu dados, aceita
        if (!empty($extracted)) {
            return [
                'intention' => 'informar_dado',
                'field' => null,
                'value' => $userMessage,
                'extractedFields' => $extracted,
                'isValidData' => true,
                'validation' => ['valid' => true],
                'action' => 'extract_multiple',
                'response' => null,
                'needsConfirmation' => false,
                'missingFieldsCount' => count($missingFields),
                'isComplete' => empty($missingFields)
            ];
        }
        
        // Fallback padrão
        return [
            'intention' => 'informar_dado',
            'field' => null,
            'value' => $userMessage,
            'extractedFields' => $extracted,
            'isValidData' => true,
            'validation' => ['valid' => true],
            'action' => 'accept',
            'response' => null,
            'needsConfirmation' => false,
            'missingFieldsCount' => count($missingFields),
            'isComplete' => empty($missingFields)
        ];
    }
    
    /**
     * Constrói prompt para a IA (método antigo - mantido para compatibilidade)
     */
    private function buildPrompt(string $userMessage, string $context, string $historyText, string $currentStep): string
    {
        return $this->buildIntelligentPrompt($userMessage, $context, $historyText, $currentStep, [], 'business_card');
    }
    
    /**
     * Parseia resposta da IA
     */
    private function parseAIResponse(array $apiResponse): array
    {
        $content = $apiResponse['choices'][0]['message']['content'];
        
        // Tenta extrair JSON da resposta
        if (preg_match('/\{[^}]+\}/s', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return $json;
            }
        }
        
        // Se não conseguiu parsear, tenta parsear JSON direto
        $json = json_decode($content, true);
        if ($json) {
            return $json;
        }
        
        // Fallback se não conseguiu parsear
        throw new \Exception('Não foi possível parsear resposta da IA');
    }
    
    /**
     * Análise de fallback sem IA (usando padrões)
     */
    private function fallbackAnalysis(string $userMessage, array $formData, string $currentStep): array
    {
        $lowerMessage = strtolower($userMessage);
        
        // Detecta intenção de corrigir
        if (preg_match('/(corrigir|alterar|mudar|trocar|quero|preciso).*(nome|email|telefone|phone|cpf|cnpj|cep|endereço)/i', $lowerMessage, $matches)) {
            $field = null;
            if (stripos($lowerMessage, 'nome') !== false) $field = 'name';
            elseif (stripos($lowerMessage, 'email') !== false) $field = 'email';
            elseif (stripos($lowerMessage, 'telefone') !== false || stripos($lowerMessage, 'phone') !== false) $field = 'phone';
            elseif (stripos($lowerMessage, 'cpf') !== false || stripos($lowerMessage, 'cnpj') !== false) $field = 'cpf_cnpj';
            elseif (stripos($lowerMessage, 'cep') !== false) $field = 'cep';
            
            return [
                'intention' => 'corrigir_campo',
                'field' => $field,
                'value' => null,
                'isValidData' => false,
                'validation' => ['valid' => false],
                'action' => $field ? 'ask_correction' : 'show_summary',
                'response' => $field ? "Entendi! Vou te ajudar a corrigir esse campo." : "Vou mostrar o resumo para você escolher o que corrigir.",
                'needsConfirmation' => false
            ];
        }
        
        // Detecta intenção de ver resumo
        if (preg_match('/(resumo|dados|informações|revisar|ver|mostrar)/i', $lowerMessage)) {
            return [
                'intention' => 'ver_resumo',
                'field' => null,
                'value' => null,
                'isValidData' => false,
                'validation' => ['valid' => false],
                'action' => 'show_summary',
                'response' => 'Vou mostrar o resumo dos dados informados.',
                'needsConfirmation' => false
            ];
        }
        
        // Assume que é dado informado
        return [
            'intention' => 'informar_dado',
            'field' => null,
            'value' => $userMessage,
            'isValidData' => true,
            'validation' => ['valid' => true],
            'action' => 'accept',
            'response' => null,
            'needsConfirmation' => false
        ];
    }
    
    /**
     * Constrói contexto dos dados coletados
     */
    private function buildContext(array $formData, string $currentStep, ?string $currentQuestion): string
    {
        $context = [];
        
        if (!empty($formData['client']['name'])) {
            $context[] = "- Nome: " . $formData['client']['name'];
        }
        if (!empty($formData['client']['email'])) {
            $context[] = "- Email: " . $formData['client']['email'];
        }
        if (!empty($formData['client']['phone'])) {
            $context[] = "- Telefone: " . $formData['client']['phone'];
        }
        if (!empty($formData['client']['cpf_cnpj'])) {
            $context[] = "- CPF/CNPJ: " . $formData['client']['cpf_cnpj'];
        }
        if (!empty($formData['address']['cep'])) {
            $context[] = "- CEP: " . $formData['address']['cep'];
            if (!empty($formData['address']['street'])) {
                $context[] = "- Endereço: " . $formData['address']['street'] . ', ' . ($formData['address']['number'] ?? '') . ' - ' . ($formData['address']['city'] ?? '');
            }
        }
        
        if (empty($context)) {
            return "Nenhum dado coletado ainda.";
        }
        
        return implode("\n", $context);
    }
    
    /**
     * Verifica se dados de cadastro (Asaas) estão completos
     */
    private function isCadastroComplete(array $formData): bool
    {
        // Campos obrigatórios para cadastro Asaas
        $required = ['name', 'cpf_cnpj', 'email'];
        
        foreach ($required as $field) {
            if (empty($formData['client'][$field] ?? null)) {
                return false;
            }
        }
        
        // CEP é necessário para nota fiscal (pode ser opcional, mas recomendado)
        // Por enquanto, não obrigatório para considerar completo
        
        return true;
    }
    
    /**
     * Formata histórico da conversa
     */
    private function formatHistory(array $history): string
    {
        if (empty($history)) {
            return "Sem histórico ainda.";
        }
        
        $formatted = [];
        foreach (array_slice($history, -10) as $msg) { // Últimas 10 mensagens
            $role = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';
            $formatted[] = ($role === 'bot' ? 'Bot: ' : 'Usuário: ') . $content;
        }
        
        return implode("\n", $formatted);
    }
    
    /**
     * Descriptografa a chave de API se necessário (mesma lógica do AISettingsController)
     */
    private function decryptApiKey(?string $apiKeyRaw): string
    {
        if (empty($apiKeyRaw)) {
            return '';
        }
        
        $apiKeyRaw = trim($apiKeyRaw);
        
        // Chaves OpenAI geralmente começam com "sk-" ou "pk-"
        if (strpos($apiKeyRaw, 'sk-') === 0 || strpos($apiKeyRaw, 'pk-') === 0) {
            return $apiKeyRaw;
        }
        
        // Se é muito longa (>100 chars), provavelmente é criptografada
        if (strlen($apiKeyRaw) > 100) {
            try {
                $decrypted = CryptoHelper::decrypt($apiKeyRaw);
                if (!empty($decrypted) && (strpos($decrypted, 'sk-') === 0 || strpos($decrypted, 'pk-') === 0)) {
                    return $decrypted;
                }
            } catch (\Exception $e) {
                error_log("Erro ao descriptografar chave OpenAI: " . $e->getMessage());
                return '';
            }
        }
        
        return $apiKeyRaw;
    }
}

