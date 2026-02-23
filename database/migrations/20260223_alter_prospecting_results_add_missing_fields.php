<?php

/**
 * Migration: Adiciona campos faltantes da API Minha Receita em prospecting_results
 *
 * Campos adicionados:
 * - razao_social (já capturado mas não salvo)
 * - motivo_situacao_cadastral + descricao_motivo
 * - qualificacao_responsavel
 * - complemento (endereço)
 * - bairro
 * - cep
 * - situacao_especial + data_situacao_especial
 * - codigo_natureza_juridica, codigo_porte
 * - data_opcao_simples, data_exclusao_simples
 * - data_opcao_mei, data_exclusao_mei
 */
class AlterProspectingResultsAddMissingFields
{
    public function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE prospecting_results
                ADD COLUMN razao_social VARCHAR(255) NULL
                    COMMENT 'Razão social da empresa' AFTER name,
                ADD COLUMN complemento VARCHAR(100) NULL
                    COMMENT 'Complemento do endereço' AFTER address,
                ADD COLUMN bairro VARCHAR(100) NULL
                    COMMENT 'Bairro' AFTER complemento,
                ADD COLUMN cep VARCHAR(10) NULL
                    COMMENT 'CEP' AFTER bairro,
                ADD COLUMN motivo_situacao_cadastral INT NULL
                    COMMENT 'Código do motivo da situação cadastral' AFTER data_situacao_cadastral,
                ADD COLUMN descricao_motivo_situacao VARCHAR(100) NULL
                    COMMENT 'Descrição do motivo da situação' AFTER motivo_situacao_cadastral,
                ADD COLUMN situacao_especial VARCHAR(100) NULL
                    COMMENT 'Situação especial se houver' AFTER descricao_motivo_situacao,
                ADD COLUMN data_situacao_especial DATE NULL
                    COMMENT 'Data da situação especial' AFTER situacao_especial,
                ADD COLUMN codigo_natureza_juridica INT NULL
                    COMMENT 'Código da natureza jurídica' AFTER natureza_juridica,
                ADD COLUMN codigo_porte INT NULL
                    COMMENT 'Código do porte (1=Micro, 3=Pequeno, 5=Demais)' AFTER porte,
                ADD COLUMN qualificacao_responsavel INT NULL
                    COMMENT 'Código da qualificação do responsável' AFTER codigo_porte,
                ADD COLUMN data_opcao_simples DATE NULL
                    COMMENT 'Data de opção pelo Simples Nacional' AFTER opcao_pelo_simples,
                ADD COLUMN data_exclusao_simples DATE NULL
                    COMMENT 'Data de exclusão do Simples Nacional' AFTER data_opcao_simples,
                ADD COLUMN data_opcao_mei DATE NULL
                    COMMENT 'Data de opção pelo MEI' AFTER opcao_pelo_mei,
                ADD COLUMN data_exclusao_mei DATE NULL
                    COMMENT 'Data de exclusão do MEI' AFTER data_opcao_mei
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE prospecting_results
                DROP COLUMN data_exclusao_mei,
                DROP COLUMN data_opcao_mei,
                DROP COLUMN data_exclusao_simples,
                DROP COLUMN data_opcao_simples,
                DROP COLUMN qualificacao_responsavel,
                DROP COLUMN codigo_porte,
                DROP COLUMN codigo_natureza_juridica,
                DROP COLUMN data_situacao_especial,
                DROP COLUMN situacao_especial,
                DROP COLUMN descricao_motivo_situacao,
                DROP COLUMN motivo_situacao_cadastral,
                DROP COLUMN cep,
                DROP COLUMN bairro,
                DROP COLUMN complemento,
                DROP COLUMN razao_social
        ");
    }
}
