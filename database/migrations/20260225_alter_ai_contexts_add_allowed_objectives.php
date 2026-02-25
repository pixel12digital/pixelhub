<?php

/**
 * Migration: Adiciona campo allowed_objectives na tabela ai_contexts
 * para filtrar objetivos disponíveis por contexto
 */
class AlterAiContextsAddAllowedObjectives
{
    public function up(PDO $db): void
    {
        // Adiciona campo allowed_objectives (JSON array com lista de objetivos permitidos)
        $db->exec("
            ALTER TABLE ai_contexts
            ADD COLUMN allowed_objectives JSON NULL COMMENT 'Lista de objetivos permitidos para este contexto (null = todos)'
            AFTER knowledge_base
        ");

        // Atualiza contextos existentes com objetivos específicos
        $now = date('Y-m-d H:i:s');

        // E-commerce: objetivos comerciais
        $db->exec("
            UPDATE ai_contexts 
            SET allowed_objectives = JSON_ARRAY(
                'first_contact', 'qualify', 'schedule_call', 'answer_question', 
                'follow_up', 'send_proposal', 'close_deal'
            ),
            updated_at = '{$now}'
            WHERE slug = 'ecommerce'
        ");

        // Sites: objetivos comerciais
        $db->exec("
            UPDATE ai_contexts 
            SET allowed_objectives = JSON_ARRAY(
                'first_contact', 'qualify', 'schedule_call', 'answer_question', 
                'follow_up', 'send_proposal', 'close_deal'
            ),
            updated_at = '{$now}'
            WHERE slug = 'sites'
        ");

        // Tráfego Pago: objetivos comerciais
        $db->exec("
            UPDATE ai_contexts 
            SET allowed_objectives = JSON_ARRAY(
                'first_contact', 'qualify', 'schedule_call', 'answer_question', 
                'follow_up', 'send_proposal', 'close_deal'
            ),
            updated_at = '{$now}'
            WHERE slug = 'trafego'
        ");

        // Social Media: objetivos comerciais
        $db->exec("
            UPDATE ai_contexts 
            SET allowed_objectives = JSON_ARRAY(
                'first_contact', 'qualify', 'schedule_call', 'answer_question', 
                'follow_up', 'send_proposal', 'close_deal'
            ),
            updated_at = '{$now}'
            WHERE slug = 'social-media'
        ");

        // Suporte Técnico: apenas suporte
        $db->exec("
            UPDATE ai_contexts 
            SET allowed_objectives = JSON_ARRAY('support', 'answer_question'),
            updated_at = '{$now}'
            WHERE slug = 'suporte'
        ");

        // Financeiro: apenas 2 objetivos (billing será automático baseado na API Asaas)
        $db->exec("
            UPDATE ai_contexts 
            SET allowed_objectives = JSON_ARRAY('answer_question', 'billing'),
            updated_at = '{$now}'
            WHERE slug = 'financeiro'
        ");

        // Geral: todos os objetivos (null = sem filtro)
        $db->exec("
            UPDATE ai_contexts 
            SET allowed_objectives = NULL,
            updated_at = '{$now}'
            WHERE slug = 'geral'
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE ai_contexts DROP COLUMN allowed_objectives");
    }
}
