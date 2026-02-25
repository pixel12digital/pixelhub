<?php

/**
 * Migration: Corrige o nome do provedor HostMidia para HostMídia (com acento)
 */
class FixHostmidiaName
{
    public function up(PDO $db): void
    {
        // Atualiza o nome do provedor de "HostMidia" para "HostMídia"
        $db->exec("
            UPDATE hosting_providers 
            SET name = 'HostMídia' 
            WHERE slug = 'hostmidia'
        ");
    }

    public function down(PDO $db): void
    {
        // Reverte para o nome anterior
        $db->exec("
            UPDATE hosting_providers 
            SET name = 'HostMidia' 
            WHERE slug = 'hostmidia'
        ");
    }
}
