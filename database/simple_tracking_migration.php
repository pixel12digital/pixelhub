<?php

/**
 * Script simples para executar as migrations do tracking system
 */

// Conexão direta com o banco
$host = 'localhost';
$dbname = 'pixelhub_db';
$username = 'pixelhub';
$password = 'SuaSenhaAqui'; // Substitua com a senha real

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Executando migrations do tracking system ===\n\n";
    
    // 1. Verificar estrutura da tabela migrations
    echo "1. Verificando estrutura da tabela migrations...\n";
    $stmt = $pdo->query("DESCRIBE migrations");
    $columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    echo "   Colunas encontradas: " . implode(', ', $columns) . "\n\n";
    
    // 2. Criar tabela tracking_codes (se não existir)
    echo "2. Criando tabela tracking_codes...\n";
    $pdo->exec("
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
    
    // 3. Adicionar coluna origin em opportunities (se não existir)
    echo "3. Adicionando coluna origin em opportunities...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM opportunities LIKE 'origin'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("
            ALTER TABLE opportunities 
            ADD COLUMN origin VARCHAR(50) NOT NULL DEFAULT 'unknown' COMMENT 'Origem da oportunidade (canal ou unknown)',
            ADD INDEX idx_origin (origin)
        ");
        echo "   ✅ Coluna origin adicionada em opportunities\n";
    } else {
        echo "   ⚠️  Coluna origin já existe em opportunities\n";
    }
    echo "\n";
    
    // 4. Atualizar valores padrão
    echo "4. Atualizando valores padrão...\n";
    $pdo->exec("UPDATE leads SET source = 'unknown' WHERE source IS NULL OR source = ''");
    $pdo->exec("UPDATE opportunities SET origin = 'unknown' WHERE origin = '' OR origin IS NULL");
    echo "   ✅ Valores padrão atualizados\n\n";
    
    // 5. Registrar migrations (adaptando para estrutura real)
    echo "5. Registrando migrations...\n";
    
    // Determinar nome da coluna principal
    $mainColumn = in_array('name', $columns) ? 'name' : (in_array('migration', $columns) ? 'migration' : 'filename');
    
    $pdo->exec("
        INSERT INTO migrations ({$mainColumn}, executed_at) VALUES 
        ('20260220_create_tracking_codes_table', NOW()),
        ('20260220_add_tracking_fields_to_leads_and_opportunities', NOW())
        ON DUPLICATE KEY UPDATE executed_at = NOW()
    ");
    echo "   ✅ Migrations registradas usando coluna '{$mainColumn}'\n\n";
    
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
    
    echo "\n🎉 Sistema de tracking implementado com sucesso!\n";
    
    // 7. Verificação final
    echo "\n7. Verificação final:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tracking_codes");
    echo "   📊 Códigos de tracking: " . $stmt->fetch()['count'] . "\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM opportunities LIKE 'origin'");
    echo "   📊 Coluna origin em opportunities: " . ($stmt->rowCount() > 0 ? "✅" : "❌") . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM opportunities WHERE origin = 'unknown'");
    echo "   📊 Opportunities com origin='unknown': " . $stmt->fetch()['count'] . "\n";
    
} catch (PDOException $e) {
    echo "❌ Erro de banco: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Erro geral: " . $e->getMessage() . "\n";
}
