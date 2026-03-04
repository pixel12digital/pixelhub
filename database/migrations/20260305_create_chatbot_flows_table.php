<?php

/**
 * Migration: Criar tabela chatbot_flows
 * 
 * Armazena fluxos de automação do chatbot WhatsApp
 * Define respostas automáticas baseadas em gatilhos (botões, keywords, eventos)
 * 
 * Data: 2026-03-04
 */
class CreateChatbotFlowsTable
{
    public function up(PDO $db): void
    {
        echo "Criando tabela chatbot_flows...\n";
        
        $db->exec("
        CREATE TABLE IF NOT EXISTS chatbot_flows (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NULL COMMENT 'Tenant dono do fluxo (NULL = global)',
            name VARCHAR(200) NOT NULL COMMENT 'Nome descritivo do fluxo',
            trigger_type ENUM('template_button', 'keyword', 'event', 'manual') NOT NULL,
            trigger_value VARCHAR(200) NOT NULL COMMENT 'ID do botão, palavra-chave ou tipo de evento',
            response_type ENUM('text', 'template', 'media', 'forward_to_human') NOT NULL DEFAULT 'text',
            response_message TEXT NULL COMMENT 'Mensagem de resposta automática',
            response_template_id INT UNSIGNED NULL COMMENT 'ID do template a enviar (se response_type=template)',
            response_media_url TEXT NULL COMMENT 'URL da mídia (se response_type=media)',
            response_media_type ENUM('image', 'video', 'document', 'audio') NULL,
            next_buttons JSON NULL COMMENT 'Botões para próxima interação: [{text: string, flow_id: int}]',
            forward_to_human BOOLEAN DEFAULT 0 COMMENT 'Se deve encaminhar para atendimento humano após resposta',
            assign_to_user_id INT UNSIGNED NULL COMMENT 'Atribuir conversa a usuário específico',
            add_tags JSON NULL COMMENT 'Tags para adicionar ao lead: [string]',
            update_lead_status VARCHAR(50) NULL COMMENT 'Status do lead para atualizar',
            priority INT DEFAULT 0 COMMENT 'Prioridade de execução (maior = primeiro)',
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            KEY idx_trigger (trigger_type, trigger_value),
            KEY idx_tenant (tenant_id),
            KEY idx_active (is_active),
            KEY idx_priority (priority),
            
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (response_template_id) REFERENCES whatsapp_message_templates(id) ON DELETE SET NULL,
            FOREIGN KEY (assign_to_user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Fluxos de automação do chatbot WhatsApp'
        ");
        
        echo "✓ Tabela chatbot_flows criada com sucesso\n";
    }
    
    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS chatbot_flows");
    }
}
