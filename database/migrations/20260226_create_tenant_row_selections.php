<?php
/**
 * Migration: Criar tabela para armazenar seleções de linhas de clientes
 * Permite marcar visualmente clientes já processados/visitados
 */

require_once __DIR__ . '/../migrate.php';

use PixelHub\Core\DB;

try {
    $db = DB::getConnection();
    
    // Cria tabela tenant_row_selections
    $db->exec("
        CREATE TABLE IF NOT EXISTS tenant_row_selections (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            selected TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_tenant_user (tenant_id, user_id),
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✅ Tabela tenant_row_selections criada com sucesso!\n";
    
} catch (Exception $e) {
    echo "❌ Erro ao criar tabela tenant_row_selections: " . $e->getMessage() . "\n";
    exit(1);
}
