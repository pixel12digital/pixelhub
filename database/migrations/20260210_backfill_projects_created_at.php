<?php

/**
 * Migration: Preenche created_at NULL em projects com a data da tarefa mais antiga
 * Corrige barras no Gantt da Visão Macro que apareciam deslocadas (ex: Pixel Hub em Jan/26)
 */
class BackfillProjectsCreatedAt
{
    public function up(PDO $db): void
    {
        $db->exec("
            UPDATE projects p
            INNER JOIN (
                SELECT project_id, MIN(created_at) as min_created
                FROM tasks
                WHERE project_id IS NOT NULL
                GROUP BY project_id
            ) t ON p.id = t.project_id
            SET p.created_at = t.min_created
            WHERE p.created_at IS NULL
        ");
    }

    public function down(PDO $db): void
    {
        // Não revertível - não há como saber quais eram NULL
    }
}
