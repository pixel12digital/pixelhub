<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para extração e gerenciamento de códigos de rastreamento
 * 
 * Detecta automaticamente códigos de tracking em mensagens WhatsApp:
 * - Padrões: SITE123, IG456, FB789, LP321, etc
 * - Formatos: [PREFIXO][NÚMERO] onde prefixo tem 2-6 chars e número 1-6 digits
 */
class TrackingCodeService
{
    // Prefixos conhecidos por canal
    private const PREFIX_PATTERNS = [
        'site' => ['SITE', 'LP', 'LAND', 'LANDING', 'FORM'],
        'instagram' => ['IG', 'INSTA', 'INSTAGRAM'],
        'facebook' => ['FB', 'FACE', 'FACEBOOK'],
        'whatsapp' => ['WA', 'WPP', 'WHATS', 'ZAP'],
        'google' => ['GOO', 'GOOGLE', 'ADS', 'ADWORDS'],
        'email' => ['EMAIL', 'MAIL', 'NEWS'],
        'indicacao' => ['IND', 'INDIC', 'REF', 'REFER'],
        'outro' => ['OUT', 'OUTRO', 'GEN']
    ];

    /**
     * Extrai códigos de tracking de uma mensagem
     * 
     * @param string $message Conteúdo da mensagem
     * @return array|null Dados do tracking detectado ou null
     */
    public static function extractFromMessage(string $message): ?array
    {
        if (empty($message)) {
            return null;
        }

        // Padrão regex: [PREFIXO][NÚMERO] (case insensitive)
        // Prefixo: 2-6 letras, Número: 1-6 dígitos
        $pattern = '/\b([A-Z]{2,6})(\d{1,6})\b/i';
        
        if (!preg_match_all($pattern, strtoupper($message), $matches, PREG_SET_ORDER)) {
            return null;
        }

        $detectedCodes = [];
        
        foreach ($matches as $match) {
            $prefix = strtoupper($match[1]);
            $code = $match[0];
            $number = $match[2];
            
            // Identifica a fonte baseada no prefixo
            $source = self::identifySource($prefix);
            
            $detectedCodes[] = [
                'code' => $code,
                'prefix' => $prefix,
                'number' => $number,
                'source' => $source,
                'confidence' => self::calculateConfidence($prefix, $source, $message)
            ];
        }

        // Retorna o código com maior confiança
        if (empty($detectedCodes)) {
            return null;
        }

        usort($detectedCodes, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        $best = $detectedCodes[0];
        
        // Só retorna se confiança for razoável (> 0.3)
        if ($best['confidence'] < 0.3) {
            return null;
        }

        return [
            'tracking_code' => $best['code'],
            'tracking_source' => $best['source'],
            'tracking_auto_detected' => true,
            'tracking_metadata' => json_encode([
                'detected_at' => date('Y-m-d H:i:s'),
                'original_message' => substr($message, 0, 500), // Primeiros 500 chars
                'prefix' => $best['prefix'],
                'number' => $best['number'],
                'confidence' => $best['confidence'],
                'all_detected' => $detectedCodes
            ])
        ];
    }

    /**
     * Identifica a fonte baseada no prefixo
     */
    private static function identifySource(string $prefix): string
    {
        foreach (self::PREFIX_PATTERNS as $source => $prefixes) {
            if (in_array($prefix, $prefixes)) {
                return $source;
            }
        }
        
        // Heurística para prefixos não conhecidos
        if (str_contains($prefix, 'SITE') || str_contains($prefix, 'LAND') || str_contains($prefix, 'FORM')) {
            return 'site';
        }
        if (str_contains($prefix, 'IG') || str_contains($prefix, 'INSTA')) {
            return 'instagram';
        }
        if (str_contains($prefix, 'FB') || str_contains($prefix, 'FACE')) {
            return 'facebook';
        }
        if (str_contains($prefix, 'WA') || str_contains($prefix, 'WPP') || str_contains($prefix, 'ZAP')) {
            return 'whatsapp';
        }
        
        return 'outro';
    }

    /**
     * Calcula confiança da detecção baseada em vários fatores
     */
    private static function calculateConfidence(string $prefix, string $source, string $message): float
    {
        $confidence = 0.5; // Base

        // Bônus para prefixos conhecidos
        foreach (self::PREFIX_PATTERNS as $knownSource => $prefixes) {
            if (in_array($prefix, $prefixes)) {
                $confidence += 0.3;
                break;
            }
        }

        // Bônus se aparece no início da mensagem
        $upperMessage = strtoupper($message);
        $firstOccurrence = strpos($upperMessage, $prefix);
        if ($firstOccurrence !== false && $firstOccurrence < 50) {
            $confidence += 0.2;
        }

        // Bônus se a mensagem menciona a fonte
        $sourceKeywords = [
            'site' => ['SITE', 'LANDING', 'FORMULÁRIO', 'PÁGINA'],
            'instagram' => ['INSTAGRAM', 'IG', 'REELS', 'STORIES'],
            'facebook' => ['FACEBOOK', 'FB', 'META'],
            'whatsapp' => ['WHATSAPP', 'ZAP', 'WPP'],
            'google' => ['GOOGLE', 'PESQUISA', 'BUSCA']
        ];

        if (isset($sourceKeywords[$source])) {
            foreach ($sourceKeywords[$source] as $keyword) {
                if (str_contains($upperMessage, $keyword)) {
                    $confidence += 0.1;
                    break;
                }
            }
        }

        // Penalidade se muitos códigos detectados (pode ser acidental)
        $pattern = '/\b[A-Z]{2,6}\d{1,6}\b/i';
        $allMatches = preg_match_all($pattern, $upperMessage);
        if ($allMatches > 3) {
            $confidence -= 0.2;
        }

        return min(1.0, max(0.0, $confidence));
    }

    /**
     * Valida se uma oportunidade pode ser criada sem tracking code
     * 
     * @param array $data Dados da oportunidade
     * @param array|null $trackingData Dados do tracking detectado
     * @return array [bool $valid, string $message]
     */
    public static function validateTrackingRequirement(array $data, ?array $trackingData): array
    {
        // Se não detectou código automaticamente, permite criação
        if (!$trackingData) {
            return [true, ''];
        }

        // Se já informou tracking_code manualmente, permite
        if (!empty($data['tracking_code'])) {
            return [true, ''];
        }

        // Detectou código mas não informou - exige preenchimento
        return [
            false, 
            "Código de rastreamento '{$trackingData['tracking_code']}' detectado na mensagem. " .
            "Por favor, confirme o código ou informe manualmente o campo 'Código Rastreamento'."
        ];
    }

    /**
     * Aplica tracking detectado a uma oportunidade
     */
    public static function applyTrackingToOpportunity(int $opportunityId, array $trackingData): bool
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE opportunities 
            SET tracking_code = ?, 
                tracking_source = ?, 
                tracking_auto_detected = ?, 
                tracking_metadata = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $trackingData['tracking_code'],
            $trackingData['tracking_source'],
            $trackingData['tracking_auto_detected'],
            $trackingData['tracking_metadata'],
            $opportunityId
        ]);
    }

    /**
     * Busca oportunidades por tracking code
     */
    public static function findByTrackingCode(string $code): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT o.*, 
                   t.name as tenant_name, 
                   l.name as lead_name,
                   u.name as responsible_name
            FROM opportunities o
            LEFT JOIN tenants t ON o.tenant_id = t.id
            LEFT JOIN leads l ON o.lead_id = l.id
            LEFT JOIN users u ON o.responsible_user_id = u.id
            WHERE o.tracking_code = ?
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$code]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Estatísticas de tracking por fonte
     */
    public static function getTrackingStats(): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT 
                tracking_source,
                COUNT(*) as total,
                COUNT(CASE WHEN tracking_auto_detected = 1 THEN 1 END) as auto_detected,
                COUNT(CASE WHEN status = 'won' THEN 1 END) as converted,
                SUM(CASE WHEN estimated_value IS NOT NULL THEN estimated_value ELSE 0 END) as total_value
            FROM opportunities 
            WHERE tracking_code IS NOT NULL
            GROUP BY tracking_source
            ORDER BY total DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
