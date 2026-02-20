<?php

/**
 * Migration: Cria tabela opportunity_products e adiciona product_id em opportunities
 * 
 * Produtos funcionam como tags para classificar oportunidades (ex: E-commerce, PixelHub CRM, etc.)
 * Evita duplicidade através de slug normalizado único
 */
class CreateOpportunityProductsTable
{
    public function up(PDO $db): void
    {
        // Tabela de produtos/tags
        $db->exec("
            CREATE TABLE IF NOT EXISTS opportunity_products (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                label VARCHAR(100) NOT NULL COMMENT 'Nome exibido (ex: E-commerce)',
                slug VARCHAR(100) NOT NULL UNIQUE COMMENT 'Slug normalizado (ex: e-commerce)',
                is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0=desativado, 1=ativo',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_slug (slug),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Adicionar campo product_id em opportunities
        $columns = $db->query("SHOW COLUMNS FROM opportunities")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('product_id', $columns)) {
            $db->exec("ALTER TABLE opportunities ADD COLUMN product_id INT UNSIGNED NULL COMMENT 'FK para opportunity_products'");
        }
        try { $db->exec("ALTER TABLE opportunities ADD INDEX idx_product (product_id)"); } catch (\Exception $e) {}

        // Adicionar foreign key (se não houver conflito)
        try {
            $db->exec("
                ALTER TABLE opportunities 
                ADD CONSTRAINT fk_opportunities_product 
                FOREIGN KEY (product_id) REFERENCES opportunity_products(id) 
                ON DELETE SET NULL
            ");
        } catch (\Exception $e) {
            // Se já existe ou outro erro, continua sem FK
            error_log('[Migration] Erro ao adicionar FK product_id: ' . $e->getMessage());
        }

        // Inserir produtos iniciais
        $initialProducts = [
            'E-commerce',
            'PixelHub CRM', 
            'ImobSites',
            'ServPro',
            'CFC',
            'Landing Page',
            'Aplicativo Mobile',
            'Sistema ERP',
            'Marketing Digital',
            'Outros'
        ];

        foreach ($initialProducts as $label) {
            $slug = $this->normalizeSlug($label);
            
            $stmt = $db->prepare("
                INSERT IGNORE INTO opportunity_products (label, slug, is_active, created_at)
                VALUES (?, ?, 1, NOW())
            ");
            $stmt->execute([$label, $slug]);
        }
    }

    public function down(PDO $db): void
    {
        // Remover foreign key se existir
        try {
            $db->exec("ALTER TABLE opportunities DROP FOREIGN KEY fk_opportunities_product");
        } catch (\Exception $e) {
            // Continua mesmo se não existir
        }

        // Remover índice e campo
        $db->exec("ALTER TABLE opportunities DROP INDEX idx_product");
        $db->exec("ALTER TABLE opportunities DROP COLUMN product_id");

        // Remover tabela
        $db->exec("DROP TABLE IF EXISTS opportunity_products");
    }

    /**
     * Normaliza texto para slug: trim + lowercase + remover acentos + espaços únicos
     */
    private function normalizeSlug(string $text): string
    {
        $text = trim($text);
        $text = strtolower($text);
        
        // Remover acentos
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        
        // Substituir caracteres especiais
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        
        // Substituir múltiplos espaços/hífens por um único hífen
        $text = preg_replace('/[\s-]+/', '-', $text);
        
        // Remover hífens do início/fim
        $text = trim($text, '-');
        
        return $text;
    }
}
