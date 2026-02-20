<?php

/**
 * Migration: Adiciona coluna updated_by na tabela opportunities
 */
class AddUpdatedByToOpportunities
{
    public function up(PDO $db): void
    {
        try {
            $check = $db->query("SHOW COLUMNS FROM opportunities LIKE 'updated_by'");
            if ($check->rowCount() === 0) {
                $db->exec("
                    ALTER TABLE opportunities 
                    ADD COLUMN updated_by INT UNSIGNED NULL COMMENT 'FK para users (quem fez a última edição)'
                ");
            }
        } catch (Exception $e) {
            error_log("[Migration] Erro ao adicionar updated_by em opportunities: " . $e->getMessage());
        }
    }

    public function down(PDO $db): void
    {
        try {
            $db->exec("ALTER TABLE opportunities DROP COLUMN updated_by");
        } catch (Exception $e) {
            // Ignora se coluna não existe
        }
    }
}
