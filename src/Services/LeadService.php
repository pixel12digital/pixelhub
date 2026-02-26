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
     * @param array $data [name, company, phone, email, source, notes, created_by]
     * @return int ID do lead criado
     */
    public static function create(array $data): int
    {
        $db = DB::getConnection();

        $name = !empty($data['name']) ? trim($data['name']) : null;
        $phone = !empty($data['phone']) ? trim($data['phone']) : null;
        $email = !empty($data['email']) ? trim($data['email']) : null;
        $company = !empty($data['company']) ? trim($data['company']) : null;

        if (empty($phone) && empty($email)) {
            throw new \InvalidArgumentException('Informe pelo menos um telefone ou e-mail');
        }

        $stmt = $db->prepare("
            INSERT INTO leads (name, company, phone, email, source, status, notes, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'new', ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $name,
            $company,
            $phone,
            $email,
            $data['source'] ?? 'crm_manual',
            $data['notes'] ?? null,
            $data['created_by'] ?? null,
        ]);

        $id = (int) $db->lastInsertId();

        // Fallback automático: se nome vazio, salva Lead #<id>
        if (empty($name)) {
            $db->prepare("UPDATE leads SET name = ? WHERE id = ?")->execute(['Lead #' . $id, $id]);
        }

        return $id;
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

        $whereClause = implode(' AND ', $where);
        $params[] = $limit;

        $stmt = $db->prepare("
            SELECT id, name, company, phone, email, source, status, created_at
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
     * Busca duplicados por e-mail (leads E tenants)
     *
     * @param string $email E-mail para buscar
     * @return array ['leads' => [...], 'tenants' => [...]]
     */
    public static function findDuplicatesByEmail(string $email): array
    {
        $db = DB::getConnection();
        $result = ['leads' => [], 'tenants' => []];

        $email = strtolower(trim($email));
        if (strlen($email) < 5 || strpos($email, '@') === false) {
            return $result;
        }

        $stmt = $db->prepare("
            SELECT id, name, company, phone, email, status, 'lead' as type
            FROM leads
            WHERE LOWER(email) = ?
            AND status != 'converted'
        ");
        $stmt->execute([$email]);
        $result['leads'] = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $stmt = $db->prepare("
            SELECT id, name, phone, email, status, 'tenant' as type
            FROM tenants
            WHERE LOWER(email) = ?
            AND (is_archived IS NULL OR is_archived = 0)
        ");
        $stmt->execute([$email]);
        $result['tenants'] = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return $result;
    }

    /**
     * Busca duplicados por domínio de site/e-mail (leads E tenants)
     *
     * Extrai o domínio do valor informado (URL ou e-mail) e busca outros
     * registros com o mesmo domínio, sugerindo que podem ser a mesma empresa.
     *
     * @param string $value URL de site ou e-mail
     * @return array ['leads' => [...], 'tenants' => [...], 'domain' => string]
     */
    public static function findDuplicatesByDomain(string $value): array
    {
        $result = ['leads' => [], 'tenants' => [], 'domain' => ''];

        $value = strtolower(trim($value));
        if (empty($value)) {
            return $result;
        }

        // Extrai domínio de URL ou e-mail
        $domain = '';
        if (strpos($value, '@') !== false) {
            // É um e-mail — pega a parte após @
            $parts = explode('@', $value);
            $domain = end($parts);
        } else {
            // É uma URL — remove protocolo, www e path
            $domain = preg_replace('#^https?://#', '', $value);
            $domain = preg_replace('#^www\.#', '', $domain);
            $domain = explode('/', $domain)[0];
            $domain = explode('?', $domain)[0];
        }

        $domain = trim($domain, '.');
        // Domínios genéricos não servem como identificador de empresa
        $genericDomains = ['gmail.com', 'hotmail.com', 'yahoo.com', 'outlook.com', 'icloud.com', 'live.com', 'bol.com.br', 'uol.com.br', 'terra.com.br'];
        if (empty($domain) || strlen($domain) < 4 || in_array($domain, $genericDomains)) {
            return $result;
        }

        $result['domain'] = $domain;
        $db = DB::getConnection();
        $pattern = '%@' . $domain;

        // Busca em leads por e-mail com mesmo domínio
        $stmt = $db->prepare("
            SELECT id, name, phone, email, status, 'lead' as type
            FROM leads
            WHERE LOWER(email) LIKE ?
            AND status != 'converted'
        ");
        $stmt->execute([$pattern]);
        $result['leads'] = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Busca em tenants por e-mail com mesmo domínio
        $stmt = $db->prepare("
            SELECT id, name, phone, email, status, 'tenant' as type
            FROM tenants
            WHERE LOWER(email) LIKE ?
            AND (is_archived IS NULL OR is_archived = 0)
        ");
        $stmt->execute([$pattern]);
        $result['tenants'] = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return $result;
    }

    /**
     * Vincula uma conversa a um lead
     * 
     * Remove vínculo com tenant se existir (lead e tenant são mutuamente exclusivos)
     * Cria opportunity automaticamente se não existir para este lead
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

        // Criar opportunity automaticamente se não existir
        self::ensureOpportunityExists($leadId, $conversationId);
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

    /**
     * Garante que exista uma opportunity para o lead
     * Se não existir, cria automaticamente no stage "new"
     * 
     * @param int $leadId
     * @param int $conversationId
     */
    private static function ensureOpportunityExists(int $leadId, int $conversationId): void
    {
        $db = DB::getConnection();

        // Verifica se já existe opportunity ativa para este lead
        $stmt = $db->prepare("
            SELECT id FROM opportunities 
            WHERE lead_id = ? AND status = 'active' 
            LIMIT 1
        ");
        $stmt->execute([$leadId]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Já existe, não faz nada
            return;
        }

        // Busca dados do lead
        $stmt = $db->prepare("SELECT name, phone, email FROM leads WHERE id = ?");
        $stmt->execute([$leadId]);
        $lead = $stmt->fetch();

        if (!$lead) {
            error_log("[LeadService] Lead {$leadId} não encontrado ao criar opportunity");
            return;
        }

        // Cria opportunity automaticamente
        $opportunityName = $lead['name'] ?: 'Lead #' . $leadId;
        
        $stmt = $db->prepare("
            INSERT INTO opportunities 
            (name, stage, status, lead_id, conversation_id, created_by, created_at, updated_at)
            VALUES (?, 'new', 'active', ?, ?, NULL, NOW(), NOW())
        ");
        
        $stmt->execute([$opportunityName, $leadId, $conversationId]);
        
        $opportunityId = (int) $db->lastInsertId();
        
        error_log("[LeadService] Opportunity {$opportunityId} criada automaticamente para lead {$leadId}");
    }

    /**
     * Exclui um lead
     * 
     * Verifica se há oportunidades ou conversas vinculadas antes de excluir.
     * Se houver, retorna erro com detalhes.
     * 
     * @param int $id
     * @return array ['success' => bool, 'error' => string|null, 'details' => array|null]
     */
    public static function delete(int $id): array
    {
        $db = DB::getConnection();

        // Verifica se o lead existe
        $lead = self::findById($id);
        if (!$lead) {
            return [
                'success' => false,
                'error' => 'Lead não encontrado',
                'details' => null,
            ];
        }

        // Verifica se há oportunidades vinculadas
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM opportunities WHERE lead_id = ? AND status = 'active'");
        $stmt->execute([$id]);
        $oppCount = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

        if ($oppCount > 0) {
            return [
                'success' => false,
                'error' => 'Não é possível excluir este lead pois existem oportunidades vinculadas',
                'details' => ['opportunities_count' => $oppCount],
            ];
        }

        // Verifica se há conversas vinculadas
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM conversations WHERE lead_id = ?");
        $stmt->execute([$id]);
        $convCount = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

        if ($convCount > 0) {
            return [
                'success' => false,
                'error' => 'Não é possível excluir este lead pois existem conversas vinculadas',
                'details' => ['conversations_count' => $convCount],
            ];
        }

        // Verifica se há resultados de prospecção vinculados
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM prospecting_results WHERE lead_id = ?");
        $stmt->execute([$id]);
        $prospectCount = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

        try {
            $db->beginTransaction();

            // Remove vínculo de prospecção se existir (não exclui o resultado, apenas desvincula)
            if ($prospectCount > 0) {
                $stmt = $db->prepare("UPDATE prospecting_results SET lead_id = NULL WHERE lead_id = ?");
                $stmt->execute([$id]);
            }

            // Exclui o lead
            $stmt = $db->prepare("DELETE FROM leads WHERE id = ?");
            $stmt->execute([$id]);

            $db->commit();

            error_log("[LeadService] Lead #{$id} excluído com sucesso");

            return [
                'success' => true,
                'error' => null,
                'details' => null,
            ];

        } catch (\Exception $e) {
            $db->rollBack();
            error_log("[LeadService] Erro ao excluir lead #{$id}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Erro ao excluir lead: ' . $e->getMessage(),
                'details' => null,
            ];
        }
    }
}
