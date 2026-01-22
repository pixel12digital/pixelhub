<?php

/**
 * Migration: Atualiza cláusula de Prazo de Execução para usar variável {prazo}
 * 
 * Atualiza a cláusula padrão para usar a variável {prazo} que será substituída
 * automaticamente pelo prazo do projeto.
 */
class UpdatePrazoClauseWithVariable
{
    public function up(PDO $db): void
    {
        // Atualiza a cláusula "Prazo de Execução" para usar a variável {prazo}
        $stmt = $db->prepare("
            UPDATE contract_clauses 
            SET content = 'O prazo para execução dos serviços será de {prazo}, contados a partir da assinatura do presente contrato.',
                updated_at = NOW()
            WHERE title = 'Prazo de Execução'
        ");
        $stmt->execute();
    }

    public function down(PDO $db): void
    {
        // Reverte para o conteúdo original
        $stmt = $db->prepare("
            UPDATE contract_clauses 
            SET content = 'O prazo para execução dos serviços será de acordo com o cronograma estabelecido, iniciando-se a partir da assinatura do presente contrato.',
                updated_at = NOW()
            WHERE title = 'Prazo de Execução'
        ");
        $stmt->execute();
    }
}

