<?php

/**
 * Migration: Adiciona campos de automação em billing_notifications
 * 
 * - triggered_by: 'manual' ou 'scheduler'
 * - dispatch_rule_id: referência à regra que disparou (se automático)
 * - gateway_message_id: ID da mensagem retornada pelo gateway
 */
class AlterBillingNotificationsAddAutomationFields
{
    public function up(PDO $db): void
    {
        $columns = $db->query("SHOW COLUMNS FROM billing_notifications")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('triggered_by', $columns)) {
            $db->exec("ALTER TABLE billing_notifications ADD COLUMN triggered_by VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER status");
        }
        
        if (!in_array('dispatch_rule_id', $columns)) {
            $db->exec("ALTER TABLE billing_notifications ADD COLUMN dispatch_rule_id INT UNSIGNED NULL AFTER triggered_by");
        }
        
        if (!in_array('gateway_message_id', $columns)) {
            $db->exec("ALTER TABLE billing_notifications ADD COLUMN gateway_message_id VARCHAR(100) NULL AFTER dispatch_rule_id");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE billing_notifications DROP COLUMN IF EXISTS triggered_by");
        $db->exec("ALTER TABLE billing_notifications DROP COLUMN IF EXISTS dispatch_rule_id");
        $db->exec("ALTER TABLE billing_notifications DROP COLUMN IF EXISTS gateway_message_id");
    }
}
