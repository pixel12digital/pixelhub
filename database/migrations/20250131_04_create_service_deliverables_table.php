<?php

/**
 * Migration: Cria tabela service_deliverables
 * 
 * Arquivos entregues ao cliente (PDF, PNG, QR Code, etc).
 */
class CreateServiceDeliverablesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS service_deliverables (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_id INT UNSIGNED NOT NULL COMMENT 'FK para service_orders',
                kind VARCHAR(50) NOT NULL COMMENT 'pdf_print | png_digital | qr_asset | source_internal',
                file_url VARCHAR(500) NULL COMMENT 'URL ou path do arquivo',
                file_path VARCHAR(500) NULL COMMENT 'Path físico do arquivo',
                metadata JSON NULL COMMENT 'Dimensões, tamanho, etc',
                created_at DATETIME NOT NULL,
                
                INDEX idx_order_id (order_id),
                INDEX idx_kind (kind),
                FOREIGN KEY (order_id) REFERENCES service_orders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS service_deliverables");
    }
}

