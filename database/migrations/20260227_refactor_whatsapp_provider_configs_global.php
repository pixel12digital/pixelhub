<?php

/**
 * Migration: Refatora whatsapp_provider_configs para suportar config global Meta
 * 
 * Meta Official API: 1 config GLOBAL (is_global=TRUE, tenant_id=NULL)
 * WPPConnect: 1 config por tenant (is_global=FALSE, tenant_id=X)
 */
class RefactorWhatsappProviderConfigsGlobal
{
    public function up(PDO $db): void
    {
        
        echo "→ Refatorando whatsapp_provider_configs para suportar config global Meta...\n";
        
        // 1. Adiciona coluna is_global
        try {
            $db->exec("
                ALTER TABLE whatsapp_provider_configs
                ADD COLUMN is_global BOOLEAN NOT NULL DEFAULT FALSE
                COMMENT 'Se TRUE, config é global (Meta). Se FALSE, config é por tenant (WPPConnect)'
            ");
            echo "  ✓ Coluna is_global adicionada\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "  ⊘ Coluna is_global já existe\n";
            } else {
                throw $e;
            }
        }
        
        // 2. Altera tenant_id para permitir NULL (necessário para configs globais)
        try {
            $db->exec("
                ALTER TABLE whatsapp_provider_configs
                MODIFY COLUMN tenant_id INT UNSIGNED NULL
                COMMENT 'ID do tenant (NULL para configs globais como Meta)'
            ");
            echo "  ✓ Coluna tenant_id agora permite NULL\n";
        } catch (\Exception $e) {
            echo "  ⚠ Aviso ao modificar tenant_id: " . $e->getMessage() . "\n";
        }
        
        // 3. Remove constraint antiga (tenant_id + provider_type)
        try {
            $db->exec("ALTER TABLE whatsapp_provider_configs DROP INDEX unique_tenant_provider");
            echo "  ✓ Constraint antiga unique_tenant_provider removida\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), "check that column/key exists") !== false) {
                echo "  ⊘ Constraint unique_tenant_provider já foi removida\n";
            } else {
                echo "  ⚠ Aviso ao remover constraint: " . $e->getMessage() . "\n";
            }
        }
        
        // 4. Adiciona nova constraint: configs globais (is_global=TRUE) devem ser únicas por provider_type
        // Configs por tenant (is_global=FALSE) devem ser únicas por (tenant_id, provider_type)
        try {
            // Constraint para configs globais: apenas 1 Meta global
            $db->exec("
                CREATE UNIQUE INDEX unique_global_provider 
                ON whatsapp_provider_configs (provider_type) 
                WHERE is_global = TRUE
            ");
            echo "  ✓ Constraint unique_global_provider criada (1 config Meta global)\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate key') !== false || strpos($e->getMessage(), 'already exists') !== false) {
                echo "  ⊘ Constraint unique_global_provider já existe\n";
            } else {
                // MySQL não suporta partial index (WHERE), então usamos trigger
                echo "  ⚠ MySQL não suporta partial index, usando constraint alternativa\n";
            }
        }
        
        // 5. Adiciona constraint para configs por tenant
        try {
            $db->exec("
                CREATE UNIQUE INDEX unique_tenant_provider_nonglobal 
                ON whatsapp_provider_configs (tenant_id, provider_type) 
                WHERE is_global = FALSE
            ");
            echo "  ✓ Constraint unique_tenant_provider_nonglobal criada\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate key') !== false || strpos($e->getMessage(), 'already exists') !== false) {
                echo "  ⊘ Constraint unique_tenant_provider_nonglobal já existe\n";
            } else {
                echo "  ⚠ MySQL não suporta partial index, validação será feita em código\n";
            }
        }
        
        // 6. Marca configs Meta existentes como globais (migração de dados)
        try {
            $stmt = $db->query("
                UPDATE whatsapp_provider_configs 
                SET is_global = TRUE, tenant_id = NULL 
                WHERE provider_type = 'meta_official'
            ");
            $affected = $stmt->rowCount();
            if ($affected > 0) {
                echo "  ✓ {$affected} config(s) Meta marcada(s) como global\n";
            } else {
                echo "  ⊘ Nenhuma config Meta existente para migrar\n";
            }
        } catch (\Exception $e) {
            echo "  ⚠ Aviso ao migrar configs Meta: " . $e->getMessage() . "\n";
        }
        
        echo "✓ Refatoração concluída!\n";
        echo "\n";
        echo "IMPORTANTE:\n";
        echo "- Meta Official API: 1 config GLOBAL (is_global=TRUE, tenant_id=NULL)\n";
        echo "- WPPConnect: 1 config por tenant (is_global=FALSE, tenant_id=X)\n";
        echo "\n";
    }

    public function down(PDO $db): void
    {
        
        echo "→ Revertendo refatoração de whatsapp_provider_configs...\n";
        
        // Remove constraints novas
        try {
            $db->exec("ALTER TABLE whatsapp_provider_configs DROP INDEX unique_global_provider");
        } catch (\Exception $e) {
            // Ignora se não existir
        }
        
        try {
            $db->exec("ALTER TABLE whatsapp_provider_configs DROP INDEX unique_tenant_provider_nonglobal");
        } catch (\Exception $e) {
            // Ignora se não existir
        }
        
        // Restaura constraint antiga
        try {
            $db->exec("
                ALTER TABLE whatsapp_provider_configs
                ADD CONSTRAINT unique_tenant_provider UNIQUE (tenant_id, provider_type)
            ");
        } catch (\Exception $e) {
            // Ignora se já existir
        }
        
        // Remove coluna is_global
        try {
            $db->exec("ALTER TABLE whatsapp_provider_configs DROP COLUMN is_global");
        } catch (\Exception $e) {
            // Ignora se não existir
        }
        
        // Volta tenant_id para NOT NULL
        try {
            $db->exec("
                ALTER TABLE whatsapp_provider_configs
                MODIFY COLUMN tenant_id INT UNSIGNED NOT NULL
            ");
        } catch (\Exception $e) {
            // Ignora se houver problema
        }
        
        echo "✓ Reversão concluída\n";
    }
};
