<?php
/**
 * Migration: adiciona colunas para follow-up de prospecção em scheduled_messages
 */
return new class {
    public function up(\PDO $db): void
    {
        $db->exec("
            ALTER TABLE scheduled_messages
                ADD COLUMN IF NOT EXISTS phone          VARCHAR(30)  DEFAULT NULL AFTER conversation_id,
                ADD COLUMN IF NOT EXISTS message_type   VARCHAR(30)  DEFAULT 'text' AFTER message_text,
                ADD COLUMN IF NOT EXISTS message_content TEXT         DEFAULT NULL AFTER message_type,
                ADD COLUMN IF NOT EXISTS template_params JSON         DEFAULT NULL AFTER message_content,
                ADD COLUMN IF NOT EXISTS trigger_event  VARCHAR(60)  DEFAULT NULL AFTER template_params,
                ADD COLUMN IF NOT EXISTS metadata       JSON         DEFAULT NULL AFTER trigger_event
        ");
    }

    public function down(\PDO $db): void
    {
        $db->exec("
            ALTER TABLE scheduled_messages
                DROP COLUMN IF EXISTS phone,
                DROP COLUMN IF EXISTS message_type,
                DROP COLUMN IF EXISTS message_content,
                DROP COLUMN IF EXISTS template_params,
                DROP COLUMN IF EXISTS trigger_event,
                DROP COLUMN IF EXISTS metadata
        ");
    }
};
