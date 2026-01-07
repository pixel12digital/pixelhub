<?php

/**
 * Migration: Cria tabela agenda_block_templates
 * Define o template semanal de blocos (segunda a sexta)
 */
class CreateAgendaBlockTemplatesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS agenda_block_templates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                dia_semana TINYINT NOT NULL COMMENT '1=Segunda, 2=Terça, 3=Quarta, 4=Quinta, 5=Sexta, 6=Sábado, 7=Domingo',
                hora_inicio TIME NOT NULL,
                hora_fim TIME NOT NULL,
                tipo_id INT UNSIGNED NOT NULL,
                descricao_padrao VARCHAR(255) NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_dia_semana (dia_semana),
                INDEX idx_tipo_id (tipo_id),
                INDEX idx_ativo (ativo),
                FOREIGN KEY (tipo_id) REFERENCES agenda_block_types(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Busca os IDs dos tipos de blocos
        $stmt = $db->query("SELECT id, codigo FROM agenda_block_types");
        $types = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $types[$row['codigo']] = $row['id'];
        }
        
        // Template base do CHARLINHO (segunda a sexta)
        // Segunda a Sexta: 07:00-09:00 FUTURE, 09:00-10:00 CLIENTES, 10:15-11:30 FUTURE, 11:30-12:00 COMERCIAL, 13:00-14:30 CLIENTES, 14:30-16:00 COMERCIAL, 16:15-17:30 SUPORTE, 17:30-18:00 ADMIN
        // Quarta: 14:30-16:00 FLEX (ao invés de COMERCIAL)
        
        $templates = [];
        
        // Para segunda, terça, quinta e sexta (dias 1, 2, 4, 5)
        foreach ([1, 2, 4, 5] as $dia) {
            $templates[] = [$dia, '07:00', '09:00', $types['FUTURE'], 'Produtos/sistemas internos'];
            $templates[] = [$dia, '09:00', '10:00', $types['CLIENTES'], 'Atendimento / Leads, triagem'];
            $templates[] = [$dia, '10:15', '11:30', $types['FUTURE'], 'Produtos/sistemas internos'];
            $templates[] = [$dia, '11:30', '12:00', $types['COMERCIAL'], 'Comercial leve'];
            $templates[] = [$dia, '13:00', '14:30', $types['CLIENTES'], 'Entrega pesada'];
            $templates[] = [$dia, '14:30', '16:00', $types['COMERCIAL'], 'Comercial forte'];
            $templates[] = [$dia, '16:15', '17:30', $types['SUPORTE'], 'Dúvidas rápidas e micro-ajustes'];
            $templates[] = [$dia, '17:30', '18:00', $types['ADMIN'], 'Financeiro, contabilidade, planejamento'];
        }
        
        // Para quarta-feira (dia 3) - com FLEX
        $templates[] = [3, '07:00', '09:00', $types['FUTURE'], 'Produtos/sistemas internos'];
        $templates[] = [3, '09:00', '10:00', $types['CLIENTES'], 'Atendimento / Leads, triagem'];
        $templates[] = [3, '10:15', '11:30', $types['FUTURE'], 'Produtos/sistemas internos'];
        $templates[] = [3, '11:30', '12:00', $types['COMERCIAL'], 'Comercial leve'];
        $templates[] = [3, '13:00', '14:30', $types['CLIENTES'], 'Entrega pesada'];
        $templates[] = [3, '14:30', '16:00', $types['FLEX'], 'Bloco coringa'];
        $templates[] = [3, '16:15', '17:30', $types['SUPORTE'], 'Dúvidas rápidas e micro-ajustes'];
        $templates[] = [3, '17:30', '18:00', $types['ADMIN'], 'Financeiro, contabilidade, planejamento'];
        
        $stmt = $db->prepare("
            INSERT INTO agenda_block_templates (dia_semana, hora_inicio, hora_fim, tipo_id, descricao_padrao, ativo)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        
        foreach ($templates as $template) {
            $stmt->execute($template);
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS agenda_block_templates");
    }
}











