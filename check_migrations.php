<?php
// Verificação das migrations e campos da tabela
error_log("=== VERIFICAÇÃO MIGRATIONS E CAMPOS === " . date('Y-m-d H:i:s'));

echo "<h1>VERIFICAÇÃO MIGRATIONS E CAMPOS</h1>";

try {
    require_once __DIR__ . '/vendor/autoload.php';
    $db = \PixelHub\Core\DB::getConnection();
    
    echo "<h2>1. Estrutura Atual da Tabela tracking_codes</h2>";
    
    $stmt = $db->query("DESCRIBE tracking_codes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    $camposEsperados = [
        'id', 'code', 'source', 'description', 'is_active', 'created_by', 
        'created_at', 'updated_at', 'channel', 'origin_page', 'cta_position',
        'campaign_name', 'campaign_id', 'ad_group', 'ad_name', 'context_metadata'
    ];
    
    foreach ($columns as $col) {
        $campo = $col['Field'];
        $esperado = in_array($campo, $camposEsperados);
        
        echo "<tr style='background: " . ($esperado ? '#d4edda' : '#fff2f2') . "'>";
        echo "<td><strong>" . htmlspecialchars($campo) . "</strong></td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . ($col['Null'] === 'YES' ? 'SIM' : 'NÃO') . "</td>";
        echo "<td>" . ($col['Key'] ?: '-') . "</td>";
        echo "<td>" . ($col['Default'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verifica campos faltantes
    $camposExistentes = array_column($columns, 'Field');
    $camposFaltantes = array_diff($camposEsperados, $camposExistentes);
    
    if (!empty($camposFaltantes)) {
        echo "<h2 style='color: red;'>⚠️ CAMPOS FALTANTES:</h2>";
        echo "<ul>";
        foreach ($camposFaltantes as $campo) {
            echo "<li style='color: red;'><strong>" . htmlspecialchars($campo) . "</strong> - Campo não existe na tabela!</li>";
        }
        echo "</ul>";
        
        echo "<h2>Solução:</h2>";
        echo "<p>Execute a migration para adicionar os campos faltantes:</p>";
        echo "<pre>";
        echo "ALTER TABLE tracking_codes 
ADD COLUMN channel VARCHAR(50) NOT NULL DEFAULT 'other' COMMENT 'Canal específico',
ADD COLUMN origin_page VARCHAR(255) NULL COMMENT 'Página/URL de origem',
ADD COLUMN cta_position VARCHAR(100) NULL COMMENT 'Posição do CTA',
ADD COLUMN campaign_name VARCHAR(255) NULL COMMENT 'Nome da campanha',
ADD COLUMN campaign_id VARCHAR(100) NULL COMMENT 'ID da campanha',
ADD COLUMN ad_group VARCHAR(255) NULL COMMENT 'Grupo de anúncio',
ADD COLUMN ad_name VARCHAR(255) NULL COMMENT 'Nome do anúncio',
ADD COLUMN context_metadata JSON NULL COMMENT 'Metadados adicionais';";
        echo "</pre>";
    } else {
        echo "<h2 style='color: green;'>✅ Todos os campos esperados existem</h2>";
    }
    
    // Teste inserção com novos campos
    echo "<h2>2. Teste Inserção com Novos Campos</h2>";
    
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
        
        echo "<p style='color: green;'>✅ Inserção com novos campos OK</p>";
        
        // Remove teste
        $stmt = $db->prepare("DELETE FROM tracking_codes WHERE code = ?");
        $stmt->execute([$testCode]);
        echo "<p>✅ Limpeza teste OK</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro na inserção com novos campos:</p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        
        // Verifica qual campo está causando erro
        echo "<h3>Teste Inserção Campo por Campo:</h3>";
        
        $testCode = 'TEST_' . time();
        
        // Teste só com campos básicos
        try {
            $stmt = $db->prepare("INSERT INTO tracking_codes (code, source, is_active, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())");
            $stmt->execute([$testCode, 'other']);
            echo "<p>✅ Inserção básica OK</p>";
            
            // Remove
            $stmt = $db->prepare("DELETE FROM tracking_codes WHERE code = ?");
            $stmt->execute([$testCode]);
            
        } catch (Exception $e2) {
            echo "<p style='color: red;'>❌ Erro até na inserção básica: " . htmlspecialchars($e2->getMessage()) . "</p>";
        }
    }
    
    // Verifica se os campos novos estão sendo usados no código
    echo "<h2>3. Verificação de Uso dos Campos Novos</h2>";
    
    $serviceFile = __DIR__ . '/src/Services/TrackingCodesService.php';
    $controllerFile = __DIR__ . '/src/Controllers/TrackingCodesController.php';
    $viewFile = __DIR__ . '/views/settings/tracking_codes.php';
    
    $camposNovos = ['channel', 'origin_page', 'cta_position', 'campaign_name', 'campaign_id', 'ad_group', 'ad_name'];
    
    foreach ([$serviceFile, $controllerFile, $viewFile] as $file) {
        $nome = basename($file);
        echo "<h3>$nome</h3>";
        
        if (file_exists($file)) {
            $content = file_get_contents($file);
            
            foreach ($camposNovos as $campo) {
                if (strpos($content, $campo) !== false) {
                    echo "<p>✅ Campo '$campo' encontrado</p>";
                } else {
                    echo "<p style='color: orange;'>⚠️ Campo '$campo' não encontrado</p>";
                }
            }
        } else {
            echo "<p style='color: red;'>❌ Arquivo não existe</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro geral: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<h2>RESUMO</h2>";
echo "<p>Se campos estiverem faltando, execute a migration manualmente.</p>";

error_log("=== FIM VERIFICAÇÃO MIGRATIONS E CAMPOS === " . date('Y-m-d H:i:s'));
?>
