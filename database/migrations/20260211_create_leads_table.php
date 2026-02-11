<?php

/**
 * Migration: Cria tabela leads
 * 
 * Leads são contatos em negociação que ainda não são clientes (tenants).
 * Uma conversa pode ser vinculada a um lead OU a um tenant, nunca ambos.
 */
class CreateLeadsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS leads (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                phone VARCHAR(30) NULL,
                email VARCHAR(255) NULL,
                source VARCHAR(50) NULL DEFAULT 'whatsapp' COMMENT 'whatsapp, site, indicacao, outro',
                status VARCHAR(30) NOT NULL DEFAULT 'new' COMMENT 'new, contacted, qualified, converted, lost',
                notes TEXT NULL COMMENT 'Observações livres',
                converted_tenant_id INT UNSIGNED NULL COMMENT 'FK para tenants se convertido em cliente',
                converted_at DATETIME NULL COMMENT 'Data da conversão em cliente',
                created_by INT UNSIGNED NULL COMMENT 'FK para users (quem criou)',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_phone (phone),
                INDEX idx_email (email),
                INDEX idx_status (status),
                INDEX idx_source (source),
                INDEX idx_converted_tenant (converted_tenant_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Adiciona coluna lead_id na tabela conversations
        // Uma conversa pode ser vinculada a um lead OU a um tenant
        try {
            $check = $db->query("SHOW COLUMNS FROM conversations LIKE 'lead_id'");
            if ($check->rowCount() === 0) {
                $db->exec("
                    ALTER TABLE conversations 
                    ADD COLUMN lead_id INT UNSIGNED NULL COMMENT 'FK para leads (NULL = não vinculado a lead)' 
                    AFTER tenant_id,
                    ADD INDEX idx_lead_id (lead_id)
                ");
            }
        } catch (Exception $e) {
            error_log("[Migration] Erro ao adicionar lead_id em conversations: " . $e->getMessage());
        }
    }

    public function down(PDO $db): void
    {
        try {
            $db->exec("ALTER TABLE conversations DROP COLUMN lead_id");
        } catch (Exception $e) {
            // Ignora se coluna não existe
        }
        $db->exec("DROP TABLE IF EXISTS leads");
    }
}
