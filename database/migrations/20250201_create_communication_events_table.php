<?php

/**
 * Migration: Cria tabela communication_events
 * 
 * Armazena todos os eventos do sistema de comunicação centralizado.
 */
class CreateCommunicationEventsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS communication_events (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_id VARCHAR(36) NOT NULL UNIQUE COMMENT 'UUID único do evento',
                idempotency_key VARCHAR(255) NOT NULL UNIQUE COMMENT 'Chave para garantir idempotência',
                event_type VARCHAR(100) NOT NULL COMMENT 'Tipo do evento (ex: whatsapp.inbound.message, billing.invoice.overdue)',
                source_system VARCHAR(50) NOT NULL COMMENT 'Sistema de origem (wpp_gateway, asaas, billing, servipro, etc.)',
                tenant_id INT UNSIGNED NULL COMMENT 'ID do tenant (pode ser NULL se não resolvido)',
                trace_id VARCHAR(36) NOT NULL COMMENT 'UUID para rastrear fluxo completo',
                correlation_id VARCHAR(36) NULL COMMENT 'UUID para agrupar eventos relacionados',
                payload JSON NOT NULL COMMENT 'Payload completo do evento',
                metadata JSON NULL COMMENT 'Metadados adicionais',
                status VARCHAR(20) NOT NULL DEFAULT 'queued' COMMENT 'queued|processing|processed|failed',
                processed_at DATETIME NULL COMMENT 'Data/hora de processamento',
                error_message TEXT NULL COMMENT 'Mensagem de erro se falhou',
                retry_count INT UNSIGNED DEFAULT 0 COMMENT 'Número de tentativas',
                max_retries INT UNSIGNED DEFAULT 3 COMMENT 'Número máximo de tentativas',
                next_retry_at DATETIME NULL COMMENT 'Próxima tentativa (backoff exponencial)',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_event_type (event_type),
                INDEX idx_source_system (source_system),
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_trace_id (trace_id),
                INDEX idx_correlation_id (correlation_id),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at),
                INDEX idx_next_retry_at (next_retry_at),
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS communication_events");
    }
}

