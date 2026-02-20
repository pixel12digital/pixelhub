<?php

namespace PixelHub\Services;

use PDO;
use Exception;
use PixelHub\Core\DB;

/**
 * Serviço de detecção e gerenciamento de tracking codes
 * 
 * Detecta automaticamente códigos de rastreio em mensagens
 * e gerencia metadados de tracking
 */
class TrackingDetectionService
{
    private PDO $db;

    public function __construct()
    {
        try {
            $this->db = DB::getConnection();
        } catch (Exception $e) {
            // Fallback se DB falhar
            $this->db = null;
        }
    }

    /**
     * Detecta tracking code em uma mensagem
     * 
     * @param string $message Mensagem para analisar
     * @return array|null Informações do tracking detectado ou null
     */
    public function detectInMessage(string $message): ?array
    {
        if (empty($message) || !$this->db) {
            return null;
        }

        // Regex para encontrar códigos no formato XXX123, ABC-456, etc.
        if (!preg_match_all('/\b([A-Z]{2,6}[-_]?[0-9]{1,6})\b/i', $message, $matches)) {
            return null;
        }

        // Busca cada código encontrado na tabela tracking_codes
        foreach ($matches[1] as $code) {
            $tracking = $this->findTrackingByCode(strtoupper($code));
            if ($tracking) {
                return [
                    'tracking_code' => $tracking['code'],
                    'origin' => $tracking['source'], // canal
                    'tracking_metadata' => $this->buildTrackingMetadata($tracking),
                    'tracking_auto_detected' => true
                ];
            }
        }

        return null;
    }

    /**
     * Busca tracking code pelo código
     * 
     * @param string $code Código para buscar
     * @return array|null Dados do tracking ou null
     */
    private function findTrackingByCode(string $code): ?array
    {
        if (!$this->db) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT * FROM tracking_codes 
            WHERE code = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$code]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Constrói metadados de tracking a partir do código encontrado
     * 
     * @param array $tracking Dados do tracking code
     * @return array Metadados estruturados
     */
    private function buildTrackingMetadata(array $tracking): array
    {
        return [
            'detected_at' => date('Y-m-d H:i:s'),
            'tracking_description' => $tracking['description'],
            'source' => $tracking['source'],
            // Campos futuros para página, cta, campanha, etc.
            // Serão preenchidos quando houver integração com analytics
        ];
    }

    /**
     * Retorna fallback para origem não identificada
     * 
     * @return array Dados padrão para tracking não detectado
     */
    public function getUnknownFallback(): array
    {
        return [
            'tracking_code' => null,
            'origin' => 'unknown',
            'tracking_metadata' => null,
            'tracking_auto_detected' => false
        ];
    }

    /**
     * Lista todos os canais disponíveis para filtro
     * 
     * @return array Lista de canais únicos + unknown
     */
    public function getAvailableOrigins(): array
    {
        // Usar catálogo unificado como fonte única
        return OriginCatalog::getKeys();
    }

    /**
     * Valida se uma origem é válida
     * 
     * @param string $origin Origem para validar
     * @return bool Se é uma origem válida
     */
    public function isValidOrigin(string $origin): bool
    {
        return OriginCatalog::isValid($origin);
    }
}
