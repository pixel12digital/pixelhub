<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/DB.php';
use PixelHub\Core\DB;
$db = DB::getConnection();

$db->exec("
    CREATE TABLE IF NOT EXISTS user_notifications (
        id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id       INT UNSIGNED NOT NULL,
        type          VARCHAR(50)  NOT NULL,
        title         VARCHAR(200) NOT NULL,
        message       TEXT         DEFAULT NULL,
        entity_type   VARCHAR(50)  DEFAULT NULL,
        entity_id     INT UNSIGNED DEFAULT NULL,
        data          JSON         DEFAULT NULL,
        is_read       TINYINT(1)   NOT NULL DEFAULT 0,
        read_at       DATETIME     DEFAULT NULL,
        created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_user_unread (user_id, is_read),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "✓ Tabela user_notifications OK\n";
