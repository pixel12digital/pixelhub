<?php

use PixelHub\Core\DB;

$db = DB::getConnection();

$db->exec("
    ALTER TABLE conversations
    ADD COLUMN IF NOT EXISTS source VARCHAR(50) NULL DEFAULT NULL AFTER is_incoming_lead
");

echo "Migration 20260307_alter_conversations_add_source: OK\n";
