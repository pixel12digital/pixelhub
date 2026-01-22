<?php

/**
 * Migration: Adiciona service_slug e ajusta status em service_orders
 * 
 * Adapta service_orders para suportar service_slug e status conforme especificação.
 */
class AlterServiceOrdersAddServiceSlugAndStatus
{
    public function up(PDO $db): void
    {
        // Adiciona service_slug se não existir
        $db->exec("
            ALTER TABLE service_orders 
            ADD COLUMN IF NOT EXISTS service_slug VARCHAR(100) NULL 
            COMMENT 'business_card_express | etc' 
            AFTER service_id
        ");
        
        // Adiciona índice para service_slug
        try {
            $db->exec("CREATE INDEX idx_service_slug ON service_orders(service_slug)");
        } catch (PDOException $e) {
            // Índice já existe, ignora
        }
        
        // Atualiza status para valores da especificação se necessário
        // Mantém compatibilidade com valores existentes
        $db->exec("
            UPDATE service_orders 
            SET status = CASE 
                WHEN status = 'pending' THEN 'draft'
                WHEN status = 'approved' THEN 'active'
                WHEN status = 'converted' THEN 'delivered'
                ELSE status
            END
            WHERE status IN ('pending', 'approved', 'converted')
        ");
        
        // Altera valores permitidos do status (MySQL não suporta ENUM alterado diretamente)
        // Por enquanto mantém VARCHAR, mas podemos adicionar constraint se necessário
    }

    public function down(PDO $db): void
    {
        // Remove service_slug
        try {
            $db->exec("ALTER TABLE service_orders DROP COLUMN service_slug");
        } catch (PDOException $e) {
            // Coluna não existe, ignora
        }
        
        // Reverte status (tentativa, pode não ser preciso)
        $db->exec("
            UPDATE service_orders 
            SET status = CASE 
                WHEN status = 'draft' THEN 'pending'
                WHEN status = 'active' THEN 'approved'
                WHEN status = 'delivered' THEN 'converted'
                ELSE status
            END
            WHERE status IN ('draft', 'active', 'delivered')
        ");
    }
}

