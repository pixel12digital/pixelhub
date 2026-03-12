<?php

/**
 * Migration: Adiciona coluna name em tenant_message_channels
 * 
 * Permite nomear canais de forma amigável (ex: "Pixel12 Digital")
 * em vez de exibir o channel_id bruto (ex: "CATWMN-JLNTR")
 */
class AlterTenantMessageChannelsAddName
{
    public function up(PDO $db): void
    {
        $cols = $db->query("SHOW COLUMNS FROM tenant_message_channels LIKE 'name'")->fetchAll();
        if (empty($cols)) {
            $db->exec("
                ALTER TABLE tenant_message_channels
                ADD COLUMN name VARCHAR(100) NULL COMMENT 'Nome amigável do canal (ex: Pixel12 Digital)'
                AFTER channel_id
            ");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE tenant_message_channels DROP COLUMN IF EXISTS name");
    }
}
