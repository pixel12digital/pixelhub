<?php

/**
 * Migration: Adiciona regras de disparo -1d e +1d
 * 
 * -1d: Lembrete 1 dia antes do vencimento
 * +1d: Cobrança 1 dia após vencimento (gentil)
 */
class AddBillingDispatchRules1d
{
    public function up(PDO $db): void
    {
        // Verifica se já existem para não duplicar
        $existing = $db->query("SELECT days_offset FROM billing_dispatch_rules WHERE days_offset IN (-1, 1)")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array(-1, $existing)) {
            $db->exec("
                INSERT INTO billing_dispatch_rules (name, stage, days_offset, channels, is_enabled, repeat_if_open, repeat_interval_days, max_repeats, template_key)
                VALUES ('Lembrete véspera do vencimento', 'pre_due_1d', -1, '[\"whatsapp\"]', 1, 0, NULL, 1, 'pre_due_1d')
            ");
        }

        if (!in_array(1, $existing)) {
            $db->exec("
                INSERT INTO billing_dispatch_rules (name, stage, days_offset, channels, is_enabled, repeat_if_open, repeat_interval_days, max_repeats, template_key)
                VALUES ('Lembrete pós-vencimento (1 dia)', 'overdue_1d', 1, '[\"whatsapp\"]', 1, 0, NULL, 1, 'overdue_1d')
            ");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("DELETE FROM billing_dispatch_rules WHERE days_offset IN (-1, 1)");
    }
}
