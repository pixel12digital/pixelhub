<?php

/**
 * Migration: Adiciona lost_reason_id à tabela opportunities
 * 
 * Vincula oportunidades perdidas aos motivos padronizados.
 * Mantém lost_reason (texto livre) para observações adicionais.
 */
class AlterOpportunitiesAddLostReasonId
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna já existe
        $stmt = $db->query("SHOW COLUMNS FROM opportunities LIKE 'lost_reason_id'");
        if ($stmt->rowCount() > 0) {
            return; // Coluna já existe
        }

        $db->exec("
            ALTER TABLE opportunities
            ADD COLUMN lost_reason_id INT UNSIGNED NULL COMMENT 'FK para opportunity_lost_reasons' AFTER lost_at,
            ADD INDEX idx_lost_reason (lost_reason_id)
        ");

        // Atualiza comentário do campo lost_reason para indicar que agora é observação adicional
        $db->exec("
            ALTER TABLE opportunities
            MODIFY COLUMN lost_reason TEXT NULL COMMENT 'Observações adicionais sobre a perda (campo livre)'
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE opportunities
            DROP COLUMN lost_reason_id
        ");

        $db->exec("
            ALTER TABLE opportunities
            MODIFY COLUMN lost_reason VARCHAR(255) NULL COMMENT 'Motivo da perda'
        ");
    }
}
