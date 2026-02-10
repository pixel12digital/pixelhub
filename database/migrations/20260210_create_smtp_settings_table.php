<?php

/**
 * Migration: Criar tabela smtp_settings
 * 
 * Armazena configurações do servidor SMTP para envio de e-mails transacionais
 */
class CreateSmtpSettingsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS smtp_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                smtp_enabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Ativa/desativa envio SMTP',
                smtp_host VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Servidor SMTP (ex: smtp.gmail.com)',
                smtp_port INT NOT NULL DEFAULT 587 COMMENT 'Porta SMTP (587 TLS, 465 SSL)',
                smtp_username VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Usuário SMTP',
                smtp_password TEXT NOT NULL COMMENT 'Senha SMTP (criptografada)',
                smtp_encryption ENUM('tls', 'ssl', 'none') NOT NULL DEFAULT 'tls' COMMENT 'Tipo de criptografia',
                smtp_from_name VARCHAR(255) NOT NULL DEFAULT 'Pixel12 Digital' COMMENT 'Nome do remetente',
                smtp_from_email VARCHAR(255) NOT NULL DEFAULT 'noreply@pixel12digital.com.br' COMMENT 'Email do remetente',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_smtp_enabled (smtp_enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Insere configuração padrão se não existir
        $existing = $db->query("SELECT COUNT(*) as count FROM smtp_settings")->fetch()['count'];
        if ($existing == 0) {
            $db->exec("
                INSERT INTO smtp_settings (
                    smtp_enabled, smtp_host, smtp_port, smtp_username, 
                    smtp_password, smtp_encryption, smtp_from_name, smtp_from_email
                ) VALUES (
                    0, '', 587, '', '', 'tls', 'Pixel12 Digital', 'noreply@pixel12digital.com.br'
                )
            ");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS smtp_settings");
    }
}
