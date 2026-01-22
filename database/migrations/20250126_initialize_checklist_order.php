<?php

/**
 * Migration: Inicializa o campo order dos itens de checklist
 * Garante que todos os itens existentes tenham order sequencial baseado em id ou created_at
 */
class InitializeChecklistOrder
{
    public function up(PDO $db): void
    {
        // Inicializa order para todos os itens que têm order = 0
        // Usa id como base para ordenação (itens mais antigos primeiro)
        // Agrupa por task_id para manter ordem sequencial dentro de cada tarefa
        $db->exec("
            UPDATE task_checklists tc1
            SET tc1.`order` = (
                SELECT COUNT(*) + 1
                FROM task_checklists tc2
                WHERE tc2.task_id = tc1.task_id
                AND tc2.id < tc1.id
            )
            WHERE tc1.`order` = 0
        ");
    }

    public function down(PDO $db): void
    {
        // Não há necessidade de reverter, pois order já existia na tabela
        // Esta migration apenas inicializa valores que estavam zerados
    }
}

