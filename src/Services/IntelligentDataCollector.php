<?php

namespace PixelHub\Services;

use PixelHub\Core\Env;

/**
 * Serviço de coleta inteligente de dados
 * 
 * Determina quais dados são necessários baseado no contexto:
 * - Para cadastro no Asaas
 * - Para criação de cartão de visita
 * - Otimiza perguntas para não cansar o usuário
 */
class IntelligentDataCollector
{
    /**
     * Retorna lista de campos obrigatórios para cadastro no Asaas
     * NOTA: Estes campos são apenas para CADASTRO. Briefing do serviço vem depois.
     */
    public static function getRequiredFieldsForAsaas(): array
    {
        return [
            'name' => [
                'label' => 'Nome completo',
                'type' => 'text',
                'required' => true,
                'priority' => 1,
                'validation' => 'min:3',
                'hint' => 'Nome completo para cadastro'
            ],
            'cpf_cnpj' => [
                'label' => 'CPF ou CNPJ',
                'type' => 'cpf_cnpj',
                'required' => true,
                'priority' => 2,
                'validation' => 'cpf_cnpj',
                'hint' => 'Necessário para cadastro no sistema de pagamentos'
            ],
            'email' => [
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
                'priority' => 3,
                'validation' => 'email',
                'hint' => 'Para envio de faturas e comunicação'
            ],
            'phone' => [
                'label' => 'Telefone/Celular',
                'type' => 'phone',
                'required' => false,
                'priority' => 4,
                'validation' => 'phone',
                'hint' => 'Para contato (opcional mas recomendado)'
            ],
            'address' => [
                'label' => 'Endereço completo',
                'type' => 'address',
                'required' => false,
                'priority' => 5,
                'fields' => ['cep', 'street', 'number', 'complement', 'neighborhood', 'city', 'state'],
                'hint' => 'Necessário para emissão de notas fiscais'
            ]
        ];
    }
    
    /**
     * Retorna lista de campos necessários para cartão de visita
     */
    public static function getRequiredFieldsForBusinessCard(): array
    {
        return [
            'name' => [
                'label' => 'Nome completo',
                'type' => 'text',
                'required' => true,
                'priority' => 1,
                'hint' => 'Nome que aparecerá no cartão'
            ],
            'phone' => [
                'label' => 'Telefone/Celular',
                'type' => 'phone',
                'required' => true,
                'priority' => 2,
                'hint' => 'Telefone para contato no cartão'
            ],
            'email' => [
                'label' => 'Email',
                'type' => 'email',
                'required' => false,
                'priority' => 3,
                'hint' => 'Email para contato (opcional)'
            ],
            'address' => [
                'label' => 'Endereço',
                'type' => 'address',
                'required' => false,
                'priority' => 4,
                'hint' => 'Endereço para aparecer no cartão (opcional)'
            ],
            'website' => [
                'label' => 'Site/Instagram',
                'type' => 'text',
                'required' => false,
                'priority' => 5,
                'hint' => 'Site ou rede social (opcional)'
            ],
            'segment' => [
                'label' => 'Segmento do negócio',
                'type' => 'segment',
                'required' => true,
                'priority' => 6,
                'options' => [
                    'corporativo' => 'Corporativo / Empresarial',
                    'saude' => 'Saúde / Medicina',
                    'beleza' => 'Beleza / Estética',
                    'advocacia' => 'Advocacia / Jurídico',
                    'arquitetura' => 'Arquitetura / Engenharia',
                    'educacao' => 'Educação / Ensino',
                    'tecnologia' => 'Tecnologia / TI',
                    'marketing' => 'Marketing / Publicidade',
                    'gastronomia' => 'Gastronomia / Restaurante',
                    'vendas' => 'Vendas / Comércio',
                    'consultoria' => 'Consultoria',
                    'outro' => 'Outro'
                ],
                'hint' => 'Selecione o segmento do seu negócio para escolhermos o template ideal'
            ],
            'frente_info' => [
                'label' => 'Informações da Frente',
                'type' => 'textarea',
                'required' => true,
                'priority' => 7,
                'hint' => 'O que deve aparecer na frente do cartão'
            ],
            'verso_info' => [
                'label' => 'Informações do Verso',
                'type' => 'textarea',
                'required' => false,
                'priority' => 8,
                'hint' => 'O que deve aparecer no verso (opcional)'
            ]
        ];
    }
    
    /**
     * Determina quais campos ainda faltam coletar
     * 
     * IMPORTANTE: Separação clara entre CADASTRO (Asaas) e BRIEFING (serviço)
     * - Primeiro: coleta dados de cadastro (sempre necessário)
     * - Depois: coleta briefing específico do serviço
     * 
     * @param array $collectedData Dados já coletados
     * @param string $serviceType Tipo de serviço ('business_card', 'other')
     * @param bool $cadastroComplete Se true, considera apenas campos do briefing. Se false, apenas cadastro.
     * @return array Campos que ainda precisam ser coletados, ordenados por prioridade
     */
    public static function getMissingFields(array $collectedData, string $serviceType = 'business_card', bool $cadastroComplete = false): array
    {
        // Se cadastro não está completo, retorna apenas campos de cadastro
        if (!$cadastroComplete) {
            return self::getMissingCadastroFields($collectedData);
        }
        
        // Se cadastro está completo, retorna campos do briefing
        $requiredFields = [];
        
        // Se for cartão de visita, adiciona campos específicos
        if ($serviceType === 'business_card') {
            $cardFields = self::getRequiredFieldsForBusinessCard();
            $requiredFields = $cardFields;
        }
        
        // Remove campos já coletados (pode ter sido coletado no cadastro)
        return self::filterCollectedFields($requiredFields, $collectedData);
    }
    
    /**
     * Retorna apenas campos de cadastro que faltam
     */
    private static function getMissingCadastroFields(array $collectedData): array
    {
        $asaasFields = self::getRequiredFieldsForAsaas();
        return self::filterCollectedFields($asaasFields, $collectedData);
    }
    
    /**
     * Filtra campos já coletados
     */
    private static function filterCollectedFields(array $requiredFields, array $collectedData): array
    {
        $missing = [];
        
        foreach ($requiredFields as $fieldKey => $fieldConfig) {
            $isCollected = false;
            
            // Verifica se campo simples foi coletado
            if (isset($collectedData[$fieldKey]) && !empty($collectedData[$fieldKey])) {
                $isCollected = true;
            }
            
            // Verifica campos aninhados (como address)
            if ($fieldKey === 'address' && isset($collectedData['address'])) {
                // Para Asaas, CEP é suficiente para endereço
                if (isset($collectedData['address']['cep']) && !empty($collectedData['address']['cep'])) {
                    $isCollected = true;
                }
            }
            
            // Verifica campos do briefing (tanto q_ quanto campos diretos como segment)
            if (isset($collectedData['briefing'][$fieldKey])) {
                $isCollected = !empty($collectedData['briefing'][$fieldKey]);
            }
            // Verifica se está no formato q_{fieldKey} (para perguntas do briefing)
            if (isset($collectedData['briefing']['q_' . $fieldKey])) {
                $isCollected = !empty($collectedData['briefing']['q_' . $fieldKey]);
            }
            
            if (!$isCollected && ($fieldConfig['required'] ?? false)) {
                $missing[$fieldKey] = $fieldConfig;
            }
        }
        
        // Ordena por prioridade
        usort($missing, function($a, $b) {
            return ($a['priority'] ?? 999) - ($b['priority'] ?? 999);
        });
        
        return $missing;
    }
    
    /**
     * Gera prompt inteligente para IA baseado no que falta
     * 
     * @param array $missingFields Campos que faltam
     * @param array $collectedData Dados já coletados
     * @param array $conversationHistory Histórico da conversa
     * @return string Prompt otimizado
     */
    public static function buildIntelligentPrompt(
        array $missingFields,
        array $collectedData,
        array $conversationHistory = []
    ): string {
        $nextField = reset($missingFields);
        $nextFieldKey = key($missingFields);
        
        if (!$nextField) {
            return "Todas as informações foram coletadas. Confirme se está tudo correto.";
        }
        
        $context = "Dados já coletados:\n";
        if (!empty($collectedData['name'])) {
            $context .= "- Nome: {$collectedData['name']}\n";
        }
        if (!empty($collectedData['email'])) {
            $context .= "- Email: {$collectedData['email']}\n";
        }
        if (!empty($collectedData['phone'])) {
            $context .= "- Telefone: {$collectedData['phone']}\n";
        }
        
        $prompt = <<<PROMPT
Você está coletando dados para criar um pedido. 

{$context}

PRÓXIMA INFORMAÇÃO NECESSÁRIA: {$nextField['label']}
{$nextField['hint']}

IMPORTANTE:
- Seja direto e objetivo
- Não faça perguntas desnecessárias
- Se o usuário tentar corrigir algo, ajude imediatamente
- Se o usuário der a informação de forma não convencional, extraia o valor

Gere uma pergunta natural e amigável, mas curta.
PROMPT;
        
        return $prompt;
    }
    
    /**
     * Valida se todos os dados necessários foram coletados
     */
    public static function isComplete(array $collectedData, string $serviceType = 'business_card'): bool
    {
        $missing = self::getMissingFields($collectedData, $serviceType);
        return empty($missing);
    }
    
    /**
     * Extrai dados relevantes de uma mensagem do usuário usando IA
     */
    public static function extractDataFromMessage(string $message, array $expectedFields): array
    {
        // Se não tiver API key, usa extração básica
        $apiKey = Env::get('OPENAI_API_KEY');
        if (empty($apiKey)) {
            return self::basicExtraction($message, $expectedFields);
        }
        
        // Usa IA para extração inteligente
        try {
            return self::aiExtraction($message, $expectedFields, $apiKey);
        } catch (\Exception $e) {
            error_log('[IntelligentDataCollector] Erro na extração com IA: ' . $e->getMessage());
            return self::basicExtraction($message, $expectedFields);
        }
    }
    
    /**
     * Extração básica sem IA (fallback)
     */
    private static function basicExtraction(string $message, array $expectedFields): array
    {
        $extracted = [];
        
        // Extrai email
        if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $message, $matches)) {
            $extracted['email'] = $matches[0];
        }
        
        // Extrai telefone
        if (preg_match('/(\(?\d{2}\)?\s?)?\d{4,5}-?\d{4}/', $message, $matches)) {
            $extracted['phone'] = $matches[0];
        }
        
        // Extrai CEP
        if (preg_match('/\d{5}-?\d{3}/', $message, $matches)) {
            $extracted['cep'] = $matches[0];
        }
        
        return $extracted;
    }
    
    /**
     * Extração usando IA
     */
    private static function aiExtraction(string $message, array $expectedFields, string $apiKey): array
    {
        $fieldList = implode(', ', array_keys($expectedFields));
        
        $prompt = <<<PROMPT
Extraia dados estruturados da seguinte mensagem do usuário:

MENSAGEM: "{$message}"

CAMPOS ESPERADOS: {$fieldList}

Extraia APENAS os dados que estão claramente presentes na mensagem. Se não tiver certeza, não extraia.

Responda em JSON:
{
    "extracted": {
        "campo1": "valor1",
        "campo2": "valor2"
    },
    "confidence": 0.0-1.0
}
PROMPT;
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.1,
                'max_tokens' => 200
            ])
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception('Erro na API: ' . $httpCode);
        }
        
        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '{}';
        
        // Tenta extrair JSON
        if (preg_match('/\{[^}]+\}/s', $content, $matches)) {
            $json = json_decode($matches[0], true);
            return $json['extracted'] ?? [];
        }
        
        return [];
    }
}

