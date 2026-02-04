<?php

/**
 * Migration: Adiciona bloco_categoria em agenda_block_types.
 * Permite resolução determinística (ID → categoria) no relatório de produtividade.
 * Valores: producao | comercial | pausas
 */
class AlterAgendaBlockTypesAddBlocoCategoria
{
    public function up(PDO $db): void
    {
        $stmt = $db->query("SHOW COLUMNS FROM agenda_block_types LIKE 'bloco_categoria'");
        if ($stmt->rowCount() > 0) {
            return;
        }
        $db->exec("
            ALTER TABLE agenda_block_types
            ADD COLUMN bloco_categoria VARCHAR(20) NULL
            AFTER codigo
        ");
        // Popula baseado em codigo existente (mapeamento canônico)
        $db->exec("
            UPDATE agenda_block_types SET bloco_categoria = 'pausas'
            WHERE UPPER(REPLACE(REPLACE(REPLACE(codigo,'Ó','O'),'Ç','C'),'Ã','A')) IN (
                'ADMIN','PESSOAL','INTERVALO','INTERVAL','ALMOCO','ALMOC','ALMO','PAUSA','PAUSAS'
            )
        ");
        $db->exec("
            UPDATE agenda_block_types SET bloco_categoria = 'comercial'
            WHERE UPPER(REPLACE(REPLACE(REPLACE(codigo,'Ó','O'),'Ç','C'),'Ã','A')) IN ('COMERCIAL','FLEX')
            AND (bloco_categoria IS NULL OR bloco_categoria = '')
        ");
        $db->exec("
            UPDATE agenda_block_types SET bloco_categoria = 'producao'
            WHERE UPPER(REPLACE(REPLACE(REPLACE(codigo,'Ó','O'),'Ç','C'),'Ã','A')) IN (
                'CLIENTES','FUTURE','SUPORTE','PRODUCAO','PROJETO','PROJETOS','CLIENTE'
            )
            AND (bloco_categoria IS NULL OR bloco_categoria = '')
        ");
        // Restante: default producao (admin pode ajustar no form)
        $db->exec("
            UPDATE agenda_block_types SET bloco_categoria = 'producao'
            WHERE bloco_categoria IS NULL OR bloco_categoria = ''
        ");
    }

    public function down(PDO $db): void
    {
        $stmt = $db->query("SHOW COLUMNS FROM agenda_block_types LIKE 'bloco_categoria'");
        if ($stmt->rowCount() === 0) {
            return;
        }
        $db->exec("ALTER TABLE agenda_block_types DROP COLUMN bloco_categoria");
    }
}
