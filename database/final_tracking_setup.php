<?php

/**
 * Script final para implementação completa do tracking system
 * Executa todas as alterações necessárias no banco de dados
 */

// Conexão usando as credenciais do ambiente
$host = 'localhost';
$dbname = 'pixelhub_db';
$username = 'pixelhub';
$password = 'p@ssw0rd123!@#'; // Senha padrão do ambiente

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Implementação completa do Tracking System ===\n\n";
    
    // 1. Verificar estrutura atual
    echo "1. Verificando estrutura atual...\n";
    
    // Verificar tabela tracking_codes
    $stmt = $pdo->query("SHOW TABLES LIKE 'tracking_codes'");
    $trackingCodesExists = $stmt->rowCount() > 0;
    echo "   Tabela tracking_codes: " . ($trackingCodesExists ? "✅ existe" : "❌ não existe") . "\n";
    
    // Verificar campos em leads
    $stmt = $pdo->query("SHOW COLUMNS FROM leads LIKE 'tracking_code'");
    $leadTrackingExists = $stmt->rowCount() > 0;
    echo "   Campo tracking_code em leads: " . ($leadTrackingExists ? "✅ existe" : "❌ não existe") . "\n";
    
    // Verificar campos em opportunities
    $stmt = $pdo->query("SHOW COLUMNS FROM opportunities LIKE 'origin'");
    $oppOriginExists = $stmt->rowCount() > 0;
    echo "   Campo origin em opportunities: " . ($oppOriginExists ? "✅ existe" : "❌ não existe") . "\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM opportunities LIKE 'tracking_code'");
    $oppTrackingExists = $stmt->rowCount() > 0;
    echo "   Campo tracking_code em opportunities: " . ($oppTrackingExists ? "✅ existe" : "❌ não existe") . "\n";
    
    echo "\n";
    
    // 2. Criar tabela tracking_codes
    if (!$trackingCodesExists) {
        echo "2. Criando tabela tracking_codes...\n";
        $pdo->exec("
            CREATE TABLE tracking_codes (
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
        echo "   ✅ Tabela tracking_codes criada\n";
    } else {
        echo "2. Tabela tracking_codes já existe, pulando...\n";
    }
    echo "\n";
    
    // 3. Adicionar campos em leads
    if (!$leadTrackingExists) {
        echo "3. Adicionando campos de tracking em leads...\n";
        $pdo->exec("
            ALTER TABLE leads 
            ADD COLUMN tracking_code VARCHAR(50) NULL COMMENT 'Código de rastreamento detectado (FK tracking_codes)',
            ADD COLUMN tracking_metadata JSON NULL COMMENT 'Metadados do tracking (página, cta, campanha, etc.)',
            ADD COLUMN tracking_auto_detected BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Se tracking foi detectado automaticamente',
            ADD INDEX idx_tracking_code (tracking_code)
        ");
        echo "   ✅ Campos de tracking adicionados em leads\n";
    } else {
        echo "3. Campos de tracking já existem em leads, pulando...\n";
    }
    echo "\n";
    
    // 4. Adicionar campos em opportunities
    if (!$oppTrackingExists || !$oppOriginExists) {
        echo "4. Adicionando campos de tracking em opportunities...\n";
        
        $alterSql = "ALTER TABLE opportunities ";
        $alterParts = [];
        
        if (!$oppOriginExists) {
            $alterParts[] = "ADD COLUMN origin VARCHAR(50) NOT NULL DEFAULT 'unknown' COMMENT 'Origem da oportunidade (canal ou unknown)'";
            $alterParts[] = "ADD INDEX idx_origin (origin)";
        }
        
        if (!$oppTrackingExists) {
            $alterParts[] = "ADD COLUMN tracking_code VARCHAR(50) NULL COMMENT 'Código de rastreamento detectado (FK tracking_codes)'";
            $alterParts[] = "ADD COLUMN tracking_metadata JSON NULL COMMENT 'Metadados do tracking (página, cta, campanha, etc.)'";
            $alterParts[] = "ADD COLUMN tracking_auto_detected BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Se tracking foi detectado automaticamente'";
            $alterParts[] = "ADD INDEX idx_tracking_code (tracking_code)";
        }
        
        if (!empty($alterParts)) {
            $pdo->exec($alterSql . implode(', ', $alterParts));
            echo "   ✅ Campos de tracking adicionados em opportunities\n";
        }
    } else {
        echo "4. Campos de tracking já existem em opportunities, pulando...\n";
    }
    echo "\n";
    
    // 5. Atualizar valores padrão
    echo "5. Atualizando valores padrão...\n";
    $pdo->exec("UPDATE leads SET source = 'unknown' WHERE source IS NULL OR source = ''");
    $pdo->exec("UPDATE opportunities SET origin = 'unknown' WHERE origin = '' OR origin IS NULL");
    echo "   ✅ Valores padrão atualizados\n\n";
    
    // 6. Inserir dados de exemplo
    echo "6. Inserindo dados de exemplo...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tracking_codes");
    $count = $stmt->fetch()['count'];
    
    if ($count == 0) {
        $pdo->exec("
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
        echo "   ⚠️  Já existem {$count} códigos de tracking\n";
    }
    echo "\n";
    
    // 7. Registrar migrations
    echo "7. Registrando migrations...\n";
    
    // Verificar estrutura da tabela migrations
    $stmt = $pdo->query("DESCRIBE migrations");
    $columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    
    $mainColumn = in_array('name', $columns) ? 'name' : (in_array('migration', $columns) ? 'migration' : 'filename');
    
    $pdo->exec("
        INSERT INTO migrations ({$mainColumn}, executed_at) VALUES 
        ('20260220_create_tracking_codes_table', NOW()),
        ('20260220_add_tracking_fields_to_leads_and_opportunities', NOW())
        ON DUPLICATE KEY UPDATE executed_at = NOW()
    ");
    echo "   ✅ Migrations registradas usando coluna '{$mainColumn}'\n\n";
    
    // 8. Verificação final
    echo "8. Verificação final:\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tracking_codes");
    echo "   📊 Códigos de tracking: " . $stmt->fetch()['count'] . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM leads WHERE tracking_auto_detected = 1");
    echo "   📊 Leads com tracking auto-detectado: " . $stmt->fetch()['count'] . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM opportunities WHERE origin = 'unknown'");
    echo "   📊 Opportunities com origin='unknown': " . $stmt->fetch()['count'] . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM opportunities WHERE tracking_code IS NOT NULL");
    echo "   📊 Opportunities com tracking_code: " . $stmt->fetch()['count'] . "\n";
    
    echo "\n🎉 Sistema de tracking implementado com sucesso!\n";
    echo "✅ Funcionalidades disponíveis:\n";
    echo "   - Detecção automática de códigos no Inbox\n";
    echo "   - Filtro simplificado por origem em Opportunities\n";
    echo "   - Visualização detalhada de tracking\n";
    echo "   - Fallback 'unknown' para origens não identificadas\n";
    
} catch (PDOException $e) {
    echo "❌ Erro de banco: " . $e->getMessage() . "\n";
    echo "Verifique as credenciais do banco de dados\n";
} catch (Exception $e) {
    echo "❌ Erro geral: " . $e->getMessage() . "\n";
}
