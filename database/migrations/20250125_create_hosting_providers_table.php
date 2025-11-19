<?php

/**
 * Migration: Cria tabela hosting_providers
 * 
 * Gerenciamento de provedores de hospedagem para o campo 'Provedor Atual' das contas de hospedagem.
 * Permite configurar provedores dinamicamente sem precisar editar código.
 */
class CreateHostingProvidersTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS hosting_providers (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(50) NOT NULL UNIQUE,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                INDEX idx_slug (slug),
                INDEX idx_is_active (is_active),
                INDEX idx_sort_order (sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Insere provedores padrão (mantendo compatibilidade com dados existentes)
        $providers = [
            ['name' => 'Hostinger', 'slug' => 'hostinger', 'sort_order' => 1],
            ['name' => 'HostWeb', 'slug' => 'hostweb', 'sort_order' => 2],
            ['name' => 'Externo', 'slug' => 'externo', 'sort_order' => 3],
        ];

        $stmt = $db->prepare("
            INSERT INTO hosting_providers (name, slug, is_active, sort_order, created_at, updated_at)
            VALUES (?, ?, 1, ?, NOW(), NOW())
        ");

        foreach ($providers as $provider) {
            // Verifica se já existe antes de inserir (evita erro em re-execução)
            $checkStmt = $db->prepare("SELECT id FROM hosting_providers WHERE slug = ?");
            $checkStmt->execute([$provider['slug']]);
            if (!$checkStmt->fetch()) {
                $stmt->execute([
                    $provider['name'],
                    $provider['slug'],
                    $provider['sort_order']
                ]);
            }
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS hosting_providers");
    }
}

