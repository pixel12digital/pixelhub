<?php
// Conserta a migration - atualiza follow-ups já enviados

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
    
    echo "=== Atualizando follow-ups já enviados ===\n\n";
    
    // Atualiza follow-ups já enviados para completed
    $sql = "UPDATE agenda_manual_items ami
            SET status = 'completed', 
                completed_at = NOW(),
                completed_by = 1
            WHERE ami.id IN (
                SELECT sm.agenda_item_id 
                FROM scheduled_messages sm 
                WHERE sm.status = 'sent' 
                AND sm.agenda_item_id IS NOT NULL
            )";
    
    $result = $db->exec($sql);
    echo "✓ {$result} follow-ups atualizados para 'completed'\n";
    
    // Verifica estrutura final
    echo "\n=== Verificando estrutura final ===\n";
    $stmt = $db->query("DESCRIBE agenda_manual_items");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($cols as $col) {
        if (in_array($col['Field'], ['status', 'completed_at', 'completed_by'])) {
            echo "✓ {$col['Field']} - {$col['Type']}\n";
        }
    }
    
    // Verifica item ID 2
    echo "\n=== Verificando item ID 2 ===\n";
    $stmt2 = $db->prepare('SELECT status, completed_at, completed_by FROM agenda_manual_items WHERE id = 2');
    $stmt2->execute();
    $item = $stmt2->fetch();
    
    if ($item) {
        echo "Status: {$item['status']}\n";
        echo "Completed at: " . ($item['completed_at'] ?? 'NULL') . "\n";
        echo "Completed by: " . ($item['completed_by'] ?? 'NULL') . "\n";
    }
    
    echo "\n=== Verificação concluída! ===\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
