<?php

/**
 * Migration: Criar tabela chatbot_events
 * 
 * Registra todos os eventos de interação com o chatbot
 * Usado para analytics, rastreamento de conversão e auditoria
 * 
 * Data: 2026-03-04
 */
class CreateChatbotEventsTable
{
    public function up(PDO $db): void
    {
        echo "Criando tabela chatbot_events...\n";
        
        $db->exec("
        CREATE TABLE IF NOT EXISTS chatbot_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT UNSIGNED NULL COMMENT 'ID da conversa (se existir)',
            lead_id INT UNSIGNED NULL COMMENT 'ID do lead (se vinculado)',
            tenant_id INT UNSIGNED NULL COMMENT 'ID do tenant',
            phone_number VARCHAR(20) NOT NULL COMMENT 'Telefone do contato',
            event_type VARCHAR(50) NOT NULL COMMENT 'Tipo: template_sent, button_clicked, link_clicked, flow_executed, etc',
            event_source VARCHAR(50) NOT NULL DEFAULT 'chatbot' COMMENT 'Origem: chatbot, campaign, manual',
            template_id INT UNSIGNED NULL COMMENT 'Template enviado (se aplicável)',
            flow_id INT UNSIGNED NULL COMMENT 'Fluxo executado (se aplicável)',
            campaign_id INT UNSIGNED NULL COMMENT 'Campanha relacionada (se aplicável)',
            event_data JSON NULL COMMENT 'Dados adicionais do evento',
            meta_message_id VARCHAR(100) NULL COMMENT 'ID da mensagem no Meta',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            KEY idx_conversation (conversation_id),
            KEY idx_lead (lead_id),
            KEY idx_tenant (tenant_id),
            KEY idx_phone (phone_number),
            KEY idx_event_type (event_type),
            KEY idx_template (template_id),
            KEY idx_flow (flow_id),
            KEY idx_campaign (campaign_id),
            KEY idx_created_at (created_at),
            
            FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
            FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (template_id) REFERENCES whatsapp_message_templates(id) ON DELETE SET NULL,
            FOREIGN KEY (flow_id) REFERENCES chatbot_flows(id) ON DELETE SET NULL
            -- campaign_id foreign key será adicionada após criação da tabela template_campaigns
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Eventos de interação com chatbot WhatsApp'
        ");
        
        echo "✓ Tabela chatbot_events criada com sucesso\n";
    }
    
    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS chatbot_events");
    }
}
