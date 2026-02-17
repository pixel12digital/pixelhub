<?php

/**
 * Migration: Cria tabela opportunity_interactions
 * 
 * Timeline de interações de comunicação separado do histórico de negócio.
 * Alinhado com CRMs de mercado (Salesforce Activity, HubSpot Timeline).
 */
class CreateOpportunityInteractionsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS opportunity_interactions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                opportunity_id INT UNSIGNED NOT NULL,
                interaction_type VARCHAR(20) NOT NULL COMMENT 'whatsapp, email, call, meeting, note',
                direction ENUM('inbound', 'outbound') NOT NULL COMMENT 'Recebido vs Enviado',
                title VARCHAR(255) NOT NULL COMMENT 'Título curto da interação',
                content TEXT NULL COMMENT 'Conteúdo completo (mensagem, email, etc)',
                metadata JSON NULL COMMENT 'Dados extras: telefone, email, duration, etc',
                user_id INT UNSIGNED NULL COMMENT 'Quem registrou (null para automático)',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_opportunity (opportunity_id),
                INDEX idx_type (interaction_type),
                INDEX idx_direction (direction),
                INDEX idx_created_at (created_at),
                INDEX idx_opportunity_type_created (opportunity_id, interaction_type, created_at),
                FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS opportunity_interactions");
    }
}
