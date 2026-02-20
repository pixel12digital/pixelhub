<?php
// Executar migration manual para adicionar campos faltantes
error_log("=== EXECUTANDO MIGRATION MANUAL === " . date('Y-m-d H:i:s'));

try {
    require_once __DIR__ . '/vendor/autoload.php';
    $db = \PixelHub\Core\DB::getConnection();
    
    echo "<h1>EXECUTANDO MIGRATION MANUAL</h1>";
    
    // Verifica campos existentes
    $stmt = $db->query("DESCRIBE tracking_codes");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Campos atuais:</h2>";
    echo "<ul>";
    foreach ($columns as $col) {
        echo "<li>" . htmlspecialchars($col) . "</li>";
    }
    echo "</ul>";
    
    // Campos que precisam ser adicionados
    $camposParaAdicionar = [
        'channel' => "ADD COLUMN channel VARCHAR(50) NOT NULL DEFAULT 'other' COMMENT 'Canal específico: google_ads, google_organic, meta_ads, etc'",
        'origin_page' => "ADD COLUMN origin_page VARCHAR(255) NULL COMMENT 'Página/URL de origem'",
        'cta_position' => "ADD COLUMN cta_position VARCHAR(100) NULL COMMENT 'Posição do CTA: header, hero, footer, popup, etc'",
        'campaign_name' => "ADD COLUMN campaign_name VARCHAR(255) NULL COMMENT 'Nome da campanha (para Ads)'",
        'campaign_id' => "ADD COLUMN campaign_id VARCHAR(100) NULL COMMENT 'ID da campanha'",
        'ad_group' => "ADD COLUMN ad_group VARCHAR(255) NULL COMMENT 'Grupo de anúncio'",
        'ad_name' => "ADD COLUMN ad_name VARCHAR(255) NULL COMMENT 'Nome do anúncio'",
        'context_metadata' => "ADD COLUMN context_metadata JSON NULL COMMENT 'Metadados adicionais do contexto'"
    ];
    
    echo "<h2>Adicionando campos faltantes...</h2>";
    
    foreach ($camposParaAdicionar as $campo => $sql) {
        if (!in_array($campo, $columns)) {
            try {
                $db->exec("ALTER TABLE tracking_codes $sql");
                echo "<p style='color: green;'>✅ Campo '$campo' adicionado com sucesso</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Erro ao adicionar campo '$campo': " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            echo "<p style='color: blue;'>ℹ️ Campo '$campo' já existe</p>";
        }
    }
    
    // Atualiza registros existentes
    echo "<h2>Atualizando registros existentes...</h2>";
    
    try {
        $stmt = $db->exec("
            UPDATE tracking_codes 
            SET channel = CASE 
                WHEN source = 'google' THEN 'google_organic'
                WHEN source = 'instagram' THEN 'instagram_organic'
                WHEN source = 'facebook' THEN 'facebook_organic'
                WHEN source = 'whatsapp' THEN 'whatsapp_direct'
                ELSE 'other'
            END
            WHERE channel = 'other' OR channel IS NULL
        ");
        echo "<p style='color: green;'>✅ Registros existentes atualizados</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro ao atualizar registros: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Cria índices
    echo "<h2>Criando índices...</h2>";
    
    $indices = [
        'idx_channel' => 'CREATE INDEX idx_channel ON tracking_codes(channel)',
        'idx_origin_page' => 'CREATE INDEX idx_origin_page ON tracking_codes(origin_page)',
        'idx_campaign_name' => 'CREATE INDEX idx_campaign_name ON tracking_codes(campaign_name)'
    ];
    
    foreach ($indices as $nome => $sql) {
        try {
            $db->exec($sql);
            echo "<p style='color: green;'>✅ Índice '$nome' criado</p>";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "<p style='color: blue;'>ℹ️ Índice '$nome' já existe</p>";
            } else {
                echo "<p style='color: red;'>❌ Erro ao criar índice '$nome': " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
    
    // Verificação final
    echo "<h2>Verificação final...</h2>";
    
    $stmt = $db->query("DESCRIBE tracking_codes");
    $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Status</th></tr>";
    
    foreach ($finalColumns as $col) {
        $status = in_array($col['Field'], array_keys($camposParaAdicionar)) ? '🆕 NOVO' : '📋 EXISTENTE';
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Teste inserção
    echo "<h2>Teste de inserção...</h2>";
    
    try {
        $testCode = 'TEST_' . time();
        $stmt = $db->prepare("
            INSERT INTO tracking_codes 
            (code, channel, source, origin_page, cta_position, campaign_name, 
             campaign_id, ad_group, ad_name, description, is_active, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        
        $stmt->execute([
            $testCode,
            'google_ads',
            'google',
            '/test',
            'header',
            'Test Campaign',
            '123456789',
            'Test Group',
            'Test Ad',
            'Test Description'
        ]);
        
        echo "<p style='color: green;'>✅ Teste de inserção com novos campos OK</p>";
        
        // Remove teste
        $stmt = $db->prepare("DELETE FROM tracking_codes WHERE code = ?");
        $stmt->execute([$testCode]);
        echo "<p>✅ Limpeza teste OK</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro no teste de inserção: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<h2>✅ MIGRATION CONCLUÍDA!</h2>";
    echo "<p>Tente acessar novamente: <a href='/settings/tracking-codes'>/settings/tracking-codes</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro geral: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

error_log("=== FIM MIGRATION MANUAL === " . date('Y-m-d H:i:s'));
?>
