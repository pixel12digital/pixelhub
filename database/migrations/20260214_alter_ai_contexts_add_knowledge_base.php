<?php

/**
 * Migration: Adiciona campo knowledge_base na tabela ai_contexts
 * para armazenar informações extras como cópia de página de vendas,
 * detalhes de produto, FAQ, etc. que a IA usa no prompt.
 */
class AlterAiContextsAddKnowledgeBase
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna já existe
        $cols = $db->query("SHOW COLUMNS FROM ai_contexts LIKE 'knowledge_base'")->fetchAll();
        if (empty($cols)) {
            $db->exec("
                ALTER TABLE ai_contexts
                ADD COLUMN knowledge_base MEDIUMTEXT NULL 
                COMMENT 'Base de conhecimento: cópia de página de vendas, FAQ, detalhes de produto, etc.'
                AFTER system_prompt
            ");
            echo "  ✅ Coluna knowledge_base adicionada em ai_contexts\n";
        } else {
            echo "  ⚠️ Coluna knowledge_base já existe\n";
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE ai_contexts DROP COLUMN IF EXISTS knowledge_base");
    }
}
