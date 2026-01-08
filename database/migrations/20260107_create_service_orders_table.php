<?php

/**
 * Migration: Cria tabela de pedidos de serviço (service_orders)
 * 
 * Pedidos são criados ANTES do projeto. Cliente preenche dados cadastrais,
 * briefing e aprova condições. Após aprovação, converte automaticamente em projeto.
 */
class CreateServiceOrdersTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS service_orders (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                service_id INT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NULL COMMENT 'NULL = cliente ainda não cadastrado',
                
                -- Dados do Pedido
                contract_value DECIMAL(10,2) NULL COMMENT 'Valor do contrato (pode variar do preço do serviço)',
                payment_condition VARCHAR(100) NULL COMMENT 'à vista, parcelado 2x, etc.',
                payment_method VARCHAR(50) NULL COMMENT 'pix, boleto, cartao',
                
                -- Dados do Cliente (temporários, até criar/atualizar tenant)
                client_data JSON NULL COMMENT 'Dados do cliente se ainda não cadastrado',
                
                -- Briefing
                briefing_data JSON NULL COMMENT 'Respostas do briefing_template',
                briefing_status VARCHAR(20) NULL DEFAULT 'pending' COMMENT 'pending, completed',
                briefing_completed_at DATETIME NULL,
                
                -- Link Público
                token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Token para link público',
                expires_at DATETIME NULL COMMENT 'Link expira em X dias',
                
                -- Status do Pedido
                status VARCHAR(50) NOT NULL DEFAULT 'draft' COMMENT 'draft, pending_approval, approved, rejected, converted',
                
                -- Conversão
                project_id INT UNSIGNED NULL COMMENT 'FK para projects quando convertido',
                converted_at DATETIME NULL,
                
                -- Metadados
                notes TEXT NULL COMMENT 'Observações internas',
                created_by INT UNSIGNED NULL COMMENT 'FK para users (quem criou o pedido)',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                
                INDEX idx_service_id (service_id),
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_token (token),
                INDEX idx_status (status),
                INDEX idx_project_id (project_id),
                FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS service_orders");
    }
}

