<?php
// Diagnóstico sistemático do erro 404
error_log("=== INÍCIO DIAGNÓSTICO TRACKING CODES === " . date('Y-m-d H:i:s'));

echo "<h1>DIAGNÓSTICO - TRACKING CODES</h1>";

// 1. Verifica se arquivo existe
$filePath = __FILE__;
echo "<p><strong>Arquivo:</strong> " . $filePath . "</p>";
echo "<p><strong>Existe:</strong> " . (file_exists($filePath) ? 'SIM' : 'NÃO') . "</p>";

// 2. Verifica se pode ler o arquivo
if (file_exists($filePath)) {
    $content = file_get_contents($filePath);
    echo "<p><strong>Tamanho:</strong> " . strlen($content) . " bytes</p>";
    echo "<p><strong>Primeiras 100 chars:</strong> " . htmlspecialchars(substr($content, 0, 100)) . "</p>";
}

// 3. Verifica se autoload funciona
echo "<h2>Teste Autoload</h2>";
try {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "<p>✅ Autoload carregado</p>";
} catch (Exception $e) {
    echo "<p>❌ Erro no autoload: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 4. Verifica se classes existem
echo "<h2>Teste Classes</h2>";
try {
    if (class_exists('PixelHub\Services\TrackingCodesService')) {
        echo "<p>✅ TrackingCodesService existe</p>";
    } else {
        echo "<p>❌ TrackingCodesService NÃO existe</p>";
    }
    
    if (class_exists('PixelHub\Controllers\TrackingCodesController')) {
        echo "<p>✅ TrackingCodesController existe</p>";
    } else {
        echo "<p>❌ TrackingCodesController NÃO existe</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Erro ao verificar classes: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 5. Verifica se DB funciona
echo "<h2>Teste Conexão DB</h2>";
try {
    $db = \PixelHub\Core\DB::getConnection();
    echo "<p>✅ Conexão DB OK</p>";
    
    // Verifica se tabela existe
    $stmt = $db->query("SHOW TABLES LIKE 'tracking_codes'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✅ Tabela tracking_codes existe</p>";
        
        // Verifica estrutura
        $stmt = $db->query("DESCRIBE tracking_codes");
        $columns = $stmt->fetchAll();
        echo "<p>✅ Estrutura OK - " . count($columns) . " colunas</p>";
        
        // Lista colunas
        echo "<ul>";
        foreach ($columns as $col) {
            echo "<li>" . htmlspecialchars($col['Field']) . " (" . htmlspecialchars($col['Type']) . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>❌ Tabela tracking_codes NÃO existe</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Erro na conexão DB: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 6. Teste service diretamente
echo "<h2>Teste TrackingCodesService</h2>";
try {
    $channels = \PixelHub\Services\TrackingCodesService::getChannels();
    echo "<p>✅ getChannels() - " . count($channels, COUNT_RECURSIVE) . " categorias</p>";
    
    $positions = \PixelHub\Services\TrackingCodesService::getCtaPositions();
    echo "<p>✅ getCtaPositions() - " . count($positions) . " posições</p>";
    
    $codes = \PixelHub\Services\TrackingCodesService::listAll();
    echo "<p>✅ listAll() - " . count($codes) . " códigos</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Erro no TrackingCodesService: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

// 7. Verifica se controller funciona
echo "<h2>Teste Controller</h2>";
try {
    $controller = new \PixelHub\Controllers\TrackingCodesController();
    echo "<p>✅ Controller instanciado</p>";
    
    // Testa método index
    ob_start();
    $controller->index();
    $output = ob_get_clean();
    echo "<p>✅ Método index() executado - " . strlen($output) . " chars</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Erro no Controller: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<h2>RESUMO</h2>";
echo "<p>Diagnóstico concluído. Verifique os resultados acima para identificar o problema.</p>";

error_log("=== FIM DIAGNÓSTICO TRACKING CODES === " . date('Y-m-d H:i:s'));
?>
