<?php

/**
 * Migration: Adiciona índice único em asaas_customer_id para evitar duplicidades
 */
class AlterTenantsAddUniqueAsaasCustomerId
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna existe
        $columns = $db->query("SHOW COLUMNS FROM tenants")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('asaas_customer_id', $columns)) {
            // Se não existir, cria a coluna primeiro
            $db->exec("ALTER TABLE tenants ADD COLUMN asaas_customer_id VARCHAR(100) NULL AFTER phone");
        }
        
        // Verifica índices existentes
        $indexes = $db->query("SHOW INDEXES FROM tenants WHERE Column_name = 'asaas_customer_id'")->fetchAll(PDO::FETCH_ASSOC);
        
        // Remove índice não-único se existir
        $hasNonUnique = false;
        foreach ($indexes as $index) {
            if ($index['Key_name'] === 'idx_asaas_customer_id' && $index['Non_unique'] == 1) {
                $hasNonUnique = true;
                break;
            }
        }
        
        if ($hasNonUnique) {
            try {
                $db->exec("ALTER TABLE tenants DROP INDEX idx_asaas_customer_id");
            } catch (\Exception $e) {
                // Ignora se não existir
            }
        }
        
        // Verifica se já existe índice único
        $hasUnique = false;
        foreach ($indexes as $index) {
            if ($index['Non_unique'] == 0) {
                $hasUnique = true;
                break;
            }
        }
        
        // Adiciona índice único se não existir
        if (!$hasUnique) {
            // Verifica se há duplicatas antes de criar o índice único
            $duplicates = $db->query("
                SELECT asaas_customer_id, COUNT(*) as count 
                FROM tenants 
                WHERE asaas_customer_id IS NOT NULL 
                GROUP BY asaas_customer_id 
                HAVING count > 1
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($duplicates)) {
                // Se houver duplicatas, loga mas não bloqueia (pode ser corrigido manualmente)
                error_log("Aviso: Existem duplicatas em asaas_customer_id. Corrija antes de criar índice único.");
            }
            
            try {
                $db->exec("ALTER TABLE tenants ADD UNIQUE INDEX idx_asaas_customer_id_unique (asaas_customer_id)");
            } catch (\Exception $e) {
                // Se falhar por duplicatas, apenas loga
                error_log("Não foi possível criar índice único em asaas_customer_id: " . $e->getMessage());
            }
        }
    }

    public function down(PDO $db): void
    {
        // Remove índice único (tenta diferentes nomes possíveis)
        try {
            $db->exec("ALTER TABLE tenants DROP INDEX idx_asaas_customer_id_unique");
        } catch (\Exception $e) {
            // Tenta encontrar o nome real do índice
            $indexes = $db->query("SHOW INDEXES FROM tenants WHERE Column_name = 'asaas_customer_id' AND Non_unique = 0")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($indexes)) {
                $indexName = $indexes[0]['Key_name'];
                $db->exec("ALTER TABLE tenants DROP INDEX {$indexName}");
            }
        }
        
        // Restaura índice não-único
        try {
            $db->exec("ALTER TABLE tenants ADD INDEX idx_asaas_customer_id (asaas_customer_id)");
        } catch (\Exception $e) {
            // Ignora se já existir
        }
    }
}

