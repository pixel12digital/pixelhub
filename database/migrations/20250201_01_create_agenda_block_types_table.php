<?php

/**
 * Migration: Cria tabela agenda_block_types
 * Define os tipos de blocos de agenda (FUTURE, CLIENTES, COMERCIAL, SUPORTE, ADMIN, PESSOAL, FLEX)
 */
class CreateAgendaBlockTypesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS agenda_block_types (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(120) NOT NULL,
                codigo VARCHAR(30) NOT NULL UNIQUE,
                cor_hex VARCHAR(10) NULL,
                descricao TEXT NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_codigo (codigo),
                INDEX idx_ativo (ativo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Seed inicial com os tipos de blocos
        $types = [
            ['FUTURE', 'FUTURE', '#4CAF50', 'Blocos para produtos/sistemas internos escaláveis'],
            ['CLIENTES', 'CLIENTES', '#2196F3', 'Blocos para projetos de clientes'],
            ['COMERCIAL', 'COMERCIAL', '#FF9800', 'Blocos para vendas, criativos, tráfego'],
            ['SUPORTE', 'SUPORTE', '#9C27B0', 'Blocos para dúvidas rápidas e micro-ajustes'],
            ['ADMIN', 'ADMIN', '#F44336', 'Blocos para financeiro, contabilidade, planejamento'],
            ['PESSOAL', 'PESSOAL', '#00BCD4', 'Blocos pessoais (caminhada, família, natação, etc.)'],
            ['FLEX', 'FLEX', '#795548', 'Bloco coringa para comercial/admin/financeiro pesado'],
        ];
        
        $stmt = $db->prepare("
            INSERT IGNORE INTO agenda_block_types (nome, codigo, cor_hex, descricao, ativo)
            VALUES (?, ?, ?, ?, 1)
        ");
        
        foreach ($types as $type) {
            $stmt->execute($type);
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS agenda_block_types");
    }
}

