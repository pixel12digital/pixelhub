<?php

/**
 * Migration: Torna nome opcional e adiciona coluna company na tabela leads
 * 
 * Permite criar leads apenas com telefone ou e-mail, sem exigir nome.
 * Adiciona campo company (nome da empresa) opcional.
 */
class AlterLeadsFlexibleFields
{
    public function up(PDO $db): void
    {
        // Torna name nullable (antes era NOT NULL)
        $db->exec("ALTER TABLE leads MODIFY COLUMN name VARCHAR(255) NULL DEFAULT NULL");

        // Adiciona coluna company (nome da empresa) após name
        try {
            $check = $db->query("SHOW COLUMNS FROM leads LIKE 'company'");
            if ($check->rowCount() === 0) {
                $db->exec("ALTER TABLE leads ADD COLUMN company VARCHAR(255) NULL DEFAULT NULL COMMENT 'Nome da empresa' AFTER name");
            }
        } catch (Exception $e) {
            error_log("[Migration] Erro ao adicionar company em leads: " . $e->getMessage());
        }
    }

    public function down(PDO $db): void
    {
        try {
            $db->exec("ALTER TABLE leads DROP COLUMN company");
        } catch (Exception $e) {
            // Ignora se coluna não existe
        }
        $db->exec("ALTER TABLE leads MODIFY COLUMN name VARCHAR(255) NOT NULL");
    }
}
