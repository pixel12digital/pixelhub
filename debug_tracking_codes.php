<?php
// Debug para Tracking Codes
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/vendor/autoload.php';
    
    echo "<h2>Debug Tracking Codes</h2>";
    
    // Testa conexão
    $db = \PixelHub\Core\DB::getConnection();
    echo "✅ Conexão OK<br>";
    
    // Testa se tabela existe e estrutura
    $stmt = $db->query("DESCRIBE tracking_codes");
    $columns = $stmt->fetchAll();
    
    echo "<h3>Estrutura da tabela:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th></tr>";
    
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . ($col['Null'] === 'YES' ? 'Sim' : 'Não') . "</td>";
        echo "<td>" . ($col['Key'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Testa se service funciona
    echo "<h3>Teste TrackingCodesService:</h3>";
    
    $channels = \PixelHub\Services\TrackingCodesService::getChannels();
    echo "✅ getChannels() OK<br>";
    
    $positions = \PixelHub\Services\TrackingCodesService::getCtaPositions();
    echo "✅ getCtaPositions() OK<br>";
    
    // Lista códigos
    $codes = \PixelHub\Services\TrackingCodesService::listAll();
    echo "✅ listAll() OK - " . count($codes) . " códigos<br>";
    
    if (!empty($codes)) {
        echo "<h4>Primeiro código:</h4>";
        echo "<pre>" . print_r($codes[0], true) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<h2>Erro:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
