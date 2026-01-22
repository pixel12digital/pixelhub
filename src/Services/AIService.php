<?php

namespace PixelHub\Services;

use PixelHub\Core\Env;
use PixelHub\Core\CryptoHelper;

/**
 * Serviço para integração com IA (OpenAI, etc.)
 */
class AIService
{
    /**
     * Gera sugestões de nomes de projeto usando IA
     * 
     * @param array $context Contexto com informações do cliente e serviços
     * @return array Lista de sugestões
     */
    public static function suggestProjectNames(array $context): array
    {
        $clientName = $context['client_name'] ?? '';
        $services = $context['services'] ?? [];
        $categories = $context['categories'] ?? [];
        $descriptions = $context['descriptions'] ?? [];
        
        // Verifica se IA está ativada
        $isActive = Env::get('OPENAI_ACTIVE', '1') === '1';
        if (!$isActive) {
            return self::suggestWithTemplates($clientName, $services, $categories, $descriptions);
        }
        
        // Tenta usar OpenAI se estiver configurado
        $openaiApiKeyRaw = Env::get('OPENAI_API_KEY');
        $openaiApiKey = self::decryptApiKey($openaiApiKeyRaw);
        
        if (!empty($openaiApiKey)) {
            return self::suggestWithOpenAI($clientName, $services, $categories, $openaiApiKey, $descriptions);
        }
        
        // Fallback: gera sugestões baseadas em templates
        return self::suggestWithTemplates($clientName, $services, $categories, $descriptions);
    }
    
    /**
     * Descriptografa a chave de API se necessário
     */
    private static function decryptApiKey(?string $apiKeyRaw): string
    {
        if (empty($apiKeyRaw)) {
            return '';
        }
        
        $apiKeyRaw = trim($apiKeyRaw);
        
        // Chaves OpenAI geralmente começam com "sk-" ou "pk-"
        // Se começa com isso, está em texto plano
        if (strpos($apiKeyRaw, 'sk-') === 0 || strpos($apiKeyRaw, 'pk-') === 0) {
            return $apiKeyRaw;
        }
        
        // Se é muito longa (>100 chars) e não começa com sk/pk, provavelmente é criptografada
        if (strlen($apiKeyRaw) > 100) {
            try {
                $decrypted = CryptoHelper::decrypt($apiKeyRaw);
                // Verifica se descriptografou corretamente (chaves OpenAI começam com sk- ou pk-)
                if (!empty($decrypted) && (strpos($decrypted, 'sk-') === 0 || strpos($decrypted, 'pk-') === 0)) {
                    return $decrypted;
                }
            } catch (\Exception $e) {
                // Se falhar ao descriptografar, retorna vazio
                error_log("Erro ao descriptografar chave OpenAI: " . $e->getMessage());
                return '';
            }
        }
        
        // Se não parece criptografada nem em formato válido, retorna como está
        return $apiKeyRaw;
    }
    
    /**
     * Gera sugestões usando OpenAI API
     */
    private static function suggestWithOpenAI(string $clientName, array $services, array $categories, string $apiKey, array $descriptions = []): array
    {
        if (empty($apiKey)) {
            return self::suggestWithTemplates($clientName, $services, $categories, $descriptions);
        }
        
        try {
            // Prepara informações dos serviços de forma mais detalhada
            $servicesCount = count($services);
            
            // Lista serviços de forma numerada e detalhada
            $servicesDetailed = [];
            for ($i = 0; $i < count($services); $i++) {
                $serviceName = $services[$i];
                $serviceDesc = !empty($descriptions[$i]) ? $descriptions[$i] : '';
                $serviceCat = !empty($categories[$i]) ? $categories[$i] : '';
                
                $serviceInfo = ($i + 1) . ". {$serviceName}";
                if (!empty($serviceCat)) {
                    $serviceInfo .= " (categoria: {$serviceCat})";
                }
                if (!empty($serviceDesc)) {
                    $serviceInfo .= "\n   Descrição: " . substr($serviceDesc, 0, 150) . (strlen($serviceDesc) > 150 ? '...' : '');
                }
                $servicesDetailed[] = $serviceInfo;
            }
            
            $servicesList = implode("\n", $servicesDetailed);
            
            // Analisa os serviços para identificar o tipo macro (incluindo descrições)
            $macroType = self::identifyMacroServiceType($services, $descriptions);
            
            // Identifica palavras-chave principais dos serviços
            $keywords = self::extractServiceKeywords($services, $descriptions);
            
            // Prepara prompt detalhado e estruturado
            $prompt = "Você é um especialista em criar nomes de projetos para sistemas de gerenciamento.\n\n";
            $prompt .= "=== ANÁLISE DOS SERVIÇOS SELECIONADOS ===\n\n";
            $prompt .= "Total de serviços: {$servicesCount}\n\n";
            $prompt .= "SERVIÇOS DETALHADOS:\n{$servicesList}\n\n";
            
            if (!empty($keywords)) {
                $prompt .= "PALAVRAS-CHAVE IDENTIFICADAS: " . implode(', ', $keywords) . "\n\n";
            }
            
            if (!empty($macroType)) {
                $prompt .= "TIPO MACRO IDENTIFICADO: {$macroType}\n\n";
            }
            
            if (!empty($clientName)) {
                $prompt .= "CLIENTE: {$clientName}\n\n";
            }
            
            $prompt .= "=== REGRAS PARA CRIAÇÃO DOS NOMES ===\n\n";
            $prompt .= "1. ESPECIFICIDADE: O nome DEVE descrever EXATAMENTE os serviços selecionados acima\n";
            $prompt .= "2. ANÁLISE PROFUNDA: Analise cada serviço individualmente e identifique:\n";
            $prompt .= "   - O tipo principal de trabalho (ex: Site, Logo, Cartão, E-commerce)\n";
            $prompt .= "   - A natureza do serviço (ex: Criação, Desenvolvimento, Design, Gestão)\n";
            $prompt .= "   - Características especiais (ex: Profissional, Institucional, Responsivo)\n\n";
            
            $prompt .= "3. COMBINAÇÃO INTELIGENTE (quando múltiplos serviços):\n";
            $prompt .= "   - Se os serviços são complementares (ex: Site + Cartão): combine-os de forma natural\n";
            $prompt .= "   - Use conectores apropriados: 'e', '+', 'com', 'mais'\n";
            $prompt .= "   - Exemplos: 'Site Institucional e Cartão de Visita', 'Desenvolvimento Web + Design Gráfico'\n";
            $prompt .= "   - Evite termos genéricos como 'Pacote' ou 'Conjunto' a menos que seja realmente um pacote pré-definido\n\n";
            
            $prompt .= "4. FORMATO PREFERENCIAL:\n";
            $prompt .= "   - '[Tipo de Serviço Principal] - [Cliente]' (quando 1 serviço)\n";
            $prompt .= "   - '[Serviço 1] e [Serviço 2] - [Cliente]' (quando 2 serviços complementares)\n";
            $prompt .= "   - '[Tipo Macro] - [Cliente]' (quando múltiplos serviços do mesmo tipo)\n\n";
            
            $prompt .= "5. EXEMPLOS CORRETOS BASEADOS EM SERVIÇOS REAIS:\n";
            $prompt .= "   - Serviços: 'Criação de Site Institucional' + 'Cartão de Visita Profissional'\n";
            $prompt .= "     → Sugestões: 'Site Institucional e Cartão de Visita - [Cliente]',\n";
            $prompt .= "                  'Desenvolvimento Web e Material Gráfico - [Cliente]',\n";
            $prompt .= "                  'Site e Cartão Profissional - [Cliente]'\n\n";
            $prompt .= "   - Serviço: 'Criação de Site Institucional'\n";
            $prompt .= "     → Sugestões: 'Site Institucional - [Cliente]',\n";
            $prompt .= "                  'Desenvolvimento de Site - [Cliente]',\n";
            $prompt .= "                  'Criação de Site Institucional - [Cliente]'\n\n";
            
            $prompt .= "6. EXEMPLOS INCORRETOS (evitar):\n";
            $prompt .= "   - 'Pacote Digital' (muito genérico)\n";
            $prompt .= "   - 'Solução Completa' (não descreve os serviços)\n";
            $prompt .= "   - 'Serviços Web e Impressão' (muito vago, não especifica)\n\n";
            
            $prompt .= "=== INSTRUÇÕES FINAIS ===\n\n";
            $prompt .= "1. LEIA CADA SERVIÇO individualmente e identifique seu tipo específico\n";
            $prompt .= "2. SE HOUVER MÚLTIPLOS SERVIÇOS, analise se são complementares ou do mesmo tipo\n";
            $prompt .= "3. CRIE 5 nomes VARIADOS que:\n";
            $prompt .= "   - Descrevam claramente os serviços (não genéricos)\n";
            $prompt .= "   - Sejam objetivos e funcionais (máximo 70 caracteres)\n";
            $prompt .= "   - Priorizem especificidade sobre generalidade\n";
            $prompt .= "   - Variem na forma de combinar (quando múltiplos serviços)\n\n";
            $prompt .= "4. Retorne APENAS os 5 nomes, um por linha, sem numeração, sem explicações.\n";
            $prompt .= "5. Cada nome deve ser único e oferecer uma perspectiva diferente dos serviços.\n";
            
            // Carrega configurações do .env
            // Temperatura mais baixa (0.5) para sugestões mais consistentes e focadas
            $model = Env::get('OPENAI_MODEL', 'gpt-4o');
            $temperature = (float) Env::get('OPENAI_TEMPERATURE', '0.5');
            $maxTokens = (int) Env::get('OPENAI_MAX_TOKENS', '800');
            
            // Chama API do OpenAI
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Você é um especialista em criar nomes de projetos para sistemas de gerenciamento interno. Sua função é analisar serviços específicos selecionados e criar nomes descritivos, funcionais e objetivos que reflitam EXATAMENTE os serviços que serão prestados. Você deve:\n\n1. Analisar profundamente cada serviço individual\n2. Identificar tipos, naturezas e características específicas\n3. Combinar serviços de forma inteligente quando houver múltiplos\n4. Priorizar especificidade e clareza sobre generalidade\n5. Evitar termos genéricos como "Pacote", "Solução", "Serviços" sem contexto\n6. Criar nomes que sejam úteis para identificação rápida no sistema\n\nOs nomes devem ser funcionais, não marketing. Foque em DESCREVER os serviços, não em vender ou impressionar.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature,
                ]),
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['choices'][0]['message']['content'])) {
                    $content = trim($data['choices'][0]['message']['content']);
                    
                    // Limpa possíveis numerações, bullets, hífens no início
                    $lines = explode("\n", $content);
                    $suggestions = [];
                    
                    foreach ($lines as $line) {
                        $line = trim($line);
                        // Remove numeração no início (1., 2., - , •, etc)
                        $line = preg_replace('/^[\d\.\-\•\*\#]\s*/', '', $line);
                        $line = trim($line);
                        
                        // Remove prefixos comuns como "Nome:", "Sugestão:", etc
                        $line = preg_replace('/^(Nome|Sugestão|Opção|Projeto):\s*/i', '', $line);
                        $line = trim($line);
                        
                        if (!empty($line) && strlen($line) <= 80 && strlen($line) > 3) {
                            $suggestions[] = $line;
                        }
                    }
                    
                    // Remove duplicatas mantendo a ordem
                    $suggestions = array_values(array_unique($suggestions));
                    
                    return array_slice($suggestions, 0, 5);
                }
            }
            
            // Se falhar, usa templates
            return self::suggestWithTemplates($clientName, $services, $categories, $descriptions);
            
        } catch (\Exception $e) {
            error_log("Erro ao chamar OpenAI: " . $e->getMessage());
            return self::suggestWithTemplates($clientName, $services, $categories, $descriptions);
        }
    }
    
    /**
     * Gera sugestões usando templates (fallback)
     */
    private static function suggestWithTemplates(string $clientName, array $services, array $categories, array $descriptions = []): array
    {
        $suggestions = [];
        $templates = [];
        
        if (empty($services)) {
            return ['Projeto - ' . ($clientName ?: 'Cliente')];
        }
        
        // Analisa todos os serviços para identificar tipos macro (incluindo descrições)
        $macroType = self::identifyMacroServiceType($services, $descriptions);
        $servicesCount = count($services);
        
        // Normaliza todos os serviços
        $normalizedServices = array_map([self::class, 'normalizeServiceName'], $services);
        $firstService = $normalizedServices[0];
        
        // Se houver apenas um serviço
        if ($servicesCount === 1) {
            if (!empty($clientName)) {
                $templates[] = $firstService . ' - ' . $clientName;
                // Adiciona variações mais descritivas
                if (stripos($firstService, 'site') !== false || stripos($firstService, 'web') !== false) {
                    $templates[] = 'Desenvolvimento de Site - ' . $clientName;
                    $templates[] = 'Site Institucional - ' . $clientName;
                } elseif (stripos($firstService, 'cartão') !== false || stripos($firstService, 'card') !== false) {
                    $templates[] = 'Cartão de Visita Profissional - ' . $clientName;
                } elseif (stripos($firstService, 'logo') !== false || stripos($firstService, 'identidade') !== false) {
                    $templates[] = 'Identidade Visual - ' . $clientName;
                }
            } else {
                $templates[] = $firstService;
            }
        } else {
            // Múltiplos serviços - combina de forma inteligente
            if (!empty($clientName)) {
                // Opção 1: Combina os dois principais com "e"
                if ($servicesCount >= 2) {
                    $secondService = $normalizedServices[1];
                    $templates[] = $firstService . ' e ' . $secondService . ' - ' . $clientName;
                    
                    // Variação com conectores diferentes
                    if ($servicesCount === 2) {
                        $templates[] = $firstService . ' + ' . $secondService . ' - ' . $clientName;
                    }
                }
                
                // Opção 2: Usa o serviço principal
                $templates[] = $firstService . ' - ' . $clientName;
                
                // Opção 3: Se todos são do mesmo tipo macro, usa o tipo macro
                if (!empty($macroType)) {
                    $templates[] = $macroType . ' - ' . $clientName;
                }
                
                // Opção 4: Combina tipos específicos quando identificados
                $hasSite = preg_match('/\b(site|website|web|institucional)\b/i', $allServicesLower);
                $hasCard = preg_match('/\b(cartão|card|visita)\b/i', $allServicesLower);
                $hasDesign = preg_match('/\b(logo|identidade|design|gráfico)\b/i', $allServicesLower);
                
                if ($hasSite && $hasCard) {
                    $templates[] = 'Site Institucional e Cartão de Visita - ' . $clientName;
                    $templates[] = 'Desenvolvimento Web e Material Gráfico - ' . $clientName;
                } elseif ($hasSite && $hasDesign) {
                    $templates[] = 'Site e Identidade Visual - ' . $clientName;
                } elseif ($hasCard && $hasDesign) {
                    $templates[] = 'Identidade Visual e Cartão de Visita - ' . $clientName;
                }
                
                // Opção 5: Pacote apenas como última opção
                if (count($templates) < 5) {
                    $templates[] = 'Pacote de Serviços - ' . $clientName;
                }
            } else {
                // Sem cliente - apenas serviços
                if ($servicesCount === 2) {
                    $templates[] = $normalizedServices[0] . ' e ' . $normalizedServices[1];
                    $templates[] = $normalizedServices[0] . ' + ' . $normalizedServices[1];
                } else {
                    $templates[] = implode(' + ', array_slice($normalizedServices, 0, 3));
                }
                if (!empty($macroType)) {
                    $templates[] = $macroType;
                }
            }
        }
        
        // Templates específicos baseados em análise detalhada dos serviços
        // Inclui descrições na análise se disponíveis
        $allServicesText = implode(' ', array_map('strtolower', $services));
        if (!empty($descriptions)) {
            $allServicesText .= ' ' . implode(' ', array_map('strtolower', array_filter($descriptions)));
        }
        $allServicesLower = $allServicesText;
        
        // Detecta Site/Website
        if (preg_match('/\b(site|website|web|institucional|desenvolvimento)\b/i', $allServicesLower)) {
            if (!empty($clientName)) {
                $templates[] = 'Site Institucional - ' . $clientName;
                $templates[] = 'Desenvolvimento de Site - ' . $clientName;
                $templates[] = 'Criação de Site Institucional - ' . $clientName;
            } else {
                $templates[] = 'Site Institucional';
                $templates[] = 'Desenvolvimento de Site';
            }
        }
        
        // Detecta Logo/Identidade Visual
        if (preg_match('/\b(logo|identidade|brand|marca|visual|branding)\b/i', $allServicesLower)) {
            if (!empty($clientName)) {
                $templates[] = 'Identidade Visual - ' . $clientName;
                $templates[] = 'Criação de Logo - ' . $clientName;
                $templates[] = 'Desenvolvimento de Identidade Visual - ' . $clientName;
            } else {
                $templates[] = 'Identidade Visual';
                $templates[] = 'Criação de Logo';
            }
        }
        
        // Detecta Cartão de Visita
        if (preg_match('/\b(cartão|card|visita|visiting)\b/i', $allServicesLower)) {
            if (!empty($clientName)) {
                $templates[] = 'Cartão de Visita - ' . $clientName;
                $templates[] = 'Cartão de Visita Profissional - ' . $clientName;
            } else {
                $templates[] = 'Cartão de Visita';
            }
        }
        
        // Detecta E-commerce
        if (preg_match('/\b(e-commerce|ecommerce|loja|online|shop)\b/i', $allServicesLower)) {
            if (!empty($clientName)) {
                $templates[] = 'E-commerce - ' . $clientName;
                $templates[] = 'Loja Online - ' . $clientName;
                $templates[] = 'Desenvolvimento de E-commerce - ' . $clientName;
            } else {
                $templates[] = 'E-commerce';
                $templates[] = 'Loja Online';
            }
        }
        
        // Detecta Redes Sociais
        if (preg_match('/\b(social|mídia|media|instagram|facebook|redes)\b/i', $allServicesLower)) {
            if (!empty($clientName)) {
                $templates[] = 'Gestão de Redes Sociais - ' . $clientName;
                $templates[] = 'Social Media - ' . $clientName;
            } else {
                $templates[] = 'Gestão de Redes Sociais';
            }
        }
        
        // Remove duplicatas mantendo ordem e limita a 5 sugestões
        $uniqueTemplates = [];
        foreach ($templates as $template) {
            if (!in_array($template, $uniqueTemplates, true)) {
                $uniqueTemplates[] = $template;
            }
            if (count($uniqueTemplates) >= 5) {
                break;
            }
        }
        
        return $uniqueTemplates;
    }
    
    /**
     * Extrai palavras-chave principais dos serviços
     */
    private static function extractServiceKeywords(array $services, array $descriptions = []): array
    {
        $keywords = [];
        $allText = implode(' ', array_map('strtolower', $services));
        
        if (!empty($descriptions)) {
            $allText .= ' ' . implode(' ', array_map('strtolower', array_filter($descriptions)));
        }
        
        // Palavras-chave importantes para identificar
        $importantKeywords = [
            'site', 'website', 'web', 'institucional', 'e-commerce', 'ecommerce', 'loja',
            'logo', 'identidade', 'visual', 'brand', 'marca', 'branding',
            'cartão', 'card', 'visita', 'profissional',
            'social', 'mídia', 'media', 'redes', 'instagram', 'facebook',
            'design', 'gráfico', 'graphic', 'impressão', 'print',
            'desenvolvimento', 'criação', 'desenvolvimento', 'gestão', 'gerenciamento'
        ];
        
        foreach ($importantKeywords as $keyword) {
            if (stripos($allText, $keyword) !== false) {
                $keywords[] = $keyword;
            }
        }
        
        return array_unique($keywords);
    }
    
    /**
     * Identifica o tipo macro de serviço baseado nos serviços selecionados
     */
    private static function identifyMacroServiceType(array $services, array $descriptions = []): string
    {
        if (empty($services)) {
            return '';
        }
        
        // Combina nomes e descrições para análise mais completa
        $allServicesText = implode(' ', array_map('strtolower', $services));
        if (!empty($descriptions)) {
            $allServicesText .= ' ' . implode(' ', array_map('strtolower', array_filter($descriptions)));
        }
        $allServicesLower = $allServicesText;
        
        // Prioridade: verifica tipos mais específicos primeiro
        if (preg_match('/\b(e-commerce|ecommerce|loja|online|shop)\b/i', $allServicesLower)) {
            return 'E-commerce';
        }
        
        if (preg_match('/\b(site|website|web|institucional)\b/i', $allServicesLower)) {
            return 'Site Institucional';
        }
        
        if (preg_match('/\b(logo|identidade|brand|marca|visual|branding)\b/i', $allServicesLower)) {
            return 'Identidade Visual';
        }
        
        if (preg_match('/\b(cartão|card|visita)\b/i', $allServicesLower)) {
            return 'Cartão de Visita';
        }
        
        if (preg_match('/\b(social|mídia|media|redes)\b/i', $allServicesLower)) {
            return 'Gestão de Redes Sociais';
        }
        
        return '';
    }
    
    /**
     * Normaliza nome do serviço para formato descritivo funcional
     */
    private static function normalizeServiceName(string $serviceName): string
    {
        $service = trim($serviceName);
        $serviceLower = strtolower($service);
        
        // Mapeia termos comuns para descrições mais funcionais
        $normalizations = [
            'site' => 'Site',
            'website' => 'Site',
            'web' => 'Site',
            'e-commerce' => 'E-commerce',
            'ecommerce' => 'E-commerce',
            'loja' => 'E-commerce',
            'loja online' => 'E-commerce',
            'logo' => 'Identidade Visual',
            'identidade' => 'Identidade Visual',
            'branding' => 'Identidade Visual',
            'marca' => 'Identidade Visual',
            'cartão' => 'Cartão de Visita',
            'card' => 'Cartão de Visita',
            'flyer' => 'Flyer',
            'panfleto' => 'Flyer',
            'social media' => 'Gestão de Redes Sociais',
            'redes sociais' => 'Gestão de Redes Sociais',
            'mídia social' => 'Gestão de Redes Sociais',
        ];
        
        // Verifica se o nome do serviço contém algum termo conhecido
        foreach ($normalizations as $term => $description) {
            if (strpos($serviceLower, $term) !== false) {
                return $description;
            }
        }
        
        // Se não encontrar normalização, capitaliza primeira letra
        return ucfirst($service);
    }
}

