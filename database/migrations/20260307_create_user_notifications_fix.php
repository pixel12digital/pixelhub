<?php

use PixelHub\Core\DB;

$db = DB::getConnection();

$db->exec("
    CREATE TABLE IF NOT EXISTS user_notifications (
        id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id       INT UNSIGNED NOT NULL COMMENT 'Usuário destinatário',
        type          VARCHAR(50)  NOT NULL COMMENT 'chatbot_lead_interest, lead_assigned, etc',
        title         VARCHAR(200) NOT NULL,
        message       TEXT         DEFAULT NULL,
        entity_type   VARCHAR(50)  DEFAULT NULL COMMENT 'opportunity, lead, conversation',
        entity_id     INT UNSIGNED DEFAULT NULL COMMENT 'ID da entidade relacionada',
        data          JSON         DEFAULT NULL COMMENT 'Dados extras (json)',
        is_read       TINYINT(1)   NOT NULL DEFAULT 0,
        read_at       DATETIME     DEFAULT NULL,
        created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_user_unread (user_id, is_read),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "Migration OK: tabela user_notifications criada/verificada\n";
