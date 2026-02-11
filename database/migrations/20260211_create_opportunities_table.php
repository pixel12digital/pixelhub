<?php

/**
 * Migration: Cria tabela opportunities + opportunity_history
 * 
 * Oportunidades são vendas em andamento no pipeline comercial.
 * Vinculadas a um lead OU a um tenant (cliente).
 * Ao ser marcada como "won", gera automaticamente um service_order.
 */
class CreateOpportunitiesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS opportunities (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL COMMENT 'Nome da oportunidade (ex: Site institucional R$ 5.000)',
                stage VARCHAR(50) NOT NULL DEFAULT 'new' COMMENT 'new, contact, proposal, negotiation, won, lost',
                estimated_value DECIMAL(12,2) NULL COMMENT 'Valor estimado da venda',
                status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, won, lost',
                
                lead_id INT UNSIGNED NULL COMMENT 'FK para leads (se contato ainda não é cliente)',
                tenant_id INT UNSIGNED NULL COMMENT 'FK para tenants (se já é cliente)',
                responsible_user_id INT UNSIGNED NULL COMMENT 'FK para users (responsável pela oportunidade)',
                service_id INT UNSIGNED NULL COMMENT 'FK para services (serviço relacionado, opcional)',
                
                expected_close_date DATE NULL COMMENT 'Data prevista de fechamento',
                won_at DATETIME NULL COMMENT 'Data em que foi marcada como ganha',
                lost_at DATETIME NULL COMMENT 'Data em que foi marcada como perdida',
                lost_reason VARCHAR(255) NULL COMMENT 'Motivo da perda',
                
                service_order_id INT UNSIGNED NULL COMMENT 'FK para service_orders (gerado ao ganhar)',
                conversation_id INT UNSIGNED NULL COMMENT 'FK para conversations (conversa de origem, se criada do inbox)',
                
                notes TEXT NULL COMMENT 'Observações livres',
                created_by INT UNSIGNED NULL COMMENT 'FK para users (quem criou)',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_stage (stage),
                INDEX idx_status (status),
                INDEX idx_lead_id (lead_id),
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_responsible (responsible_user_id),
                INDEX idx_service_order (service_order_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS opportunity_history (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                opportunity_id INT UNSIGNED NOT NULL,
                action VARCHAR(50) NOT NULL COMMENT 'created, stage_changed, value_changed, status_changed, note_added, assigned',
                old_value VARCHAR(255) NULL,
                new_value VARCHAR(255) NULL,
                description TEXT NULL COMMENT 'Descrição legível do evento',
                user_id INT UNSIGNED NULL COMMENT 'Quem fez a ação',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_opportunity (opportunity_id),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS opportunity_history");
        $db->exec("DROP TABLE IF EXISTS opportunities");
    }
}
