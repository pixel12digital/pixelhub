<?php

/**
 * Migration: Adiciona campos de tracking em leads e opportunities
 * 
 * Implementa origem obrigatória com fallback "unknown"
 * e tracking detalhado para detecção automática
 */
class AddTrackingFieldsToLeadsAndOpportunities
{
    public function up(PDO $db): void
    {
        // Adiciona campos de tracking na tabela leads
        try {
            $check = $db->query("SHOW COLUMNS FROM leads LIKE 'tracking_code'");
            if ($check->rowCount() === 0) {
                $db->exec("
                    ALTER TABLE leads 
                    ADD COLUMN tracking_code VARCHAR(50) NULL COMMENT 'Código de rastreamento detectado (FK tracking_codes)',
                    ADD COLUMN tracking_metadata JSON NULL COMMENT 'Metadados do tracking (página, cta, campanha, etc.)',
                    ADD COLUMN tracking_auto_detected BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Se tracking foi detectado automaticamente',
                    ADD INDEX idx_tracking_code (tracking_code)
                ");
            }
        } catch (Exception $e) {
            error_log("[Migration] Erro ao adicionar tracking em leads: " . $e->getMessage());
        }

        // Adiciona campos de tracking na tabela opportunities
        try {
            $check = $db->query("SHOW COLUMNS FROM opportunities LIKE 'tracking_code'");
            if ($check->rowCount() === 0) {
                $db->exec("
                    ALTER TABLE opportunities 
                    ADD COLUMN tracking_code VARCHAR(50) NULL COMMENT 'Código de rastreamento detectado (FK tracking_codes)',
                    ADD COLUMN tracking_metadata JSON NULL COMMENT 'Metadados do tracking (página, cta, campanha, etc.)',
                    ADD COLUMN tracking_auto_detected BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Se tracking foi detectado automaticamente',
                    ADD INDEX idx_tracking_code (tracking_code)
                ");
            }
        } catch (Exception $e) {
            error_log("[Migration] Erro ao adicionar tracking em opportunities: " . $e->getMessage());
        }

        // Adiciona campo origin em opportunities (obrigatório)
        try {
            $check = $db->query("SHOW COLUMNS FROM opportunities LIKE 'origin'");
            if ($check->rowCount() === 0) {
                $db->exec("
                    ALTER TABLE opportunities 
                    ADD COLUMN origin VARCHAR(50) NOT NULL DEFAULT 'unknown' COMMENT 'Origem da oportunidade (canal ou unknown)',
                    ADD INDEX idx_origin (origin)
                ");
            }
        } catch (Exception $e) {
            error_log("[Migration] Erro ao adicionar origin em opportunities: " . $e->getMessage());
        }

        // Atualiza valores existentes para garantir origem obrigatória
        try {
            // Mapeia valores antigos de leads.source para novos valores padrão
            $db->exec("
                UPDATE leads 
                SET source = 'unknown' 
                WHERE source IS NULL OR source = ''
            ");

            // Se opportunities já tiverem registros, define origin como unknown
            $db->exec("
                UPDATE opportunities 
                SET origin = 'unknown' 
                WHERE origin = '' OR origin IS NULL
            ");
        } catch (Exception $e) {
            error_log("[Migration] Erro ao atualizar valores padrão: " . $e->getMessage());
        }
    }

    public function down(PDO $db): void
    {
        try {
            $db->exec("ALTER TABLE leads DROP COLUMN tracking_code");
            $db->exec("ALTER TABLE leads DROP COLUMN tracking_metadata");
            $db->exec("ALTER TABLE leads DROP COLUMN tracking_auto_detected");
        } catch (Exception $e) {
            // Ignora se coluna não existe
        }

        try {
            $db->exec("ALTER TABLE opportunities DROP COLUMN tracking_code");
            $db->exec("ALTER TABLE opportunities DROP COLUMN tracking_metadata");
            $db->exec("ALTER TABLE opportunities DROP COLUMN tracking_auto_detected");
            $db->exec("ALTER TABLE opportunities DROP COLUMN origin");
        } catch (Exception $e) {
            // Ignora se coluna não existe
        }
    }
}
