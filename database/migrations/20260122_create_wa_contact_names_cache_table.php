<?php

/**
 * Migration: Cria tabela wa_contact_names_cache para cachear nomes de contatos WhatsApp
 * 
 * Esta tabela cacheia nomes extraídos de payloads ou obtidos via API do gateway,
 * permitindo exibir nomes mesmo quando contact_name da conversa está vazio.
 */
class CreateWaContactNamesCacheTable
{
    public function up(PDO $db): void
    {
        // Verifica se a tabela já existe
        $stmt = $db->query("SHOW TABLES LIKE 'wa_contact_names_cache'");
        if ($stmt->rowCount() > 0) {
            return; // Tabela já existe
        }

        $db->exec("
            CREATE TABLE wa_contact_names_cache (
                id INT PRIMARY KEY AUTO_INCREMENT,
                provider VARCHAR(50) NOT NULL DEFAULT 'wpp_gateway',
                session_id VARCHAR(100) NULL COMMENT 'ID da sessão WhatsApp (opcional)',
                phone_e164 VARCHAR(20) NOT NULL COMMENT 'Número de telefone em formato E.164',
                display_name VARCHAR(255) NOT NULL COMMENT 'Nome do contato (ex: Victor)',
                source VARCHAR(50) NOT NULL DEFAULT 'payload' COMMENT 'Origem: payload, provider, manual',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_provider_session_phone (provider, session_id, phone_e164),
                INDEX idx_provider_phone (provider, phone_e164),
                INDEX idx_phone_e164 (phone_e164),
                INDEX idx_updated_at (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS wa_contact_names_cache");
    }
}

