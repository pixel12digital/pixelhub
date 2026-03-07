<?php
$db = DB::getConnection();
$db->exec("ALTER TABLE prospecting_results ADD COLUMN IF NOT EXISTS whatsapp_sent_at DATETIME NULL DEFAULT NULL AFTER status");
echo "Migration OK: whatsapp_sent_at adicionado em prospecting_results\n";
