<?php

/**
 * Migration: Criar tabela template_campaigns
 * 
 * Gerencia campanhas de envio em massa de templates WhatsApp
 * Controla lotes, rate limiting e métricas de entrega
 * 
 * Data: 2026-03-04
 */
class CreateTemplateCampaignsTable
{
    public function up(PDO $db): void
    {
        echo "Criando tabela template_campaigns...\n";
        
        $db->exec("
        CREATE TABLE IF NOT EXISTS template_campaigns (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NULL COMMENT 'Tenant dono da campanha (NULL = global)',
            template_id INT UNSIGNED NOT NULL COMMENT 'Template a ser enviado',
            name VARCHAR(200) NOT NULL COMMENT 'Nome da campanha',
            description TEXT NULL,
            target_list JSON NOT NULL COMMENT 'Lista de telefones: [{phone: string, variables: {}}]',
            batch_size INT DEFAULT 50 COMMENT 'Tamanho do lote de envio',
            batch_delay_seconds INT DEFAULT 60 COMMENT 'Delay entre lotes (rate limiting)',
            status ENUM('draft', 'scheduled', 'running', 'paused', 'completed', 'failed') NOT NULL DEFAULT 'draft',
            scheduled_at TIMESTAMP NULL COMMENT 'Data/hora agendada para início',
            started_at TIMESTAMP NULL COMMENT 'Data/hora de início real',
            completed_at TIMESTAMP NULL COMMENT 'Data/hora de conclusão',
            total_count INT DEFAULT 0 COMMENT 'Total de destinatários',
            sent_count INT DEFAULT 0 COMMENT 'Enviados com sucesso',
            delivered_count INT DEFAULT 0 COMMENT 'Entregues (confirmado pelo Meta)',
            read_count INT DEFAULT 0 COMMENT 'Lidos pelo destinatário',
            clicked_count INT DEFAULT 0 COMMENT 'Cliques em botões/links',
            failed_count INT DEFAULT 0 COMMENT 'Falhas de envio',
            error_log JSON NULL COMMENT 'Log de erros: [{phone: string, error: string, timestamp: string}]',
            created_by INT UNSIGNED NULL COMMENT 'Usuário que criou',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            KEY idx_tenant (tenant_id),
            KEY idx_template (template_id),
            KEY idx_status (status),
            KEY idx_scheduled (scheduled_at),
            KEY idx_created_by (created_by),
            
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (template_id) REFERENCES whatsapp_message_templates(id) ON DELETE RESTRICT,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Campanhas de envio em massa de templates WhatsApp'
        ");
        
        echo "✓ Tabela template_campaigns criada com sucesso\n";
    }
    
    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS template_campaigns");
    }
}
