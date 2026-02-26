<?php

/**
 * Migration: Sistema de Mensagem de Start ao Ativar Cobrança Automática
 * 
 * Adiciona:
 * 1. Campo billing_started_at em tenants (timestamp único de quando foi ativado)
 * 2. Tabela billing_start_messages (mensagens de regularização geradas ao ativar)
 * 
 * Proteção Anti-Duplicação:
 * - billing_started_at: marca quando foi feito o primeiro start (não repete)
 * - billing_start_messages: registra cada mensagem gerada (auditoria)
 * - status: pending/approved/sent/cancelled (controle de fluxo)
 */

require_once __DIR__ . '/../../src/Core/Env.php';
require_once __DIR__ . '/../../src/Core/DB.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

try {
    $db = DB::getConnection();
    
    echo "=== Criando Sistema de Billing Start ===\n\n";
    
    // 1. Adiciona campo billing_started_at em tenants
    echo "1. Adicionando campo billing_started_at em tenants...\n";
    $db->exec("
        ALTER TABLE tenants
        ADD COLUMN billing_started_at DATETIME NULL DEFAULT NULL
        COMMENT 'Timestamp único de quando cobrança automática foi ativada pela primeira vez (proteção anti-duplicação)'
        AFTER billing_auto_channel
    ");
    echo "   ✓ Campo billing_started_at adicionado\n\n";
    
    // 2. Cria tabela billing_start_messages
    echo "2. Criando tabela billing_start_messages...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS billing_start_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            
            -- Análise da situação
            total_amount DECIMAL(10,2) NOT NULL COMMENT 'Valor total em aberto',
            overdue_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Quantidade de faturas vencidas',
            pending_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Quantidade de faturas a vencer',
            invoice_ids JSON NOT NULL COMMENT 'Array de IDs das faturas incluídas',
            
            -- Mensagem gerada
            message_type ENUM('billing_critical', 'billing_collection', 'billing_reminder') NOT NULL
                COMMENT 'Tipo de mensagem baseado na gravidade',
            message_text TEXT NOT NULL COMMENT 'Texto da mensagem gerada (pode ser editado)',
            ai_context TEXT NULL COMMENT 'Contexto usado para gerar a mensagem (referência)',
            
            -- Controle de envio
            status ENUM('pending', 'approved', 'sent', 'cancelled') NOT NULL DEFAULT 'pending'
                COMMENT 'pending=aguardando aprovação, approved=aprovado mas não enviado, sent=enviado, cancelled=cancelado',
            channel ENUM('whatsapp', 'email', 'both') NOT NULL DEFAULT 'whatsapp',
            
            -- Proteção anti-duplicação
            is_start_message TINYINT(1) NOT NULL DEFAULT 1 
                COMMENT 'Flag para identificar que é mensagem de start (não repetir)',
            
            -- Auditoria
            sent_at DATETIME NULL DEFAULT NULL,
            sent_by INT UNSIGNED NULL COMMENT 'ID do usuário que aprovou/enviou',
            gateway_message_id VARCHAR(255) NULL COMMENT 'ID da mensagem no gateway (se enviada)',
            
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            -- Índices
            INDEX idx_tenant_status (tenant_id, status),
            INDEX idx_created (created_at),
            
            -- Constraint: apenas 1 mensagem de start por tenant (proteção anti-duplicação)
            UNIQUE KEY unique_start_per_tenant (tenant_id, is_start_message),
            
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Mensagens de regularização geradas ao ativar cobrança automática (proteção anti-duplicação)'
    ");
    echo "   ✓ Tabela billing_start_messages criada\n";
    echo "   ✓ UNIQUE constraint adicionada: apenas 1 start por tenant\n\n";
    
    echo "=== Migration concluída com sucesso! ===\n\n";
    echo "Proteções anti-duplicação implementadas:\n";
    echo "1. billing_started_at: marca timestamp único do primeiro start\n";
    echo "2. UNIQUE constraint: apenas 1 mensagem de start por tenant\n";
    echo "3. Status workflow: pending → approved → sent (controle manual)\n";
    
} catch (PDOException $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
