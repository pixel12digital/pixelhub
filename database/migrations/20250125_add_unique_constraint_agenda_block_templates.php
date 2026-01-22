<?php

/**
 * Migration: Adiciona constraint única em agenda_block_templates para evitar duplicatas
 * 
 * Evita que sejam criados templates duplicados com mesmo dia_semana, hora_inicio, hora_fim e tipo_id
 */
class AddUniqueConstraintAgendaBlockTemplates
{
    public function up(PDO $db): void
    {
        // Verifica se já existe a constraint única
        $indexes = $db->query("SHOW INDEXES FROM agenda_block_templates")->fetchAll(PDO::FETCH_ASSOC);
        
        $hasUnique = false;
        foreach ($indexes as $index) {
            if ($index['Key_name'] === 'unique_template_datetime' && $index['Non_unique'] == 0) {
                $hasUnique = true;
                break;
            }
        }
        
        if (!$hasUnique) {
            // Verifica se há duplicatas antes de criar a constraint
            $duplicates = $db->query("
                SELECT dia_semana, hora_inicio, hora_fim, tipo_id, COUNT(*) as count
                FROM agenda_block_templates
                WHERE ativo = 1
                GROUP BY dia_semana, hora_inicio, hora_fim, tipo_id
                HAVING count > 1
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($duplicates)) {
                // Remove duplicatas, mantendo apenas o primeiro (menor ID)
                foreach ($duplicates as $dup) {
                    $stmt = $db->prepare("
                        SELECT id FROM agenda_block_templates
                        WHERE dia_semana = ? AND hora_inicio = ? AND hora_fim = ? AND tipo_id = ? AND ativo = 1
                        ORDER BY id ASC
                    ");
                    $stmt->execute([$dup['dia_semana'], $dup['hora_inicio'], $dup['hora_fim'], $dup['tipo_id']]);
                    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Mantém o primeiro, remove os demais
                    if (count($ids) > 1) {
                        $primeiroId = array_shift($ids);
                        $placeholders = implode(',', array_fill(0, count($ids), '?'));
                        $stmt = $db->prepare("DELETE FROM agenda_block_templates WHERE id IN ($placeholders)");
                        $stmt->execute($ids);
                    }
                }
            }
            
            // Cria a constraint única
            try {
                $db->exec("
                    ALTER TABLE agenda_block_templates 
                    ADD UNIQUE KEY unique_template_datetime (dia_semana, hora_inicio, hora_fim, tipo_id)
                ");
            } catch (\Exception $e) {
                error_log("Erro ao criar constraint única em agenda_block_templates: " . $e->getMessage());
            }
        }
    }

    public function down(PDO $db): void
    {
        try {
            $db->exec("ALTER TABLE agenda_block_templates DROP INDEX unique_template_datetime");
        } catch (\Exception $e) {
            // Ignora se não existir
        }
    }
}










