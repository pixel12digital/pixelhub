<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service unificado para gerenciamento de Contatos (Leads e Clientes)
 * 
 * Implementa modelo profissional unificado similar a Pipedrive/Zoho CRM:
 * - Única tabela (tenants) para todos os contatos
 * - Diferenciação por contact_type: 'lead' vs 'client'
 * - Conversão seamless sem perda de dados
 * - Histórico completo preservado
 */
class ContactService
{
    // Tipos de contato
    const TYPE_LEAD = 'lead';
    const TYPE_CLIENT = 'client';
    
    // Fontes de origem
    const SOURCE_WHATSAPP = 'whatsapp';
    const SOURCE_SITE = 'site';
    const SOURCE_INDICATION = 'indicacao';
    const SOURCE_OTHER = 'outro';
    
    // Status (mantidos para compatibilidade)
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_ARCHIVED = 'archived';

    /**
     * Cria um novo contato (lead ou cliente)
     * 
     * @param array $data Dados do contato
     * @param string $contactType Tipo: 'lead' ou 'client'
     * @return int ID do contato criado
     */
    public static function create(array $data, string $contactType = self::TYPE_LEAD): int
    {
        $db = DB::getConnection();

        // Validação básica
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Nome é obrigatório');
        }

        // Normaliza dados
        $name = trim($data['name']);
        $phone = !empty($data['phone']) ? trim($data['phone']) : null;
        $email = !empty($data['email']) ? trim($data['email']) : null;
        $company = !empty($data['company']) ? trim($data['company']) : null;

        // Prepara campos específicos por tipo
        $fields = [
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'contact_type' => $contactType,
            'status' => $data['status'] ?? self::STATUS_ACTIVE,
            'source' => $data['source'] ?? self::SOURCE_WHATSAPP,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Campos específicos de cliente
        if ($contactType === self::TYPE_CLIENT) {
            $fields['person_type'] = $data['person_type'] ?? 'pf';
            $fields['document'] = $data['document'] ?? null;
            $fields['address'] = $data['address'] ?? null;
            $fields['city'] = $data['city'] ?? null;
            $fields['state'] = $data['state'] ?? null;
            $fields['zip'] = $data['zip'] ?? null;
        }

        // Campos específicos de lead
        if ($contactType === self::TYPE_LEAD) {
            $fields['company'] = $company; // Usa campo company como empresa do lead
        }

        // Constrói SQL dinamicamente
        $columns = implode(', ', array_keys($fields));
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        
        $stmt = $db->prepare("
            INSERT INTO tenants ({$columns})
            VALUES ({$placeholders})
        ");

        $stmt->execute(array_values($fields));

        $contactId = (int) $db->lastInsertId();

        error_log("[ContactService] Contato {$contactId} criado como {$contactType}");
        
        return $contactId;
    }

    /**
     * Busca contato por ID
     */
    public static function findById(int $id): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Lista contatos com filtros
     * 
     * @param string|null $contactType Filtrar por tipo: 'lead', 'client' ou null para todos
     * @param array $filters Filtros adicionais
     * @param int $limit Limite de resultados
     * @return array
     */
    public static function list(?string $contactType = null, array $filters = [], int $limit = 200): array
    {
        $db = DB::getConnection();

        $where = ["1=1"];
        $params = [];

        // Filtro por tipo
        if ($contactType) {
            $where[] = "contact_type = ?";
            $params[] = $contactType;
        }

        // Filtros adicionais
        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim($filters['search']) . '%';
            $searchDigits = preg_replace('/[^0-9]/', '', $filters['search']);
            
            if (!empty($searchDigits)) {
                $where[] = "(name LIKE ? OR company LIKE ? OR email LIKE ? OR REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', '') LIKE ?)";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = '%' . $searchDigits . '%';
            } else {
                $where[] = "(name LIKE ? OR company LIKE ? OR email LIKE ?)";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
        }

        if (!empty($filters['source'])) {
            $where[] = "source = ?";
            $params[] = $filters['source'];
        }

        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }

        // Exclui arquivados por padrão
        if (!($filters['include_archived'] ?? false)) {
            $where[] = "(is_archived IS NULL OR is_archived = 0)";
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit;

        $stmt = $db->prepare("
            SELECT id, name, phone, email, contact_type, status, source, 
                   company, person_type, created_at, updated_at,
                   lead_converted_at, original_lead_id
            FROM tenants
            WHERE {$whereClause}
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Atualiza um contato
     */
    public static function update(int $id, array $data): void
    {
        $db = DB::getConnection();

        $fields = [];
        $params = [];

        // Campos atualizáveis
        $updatable = ['name', 'phone', 'email', 'status', 'source', 'notes', 
                      'person_type', 'document', 'address', 'city', 'state', 'zip', 'company'];

        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) return;

        $fields[] = "updated_at = NOW()";
        $params[] = $id;

        $stmt = $db->prepare("UPDATE tenants SET " . implode(', ', $fields) . " WHERE id = ?");
        $stmt->execute($params);

        error_log("[ContactService] Contato {$id} atualizado");
    }

    /**
     * Converte lead para cliente
     * 
     * @param int $leadId ID do lead a ser convertido
     * @param array $clientData Dados adicionais do cliente
     * @return int ID do mesmo contato (agora cliente)
     */
    public static function convertLeadToClient(int $leadId, array $clientData = []): int
    {
        $db = DB::getConnection();

        // Verifica se é lead e não foi convertido
        $lead = self::findById($leadId);
        if (!$lead) {
            throw new \RuntimeException("Lead #{$leadId} não encontrado");
        }

        if ($lead['contact_type'] !== self::TYPE_LEAD) {
            throw new \RuntimeException("Contato #{$leadId} não é um lead");
        }

        if ($lead['lead_converted_at']) {
            throw new \RuntimeException("Lead #{$leadId} já foi convertido em " . $lead['lead_converted_at']);
        }

        $db->beginTransaction();

        try {
            // Prepara dados de cliente
            $updateData = [
                'contact_type' => self::TYPE_CLIENT,
                'lead_converted_at' => date('Y-m-d H:i:s'),
                'original_lead_id' => $leadId // Auto-referência para rastreabilidade
            ];

            // Adiciona dados específicos de cliente se fornecidos
            if (!empty($clientData['person_type'])) $updateData['person_type'] = $clientData['person_type'];
            if (!empty($clientData['document'])) $updateData['document'] = $clientData['document'];
            if (!empty($clientData['address'])) $updateData['address'] = $clientData['address'];
            if (!empty($clientData['city'])) $updateData['city'] = $clientData['city'];
            if (!empty($clientData['state'])) $updateData['state'] = $clientData['state'];
            if (!empty($clientData['zip'])) $updateData['zip'] = $clientData['zip'];

            // Atualiza o registro
            self::update($leadId, $updateData);

            // Atualiza conversas vinculadas (remove lead_id, mantém tenant_id)
            $stmt = $db->prepare("
                UPDATE conversations 
                SET tenant_id = ?, lead_id = NULL, updated_at = NOW()
                WHERE lead_id = ?
            ");
            $stmt->execute([$leadId, $leadId]);

            // Atualiza oportunidades vinculadas
            $stmt = $db->prepare("
                UPDATE opportunities 
                SET tenant_id = ?, lead_id = NULL, updated_at = NOW()
                WHERE lead_id = ?
            ");
            $stmt->execute([$leadId, $leadId]);

            $db->commit();

            error_log("[ContactService] Lead {$leadId} convertido para cliente com sucesso");

            return $leadId;

        } catch (\Exception $e) {
            $db->rollBack();
            error_log("[ContactService] Erro ao converter lead {$leadId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca duplicados por telefone
     * 
     * @param string $phone Telefone para buscar
     * @return array Contatos duplicados
     */
    public static function findDuplicatesByPhone(string $phone): array
    {
        $db = DB::getConnection();
        $result = [];

        // Normaliza: extrai apenas dígitos
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) < 8) {
            return $result;
        }

        // Gera variações para busca (com/sem 9º dígito para BR)
        $variations = [$digits];
        
        // Garante prefixo 55 para números BR
        if (substr($digits, 0, 2) !== '55' && (strlen($digits) === 10 || strlen($digits) === 11)) {
            $digits = '55' . $digits;
            $variations[] = $digits;
        }

        // Variação de 9º dígito para números BR
        if (strlen($digits) === 13 && substr($digits, 0, 2) === '55') {
            // Com 9: remove
            $without9 = substr($digits, 0, 4) . substr($digits, 5);
            $variations[] = $without9;
        } elseif (strlen($digits) === 12 && substr($digits, 0, 2) === '55') {
            // Sem 9: adiciona
            $with9 = substr($digits, 0, 4) . '9' . substr($digits, 4);
            $variations[] = $with9;
        }

        $variations = array_unique($variations);

        // Busca contatos
        foreach ($variations as $v) {
            $pattern = '%' . $v . '%';
            $stmt = $db->prepare("
                SELECT id, name, phone, email, contact_type, status
                FROM tenants
                WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ?
                AND (is_archived IS NULL OR is_archived = 0)
            ");
            $stmt->execute([$pattern]);
            $found = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($found as $row) {
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * Busca leads para autocomplete (compatibilidade com sistema atual)
     */
    public static function searchLeads(?string $search = null, int $limit = 50): array
    {
        return self::list(self::TYPE_LEAD, ['search' => $search], $limit);
    }

    /**
     * Busca clientes para autocomplete (compatibilidade com sistema atual)
     */
    public static function searchClients(?string $search = null, int $limit = 50): array
    {
        return self::list(self::TYPE_CLIENT, ['search' => $search], $limit);
    }

    /**
     * Obtém estatísticas de contatos
     */
    public static function getStats(): array
    {
        $db = DB::getConnection();
        
        $stats = [];
        
        // Total por tipo
        $stmt = $db->query("
            SELECT contact_type, COUNT(*) as count
            FROM tenants
            WHERE (is_archived IS NULL OR is_archived = 0)
            GROUP BY contact_type
        ");
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $stats['by_type'][$row['contact_type']] = (int) $row['count'];
        }
        
        // Convertidos este mês
        $stmt = $db->query("
            SELECT COUNT(*) as count
            FROM tenants
            WHERE lead_converted_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        ");
        $stats['converted_this_month'] = (int) $stmt->fetchColumn();
        
        return $stats;
    }
}
