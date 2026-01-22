<?php

/**
 * Migration: Adiciona campo service_id em projects (OPCIONAL)
 * 
 * Permite vincular projetos aos serviços do catálogo.
 * Campo é opcional (NULL), não afeta projetos existentes.
 */
class AlterProjectsAddServiceId
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna já existe antes de adicionar
        $stmt = $db->query("SHOW COLUMNS FROM projects LIKE 'service_id'");
        if ($stmt->rowCount() === 0) {
            $db->exec("
                ALTER TABLE projects
                ADD COLUMN service_id INT UNSIGNED NULL AFTER tenant_id,
                ADD INDEX idx_service_id (service_id)
            ");
            
            // Verifica se a tabela services existe antes de criar foreign key
            // (pode não existir em ambientes de desenvolvimento)
            try {
                $servicesExists = $db->query("SHOW TABLES LIKE 'services'")->rowCount() > 0;
                if ($servicesExists) {
                    // Adiciona foreign key apenas se a tabela services existir
                    // Usa SET NULL para não bloquear exclusões
                    $db->exec("
                        ALTER TABLE projects
                        ADD CONSTRAINT fk_projects_service_id 
                        FOREIGN KEY (service_id) REFERENCES services(id) 
                        ON DELETE SET NULL
                    ");
                }
            } catch (\Exception $e) {
                // Se falhar ao criar foreign key, apenas registra o erro mas não interrompe
                error_log("Aviso: Não foi possível criar foreign key para service_id: " . $e->getMessage());
            }
        }
    }

    public function down(PDO $db): void
    {
        // Remove foreign key primeiro, depois a coluna
        try {
            $db->exec("ALTER TABLE projects DROP FOREIGN KEY fk_projects_service_id");
        } catch (\Exception $e) {
            // Ignora se não existir
        }
        
        // Verifica se a coluna existe antes de remover
        $stmt = $db->query("SHOW COLUMNS FROM projects LIKE 'service_id'");
        if ($stmt->rowCount() > 0) {
            $db->exec("
                ALTER TABLE projects
                DROP INDEX idx_service_id,
                DROP COLUMN service_id
            ");
        }
    }
}

