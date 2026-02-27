<?php

/**
 * Migration: Adiciona status 'sent_uncertain' em billing_notifications
 * 
 * Usado quando há timeout na requisição ao gateway, mas a mensagem
 * provavelmente foi enviada (timeout resilience).
 */
class AlterBillingNotificationsAddSentUncertainStatus
{
    public function up(PDO $db): void
    {
        // Altera o ENUM para incluir 'sent_uncertain'
        $db->exec("
            ALTER TABLE billing_notifications 
            MODIFY COLUMN status ENUM('pending', 'sent', 'sent_uncertain', 'failed') 
            NOT NULL DEFAULT 'pending'
        ");
    }

    public function down(PDO $db): void
    {
        // Remove 'sent_uncertain' do ENUM
        $db->exec("
            ALTER TABLE billing_notifications 
            MODIFY COLUMN status ENUM('pending', 'sent', 'failed') 
            NOT NULL DEFAULT 'pending'
        ");
    }
}
