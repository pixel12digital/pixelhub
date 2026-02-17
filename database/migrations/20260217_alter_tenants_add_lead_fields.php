<?php

/**
 * Migration: Adiciona campos de lead na tabela tenants
 * 
 * Esta migration unifica a gestão de leads e clientes em uma única tabela (tenants),
 * seguindo as melhores práticas de sistemas CRM profissionais como Pipedrive e Zoho.
 * 
 * Estratégia:
 * - Mantém tabela tenants como base (contatos unificados)
 * - Adiciona campos específicos de leads
 * - Usa contact_type para diferenciar: 'lead' vs 'client'
 * - Permite conversão seamless sem perda de dados
 */
class AlterTenantsAddLeadFields
{
    public function up(PDO $db): void
    {
        // 1. Adiciona campos específicos de leads
        $db->exec("
            ALTER TABLE tenants 
            ADD COLUMN contact_type ENUM('lead', 'client') NOT NULL DEFAULT 'client' 
                COMMENT 'lead=prospecto em negociação, client=cliente convertido',
            ADD COLUMN source VARCHAR(50) NULL 
                COMMENT 'Origem do contato: whatsapp, site, indicacao, outro',
            ADD COLUMN notes TEXT NULL 
                COMMENT 'Observações livres sobre o contato',
            ADD COLUMN created_by INT UNSIGNED NULL 
                COMMENT 'FK para users (quem criou o registro)',
            ADD COLUMN lead_converted_at DATETIME NULL 
                COMMENT 'Data de conversão de lead para cliente',
            ADD COLUMN original_lead_id INT UNSIGNED NULL 
                COMMENT 'ID original do lead se convertido (para rastreabilidade)'
        ");

        // 2. Adiciona índices para performance
        $db->exec("
            ALTER TABLE tenants 
            ADD INDEX idx_contact_type (contact_type),
            ADD INDEX idx_source (source),
            ADD INDEX idx_created_by (created_by),
            ADD INDEX idx_lead_converted_at (lead_converted_at),
            ADD INDEX idx_original_lead_id (original_lead_id)
        ");

        // 3. Adiciona chaves estrangeiras se não existirem
        try {
            // FK para created_by → users
            $checkFK = $db->query("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'tenants' 
                AND CONSTRAINT_NAME = 'fk_tenants_created_by'
            ");
            if ($checkFK->rowCount() === 0) {
                $db->exec("
                    ALTER TABLE tenants 
                    ADD CONSTRAINT fk_tenants_created_by 
                    FOREIGN KEY (created_by) REFERENCES users(id) 
                    ON DELETE SET NULL
                ");
            }
        } catch (Exception $e) {
            error_log("[Migration] Erro ao adicionar FK created_by: " . $e->getMessage());
        }

        // 4. Atualiza valores existentes para compatibilidade
        $db->exec("
            UPDATE tenants 
            SET contact_type = 'client', 
                source = 'legacy',
                notes = 'Migrado de tenant legado'
            WHERE contact_type = 'client' 
            AND source IS NULL
        ");

        error_log("[Migration] Campos de lead adicionados à tabela tenants com sucesso");
    }

    public function down(PDO $db): void
    {
        // Remove as colunas adicionadas (rollback)
        $db->exec("
            ALTER TABLE tenants 
            DROP COLUMN contact_type,
            DROP COLUMN source,
            DROP COLUMN notes,
            DROP COLUMN created_by,
            DROP COLUMN lead_converted_at,
            DROP COLUMN original_lead_id
        ");

        error_log("[Migration] Campos de lead removidos da tabela tenants (rollback)");
    }
}
