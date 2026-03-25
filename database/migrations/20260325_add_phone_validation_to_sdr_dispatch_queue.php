<?php

/**
 * Migration: Adiciona colunas de validação de telefone na sdr_dispatch_queue
 * 
 * Para integração com API de validação do Whapi.Cloud
 */
class AddPhoneValidationToSdrDispatchQueue
{
    public function up(PDO $db): void
    {
        echo "  📋 Adicionando colunas de validação de telefone em sdr_dispatch_queue\n";
        
        // Verificar se as colunas já existem
        $cols = $db->query("SHOW COLUMNS FROM sdr_dispatch_queue LIKE 'phone_validated'")->fetchAll();
        
        if (count($cols) === 0) {
            $db->exec("
                ALTER TABLE sdr_dispatch_queue 
                ADD COLUMN phone_validated TINYINT(1) DEFAULT NULL 
                    COMMENT 'NULL=não validado, 1=válido, 0=inválido',
                ADD COLUMN phone_validation_status VARCHAR(20) DEFAULT NULL 
                    COMMENT 'valid/invalid/error',
                ADD COLUMN phone_validated_at DATETIME DEFAULT NULL
            ");
            echo "  ✅ Colunas phone_validated, phone_validation_status e phone_validated_at adicionadas\n";
        } else {
            echo "  ⚠️ Colunas de validação já existem\n";
        }
    }

    public function down(PDO $db): void
    {
        echo "  🗑️ Removendo colunas de validação de telefone de sdr_dispatch_queue\n";
        
        $db->exec("
            ALTER TABLE sdr_dispatch_queue 
            DROP COLUMN IF EXISTS phone_validated,
            DROP COLUMN IF EXISTS phone_validation_status,
            DROP COLUMN IF EXISTS phone_validated_at
        ");
        
        echo "  ✅ Colunas removidas\n";
    }
}
