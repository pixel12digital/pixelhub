<?php

/**
 * Migration: Reestrutura tracking_codes para incluir contexto completo
 */
class RestructureTrackingCodesTable
{
    public function up(PDO $db): void
    {
        // Adiciona novos campos à tabela tracking_codes
        $db->exec("
            ALTER TABLE tracking_codes 
            ADD COLUMN channel VARCHAR(50) NOT NULL DEFAULT 'other' COMMENT 'Canal específico: google_ads, google_organic, meta_ads, etc',
            ADD COLUMN origin_page VARCHAR(255) NULL COMMENT 'Página/URL de origem',
            ADD COLUMN cta_position VARCHAR(100) NULL COMMENT 'Posição do CTA: header, hero, footer, popup, etc',
            ADD COLUMN campaign_name VARCHAR(255) NULL COMMENT 'Nome da campanha (para Ads)',
            ADD COLUMN campaign_id VARCHAR(100) NULL COMMENT 'ID da campanha',
            ADD COLUMN ad_group VARCHAR(255) NULL COMMENT 'Grupo de anúncio',
            ADD COLUMN ad_name VARCHAR(255) NULL COMMENT 'Nome do anúncio',
            ADD COLUMN context_metadata JSON NULL COMMENT 'Metadados adicionais do contexto'
        ");

        // Atualiza registros existentes
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

        // Cria índices
        $db->exec("
            CREATE INDEX idx_channel ON tracking_codes(channel);
            CREATE INDEX idx_origin_page ON tracking_codes(origin_page);
            CREATE INDEX idx_campaign_name ON tracking_codes(campaign_name);
        ");

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
