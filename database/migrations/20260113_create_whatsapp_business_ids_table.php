<?php

/**
 * Migration: Cria tabela whatsapp_business_ids para mapear IDs @lid aos números reais
 * 
 * Esta tabela resolve o problema de contas WhatsApp Business que usam IDs internos
 * (@lid) ao invés de números de telefone diretos no payload.
 */
class CreateWhatsappBusinessIdsTable
{
    public function up(PDO $db): void
    {
        // Verifica se a tabela já existe
        $stmt = $db->query("SHOW TABLES LIKE 'whatsapp_business_ids'");
        if ($stmt->rowCount() > 0) {
            return; // Tabela já existe
        }

        $db->exec("
            CREATE TABLE whatsapp_business_ids (
                id INT PRIMARY KEY AUTO_INCREMENT,
                business_id VARCHAR(100) NOT NULL COMMENT 'ID interno do WhatsApp Business (ex: 10523374551225@lid)',
                phone_number VARCHAR(20) NOT NULL COMMENT 'Número de telefone real em formato E.164 (ex: 554796474223)',
                tenant_id INT UNSIGNED NULL COMMENT 'ID do tenant (opcional, para isolamento)',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_business_id (business_id),
                INDEX idx_phone_number (phone_number),
                INDEX idx_tenant_id (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS whatsapp_business_ids");
    }
}

