<?php

/**
 * Script para executar manualmente as migrations do tracking system
 * Executa as SQLs das migrations e registra como executadas
 */

// Carrega o ambiente manualmente
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/src/Core/Env.php';
require_once ROOT_PATH . '/src/Core/DB.php';

use PixelHub\Core\DB;

try {
    $db = DB::getConnection();
    
    echo "=== Executando migrations do tracking system ===\n\n";
    
    // 1. Criar tabela tracking_codes
    echo "1. Criando tabela tracking_codes...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS tracking_codes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL COMMENT 'Código de rastreamento (ex: SITE123)',
            source VARCHAR(50) NOT NULL COMMENT 'Fonte: site, instagram, facebook, whatsapp, google, email, indicacao, outro',
            description TEXT NULL COMMENT 'Descrição opcional do código',
            is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Se o código está ativo para detecção',
            created_by INT UNSIGNED NULL COMMENT 'Quem cadastrou',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            UNIQUE KEY uk_code (code),
            INDEX idx_source (source),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✅ Tabela tracking_codes criada\n\n";
    
    // 2. Adicionar campos de tracking em leads
    echo "2. Adicionando campos de tracking em leads...\n";
    
    // Verificar se colunas já existem
    $checkLeadColumns = $db->query("SHOW COLUMNS FROM leads LIKE 'tracking_code'")->rowCount();
    
    if ($checkLeadColumns === 0) {
        $db->exec("
            ALTER TABLE leads 
            ADD COLUMN tracking_code VARCHAR(50) NULL COMMENT 'Código de rastreamento detectado (FK tracking_codes)',
            ADD COLUMN tracking_metadata JSON NULL COMMENT 'Metadados do tracking (página, cta, campanha, etc.)',
            ADD COLUMN tracking_auto_detected BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Se tracking foi detectado automaticamente',
            ADD INDEX idx_tracking_code (tracking_code)
        ");
        echo "   ✅ Campos de tracking adicionados em leads\n";
    } else {
        echo "   ⚠️  Campos de tracking já existem em leads\n";
    }
    echo "\n";
    
    // 3. Adicionar campos de tracking em opportunities
    echo "3. Adicionando campos de tracking em opportunities...\n";
    
    $checkOppColumns = $db->query("SHOW COLUMNS FROM opportunities LIKE 'tracking_code'")->rowCount();
    
    if ($checkOppColumns === 0) {
        $db->exec("
            ALTER TABLE opportunities 
            ADD COLUMN tracking_code VARCHAR(50) NULL COMMENT 'Código de rastreamento detectado (FK tracking_codes)',
            ADD COLUMN tracking_metadata JSON NULL COMMENT 'Metadados do tracking (página, cta, campanha, etc.)',
            ADD COLUMN tracking_auto_detected BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Se tracking foi detectado automaticamente',
            ADD COLUMN origin VARCHAR(50) NOT NULL DEFAULT 'unknown' COMMENT 'Origem da oportunidade (canal ou unknown)',
            ADD INDEX idx_tracking_code (tracking_code),
            ADD INDEX idx_origin (origin)
        ");
        echo "   ✅ Campos de tracking adicionados em opportunities\n";
    } else {
        echo "   ⚠️  Campos de tracking já existem em opportunities\n";
    }
    echo "\n";
    
    // 4. Atualizar valores padrão
    echo "4. Atualizando valores padrão...\n";
    $db->exec("UPDATE leads SET source = 'unknown' WHERE source IS NULL OR source = ''");
    
    // Verificar se coluna origin existe antes de atualizar
    $originExists = $db->query("SHOW COLUMNS FROM opportunities LIKE 'origin'")->rowCount();
    if ($originExists > 0) {
        $db->exec("UPDATE opportunities SET origin = 'unknown' WHERE origin = '' OR origin IS NULL");
        echo "   ✅ Valores padrão atualizados\n";
    } else {
        echo "   ⚠️  Coluna origin não encontrada em opportunities\n";
    }
    echo "\n";
    
    // 5. Registrar migrations como executadas
    echo "5. Registrando migrations como executadas...\n";
    
    // Verificar estrutura da tabela migrations
    $migrationColumns = $db->query("SHOW COLUMNS FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('name', $migrationColumns)) {
        $db->exec("
            INSERT INTO migrations (name, executed_at) VALUES 
            ('20260220_create_tracking_codes_table', NOW()),
            ('20260220_add_tracking_fields_to_leads_and_opportunities', NOW())
            ON DUPLICATE KEY UPDATE executed_at = NOW()
        ");
    } else {
        // Tentar com 'migration' como nome da coluna
        $db->exec("
            INSERT INTO migrations (migration, executed_at) VALUES 
            ('20260220_create_tracking_codes_table', NOW()),
            ('20260220_add_tracking_fields_to_leads_and_opportunities', NOW())
            ON DUPLICATE KEY UPDATE executed_at = NOW()
        ");
    }
    echo "   ✅ Migrations registradas\n\n";
    
    // 6. Verificação final
    echo "6. Verificação final...\n";
    
    $trackingCodesCount = $db->query("SELECT COUNT(*) as count FROM tracking_codes")->fetch()['count'];
    $leadTrackingFields = $db->query("SHOW COLUMNS FROM leads LIKE 'tracking_code'")->rowCount();
    $oppTrackingFields = $db->query("SHOW COLUMNS FROM opportunities LIKE 'tracking_code'")->rowCount();
    $oppOriginField = $db->query("SHOW COLUMNS FROM opportunities LIKE 'origin'")->rowCount();
    
    echo "   📊 Tabela tracking_codes: {$trackingCodesCount} registros\n";
    echo "   📊 Campo tracking_code em leads: " . ($leadTrackingFields > 0 ? "✅ existe" : "❌ não existe") . "\n";
    echo "   📊 Campo tracking_code em opportunities: " . ($oppTrackingFields > 0 ? "✅ existe" : "❌ não existe") . "\n";
    echo "   📊 Campo origin em opportunities: " . ($oppOriginField > 0 ? "✅ existe" : "❌ não existe") . "\n\n";
    
    echo "🎉 Migration do tracking system concluída com sucesso!\n";
    
    // 7. Inserir alguns dados de exemplo
    echo "\n7. Inserindo dados de exemplo...\n";
    
    $existingCodes = $db->query("SELECT COUNT(*) as count FROM tracking_codes")->fetch()['count'];
    
    if ($existingCodes == 0) {
        $db->exec("
            INSERT INTO tracking_codes (code, source, description, is_active) VALUES
            ('SITE123', 'site', 'Landing page principal', 1),
            ('INST456', 'instagram', 'Perfil Instagram - Campanha Verão', 1),
            ('FACE789', 'facebook', 'Facebook Ads - Tráfego Pago', 1),
            ('GOOG012', 'google', 'Google Ads - Palavra-chave: sites', 1),
            ('WHATS345', 'whatsapp', 'Contato direto WhatsApp', 1),
            ('EMAIL678', 'email', 'Email Marketing - Newsletter', 1),
            ('INDIC901', 'indicacao', 'Programa de indicação', 1),
            ('OUTRO234', 'outro', 'Outras fontes', 1)
        ");
        echo "   ✅ 8 códigos de tracking de exemplo inseridos\n";
    } else {
        echo "   ⚠️  Já existem {$existingCodes} códigos de tracking\n";
    }
    
    echo "\n✅ Sistema de tracking pronto para uso!\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
