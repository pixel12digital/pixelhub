<?php
/**
 * Script para verificar se a migration de communication_media foi executada
 */

require_once __DIR__ . '/../public/index.php';

use PixelHub\Core\DB;

echo "=== Verificação da Migration: communication_media ===\n\n";

try {
    $db = DB::getConnection();
    
    // Verifica se a tabela existe
    $stmt = $db->query("SHOW TABLES LIKE 'communication_media'");
    if ($stmt->rowCount() === 0) {
        echo "❌ Tabela 'communication_media' NÃO existe!\n\n";
        echo "Execute a migration:\n";
        echo "  php database/run-migration-communication-media.php\n\n";
        echo "Ou execute o SQL manualmente:\n";
        echo "  CREATE TABLE communication_media (...)\n";
        exit(1);
    }
    
    echo "✅ Tabela 'communication_media' existe!\n\n";
    
    // Verifica estrutura
    $stmt = $db->query("DESCRIBE communication_media");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Estrutura da tabela:\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']})\n";
    }
    
    // Verifica registros
    $stmt = $db->query("SELECT COUNT(*) as count FROM communication_media");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\n✅ Total de registros: {$count['count']}\n";
    
    if ($count['count'] > 0) {
        // Mostra últimos registros
        $stmt = $db->query("
            SELECT id, event_id, media_type, mime_type, stored_path, created_at 
            FROM communication_media 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $medias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nÚltimos registros:\n";
        foreach ($medias as $media) {
            echo "  - {$media['media_type']} ({$media['mime_type']}): " . ($media['stored_path'] ?? 'sem caminho') . "\n";
        }
    }
    
    echo "\n✅ Migration verificada com sucesso!\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}










