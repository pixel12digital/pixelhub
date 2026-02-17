<?php

/**
 * Script de Migração: Leads → Tenants (Contatos Unificados)
 * 
 * Este script migra todos os leads da tabela leads para a tabela tenants,
 * mantendo a integridade dos dados e preservando relacionamentos.
 * 
 * EXECUÇÃO:
 * php scripts/migrate_leads_to_tenants.php [--dry-run] [--force]
 * 
 * OPTIONS:
 * --dry-run  : Simula a migração sem executar (recomendado primeiro)
 * --force    : Executa a migração mesmo que já tenha sido executada
 */

require_once __DIR__ . '/../src/Core/DB.php';

use PixelHub\Core\DB;

class LeadMigration
{
    private $db;
    private $dryRun;
    private $force;
    
    public function __construct(bool $dryRun = false, bool $force = false)
    {
        $this->db = DB::getConnection();
        $this->dryRun = $dryRun;
        $this->force = $force;
    }
    
    /**
     * Executa a migração completa
     */
    public function migrate(): void
    {
        echo "=== MIGRAÇÃO LEADS → TENANTS ===\n";
        echo "Modo: " . ($this->dryRun ? "DRY RUN (simulação)" : "EXECUÇÃO REAL") . "\n\n";
        
        // 1. Verifica pré-requisitos
        $this->checkPrerequisites();
        
        // 2. Verifica se já foi executada
        if (!$this->force && $this->alreadyMigrated()) {
            echo "⚠️  Migração já executada anteriormente. Use --force para executar novamente.\n";
            return;
        }
        
        // 3. Conta leads a migrar
        $leadsCount = $this->countLeads();
        echo "📊 Leads encontrados: {$leadsCount}\n";
        
        if ($leadsCount === 0) {
            echo "✅ Nenhum lead para migrar.\n";
            return;
        }
        
        // 4. Executa migração
        $this->performMigration($leadsCount);
        
        // 5. Validação
        if (!$this->dryRun) {
            $this->validateMigration();
        }
        
        echo "\n✅ Migração concluída com sucesso!\n";
    }
    
    /**
     * Verifica pré-requisitos
     */
    private function checkPrerequisites(): void
    {
        // Verifica se tabela tenants tem os novos campos
        $requiredFields = ['contact_type', 'source', 'notes', 'created_by', 'lead_converted_at', 'original_lead_id'];
        
        foreach ($requiredFields as $field) {
            $stmt = $this->db->query("SHOW COLUMNS FROM tenants LIKE '{$field}'");
            if ($stmt->rowCount() === 0) {
                throw new \RuntimeException("Campo '{$field}' não encontrado na tabela tenants. Execute a migration 20260217_alter_tenants_add_lead_fields.php primeiro.");
            }
        }
        
        echo "✅ Pré-requisitos verificados\n";
    }
    
    /**
     * Verifica se migração já foi executada
     */
    private function alreadyMigrated(): bool
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM tenants WHERE contact_type = 'lead' AND original_lead_id IS NOT NULL");
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Conta leads a migrar
     */
    private function countLeads(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM leads WHERE status != 'converted'");
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Executa a migração
     */
    private function performMigration(int $totalLeads): void
    {
        $batchSize = 100;
        $processed = 0;
        $errors = [];
        
        // Busca leads em lotes
        $offset = 0;
        while ($offset < $totalLeads) {
            $stmt = $this->db->prepare("
                SELECT * FROM leads 
                WHERE status != 'converted' 
                ORDER BY id 
                LIMIT {$batchSize} OFFSET {$offset}
            ");
            $stmt->execute();
            $leads = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (empty($leads)) break;
            
            foreach ($leads as $lead) {
                try {
                    $this->migrateLead($lead);
                    $processed++;
                    
                    if ($processed % 10 === 0 || $processed === $totalLeads) {
                        $percent = round(($processed / $totalLeads) * 100, 1);
                        echo "📈 Progresso: {$processed}/{$totalLeads} ({$percent}%)\n";
                    }
                } catch (\Exception $e) {
                    $errors[] = "Lead #{$lead['id']}: " . $e->getMessage();
                    echo "❌ Erro no Lead #{$lead['id']}: " . $e->getMessage() . "\n";
                }
            }
            
            $offset += $batchSize;
        }
        
        // Resumo final
        echo "\n📋 RESUMO:\n";
        echo "✅ Processados: {$processed}\n";
        echo "❌ Erros: " . count($errors) . "\n";
        
        if (!empty($errors)) {
            echo "\n🚨 DETALHE DOS ERROS:\n";
            foreach ($errors as $error) {
                echo "- {$error}\n";
            }
        }
    }
    
    /**
     * Migra um lead individual
     */
    private function migrateLead(array $lead): void
    {
        // Verifica duplicidade
        if ($lead['phone']) {
            $duplicates = $this->findDuplicatesInTenants($lead['phone']);
            if (!empty($duplicates)) {
                throw new \RuntimeException("Telefone duplicado com tenant(s): " . implode(', ', array_column($duplicates, 'id')));
            }
        }
        
        // Prepara dados para inserção
        $data = [
            'name' => $lead['name'],
            'phone' => $lead['phone'],
            'email' => $lead['email'],
            'contact_type' => 'lead',
            'status' => $lead['status'] === 'converted' ? 'active' : $lead['status'],
            'source' => $lead['source'] ?? 'whatsapp',
            'notes' => $lead['notes'],
            'created_by' => $lead['created_by'],
            'company' => $lead['company'], // Usa campo company para empresa do lead
            'original_lead_id' => $lead['id'], // Mantém referência ao lead original
            'created_at' => $lead['created_at'],
            'updated_at' => $lead['updated_at']
        ];
        
        if ($this->dryRun) {
            echo "🔍 [DRY RUN] Migrar lead #{$lead['id']}: {$lead['name']}\n";
            return;
        }
        
        // Insere na tabela tenants
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $stmt = $this->db->prepare("
            INSERT INTO tenants ({$columns})
            VALUES ({$placeholders})
        ");
        
        $stmt->execute(array_values($data));
        $newTenantId = (int) $this->db->lastInsertId();
        
        // Atualiza relacionamentos
        $this->updateRelationships($lead['id'], $newTenantId);
        
        // Marca lead como migrado (opcional - poderíamos manter para backup)
        // $this->markLeadAsMigrated($lead['id']);
    }
    
    /**
     * Busca duplicados na tabela tenants
     */
    private function findDuplicatesInTenants(string $phone): array
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) < 8) return [];
        
        $stmt = $this->db->prepare("
            SELECT id, name, phone 
            FROM tenants 
            WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ?
            AND (is_archived IS NULL OR is_archived = 0)
        ");
        $stmt->execute(['%' . $digits . '%']);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Atualiza relacionamentos do lead para o novo tenant
     */
    private function updateRelationships(int $leadId, int $newTenantId): void
    {
        // Atualiza conversations
        $stmt = $this->db->prepare("
            UPDATE conversations 
            SET tenant_id = ?, lead_id = NULL, updated_at = NOW()
            WHERE lead_id = ?
        ");
        $stmt->execute([$newTenantId, $leadId]);
        
        // Atualiza opportunities
        $stmt = $this->db->prepare("
            UPDATE opportunities 
            SET tenant_id = ?, lead_id = NULL, updated_at = NOW()
            WHERE lead_id = ?
        ");
        $stmt->execute([$newTenantId, $leadId]);
        
        // Se o lead foi convertido, mantém o registro de conversão
        $lead = $this->db->query("SELECT converted_tenant_id, converted_at FROM leads WHERE id = {$leadId}")->fetch();
        if ($lead && $lead['converted_tenant_id']) {
            $stmt = $this->db->prepare("
                UPDATE tenants 
                SET lead_converted_at = ?, 
                    original_lead_id = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$lead['converted_at'], $leadId, $newTenantId]);
        }
    }
    
    /**
     * Valida a migração
     */
    private function validateMigration(): void
    {
        echo "\n🔍 VALIDAÇÃO DA MIGRAÇÃO:\n";
        
        // Conta leads vs tenants
        $leadsCount = $this->db->query("SELECT COUNT(*) FROM leads WHERE status != 'converted'")->fetchColumn();
        $tenantsLeadsCount = $this->db->query("SELECT COUNT(*) FROM tenants WHERE contact_type = 'lead'")->fetchColumn();
        
        echo "- Leads restantes: {$leadsCount}\n";
        echo "- Tenants como lead: {$tenantsLeadsCount}\n";
        
        // Verifica relacionamentos
        $convsWithoutTenant = $this->db->query("
            SELECT COUNT(*) FROM conversations 
            WHERE lead_id IS NOT NULL AND tenant_id IS NULL
        ")->fetchColumn();
        
        $oppsWithoutTenant = $this->db->query("
            SELECT COUNT(*) FROM opportunities 
            WHERE lead_id IS NOT NULL AND tenant_id IS NULL
        ")->fetchColumn();
        
        echo "- Conversas sem tenant: {$convsWithoutTenant}\n";
        echo "- Oportunidades sem tenant: {$oppsWithoutTenant}\n";
        
        if ($convsWithoutTenant > 0 || $oppsWithoutTenant > 0) {
            echo "⚠️  Atenção: Existem relacionamentos não atualizados!\n";
        } else {
            echo "✅ Todos os relacionamentos foram atualizados\n";
        }
        
        // Estatísticas finais
        $stats = $this->db->query("
            SELECT 
                contact_type,
                COUNT(*) as count,
                source
            FROM tenants 
            WHERE contact_type = 'lead'
            GROUP BY contact_type, source
            ORDER BY count DESC
        ")->fetchAll();
        
        echo "\n📊 LEADS MIGRADOS POR FONTE:\n";
        foreach ($stats as $stat) {
            echo "- {$stat['source']}: {$stat['count']}\n";
        }
    }
}

// Execução do script
$dryRun = in_array('--dry-run', $argv);
$force = in_array('--force', $argv);

try {
    $migration = new LeadMigration($dryRun, $force);
    $migration->migrate();
} catch (\Exception $e) {
    echo "🚨 ERRO FATAL: " . $e->getMessage() . "\n";
    exit(1);
}
