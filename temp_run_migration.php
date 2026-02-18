<?php
// Copia e executa a migration localmente

// Carrega autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\DB;

try {
    $db = DB::getConnection();
    
    echo "=== Adicionando campos de status em agenda_manual_items ===\n\n";
    
    // Adiciona campo status
    $sql = "ALTER TABLE agenda_manual_items 
            ADD COLUMN status ENUM('pending', 'completed', 'cancelled', 'failed') DEFAULT 'pending' 
            AFTER item_type";
    
    $db->exec($sql);
    echo "✓ Campo status adicionado com sucesso\n";
    
    // Adiciona campo completed_at
    $sql2 = "ALTER TABLE agenda_manual_items 
             ADD COLUMN completed_at DATETIME NULL 
             AFTER updated_at";
    
    $db->exec($sql2);
    echo "✓ Campo completed_at adicionado com sucesso\n";
    
    // Adiciona campo completed_by
    $sql3 = "ALTER TABLE agenda_manual_items 
             ADD COLUMN completed_by INT UNSIGNED NULL 
             AFTER completed_at";
    
    $db->exec($sql3);
    echo "✓ Campo completed_by adicionado com sucesso\n";
    
    // Atualiza follow-ups já enviados para completed
    $sql4 = "UPDATE agenda_manual_items ami
             SET status = 'completed', 
                 completed_at = NOW(),
                 completed_by = 1
             FROM scheduled_messages sm
             WHERE ami.id = sm.agenda_item_id 
             AND sm.status = 'sent'";
    
    $result = $db->exec($sql4);
    echo "✓ {$result} follow-ups atualizados para 'completed'\n";
    
    echo "\n=== Migration executada com sucesso! ===\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
