<?php

use PixelHub\Core\DB;

/**
 * Migration: Adicionar campos para bot de prospecção na tabela leads
 * 
 * Campos adicionados:
 * - profile_type: Tipo de perfil do corretor (autonomo/imobiliaria)
 * - opted_out: Se o lead optou por não receber mais mensagens
 * - nurture_ok: Se o lead aceitou receber follow-ups
 * 
 * Data: 2026-03-04
 */

try {
    $db = DB::getConnection();
    
    echo "Iniciando migration: Adicionar campos de bot em leads...\n";
    
    // Verifica se os campos já existem
    $stmt = $db->query("SHOW COLUMNS FROM leads LIKE 'profile_type'");
    if ($stmt->rowCount() > 0) {
        echo "Campo 'profile_type' já existe, pulando...\n";
    } else {
        $db->exec("
            ALTER TABLE leads 
            ADD COLUMN profile_type ENUM('autonomo', 'imobiliaria') NULL DEFAULT NULL 
            COMMENT 'Tipo de perfil do corretor' 
            AFTER source
        ");
        echo "✓ Campo 'profile_type' adicionado\n";
    }
    
    $stmt = $db->query("SHOW COLUMNS FROM leads LIKE 'opted_out'");
    if ($stmt->rowCount() > 0) {
        echo "Campo 'opted_out' já existe, pulando...\n";
    } else {
        $db->exec("
            ALTER TABLE leads 
            ADD COLUMN opted_out TINYINT(1) NOT NULL DEFAULT 0 
            COMMENT 'Lead optou por não receber mensagens' 
            AFTER profile_type
        ");
        echo "✓ Campo 'opted_out' adicionado\n";
    }
    
    $stmt = $db->query("SHOW COLUMNS FROM leads LIKE 'nurture_ok'");
    if ($stmt->rowCount() > 0) {
        echo "Campo 'nurture_ok' já existe, pulando...\n";
    } else {
        $db->exec("
            ALTER TABLE leads 
            ADD COLUMN nurture_ok TINYINT(1) NOT NULL DEFAULT 0 
            COMMENT 'Lead aceitou receber follow-ups' 
            AFTER opted_out
        ");
        echo "✓ Campo 'nurture_ok' adicionado\n";
    }
    
    // Adiciona índice para opted_out (usado em queries de disparo)
    $stmt = $db->query("SHOW INDEX FROM leads WHERE Key_name = 'idx_opted_out'");
    if ($stmt->rowCount() > 0) {
        echo "Índice 'idx_opted_out' já existe, pulando...\n";
    } else {
        $db->exec("ALTER TABLE leads ADD INDEX idx_opted_out (opted_out)");
        echo "✓ Índice 'idx_opted_out' adicionado\n";
    }
    
    echo "\n✅ Migration concluída com sucesso!\n";
    
} catch (Exception $e) {
    echo "\n❌ Erro na migration: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
