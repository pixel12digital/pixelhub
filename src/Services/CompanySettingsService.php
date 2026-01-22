<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para gerenciar configurações da empresa
 */
class CompanySettingsService
{
    /**
     * Busca as configurações da empresa
     * 
     * @return array|null Configurações ou null se não encontrado
     */
    public static function getSettings(): ?array
    {
        $db = DB::getConnection();
        
        try {
            $stmt = $db->query("SELECT * FROM company_settings ORDER BY id ASC LIMIT 1");
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (\PDOException $e) {
            // Se a tabela não existe, retorna null
            if (strpos($e->getMessage(), "doesn't exist") !== false || 
                strpos($e->getMessage(), "Table") !== false) {
                return null;
            }
            throw $e;
        }
    }
    
    /**
     * Atualiza as configurações da empresa
     * 
     * @param array $data Dados para atualizar
     * @return bool Sucesso da operação
     */
    public static function updateSettings(array $data): bool
    {
        $db = DB::getConnection();
        
        // Verifica se existe registro
        $existing = self::getSettings();
        
        if (!$existing) {
            // Cria novo registro
            $fields = [];
            $values = [];
            $placeholders = [];
            
            $allowedFields = [
                'company_name', 'company_name_fantasy', 'cnpj', 'ie', 'im',
                'address_street', 'address_number', 'address_complement', 
                'address_neighborhood', 'address_city', 'address_state', 'address_cep',
                'phone', 'email', 'website',
                'logo_url', 'logo_path'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = $field;
                    $values[] = !empty($data[$field]) ? trim($data[$field]) : null;
                    $placeholders[] = '?';
                }
            }
            
            if (empty($fields)) {
                throw new \InvalidArgumentException('Nenhum campo válido fornecido');
            }
            
            $fields[] = 'created_at';
            $fields[] = 'updated_at';
            $placeholders[] = 'NOW()';
            $placeholders[] = 'NOW()';
            
            $sql = "INSERT INTO company_settings (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $db->prepare($sql);
            $stmt->execute($values);
            
            return true;
        } else {
            // Atualiza registro existente
            $updates = [];
            $params = [];
            
            $allowedFields = [
                'company_name', 'company_name_fantasy', 'cnpj', 'ie', 'im',
                'address_street', 'address_number', 'address_complement', 
                'address_neighborhood', 'address_city', 'address_state', 'address_cep',
                'phone', 'email', 'website',
                'logo_url', 'logo_path'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "{$field} = ?";
                    $params[] = !empty($data[$field]) ? trim($data[$field]) : null;
                }
            }
            
            if (empty($updates)) {
                return true; // Nada para atualizar
            }
            
            $updates[] = "updated_at = NOW()";
            $params[] = $existing['id'];
            
            $sql = "UPDATE company_settings SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            return true;
        }
    }
    
    /**
     * Retorna o nome da empresa (com fallback)
     * 
     * @return string Nome da empresa
     */
    public static function getCompanyName(): string
    {
        $settings = self::getSettings();
        return $settings['company_name'] ?? 'Pixel12 Digital';
    }
    
    /**
     * Retorna o CNPJ da empresa (com fallback)
     * 
     * @return string|null CNPJ ou null
     */
    public static function getCompanyCnpj(): ?string
    {
        $settings = self::getSettings();
        return $settings['cnpj'] ?? null;
    }
    
    /**
     * Retorna o endereço completo formatado
     * 
     * @return string Endereço formatado
     */
    public static function getFormattedAddress(): string
    {
        $settings = self::getSettings();
        if (!$settings) {
            return '';
        }
        
        $parts = [];
        
        if (!empty($settings['address_street'])) {
            $street = $settings['address_street'];
            if (!empty($settings['address_number'])) {
                $street .= ', ' . $settings['address_number'];
            }
            if (!empty($settings['address_complement'])) {
                $street .= ' - ' . $settings['address_complement'];
            }
            $parts[] = $street;
        }
        
        if (!empty($settings['address_neighborhood'])) {
            $parts[] = $settings['address_neighborhood'];
        }
        
        if (!empty($settings['address_city'])) {
            $city = $settings['address_city'];
            if (!empty($settings['address_state'])) {
                $city .= ' - ' . $settings['address_state'];
            }
            if (!empty($settings['address_cep'])) {
                $city .= ' - CEP: ' . $settings['address_cep'];
            }
            $parts[] = $city;
        }
        
        return implode(', ', $parts);
    }
    
    /**
     * Retorna a URL do logo (com fallback)
     * 
     * @return string|null URL do logo ou null
     */
    public static function getLogoUrl(): ?string
    {
        $settings = self::getSettings();
        return $settings['logo_url'] ?? $settings['logo_path'] ?? null;
    }
}

