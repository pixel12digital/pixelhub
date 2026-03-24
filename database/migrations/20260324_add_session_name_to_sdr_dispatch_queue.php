<?php

/**
 * Migration: Adiciona session_name à sdr_dispatch_queue
 * Permite que cada job SDR use uma sessão Whapi específica.
 */

$db->exec("
    ALTER TABLE sdr_dispatch_queue
    ADD COLUMN session_name VARCHAR(100) NOT NULL DEFAULT ''
        COMMENT 'session_name do canal Whapi a usar (whatsapp_provider_configs.session_name)'
        AFTER recipe_id
");

echo "OK: sdr_dispatch_queue.session_name adicionado\n";
