<?php

/**
 * Migration: Adiciona campos para envio manual de cobrança
 * 
 * Campos adicionados:
 * - trigger_source: 'automatic'|'manual'
 * - triggered_by_user_id: ID do usuário que fez envio manual
 * - reason: motivo do envio manual
 * - is_forced: se ignora cooldown
 * - force_reason: motivo do envio forçado
 * - sent_at: data/hora real do envio (fonte de verdade)
 * - idempotency_key: chave única para evitar corrida
 */
class AlterBillingDispatchQueueAddManualFields
{
    public function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE billing_dispatch_queue
            ADD COLUMN trigger_source ENUM('automatic', 'manual') NOT NULL DEFAULT 'automatic' AFTER channel,
            ADD COLUMN triggered_by_user_id INT UNSIGNED NULL AFTER trigger_source,
            ADD COLUMN reason VARCHAR(255) NULL AFTER triggered_by_user_id,
            ADD COLUMN is_forced TINYINT(1) NOT NULL DEFAULT 0 AFTER reason,
            ADD COLUMN force_reason VARCHAR(255) NULL AFTER is_forced,
            ADD COLUMN sent_at DATETIME NULL AFTER last_attempt_at,
            ADD COLUMN idempotency_key VARCHAR(100) NULL AFTER sent_at,
            ADD INDEX idx_bdq_idempotency (idempotency_key),
            ADD INDEX idx_bdq_sent_at (sent_at),
            ADD CONSTRAINT fk_bdq_user FOREIGN KEY (triggered_by_user_id) REFERENCES users(id) ON DELETE SET NULL
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE billing_dispatch_queue
            DROP FOREIGN KEY fk_bdq_user,
            DROP INDEX idx_bdq_idempotency,
            DROP INDEX idx_bdq_sent_at,
            DROP COLUMN trigger_source,
            DROP COLUMN triggered_by_user_id,
            DROP COLUMN reason,
            DROP COLUMN is_forced,
            DROP COLUMN force_reason,
            DROP COLUMN sent_at,
            DROP COLUMN idempotency_key
        ");
    }
}
