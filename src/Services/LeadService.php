<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para gerenciamento de Leads
 * 
 * Leads são contatos em negociação que ainda não são clientes (tenants).
 * Uma conversa pode ser vinculada a um lead OU a um tenant, nunca ambos.
 */
class LeadService
{
    /**
     * Cria um novo lead
     * 
     * @param array $data [name, phone, email, source, notes, created_by]
     * @return int ID do lead criado
     */
    public static function create(array $data): int
    {
        $db = DB::getConnection();

        $stmt = $db->prepare("
            INSERT INTO leads (name, phone, email, source, status, notes, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'new', ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            trim($data['name']),
            !empty($data['phone']) ? trim($data['phone']) : null,
            !empty($data['email']) ? trim($data['email']) : null,
            $data['source'] ?? 'whatsapp',
            $data['notes'] ?? null,
            $data['created_by'] ?? null,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Busca lead por ID
     */
    public static function findById(int $id): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * FROM leads WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Lista todos os leads (para modal de vincular)
     * 
     * @param string|null $search Termo de busca (nome, telefone, email)
     * @param int $limit Limite de resultados
     * @return array
     */
    public static function list(?string $search = null, int $limit = 200): array
    {
        $db = DB::getConnection();

        $where = ["status != 'converted'"];
        $params = [];

        if (!empty($search)) {
            $searchTerm = '%' . trim($search) . '%';
            $searchDigits = preg_replace('/[^0-9]/', '', $search);
            
            if (!empty($searchDigits)) {
                $where[] = "(name LIKE ? OR email LIKE ? OR REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', '') LIKE ?)";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = '%' . $searchDigits . '%';
            } else {
                $where[] = "(name LIKE ? OR email LIKE ?)";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit;

        $stmt = $db->prepare("
            SELECT id, name, phone, email, source, status, created_at
            FROM leads
            WHERE {$whereClause}
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Busca duplicados por telefone (leads E tenants)
     * 
     * Retorna todos os registros (leads e tenants/clientes) que possuem
     * o mesmo telefone, para prevenção de duplicidade.
     * 
     * @param string $phone Telefone para buscar
     * @return array ['leads' => [...], 'tenants' => [...]]
     */
    public static function findDuplicatesByPhone(string $phone): array
    {
        $db = DB::getConnection();
        $result = ['leads' => [], 'tenants' => []];

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

        // Busca em leads
        foreach ($variations as $v) {
            $pattern = '%' . $v . '%';
            $stmt = $db->prepare("
                SELECT id, name, phone, email, status, 'lead' as type
                FROM leads
                WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ?
                AND status != 'converted'
            ");
            $stmt->execute([$pattern]);
            $found = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($found as $row) {
                $result['leads'][$row['id']] = $row;
            }
        }

        // Busca em tenants (clientes)
        foreach ($variations as $v) {
            $pattern = '%' . $v . '%';
            $stmt = $db->prepare("
                SELECT id, name, phone, email, status, 'tenant' as type
                FROM tenants
                WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ?
                AND (is_archived IS NULL OR is_archived = 0)
            ");
            $stmt->execute([$pattern]);
            $found = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($found as $row) {
                $result['tenants'][$row['id']] = $row;
            }
        }

        // Reindexa arrays
        $result['leads'] = array_values($result['leads']);
        $result['tenants'] = array_values($result['tenants']);

        return $result;
    }

    /**
     * Vincula um lead a uma conversa
     * 
     * Remove vínculo com tenant se existir (lead e tenant são mutuamente exclusivos)
     * 
     * @param int $conversationId
     * @param int $leadId
     */
    public static function linkToConversation(int $conversationId, int $leadId): void
    {
        $db = DB::getConnection();

        $stmt = $db->prepare("
            UPDATE conversations 
            SET lead_id = ?,
                tenant_id = NULL,
                is_incoming_lead = 0,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$leadId, $conversationId]);
    }

    /**
     * Atualiza um lead
     */
    public static function update(int $id, array $data): void
    {
        $db = DB::getConnection();

        $fields = [];
        $params = [];

        foreach (['name', 'phone', 'email', 'source', 'status', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) return;

        $fields[] = "updated_at = NOW()";
        $params[] = $id;

        $stmt = $db->prepare("UPDATE leads SET " . implode(', ', $fields) . " WHERE id = ?");
        $stmt->execute($params);
    }

    /**
     * Converte lead em tenant (cliente)
     * 
     * @param int $leadId
     * @return int ID do tenant criado
     */
    public static function convertToTenant(int $leadId): int
    {
        $db = DB::getConnection();
        $lead = self::findById($leadId);

        if (!$lead) {
            throw new \RuntimeException("Lead #{$leadId} não encontrado");
        }

        $db->beginTransaction();

        try {
            // Cria tenant
            $stmt = $db->prepare("
                INSERT INTO tenants (name, phone, email, person_type, status, created_at, updated_at)
                VALUES (?, ?, ?, 'pf', 'active', NOW(), NOW())
            ");
            $stmt->execute([
                $lead['name'],
                $lead['phone'],
                $lead['email'],
            ]);
            $tenantId = (int) $db->lastInsertId();

            // Marca lead como convertido
            $stmt = $db->prepare("
                UPDATE leads 
                SET status = 'converted', 
                    converted_tenant_id = ?, 
                    converted_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$tenantId, $leadId]);

            // Atualiza conversas vinculadas ao lead → agora vinculadas ao tenant
            $stmt = $db->prepare("
                UPDATE conversations 
                SET tenant_id = ?,
                    lead_id = NULL,
                    is_incoming_lead = 0,
                    updated_at = NOW()
                WHERE lead_id = ?
            ");
            $stmt->execute([$tenantId, $leadId]);

            $db->commit();
            return $tenantId;

        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
