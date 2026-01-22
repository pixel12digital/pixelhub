<?php

/**
 * Migration: Cria tabela service_intakes
 * 
 * Brief estruturado por serviço (coleta de dados do chat).
 */
class CreateServiceIntakesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS service_intakes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_id INT UNSIGNED NOT NULL COMMENT 'FK para service_orders',
                service_slug VARCHAR(100) NOT NULL COMMENT 'business_card_express | etc',
                data_json JSON NOT NULL COMMENT 'Campos do cartão + preferências coletados',
                completeness_score TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0-100',
                is_valid TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = pronto para gerar',
                validated_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                
                INDEX idx_order_id (order_id),
                INDEX idx_service_slug (service_slug),
                INDEX idx_is_valid (is_valid),
                INDEX idx_completeness_score (completeness_score),
                FOREIGN KEY (order_id) REFERENCES service_orders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS service_intakes");
    }
}

