<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PDO;

/**
 * Serviço para detectar e prevenir duplicação de conversas
 * 
 * Problema: A mesma pessoa pode ter múltiplas conversas com:
 * - contact_external_id diferentes (E.164 vs @lid vs telefone formatado)
 * - tenant_id diferentes (ou null)
 * - Nomes similares mas não idênticos
 */
class ConversationDeduplicationService
{
    private PDO $db;
    
    public function __construct()
    {
        $this->db = DB::getConnection();
    }
    
    /**
     * Detecta conversas duplicadas para um contato
     * 
     * @param string $contactExternalId ID do contato (pode ser E.164, @lid, etc.)
     * @param string|null $contactName Nome do contato
     * @param int|null $tenantId ID do tenant
     * @return array|null Conversa existente para merge ou null
     */
    public function detectDuplicateConversation(string $contactExternalId, ?string $contactName = null, ?int $tenantId = null): ?array
    {
        // 1. Normaliza o contact_external_id
        $normalizedContact = $this->normalizeContactId($contactExternalId);
        
        // 2. Busca conversas existentes com vários critérios
        $duplicates = $this->findPotentialDuplicates($normalizedContact, $contactName, $tenantId);
        
        if (empty($duplicates)) {
            return null;
        }
        
        // 3. Escolhe a melhor conversa para merge (prioridade: com tenant > mais recente > mais mensagens)
        return $this->selectBestConversation($duplicates);
    }
    
    /**
     * Normaliza contact_external_id para formato canônico
     * 
     * @param string $contactId
     * @return string ID normalizado
     */
    private function normalizeContactId(string $contactId): string
    {
        // Remove espaços e caracteres especiais
        $clean = preg_replace('/[^\d@.]/', '', trim($contactId));
        
        // Se é @lid, mantém como está
        if (strpos($clean, '@lid') !== false) {
            return $clean;
        }
        
        // Se é número puro, garante formato E.164 (sem +)
        if (preg_match('/^\d+$/', $clean)) {
            // Remove zeros iniciais exceto se for número brasileiro começando com 55
            if (strlen($clean) > 11 && substr($clean, 0, 2) === '55') {
                return $clean;
            }
            // Remove zero inicial de números locais
            return ltrim($clean, '0');
        }
        
        // Se tem @ (c.us, s.whatsapp.net, etc), extrai apenas dígitos
        if (strpos($clean, '@') !== false) {
            $digits = preg_replace('/[^0-9]/', '', $clean);
            if ($digits !== '') {
                return $digits;
            }
        }
        
        return $clean;
    }
    
    /**
     * Busca conversas potencialmente duplicadas
     */
    private function findPotentialDuplicates(string $normalizedContact, ?string $contactName, ?int $tenantId): array
    {
        $sql = "
            SELECT 
                c.*,
                t.name as tenant_name
            FROM conversations c
            LEFT JOIN tenants t ON c.tenant_id = t.id
            WHERE c.channel_type = 'whatsapp'
              AND c.status NOT IN ('closed', 'archived', 'ignored')
              AND (
                  -- 1. Mesmo contact_external_id normalizado
                  ? IN (
                      c.contact_external_id,
                      REGEXP_REPLACE(c.contact_external_id, '[^0-9]', ''),
                      CASE 
                          WHEN c.contact_external_id LIKE '%@lid%' THEN c.contact_external_id
                          ELSE REGEXP_REPLACE(c.contact_external_id, '@.*$', '')
                      END
                  )
                  OR
                  -- 2. Mesmo tenant (se fornecido)
                  " . ($tenantId ? "c.tenant_id = ?" : "FALSE") . "
                  OR
                  -- 3. Nome similar (se fornecido)
                  " . ($contactName ? "LOWER(TRIM(c.contact_name)) = LOWER(TRIM(?)) OR LOWER(TRIM(t.name)) = LOWER(TRIM(?))" : "FALSE") . "
              )
            ORDER BY 
                CASE WHEN c.tenant_id IS NOT NULL THEN 1 ELSE 2 END,
                c.message_count DESC,
                c.last_message_at DESC
            LIMIT 5
        ";
        
        $params = [$normalizedContact];
        if ($tenantId) {
            $params[] = $tenantId;
        }
        if ($contactName) {
            $params[] = $contactName;
            $params[] = $contactName;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Seleciona a melhor conversa para merge
     */
    private function selectBestConversation(array $duplicates): array
    {
        // Prioridade:
        // 1. Conversa com tenant_id não nulo
        // 2. Mais mensagens
        // 3. Mais recente
        
        usort($duplicates, function($a, $b) {
            // Prioridade 1: Com tenant
            $aHasTenant = $a['tenant_id'] !== null;
            $bHasTenant = $b['tenant_id'] !== null;
            
            if ($aHasTenant !== $bHasTenant) {
                return $bHasTenant ? 1 : -1;
            }
            
            // Prioridade 2: Mais mensagens
            if ($a['message_count'] !== $b['message_count']) {
                return $b['message_count'] <=> $a['message_count'];
            }
            
            // Prioridade 3: Mais recente
            return strtotime($b['last_message_at'] ?? $b['created_at']) <=> strtotime($a['last_message_at'] ?? $a['created_at']);
        });
        
        return $duplicates[0];
    }
    
    /**
     * Merge de duas conversas
     * 
     * @param int $targetConversationId ID da conversa que receberá os dados
     * @param int $sourceConversationId ID da conversa que será mesclada e excluída
     * @return bool Sucesso da operação
     */
    public function mergeConversations(int $targetConversationId, int $sourceConversationId): bool
    {
        try {
            $this->db->beginTransaction();
            
            // 1. Move eventos da source para target
            $moveEvents = "
                UPDATE communication_events 
                SET conversation_id = ? 
                WHERE conversation_id = ?
            ";
            $stmt = $this->db->prepare($moveEvents);
            $stmt->execute([$targetConversationId, $sourceConversationId]);
            $movedEvents = $stmt->rowCount();
            
            // 2. Atualiza dados da conversa target
            $updateTarget = "
                UPDATE conversations c
                SET message_count = (
                    SELECT COUNT(*) 
                    FROM communication_events ce 
                    WHERE ce.conversation_id = c.id
                ),
                last_message_at = (
                    SELECT GREATEST(
                        c.last_message_at,
                        (SELECT MAX(created_at) FROM communication_events WHERE conversation_id = c.id)
                    )
                ),
                updated_at = NOW()
                WHERE id = ?
            ";
            $stmt = $this->db->prepare($updateTarget);
            $stmt->execute([$targetConversationId]);
            
            // 3. Exclui conversa source
            $deleteSource = "DELETE FROM conversations WHERE id = ?";
            $stmt = $this->db->prepare($deleteSource);
            $stmt->execute([$sourceConversationId]);
            
            $this->db->commit();
            
            error_log("[ConversationDeduplication] Conversas merge com sucesso: target=$targetConversationId, source=$sourceConversationId, events=$movedEvents");
            
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("[ConversationDeduplication] Erro ao merge conversas: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica duplicações no sistema (para monitoramento)
     * 
     * @param int $days Dias para trás na busca
     * @return array Lista de potenciais duplicações
     */
    public function checkSystemDuplicates(int $days = 7): array
    {
        $sql = "
            SELECT 
                c1.id as conv1_id,
                c1.contact_external_id as contact1,
                c1.contact_name as name1,
                c1.tenant_id as tenant1_id,
                t1.name as tenant1_name,
                c1.message_count as msg1_count,
                c1.last_message_at as last1,
                
                c2.id as conv2_id,
                c2.contact_external_id as contact2,
                c2.contact_name as name2,
                c2.tenant_id as tenant2_id,
                t2.name as tenant2_name,
                c2.message_count as msg2_count,
                c2.last_message_at as last2,
                
                CASE 
                    WHEN LOWER(TRIM(c1.contact_name)) = LOWER(TRIM(c2.contact_name)) AND c1.contact_name != '' AND c2.contact_name != '' THEN 1
                    WHEN LOWER(TRIM(t1.name)) = LOWER(TRIM(t2.name)) AND t1.name != '' AND t2.name != '' THEN 1
                    WHEN LOWER(TRIM(c1.contact_name)) = LOWER(TRIM(t2.name)) AND c1.contact_name != '' AND t2.name != '' THEN 1
                    WHEN LOWER(TRIM(t1.name)) = LOWER(TRIM(c2.contact_name)) AND t1.name != '' AND c2.contact_name != '' THEN 1
                    ELSE 0
                END as name_similarity,
                
                CASE 
                    WHEN ABS(TIMESTAMPDIFF(HOUR, c1.last_message_at, c2.last_message_at)) <= 24 THEN 1
                    ELSE 0
                END as temporal_similarity
                
            FROM conversations c1
            LEFT JOIN tenants t1 ON c1.tenant_id = t1.id
            JOIN conversations c2 ON c1.id < c2.id
            LEFT JOIN tenants t2 ON c2.tenant_id = t2.id
            WHERE c1.channel_type = 'whatsapp' 
              AND c2.channel_type = 'whatsapp'
              AND c1.status NOT IN ('closed', 'archived', 'ignored')
              AND c2.status NOT IN ('closed', 'archived', 'ignored')
              AND c1.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              AND (
                  c1.tenant_id = c2.tenant_id 
                  OR LOWER(TRIM(c1.contact_name)) = LOWER(TRIM(c2.contact_name))
                  OR LOWER(TRIM(t1.name)) = LOWER(TRIM(t2.name))
                  OR LOWER(TRIM(c1.contact_name)) = LOWER(TRIM(t2.name))
                  OR LOWER(TRIM(t1.name)) = LOWER(TRIM(c2.contact_name))
              )
            HAVING name_similarity = 1 OR temporal_similarity = 1
            ORDER BY name_similarity DESC, temporal_similarity DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$days]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
