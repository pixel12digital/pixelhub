<?php

/**
 * Migration: Adiciona campos role, is_active, last_login_at, phone na tabela users
 */
class AlterUsersAddRoleAndStatus
{
    public function up(PDO $db): void
    {
        // Verificar se coluna role já existe
        $cols = $db->query("SHOW COLUMNS FROM users LIKE 'role'")->fetchAll();
        if (empty($cols)) {
            $db->exec("ALTER TABLE users ADD COLUMN role VARCHAR(30) NOT NULL DEFAULT 'admin' AFTER is_internal");
        }

        $cols = $db->query("SHOW COLUMNS FROM users LIKE 'is_active'")->fetchAll();
        if (empty($cols)) {
            $db->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role");
        }

        $cols = $db->query("SHOW COLUMNS FROM users LIKE 'last_login_at'")->fetchAll();
        if (empty($cols)) {
            $db->exec("ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER is_active");
        }

        $cols = $db->query("SHOW COLUMNS FROM users LIKE 'phone'")->fetchAll();
        if (empty($cols)) {
            $db->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL AFTER email");
        }

        // Atualizar usuários existentes: is_internal=1 → role='admin'
        $db->exec("UPDATE users SET role = 'admin' WHERE is_internal = 1 AND role = 'admin'");
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE users DROP COLUMN IF EXISTS role");
        $db->exec("ALTER TABLE users DROP COLUMN IF EXISTS is_active");
        $db->exec("ALTER TABLE users DROP COLUMN IF EXISTS last_login_at");
        $db->exec("ALTER TABLE users DROP COLUMN IF EXISTS phone");
    }
}
