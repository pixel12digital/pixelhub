<?php

class AlterBillingInvoicesAddWhatsappFields
{
    public function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE billing_invoices
            ADD COLUMN whatsapp_last_stage VARCHAR(50) NULL AFTER status,
            ADD COLUMN whatsapp_last_at DATETIME NULL AFTER whatsapp_last_stage,
            ADD COLUMN whatsapp_total_messages INT UNSIGNED NOT NULL DEFAULT 0 AFTER whatsapp_last_at
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE billing_invoices
            DROP COLUMN whatsapp_last_stage,
            DROP COLUMN whatsapp_last_at,
            DROP COLUMN whatsapp_total_messages
        ");
    }
}

