<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PDO;

/**
 * Service de orquestração de coleta de dados para Cartão de Visita Express
 * 
 * Gerencia as etapas do brief estruturado e extrai dados para service_intakes.
 */
class BusinessCardIntakeService
{
    /**
     * Define as etapas do fluxo de cartão de visita
     */
    const STEPS = [
        'step_0_welcome' => [
            'name' => 'Boas-vindas',
            'order' => 0
        ],
        'step_1_identity' => [
            'name' => 'Identidade',
            'order' => 1,
            'fields' => ['full_name', 'job_title', 'company']
        ],
        'step_2_contacts' => [
            'name' => 'Contatos',
            'order' => 2,
            'fields' => ['phone_whatsapp', 'email', 'website', 'instagram']
        ],
        'step_3_style' => [
            'name' => 'Estilo',
            'order' => 3,
            'fields' => ['style']
        ],
        'step_4_logo_assets' => [
            'name' => 'Logo e Assets',
            'order' => 4,
            'fields' => ['logo']
        ],
        'step_5_qr' => [
            'name' => 'QR Code',
            'order' => 5,
            'fields' => ['qr']
        ],
        'step_6_confirmation' => [
            'name' => 'Confirmação',
            'order' => 6
        ],
        'step_7_generation' => [
            'name' => 'Geração',
            'order' => 7
        ],
        'step_8_delivery' => [
            'name' => 'Entrega',
            'order' => 8
        ]
    ];
    
    /**
     * Cria ou atualiza intake de cartão de visita
     * 
     * @param int $orderId ID do pedido
     * @param array $data Dados coletados (parciais ou completos)
     * @return int ID do intake (criado ou atualizado)
     */
    public static function updateIntake(int $orderId, array $data): int
    {
        $db = DB::getConnection();
        
        // Verifica se já existe intake
        $existing = self::findIntakeByOrder($orderId);
        
        // Faz merge com dados existentes se houver
        $mergedData = $existing ? json_decode($existing['data_json'], true) : [];
        $mergedData = array_merge($mergedData, $data);
        
        // Calcula completeness_score
        $completeness = self::calculateCompleteness($mergedData);
        
        // Valida se está completo
        $isValid = $completeness >= 80; // Threshold de 80%
        
        $dataJson = json_encode($mergedData, JSON_UNESCAPED_UNICODE);
        
        if ($existing) {
            // Atualiza
            $stmt = $db->prepare("
                UPDATE service_intakes 
                SET data_json = ?, 
                    completeness_score = ?, 
                    is_valid = ?,
                    updated_at = NOW(),
                    validated_at = CASE WHEN ? = 1 AND validated_at IS NULL THEN NOW() ELSE validated_at END
                WHERE id = ?
            ");
            $stmt->execute([$dataJson, $completeness, $isValid ? 1 : 0, $isValid ? 1 : 0, $existing['id']]);
            
            // Log
            error_log(sprintf(
                '[BusinessCardIntake] intake_updated: order_id=%d, score=%d, valid=%s',
                $orderId,
                $completeness,
                $isValid ? 'YES' : 'NO'
            ));
            
            $intakeId = (int) $existing['id'];
        } else {
            // Cria novo
            $stmt = $db->prepare("
                INSERT INTO service_intakes 
                (order_id, service_slug, data_json, completeness_score, is_valid, created_at, updated_at, validated_at)
                VALUES (?, 'business_card_express', ?, ?, ?, NOW(), NOW(), ?)
            ");
            
            $validatedAt = $isValid ? date('Y-m-d H:i:s') : null;
            $stmt->execute([$orderId, $dataJson, $completeness, $isValid ? 1 : 0, $validatedAt]);
            
            $intakeId = (int) $db->lastInsertId();
            
            // Log
            error_log(sprintf(
                '[BusinessCardIntake] intake_created: intake_id=%d, order_id=%d, score=%d',
                $intakeId,
                $orderId,
                $completeness
            ));
        }
        
        // SINCRONIZAÇÃO: Atualiza service_orders.briefing_data e client_data em tempo real
        self::syncIntakeToServiceOrder($orderId, $mergedData);
        
        return $intakeId;
    }
    
    /**
     * Sincroniza dados do intake para service_orders (briefing_data e client_data)
     * 
     * @param int $orderId ID do pedido
     * @param array $intakeData Dados do intake
     * @return bool Sucesso da sincronização
     */
    private static function syncIntakeToServiceOrder(int $orderId, array $intakeData): bool
    {
        $db = DB::getConnection();
        
        try {
            // Busca pedido atual
            $order = \PixelHub\Services\ServiceOrderService::findOrder($orderId);
            if (!$order) {
                error_log("[BusinessCardIntake] Erro ao sincronizar: pedido não encontrado (order_id={$orderId})");
                return false;
            }
            
            // Prepara dados do cliente (client_data)
            $clientData = [];
            if (!empty($intakeData['full_name'])) {
                $clientData['name'] = $intakeData['full_name'];
            }
            if (!empty($intakeData['phone_whatsapp'])) {
                $clientData['phone'] = $intakeData['phone_whatsapp'];
            }
            if (!empty($intakeData['email'])) {
                $clientData['email'] = $intakeData['email'];
            }
            
            // Faz merge com client_data existente
            $existingClientData = !empty($order['client_data']) ? json_decode($order['client_data'], true) : [];
            if (!is_array($existingClientData)) {
                $existingClientData = [];
            }
            $clientData = array_merge($existingClientData, $clientData);
            
            // Prepara dados do briefing (briefing_data)
            // Converte formato do intake para formato esperado pelo briefing
            $briefingData = [];
            
            // Dados básicos
            if (!empty($intakeData['company'])) {
                $briefingData['q_empresa_nome'] = $intakeData['company'];
            }
            if (!empty($intakeData['segment'])) {
                $briefingData['q_segment'] = $intakeData['segment'];
            }
            if (!empty($intakeData['style'])) {
                if (is_array($intakeData['style'])) {
                    if (!empty($intakeData['style']['background'])) {
                        $briefingData['q_cores_preferencia'] = ucfirst($intakeData['style']['background']);
                    }
                    if (!empty($intakeData['style']['mood'])) {
                        $briefingData['q_estilo'] = $intakeData['style']['mood'];
                    }
                    if (!empty($intakeData['style']['colors'])) {
                        $briefingData['q_cores_especificas'] = implode(', ', $intakeData['style']['colors']);
                    }
                }
            }
            
            // Dados do verso do cartão
            $backSideFields = [];
            $backSideInclude = [];
            
            if (!empty($intakeData['full_name'])) {
                $backSideFields['nome'] = $intakeData['full_name'];
                $backSideInclude[] = 'nome';
            }
            if (!empty($intakeData['job_title'])) {
                $backSideFields['cargo'] = $intakeData['job_title'];
                $backSideInclude[] = 'cargo';
            }
            if (!empty($intakeData['phone_whatsapp'])) {
                $backSideFields['whatsapp'] = $intakeData['phone_whatsapp'];
                $backSideInclude[] = 'whatsapp';
            }
            if (!empty($intakeData['email'])) {
                $backSideFields['email'] = $intakeData['email'];
                $backSideInclude[] = 'email';
            }
            if (!empty($intakeData['city_state'])) {
                $backSideFields['endereco'] = $intakeData['city_state'];
                $backSideInclude[] = 'endereco';
            }
            
            // QR Code
            if (!empty($intakeData['qr']) && is_array($intakeData['qr']) && !empty($intakeData['qr']['enabled'])) {
                $backSideInclude[] = 'qr_code';
                $backSideQr = [
                    'type' => $intakeData['qr']['target'] ?? 'whatsapp',
                    'value' => $intakeData['qr']['value'] ?? ''
                ];
                $briefingData['back_side'] = [
                    'include' => $backSideInclude,
                    'fields' => $backSideFields,
                    'qr' => $backSideQr
                ];
            } else {
                $briefingData['back_side'] = [
                    'include' => $backSideInclude,
                    'fields' => $backSideFields
                ];
            }
            
            // Faz merge com briefing_data existente
            $existingBriefingData = !empty($order['briefing_data']) ? json_decode($order['briefing_data'], true) : [];
            if (!is_array($existingBriefingData)) {
                $existingBriefingData = [];
            }
            $briefingData = array_merge($existingBriefingData, $briefingData);
            
            // Atualiza status do pedido baseado na completude
            $newStatus = $order['status'];
            if (!empty($clientData) && $order['status'] === 'draft') {
                $newStatus = 'client_data_filled';
            }
            if (!empty($briefingData) && in_array($order['status'], ['draft', 'client_data_filled'])) {
                $newStatus = 'briefing_filled';
            }
            
            // Atualiza service_orders
            $stmt = $db->prepare("
                UPDATE service_orders 
                SET client_data = ?,
                    briefing_data = ?,
                    briefing_status = CASE WHEN ? = 1 THEN 'completed' ELSE briefing_status END,
                    briefing_completed_at = CASE WHEN ? = 1 AND briefing_completed_at IS NULL THEN NOW() ELSE briefing_completed_at END,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $clientDataJson = !empty($clientData) ? json_encode($clientData, JSON_UNESCAPED_UNICODE) : null;
            $briefingDataJson = !empty($briefingData) ? json_encode($briefingData, JSON_UNESCAPED_UNICODE) : null;
            
            // Calcula completude para determinar se está completo
            $completeness = self::calculateCompleteness($intakeData);
            $isComplete = $completeness >= 80;
            
            $stmt->execute([
                $clientDataJson,
                $briefingDataJson,
                $isComplete ? 1 : 0,
                $isComplete ? 1 : 0,
                $newStatus,
                $orderId
            ]);
            
            // Log
            error_log(sprintf(
                '[BusinessCardIntake] sync_to_service_order: order_id=%d, client_data=%s, briefing_data=%s, status=%s',
                $orderId,
                !empty($clientData) ? 'YES' : 'NO',
                !empty($briefingData) ? 'YES' : 'NO',
                $newStatus
            ));
            
            return true;
        } catch (\Exception $e) {
            error_log("[BusinessCardIntake] Erro ao sincronizar para service_orders: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Busca intake por order_id
     * 
     * @param int $orderId ID do pedido
     * @return array|null Intake ou null se não encontrado
     */
    public static function findIntakeByOrder(int $orderId): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM service_intakes 
            WHERE order_id = ?
            LIMIT 1
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Calcula score de completude (0-100)
     * 
     * @param array $data Dados coletados
     * @return int Score de 0 a 100
     */
    private static function calculateCompleteness(array $data): int
    {
        $weights = [
            'full_name' => 10,
            'job_title' => 5,
            'company' => 5,
            'phone_whatsapp' => 15,
            'email' => 15,
            'website' => 3,
            'instagram' => 2,
            'city_state' => 5,
            'segment' => 10,
            'style' => 20,
            'logo' => 5,
            'qr' => 15
        ];
        
        $totalWeight = array_sum($weights);
        $collectedWeight = 0;
        
        // Verifica campos simples
        foreach (['full_name', 'job_title', 'company', 'phone_whatsapp', 'email', 'website', 'instagram', 'city_state', 'segment'] as $field) {
            if (!empty($data[$field])) {
                $collectedWeight += $weights[$field] ?? 0;
            }
        }
        
        // Verifica style (objeto)
        if (!empty($data['style']) && is_array($data['style'])) {
            $styleFields = ['mood', 'background', 'colors'];
            $hasStyle = false;
            foreach ($styleFields as $field) {
                if (!empty($data['style'][$field])) {
                    $hasStyle = true;
                    break;
                }
            }
            if ($hasStyle) {
                $collectedWeight += $weights['style'] ?? 0;
            }
        }
        
        // Verifica logo
        if (!empty($data['logo'])) {
            $collectedWeight += $weights['logo'] ?? 0;
        }
        
        // Verifica QR
        if (!empty($data['qr']) && is_array($data['qr']) && !empty($data['qr']['enabled'])) {
            $collectedWeight += $weights['qr'] ?? 0;
        }
        
        return (int) round(($collectedWeight / $totalWeight) * 100);
    }
    
    /**
     * Extrai dados estruturados de uma resposta do usuário
     * 
     * @param string $step Step atual
     * @param string $message Mensagem do usuário
     * @param array $currentData Dados já coletados
     * @return array Dados extraídos
     */
    public static function extractDataFromStep(string $step, string $message, array $currentData = []): array
    {
        $extracted = [];
        
        switch ($step) {
            case 'step_1_identity':
                // Extrai nome, cargo, empresa
                $extracted = self::extractIdentityData($message, $currentData);
                break;
                
            case 'step_2_contacts':
                // Extrai contatos
                $extracted = self::extractContactData($message, $currentData);
                break;
                
            case 'step_3_style':
                // Extrai preferências de estilo
                $extracted = self::extractStyleData($message, $currentData);
                break;
                
            case 'step_4_logo_assets':
                // Extrai referências de logo
                $extracted = self::extractLogoData($message, $currentData);
                break;
                
            case 'step_5_qr':
                // Extrai dados de QR
                $extracted = self::extractQRData($message, $currentData);
                break;
        }
        
        return $extracted;
    }
    
    /**
     * Extrai dados de identidade (nome, cargo, empresa)
     */
    private static function extractIdentityData(string $message, array $currentData): array
    {
        $extracted = [];
        
        // Normaliza mensagem
        $message = trim($message);
        
        // Detecta padrões comuns
        // Ex: "Roberto Junior, Consultor na Falcon Securitizadora"
        if (preg_match('/(.+?)(?:,| - |\s+(?:é|na|da|em|atua como|trabalha como))\s*(.+?)(?:\s+(?:na|da|em))\s*(.+)$/i', $message, $matches)) {
            $extracted['full_name'] = self::normalizeName(trim($matches[1]));
            $extracted['job_title'] = trim($matches[2]);
            if (!empty($matches[3])) {
                $extracted['company'] = trim($matches[3]);
            }
        } elseif (preg_match('/(.+?)(?:,| - |\s+)(.+)$/', $message, $matches)) {
            // Formato simples: "Nome, Cargo" ou "Nome - Empresa"
            $part1 = trim($matches[1]);
            $part2 = trim($matches[2]);
            
            // Tenta identificar se é cargo ou empresa
            if (strlen($part2) > 2 && strlen($part2) < 50) {
                // Provavelmente é cargo
                $extracted['full_name'] = self::normalizeName($part1);
                $extracted['job_title'] = $part2;
            } else {
                // Provavelmente é empresa
                $extracted['full_name'] = self::normalizeName($part1);
                $extracted['company'] = $part2;
            }
        } else {
            // Apenas nome
            $extracted['full_name'] = self::normalizeName($message);
        }
        
        return $extracted;
    }
    
    /**
     * Extrai dados de contato
     */
    private static function extractContactData(string $message, array $currentData): array
    {
        $extracted = [];
        
        // Extrai telefone
        if (preg_match('/(\+?55\s?)?(\(?\d{2}\)?\s?)(\d{4,5}-?\d{4})/', $message, $matches)) {
            $phone = preg_replace('/[^0-9+]/', '', $matches[0]);
            if (strpos($phone, '+55') === false && strlen($phone) >= 10) {
                $phone = '+55' . $phone;
            }
            $extracted['phone_whatsapp'] = $phone;
        }
        
        // Extrai email
        if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $message, $matches)) {
            $extracted['email'] = strtolower(trim($matches[0]));
        }
        
        // Extrai site
        if (preg_match('/(https?:\/\/)?(www\.)?([a-z0-9.-]+\.[a-z]{2,})/i', $message, $matches)) {
            $website = $matches[0];
            if (strpos($website, 'http') === false) {
                $website = 'https://' . $website;
            }
            $extracted['website'] = strtolower($website);
        }
        
        // Extrai Instagram
        if (preg_match('/(?:@|instagram\.com\/)([a-z0-9._]+)/i', $message, $matches)) {
            $extracted['instagram'] = '@' . strtolower($matches[1]);
        }
        
        return $extracted;
    }
    
    /**
     * Extrai preferências de estilo
     */
    private static function extractStyleData(string $message, array $currentData): array
    {
        $extracted = [];
        $messageLower = strtolower($message);
        
        $style = $currentData['style'] ?? [];
        
        // Detecta mood
        if (stripos($messageLower, 'corporativo') !== false || stripos($messageLower, 'formal') !== false) {
            $style['mood'] = 'corporativo_moderno';
        } elseif (stripos($messageLower, 'criativo') !== false || stripos($messageLower, 'despojado') !== false) {
            $style['mood'] = 'criativo_moderno';
        } elseif (stripos($messageLower, 'elegante') !== false || stripos($messageLower, 'sofisticado') !== false) {
            $style['mood'] = 'elegante_sofisticado';
        } else {
            $style['mood'] = 'corporativo_moderno'; // Padrão
        }
        
        // Detecta background
        if (stripos($messageLower, 'claro') !== false || stripos($messageLower, 'branco') !== false || stripos($messageLower, 'light') !== false) {
            $style['background'] = 'claro';
        } elseif (stripos($messageLower, 'escuro') !== false || stripos($messageLower, 'preto') !== false || stripos($messageLower, 'dark') !== false) {
            $style['background'] = 'escuro';
        } else {
            $style['background'] = 'claro'; // Padrão
        }
        
        // Detecta cores (hex)
        if (preg_match_all('/#([0-9a-f]{6})/i', $message, $matches)) {
            $style['colors'] = array_slice($matches[0], 0, 3); // Máximo 3 cores
        } else {
            // Cores padrão baseadas no mood
            if (empty($style['colors'])) {
                $style['colors'] = ['#0B1B2B', '#F59E0B'];
            }
        }
        
        $extracted['style'] = $style;
        
        return $extracted;
    }
    
    /**
     * Extrai dados de logo/assets
     */
    private static function extractLogoData(string $message, array $currentData): array
    {
        $extracted = [];
        
        // Se a mensagem menciona "tenho logo" ou similar, marca como sim
        $messageLower = strtolower($message);
        if (stripos($messageLower, 'tenho') !== false || stripos($messageLower, 'sim') !== false) {
            $extracted['logo'] = 'will_provide';
        } elseif (stripos($messageLower, 'não') !== false || stripos($messageLower, 'nao') !== false) {
            $extracted['logo'] = null;
        }
        
        return $extracted;
    }
    
    /**
     * Extrai dados de QR Code
     */
    private static function extractQRData(string $message, array $currentData): array
    {
        $extracted = [];
        $messageLower = strtolower($message);
        
        $qr = $currentData['qr'] ?? ['enabled' => false];
        
        // Detecta se quer QR
        if (stripos($messageLower, 'sim') !== false || stripos($messageLower, 'quero') !== false) {
            $qr['enabled'] = true;
            $qr['target'] = 'whatsapp';
            
            // Tenta extrair número do WhatsApp
            if (preg_match('/(\+?55\s?)?(\(?\d{2}\)?\s?)(\d{4,5}-?\d{4})/', $message, $matches)) {
                $phone = preg_replace('/[^0-9+]/', '', $matches[0]);
                if (strpos($phone, '+55') === false && strlen($phone) >= 10) {
                    $phone = '+55' . $phone;
                }
                $qr['value'] = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $phone);
            } elseif (!empty($currentData['phone_whatsapp'])) {
                // Usa telefone já coletado
                $phone = preg_replace('/[^0-9]/', '', $currentData['phone_whatsapp']);
                $qr['value'] = 'https://wa.me/' . $phone;
            }
        } else {
            $qr['enabled'] = false;
        }
        
        $extracted['qr'] = $qr;
        
        return $extracted;
    }
    
    /**
     * Normaliza nome (evita caixa alta total, padroniza)
     */
    private static function normalizeName(string $name): string
    {
        $name = trim($name);
        
        // Se está todo em maiúscula, converte para Title Case
        if (strtoupper($name) === $name && strlen($name) > 1) {
            $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
        }
        
        return $name;
    }
    
    /**
     * Valida telefone (formato e DDD)
     */
    public static function validatePhone(string $phone): array
    {
        $phoneClean = preg_replace('/[^0-9+]/', '', $phone);
        
        // Remove código do país se houver
        if (strpos($phoneClean, '+55') === 0) {
            $phoneClean = substr($phoneClean, 3);
        }
        
        // Valida DDD (2 dígitos) + número (8 ou 9 dígitos)
        if (preg_match('/^(\d{2})(\d{8,9})$/', $phoneClean, $matches)) {
            $ddd = $matches[1];
            $number = $matches[2];
            
            // Valida DDD (11-99 são válidos)
            if ((int) $ddd < 11 || (int) $ddd > 99) {
                return [
                    'valid' => false,
                    'error' => 'DDD inválido. Use um DDD válido do Brasil (11-99).',
                    'suggestion' => null
                ];
            }
            
            return [
                'valid' => true,
                'error' => null,
                'suggestion' => '+55' . $ddd . $number
            ];
        }
        
        return [
            'valid' => false,
            'error' => 'Telefone inválido. Use o formato (DD) 00000-0000.',
            'suggestion' => null
        ];
    }
    
    /**
     * Valida email
     */
    public static function validateEmail(string $email): array
    {
        $email = trim($email);
        
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => true,
                'error' => null,
                'suggestion' => null
            ];
        }
        
        return [
            'valid' => false,
            'error' => 'Email inválido. Verifique o formato.',
            'suggestion' => null
        ];
    }
    
    /**
     * Sanitiza URL (site/instagram)
     */
    public static function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        
        // Se é Instagram sem @
        if (preg_match('/^[a-z0-9._]+$/i', $url)) {
            return '@' . strtolower($url);
        }
        
        // Remove @ se tiver
        $url = ltrim($url, '@');
        
        // Adiciona https:// se não tiver
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }
        
        return strtolower($url);
    }
    
    /**
     * Formata data_json final conforme especificação
     * 
     * @param array $data Dados coletados
     * @return array Data JSON formatado
     */
    public static function formatFinalDataJson(array $data): array
    {
        return [
            'full_name' => $data['full_name'] ?? '',
            'job_title' => $data['job_title'] ?? '',
            'company' => $data['company'] ?? '',
            'phone_whatsapp' => $data['phone_whatsapp'] ?? '',
            'email' => $data['email'] ?? '',
            'website' => $data['website'] ?? '',
            'instagram' => $data['instagram'] ?? '',
            'city_state' => $data['city_state'] ?? '',
            'segment' => $data['segment'] ?? '',
            'style' => $data['style'] ?? [
                'mood' => 'corporativo_moderno',
                'background' => 'claro',
                'colors' => ['#0B1B2B', '#F59E0B'],
                'font_pref' => 'sem_serifa'
            ],
            'qr' => $data['qr'] ?? [
                'enabled' => false,
                'target' => null,
                'value' => null
            ],
            'notes' => $data['notes'] ?? ''
        ];
    }
}

