<?php

/**
 * Migration: Cria tabela conversations
 * 
 * Núcleo conversacional central - Etapa 1
 * 
 * Esta tabela representa a entidade central de conversa, que agrupa mensagens
 * por canal + contato, sem alterar fluxos existentes.
 */
class CreateConversationsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS conversations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                conversation_key VARCHAR(255) NOT NULL UNIQUE COMMENT 'Chave única: {channel_type}_{channel_account_id}_{contact_external_id}',
                channel_type VARCHAR(50) NOT NULL COMMENT 'whatsapp, email, webchat, etc.',
                channel_account_id INT UNSIGNED NULL COMMENT 'FK para tenant_message_channels (pode ser NULL se não mapeado)',
                contact_external_id VARCHAR(255) NOT NULL COMMENT 'ID externo do contato (telefone, e-mail, etc.)',
                contact_name VARCHAR(255) NULL COMMENT 'Nome do contato (extraído do provedor)',
                tenant_id INT UNSIGNED NULL COMMENT 'FK para tenants (NULL = contato não identificado como cliente)',
                product_id INT UNSIGNED NULL COMMENT 'Produto associado (NULL = genérico)',
                status VARCHAR(20) NOT NULL DEFAULT 'new' COMMENT 'new, open, pending, closed, archived',
                assigned_to INT UNSIGNED NULL COMMENT 'FK para users (atendente atribuído)',
                assigned_at DATETIME NULL COMMENT 'Data/hora da atribuição',
                first_response_at DATETIME NULL COMMENT 'Data/hora da primeira resposta',
                first_response_by INT UNSIGNED NULL COMMENT 'FK para users (quem respondeu primeiro)',
                closed_at DATETIME NULL COMMENT 'Data/hora de fechamento',
                closed_by INT UNSIGNED NULL COMMENT 'FK para users (quem fechou)',
                sla_minutes INT UNSIGNED DEFAULT 60 COMMENT 'SLA em minutos (tempo de primeira resposta)',
                sla_status VARCHAR(20) DEFAULT 'ok' COMMENT 'ok, warning, breach (calculado)',
                last_message_at DATETIME NULL COMMENT 'Data/hora da última mensagem',
                last_message_direction VARCHAR(10) NULL COMMENT 'inbound, outbound',
                message_count INT UNSIGNED DEFAULT 0 COMMENT 'Contador de mensagens',
                unread_count INT UNSIGNED DEFAULT 0 COMMENT 'Contador de não lidas',
                metadata JSON NULL COMMENT 'Metadados adicionais (tags, notas, etc.)',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_channel_type (channel_type),
                INDEX idx_channel_account (channel_account_id),
                INDEX idx_contact_external (contact_external_id),
                INDEX idx_tenant (tenant_id),
                INDEX idx_status (status),
                INDEX idx_assigned_to (assigned_to),
                INDEX idx_last_message_at (last_message_at),
                INDEX idx_sla_status (sla_status),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
                FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (first_response_by) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS conversations");
    }
}

