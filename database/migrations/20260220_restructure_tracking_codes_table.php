<?php

/**
 * Migration: Reestrutura tracking_codes para incluir contexto completo
 */
class RestructureTrackingCodesTable
{
    public function up(PDO $db): void
    {
        $columns = $db->query("SHOW COLUMNS FROM tracking_codes")->fetchAll(PDO::FETCH_COLUMN);

        $toAdd = [];
        if (!in_array('channel', $columns)) {
            $toAdd[] = "ADD COLUMN channel VARCHAR(50) NOT NULL DEFAULT 'other' COMMENT 'Canal específico: google_ads, google_organic, meta_ads, etc'";
        }
        if (!in_array('origin_page', $columns)) {
            $toAdd[] = "ADD COLUMN origin_page VARCHAR(255) NULL COMMENT 'Página/URL de origem'";
        }
        if (!in_array('cta_position', $columns)) {
            $toAdd[] = "ADD COLUMN cta_position VARCHAR(100) NULL COMMENT 'Posição do CTA: header, hero, footer, popup, etc'";
        }
        if (!in_array('campaign_name', $columns)) {
            $toAdd[] = "ADD COLUMN campaign_name VARCHAR(255) NULL COMMENT 'Nome da campanha (para Ads)'";
        }
        if (!in_array('campaign_id', $columns)) {
            $toAdd[] = "ADD COLUMN campaign_id VARCHAR(100) NULL COMMENT 'ID da campanha'";
        }
        if (!in_array('ad_group', $columns)) {
            $toAdd[] = "ADD COLUMN ad_group VARCHAR(255) NULL COMMENT 'Grupo de anúncio'";
        }
        if (!in_array('ad_name', $columns)) {
            $toAdd[] = "ADD COLUMN ad_name VARCHAR(255) NULL COMMENT 'Nome do anúncio'";
        }
        if (!in_array('context_metadata', $columns)) {
            $toAdd[] = "ADD COLUMN context_metadata JSON NULL COMMENT 'Metadados adicionais do contexto'";
        }

        if (!empty($toAdd)) {
            $db->exec("ALTER TABLE tracking_codes " . implode(', ', $toAdd));
        }

        // Atualiza registros existentes
        try {
            $db->exec("
                UPDATE tracking_codes 
                SET channel = CASE 
                    WHEN source = 'google' THEN 'google_organic'
                    WHEN source = 'instagram' THEN 'instagram_organic'
                    WHEN source = 'facebook' THEN 'facebook_organic'
                    WHEN source = 'whatsapp' THEN 'whatsapp_direct'
                    ELSE 'other'
                END
                WHERE channel = 'other'
            ");
        } catch (\Exception $e) {}

        // Cria índices
        foreach (['idx_channel' => 'channel', 'idx_origin_page' => 'origin_page', 'idx_campaign_name' => 'campaign_name'] as $idx => $col) {
            try { $db->exec("CREATE INDEX {$idx} ON tracking_codes({$col})"); } catch (\Exception $e) {}
        }

        echo "Tabela tracking_codes reestruturada com sucesso!\n";
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE tracking_codes 
            DROP COLUMN channel,
            DROP COLUMN origin_page,
            DROP COLUMN cta_position,
            DROP COLUMN campaign_name,
            DROP COLUMN campaign_id,
            DROP COLUMN ad_group,
            DROP COLUMN ad_name,
            DROP COLUMN context_metadata
        ");
    }
}
