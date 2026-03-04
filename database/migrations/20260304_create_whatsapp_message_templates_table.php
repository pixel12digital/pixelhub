<?php

/**
 * Migration: Criar tabela whatsapp_message_templates
 * 
 * Armazena templates de mensagem do WhatsApp Business API
 * Templates precisam ser aprovados pelo Meta antes de uso
 * 
 * Data: 2026-03-04
 */
class CreateWhatsappMessageTemplatesTable
{
    public function up(PDO $db): void
    {
        echo "Criando tabela whatsapp_message_templates...\n";
        
        $db->exec("
            CREATE TABLE IF NOT EXISTS whatsapp_message_templates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NULL COMMENT 'Tenant dono do template (NULL = global)',
                template_name VARCHAR(100) NOT NULL COMMENT 'Nome do template (snake_case, sem espaços)',
                meta_template_id VARCHAR(100) NULL COMMENT 'ID do template no Meta (após aprovação)',
                category ENUM('marketing', 'utility', 'authentication') NOT NULL DEFAULT 'marketing',
                language VARCHAR(10) NOT NULL DEFAULT 'pt_BR',
                status ENUM('draft', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'draft',
                content TEXT NOT NULL COMMENT 'Conteúdo da mensagem (pode conter variáveis {{1}}, {{2}})',
                header_type ENUM('none', 'text', 'image', 'video', 'document') DEFAULT 'none',
                header_content TEXT NULL COMMENT 'Conteúdo do header (texto ou URL de mídia)',
                footer_text VARCHAR(60) NULL COMMENT 'Texto do rodapé (opcional, max 60 chars)',
                buttons JSON NULL COMMENT 'Array de botões: [{type: quick_reply|call_to_action, text: string, id: string}]',
                variables JSON NULL COMMENT 'Definição de variáveis: [{name: string, example: string}]',
                rejection_reason TEXT NULL COMMENT 'Motivo da rejeição pelo Meta',
                submitted_at TIMESTAMP NULL COMMENT 'Data de submissão para aprovação',
                approved_at TIMESTAMP NULL COMMENT 'Data de aprovação pelo Meta',
                is_active BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_template_name (template_name, tenant_id),
                KEY idx_status (status),
                KEY idx_category (category),
                KEY idx_tenant (tenant_id),
                KEY idx_meta_template_id (meta_template_id),
                
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Templates de mensagem WhatsApp Business API'
        ");
        
        echo "✓ Tabela whatsapp_message_templates criada com sucesso\n";
    }
    
    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS whatsapp_message_templates");
    }
}
