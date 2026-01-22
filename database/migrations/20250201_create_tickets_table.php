<?php

/**
 * Migration: Cria tabela tickets
 * Módulo de suporte/tickets
 */
class CreateTicketsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS tickets (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NULL,
                project_id INT UNSIGNED NULL,
                task_id INT UNSIGNED NULL,
                titulo VARCHAR(200) NOT NULL,
                descricao TEXT NULL,
                prioridade ENUM('baixa', 'media', 'alta', 'critica') NOT NULL DEFAULT 'media',
                status ENUM('aberto', 'em_atendimento', 'aguardando_cliente', 'resolvido') NOT NULL DEFAULT 'aberto',
                origem ENUM('cliente', 'interno', 'whatsapp', 'automatico') NOT NULL DEFAULT 'cliente',
                prazo_sla DATETIME NULL,
                data_resolucao DATETIME NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_project_id (project_id),
                INDEX idx_task_id (task_id),
                INDEX idx_status (status),
                INDEX idx_prioridade (prioridade),
                INDEX idx_created_by (created_by),
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS tickets");
    }
}











