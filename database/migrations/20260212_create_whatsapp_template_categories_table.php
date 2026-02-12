<?php

/**
 * Migration: Cria tabela de categorias hierárquicas para templates de WhatsApp
 * 
 * Suporta categorias e subcategorias (parent_id).
 * Também adiciona coluna category_id na tabela whatsapp_templates.
 */
class CreateWhatsappTemplateCategoriesTable
{
    public function up(PDO $db): void
    {
        // Tabela de categorias com hierarquia (parent_id)
        $db->exec("
            CREATE TABLE IF NOT EXISTS whatsapp_template_categories (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL,
                parent_id INT UNSIGNED NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_parent_id (parent_id),
                INDEX idx_slug (slug),
                INDEX idx_sort_order (sort_order),
                CONSTRAINT fk_template_cat_parent FOREIGN KEY (parent_id) REFERENCES whatsapp_template_categories(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Adiciona coluna category_id na tabela de templates
        $db->exec("
            ALTER TABLE whatsapp_templates 
            ADD COLUMN category_id INT UNSIGNED NULL AFTER category,
            ADD INDEX idx_category_id (category_id),
            ADD CONSTRAINT fk_template_category FOREIGN KEY (category_id) REFERENCES whatsapp_template_categories(id) ON DELETE SET NULL
        ");

        // Seed: categorias padrão baseadas nos valores existentes
        $db->exec("
            INSERT INTO whatsapp_template_categories (name, slug, parent_id, sort_order) VALUES
            ('Comercial', 'comercial', NULL, 1),
            ('Campanha', 'campanha', NULL, 2),
            ('Geral', 'geral', NULL, 3)
        ");

        // Migra templates existentes para usar category_id
        $db->exec("
            UPDATE whatsapp_templates t
            JOIN whatsapp_template_categories c ON c.slug = t.category
            SET t.category_id = c.id
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE whatsapp_templates DROP FOREIGN KEY fk_template_category");
        $db->exec("ALTER TABLE whatsapp_templates DROP COLUMN category_id");
        $db->exec("DROP TABLE IF EXISTS whatsapp_template_categories");
    }
}
