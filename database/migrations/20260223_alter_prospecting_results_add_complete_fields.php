<?php

/**
 * Migration: Adiciona campos completos da API Minha Receita em prospecting_results
 *
 * Campos adicionados:
 * - Situação cadastral (ATIVA, BAIXADA, SUSPENSA, INAPTA)
 * - Data de início de atividade (abertura da empresa)
 * - Porte (MICRO EMPRESA, PEQUENO PORTE, DEMAIS)
 * - Natureza jurídica (MEI, LTDA, SA, etc.)
 * - Regime tributário (Simples, MEI)
 * - Capital social
 * - Telefone secundário
 * - Identificador matriz/filial
 * - CNAEs secundários
 */
class AlterProspectingResultsAddCompleteFields
{
    public function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE prospecting_results
                ADD COLUMN situacao_cadastral VARCHAR(50) NULL
                    COMMENT 'Situação: ATIVA, BAIXADA, SUSPENSA, INAPTA' AFTER cnae_description,
                ADD COLUMN data_situacao_cadastral DATE NULL
                    COMMENT 'Data da última mudança de situação cadastral' AFTER situacao_cadastral,
                ADD COLUMN data_inicio_atividade DATE NULL
                    COMMENT 'Data de abertura/início das atividades' AFTER data_situacao_cadastral,
                ADD COLUMN porte VARCHAR(50) NULL
                    COMMENT 'Porte: MICRO EMPRESA, EMPRESA DE PEQUENO PORTE, DEMAIS' AFTER data_inicio_atividade,
                ADD COLUMN natureza_juridica VARCHAR(100) NULL
                    COMMENT 'Natureza jurídica: MEI, LTDA, SA, etc.' AFTER porte,
                ADD COLUMN opcao_pelo_mei BOOLEAN NULL
                    COMMENT 'Se é optante pelo MEI' AFTER natureza_juridica,
                ADD COLUMN opcao_pelo_simples BOOLEAN NULL
                    COMMENT 'Se é optante pelo Simples Nacional' AFTER opcao_pelo_mei,
                ADD COLUMN capital_social BIGINT NULL
                    COMMENT 'Capital social em centavos' AFTER opcao_pelo_simples,
                ADD COLUMN telefone_secundario VARCHAR(50) NULL
                    COMMENT 'Telefone secundário (ddd_telefone_2)' AFTER capital_social,
                ADD COLUMN identificador_matriz_filial TINYINT NULL
                    COMMENT '1=Matriz, 2=Filial' AFTER telefone_secundario,
                ADD COLUMN cnaes_secundarios JSON NULL
                    COMMENT 'Array de CNAEs secundários [{codigo, descricao}]' AFTER identificador_matriz_filial,
                ADD INDEX idx_situacao_cadastral (situacao_cadastral),
                ADD INDEX idx_porte (porte),
                ADD INDEX idx_opcao_mei (opcao_pelo_mei),
                ADD INDEX idx_matriz_filial (identificador_matriz_filial)
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE prospecting_results
                DROP INDEX idx_matriz_filial,
                DROP INDEX idx_opcao_mei,
                DROP INDEX idx_porte,
                DROP INDEX idx_situacao_cadastral,
                DROP COLUMN cnaes_secundarios,
                DROP COLUMN identificador_matriz_filial,
                DROP COLUMN telefone_secundario,
                DROP COLUMN capital_social,
                DROP COLUMN opcao_pelo_simples,
                DROP COLUMN opcao_pelo_mei,
                DROP COLUMN natureza_juridica,
                DROP COLUMN porte,
                DROP COLUMN data_inicio_atividade,
                DROP COLUMN data_situacao_cadastral,
                DROP COLUMN situacao_cadastral
        ");
    }
}
