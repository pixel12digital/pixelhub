<?php
// Verifica se as colunas de tracking existem na tabela opportunities
require_once 'src/Core/Env.php';
\PixelHub\Core\Env::load();
require_once 'src/Core/DB.php';

$db = \PixelHub\Core\DB::getConnection();

echo "<h1>Verificação das colunas de tracking em opportunities</h1>";

try {
    $stmt = $db->query('DESCRIBE opportunities');
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Colunas com 'tracking' encontradas:</h2>";
    $trackingColumns = [];
    foreach ($columns as $col) {
        if (strpos($col, 'tracking') !== false) {
            $trackingColumns[] = $col;
            echo "<p style='color: green;'>✓ $col</p>";
        }
    }
    
    if (empty($trackingColumns)) {
        echo "<p style='color: red;'>✗ Nenhuma coluna de tracking encontrada</p>";
        echo "<p>Isso significa que a migração 20260220_add_tracking_to_opportunities_table não foi executada.</p>";
    }
    
    // Verifica também a estrutura completa da tabela
    echo "<h2>Estrutura completa da tabela opportunities:</h2>";
    $stmt = $db->query('DESCRIBE opportunities');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}

?>
