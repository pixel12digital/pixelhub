<?php

/**
 * Migration: Ajusta tabela tickets
 * - Torna tenant_id obrigatório (NOT NULL)
 * - Adiciona status 'cancelado' ao ENUM
 * 
 * IMPORTANTE: Tickets devem sempre estar vinculados a um cliente.
 * project_id permanece opcional (ticket pode existir sem projeto).
 */
class AlterTicketsMakeTenantRequiredAndAddCanceladoStatus
{
    public function up(PDO $db): void
    {
        // Primeiro, remove tickets órfãos (sem tenant_id) se existirem
        // Isso garante que não haverá erro ao tornar o campo NOT NULL
        $db->exec("DELETE FROM tickets WHERE tenant_id IS NULL");
        
        // Adiciona status 'cancelado' ao ENUM
        // MySQL não permite MODIFY ENUM diretamente, então precisamos recriar a coluna
        $db->exec("
            ALTER TABLE tickets 
            MODIFY COLUMN status ENUM('aberto', 'em_atendimento', 'aguardando_cliente', 'resolvido', 'cancelado') 
            NOT NULL DEFAULT 'aberto'
        ");
        
        // Torna tenant_id obrigatório
        // Primeiro remove a FK antiga
        $db->exec("ALTER TABLE tickets DROP FOREIGN KEY tickets_ibfk_1");
        
        // Altera a coluna para NOT NULL
        $db->exec("
            ALTER TABLE tickets 
            MODIFY COLUMN tenant_id INT UNSIGNED NOT NULL
        ");
        
        // Recria a FK com ON DELETE RESTRICT (não permite deletar tenant com tickets)
        $db->exec("
            ALTER TABLE tickets 
            ADD CONSTRAINT fk_tickets_tenant 
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT
        ");
    }

    public function down(PDO $db): void
    {
        // Remove a FK
        $db->exec("ALTER TABLE tickets DROP FOREIGN KEY fk_tickets_tenant");
        
        // Volta tenant_id para nullable
        $db->exec("
            ALTER TABLE tickets 
            MODIFY COLUMN tenant_id INT UNSIGNED NULL
        ");
        
        // Recria FK antiga
        $db->exec("
            ALTER TABLE tickets 
            ADD CONSTRAINT tickets_ibfk_1 
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL
        ");
        
        // Remove status 'cancelado' do ENUM
        $db->exec("
            ALTER TABLE tickets 
            MODIFY COLUMN status ENUM('aberto', 'em_atendimento', 'aguardando_cliente', 'resolvido') 
            NOT NULL DEFAULT 'aberto'
        ");
    }
}










