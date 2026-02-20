<?php

/**
 * Migration: Adiciona campo status em agenda_manual_items
 * Para controlar follow-ups enviados vs pendentes
 */
class AlterAgendaManualItemsAddStatus
{
    public function up(PDO $db): void
    {
        $columns = $db->query("SHOW COLUMNS FROM agenda_manual_items")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('status', $columns)) {
            $db->exec("ALTER TABLE agenda_manual_items 
                ADD COLUMN status ENUM('pending', 'completed', 'cancelled', 'failed') DEFAULT 'pending' 
                AFTER item_type");
        }

        if (!in_array('completed_at', $columns)) {
            $db->exec("ALTER TABLE agenda_manual_items 
                ADD COLUMN completed_at DATETIME NULL");
        }

        if (!in_array('completed_by', $columns)) {
            $db->exec("ALTER TABLE agenda_manual_items 
                ADD COLUMN completed_by INT UNSIGNED NULL");
        }
    }

    public function down(PDO $db): void
    {
        try { $db->exec("ALTER TABLE agenda_manual_items DROP COLUMN status"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE agenda_manual_items DROP COLUMN completed_at"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE agenda_manual_items DROP COLUMN completed_by"); } catch (Exception $e) {}
    }
}
