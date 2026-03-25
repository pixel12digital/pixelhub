<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== ADICIONANDO COLUNAS DE VALIDAÇÃO ===\n";

try {
    // Verificar se as colunas já existem
    $stmt = $db->query("SHOW COLUMNS FROM sdr_dispatch_queue LIKE 'phone_validated'");
    $cols = $stmt->fetchAll();
    
    if (count($cols) === 0) {
        // Adicionar colunas
        $db->exec("
            ALTER TABLE sdr_dispatch_queue 
            ADD COLUMN phone_validated TINYINT(1) DEFAULT NULL 
                COMMENT 'NULL=não validado, 1=válido, 0=inválido'
        ");
        echo "✅ Coluna phone_validated adicionada\n";
        
        $db->exec("
            ALTER TABLE sdr_dispatch_queue 
            ADD COLUMN phone_validation_status VARCHAR(20) DEFAULT NULL 
                COMMENT 'valid/invalid/error'
        ");
        echo "✅ Coluna phone_validation_status adicionada\n";
        
        $db->exec("
            ALTER TABLE sdr_dispatch_queue 
            ADD COLUMN phone_validated_at DATETIME DEFAULT NULL
        ");
        echo "✅ Coluna phone_validated_at adicionada\n";
        
        echo "\n✅ Todas as colunas adicionadas com sucesso!\n";
    } else {
        echo "⚠️ Colunas já existem\n";
    }
    
    // Verificar estrutura final
    echo "\nEstrutura atual da tabela sdr_dispatch_queue:\n";
    $stmt = $db->query("SHOW COLUMNS FROM sdr_dispatch_queue WHERE Field LIKE '%phone_valid%'");
    $cols = $stmt->fetchAll();
    
    foreach ($cols as $col) {
        echo sprintf("- %s: %s %s %s\n", 
            $col['Field'], 
            $col['Type'], 
            $col['Null'], 
            $col['Default'] ?? 'NULL'
        );
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n=== FIM ===\n";
