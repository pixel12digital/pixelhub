<?php

/**
 * Migration: Cria tabela de contratos de projetos
 * 
 * Contratos formais vinculados a projetos, com valor editável,
 * link único para aceite pelo cliente e histórico de aceitação.
 */
class CreateProjectContractsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS project_contracts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id INT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                service_id INT UNSIGNED NULL,
                
                -- Valores
                contract_value DECIMAL(10,2) NOT NULL COMMENT 'Valor do contrato (editável, pode ser diferente do preço do serviço)',
                service_price DECIMAL(10,2) NULL COMMENT 'Preço original do serviço (para referência)',
                
                -- Link público para aceite
                contract_token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Token único para link público de aceite',
                
                -- Status do contrato
                status VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, sent, accepted, rejected',
                
                -- Histórico de aceite
                accepted_at DATETIME NULL COMMENT 'Data/hora do aceite pelo cliente',
                accepted_by_ip VARCHAR(45) NULL COMMENT 'IP do cliente que aceitou',
                accepted_by_user_agent TEXT NULL COMMENT 'User agent do cliente que aceitou',
                
                -- Envio via WhatsApp
                whatsapp_sent_at DATETIME NULL COMMENT 'Data/hora do envio do link via WhatsApp',
                whatsapp_sent_by INT UNSIGNED NULL COMMENT 'ID do usuário que enviou via WhatsApp',
                
                -- Metadados
                notes TEXT NULL COMMENT 'Observações internas sobre o contrato',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                
                INDEX idx_project_id (project_id),
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_service_id (service_id),
                INDEX idx_contract_token (contract_token),
                INDEX idx_status (status),
                
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
                FOREIGN KEY (whatsapp_sent_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS project_contracts");
    }
}





