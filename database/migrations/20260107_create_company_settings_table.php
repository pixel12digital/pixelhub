<?php

/**
 * Migration: Cria tabela de configurações da empresa
 * 
 * Armazena dados da empresa (Pixel12 Digital) para uso em contratos e documentos.
 */
class CreateCompanySettingsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS company_settings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                
                -- Dados básicos
                company_name VARCHAR(255) NOT NULL DEFAULT 'Pixel12 Digital' COMMENT 'Nome da empresa',
                company_name_fantasy VARCHAR(255) NULL COMMENT 'Nome fantasia',
                cnpj VARCHAR(20) NULL COMMENT 'CNPJ da empresa',
                ie VARCHAR(50) NULL COMMENT 'Inscrição Estadual',
                im VARCHAR(50) NULL COMMENT 'Inscrição Municipal',
                
                -- Endereço
                address_street VARCHAR(255) NULL COMMENT 'Rua',
                address_number VARCHAR(20) NULL COMMENT 'Número',
                address_complement VARCHAR(255) NULL COMMENT 'Complemento',
                address_neighborhood VARCHAR(100) NULL COMMENT 'Bairro',
                address_city VARCHAR(100) NULL COMMENT 'Cidade',
                address_state VARCHAR(2) NULL COMMENT 'Estado (UF)',
                address_cep VARCHAR(10) NULL COMMENT 'CEP',
                
                -- Contato
                phone VARCHAR(20) NULL COMMENT 'Telefone',
                email VARCHAR(255) NULL COMMENT 'Email',
                website VARCHAR(255) NULL COMMENT 'Website',
                
                -- Logo e branding
                logo_url VARCHAR(500) NULL COMMENT 'URL do logo da empresa',
                logo_path VARCHAR(500) NULL COMMENT 'Caminho do arquivo do logo (upload)',
                
                -- Metadados
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                
                -- Garante apenas um registro
                UNIQUE KEY unique_company (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Insere registro padrão
        $stmt = $db->prepare("
            INSERT INTO company_settings 
            (company_name, created_at, updated_at)
            VALUES ('Pixel12 Digital', NOW(), NOW())
        ");
        $stmt->execute();
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS company_settings");
    }
}

