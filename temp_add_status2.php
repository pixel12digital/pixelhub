<?php
// Conexão com o banco remoto
$pdo = new PDO('mysql:host=r225us.hmservers.net;port=3306;dbname=pixel12digital_pixelhub;charset=utf8mb4', 'pixel12digital_pixelhub', 'pixel@2024');

echo "=== Adicionando campo status em agenda_manual_items ===\n\n";

try {
    // Adiciona campo status
    $sql = "ALTER TABLE agenda_manual_items 
            ADD COLUMN status ENUM('pending', 'completed', 'cancelled', 'failed') DEFAULT 'pending' 
            AFTER item_type";
    
    $pdo->exec($sql);
    echo "✓ Campo status adicionado com sucesso\n";
    
    // Adiciona campo completed_at
    $sql2 = "ALTER TABLE agenda_manual_items 
             ADD COLUMN completed_at DATETIME NULL 
             AFTER updated_at";
    
    $pdo->exec($sql2);
    echo "✓ Campo completed_at adicionado com sucesso\n";
    
    // Adiciona campo completed_by
    $sql3 = "ALTER TABLE agenda_manual_items 
             ADD COLUMN completed_by INT UNSIGNED NULL 
             AFTER completed_at";
    
    $pdo->exec($sql3);
    echo "✓ Campo completed_by adicionado com sucesso\n";
    
    echo "\n=== Migration concluída com sucesso! ===\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
