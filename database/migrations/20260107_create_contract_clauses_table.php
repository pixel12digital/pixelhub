<?php

/**
 * Migration: Cria tabela de cláusulas de contrato configuráveis
 * 
 * Permite configurar cláusulas padrão que serão usadas na montagem automática de contratos.
 */
class CreateContractClausesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS contract_clauses (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL COMMENT 'Título da cláusula (ex: Objeto, Prazo, Pagamento)',
                content TEXT NOT NULL COMMENT 'Conteúdo da cláusula (pode conter variáveis como {valor}, {cliente}, etc.)',
                order_index INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição das cláusulas',
                is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Se a cláusula está ativa',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                
                INDEX idx_order (order_index),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Insere cláusulas padrão
        $defaultClauses = [
            [
                'title' => 'Objeto do Contrato',
                'content' => 'O presente contrato tem por objeto a prestação de serviços de {servico} pela CONTRATADA em favor do CONTRATANTE, conforme especificações técnicas acordadas entre as partes.',
                'order_index' => 1,
            ],
            [
                'title' => 'Valor e Forma de Pagamento',
                'content' => 'O valor total do contrato é de R$ {valor}, a ser pago conforme condições acordadas entre as partes.',
                'order_index' => 2,
            ],
            [
                'title' => 'Prazo de Execução',
                'content' => 'O prazo para execução dos serviços será de {prazo}, contados a partir da assinatura do presente contrato.',
                'order_index' => 3,
            ],
            [
                'title' => 'Obrigações do Contratante',
                'content' => 'O CONTRATANTE se compromete a fornecer todas as informações, documentos e materiais necessários para a execução dos serviços, no prazo acordado.',
                'order_index' => 4,
            ],
            [
                'title' => 'Obrigações da Contratada',
                'content' => 'A CONTRATADA se compromete a executar os serviços com qualidade, observando os prazos estabelecidos e mantendo sigilo sobre informações confidenciais do CONTRATANTE.',
                'order_index' => 5,
            ],
            [
                'title' => 'Rescisão',
                'content' => 'O presente contrato poderá ser rescindido por qualquer das partes, mediante aviso prévio de 30 (trinta) dias, sem prejuízo das obrigações já assumidas.',
                'order_index' => 6,
            ],
        ];
        
        $stmt = $db->prepare("
            INSERT INTO contract_clauses (title, content, order_index, is_active, created_at, updated_at)
            VALUES (?, ?, ?, 1, NOW(), NOW())
        ");
        
        foreach ($defaultClauses as $clause) {
            $stmt->execute([
                $clause['title'],
                $clause['content'],
                $clause['order_index'],
            ]);
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS contract_clauses");
    }
}

