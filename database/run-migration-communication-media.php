<?php
/**
 * Script para executar a migration de communication_media no banco remoto
 */

require_once __DIR__ . '/../public/index.php';

use PixelHub\Core\DB;

echo "=== Executando Migration: communication_media ===\n\n";

try {
    $db = DB::getConnection();
    
    // Verifica se a tabela já existe
    $stmt = $db->query("SHOW TABLES LIKE 'communication_media'");
    if ($stmt->rowCount() > 0) {
        echo "⚠️  Tabela 'communication_media' já existe!\n";
        echo "   Deseja recriar? (Atenção: isso apagará todos os dados existentes)\n\n";
        echo "   Para recriar, execute primeiro: DROP TABLE IF EXISTS communication_media;\n\n";
        echo "   Prosseguindo com verificação da estrutura...\n\n";
        
        // Verifica estrutura da tabela
        $stmt = $db->query("DESCRIBE communication_media");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Colunas existentes:\n";
        foreach ($columns as $column) {
            echo "     - {$column['Field']} ({$column['Type']})\n";
        }
        echo "\n";
        
        exit(0);
    }
    
    echo "Criando tabela 'communication_media'...\n";
    
    // Executa a migration
    $sql = "
        CREATE TABLE IF NOT EXISTS communication_media (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id VARCHAR(36) NOT NULL UNIQUE COMMENT 'UUID do evento (FK para communication_events)',
            media_id VARCHAR(255) NOT NULL COMMENT 'ID da mídia no WhatsApp Gateway',
            media_type VARCHAR(50) NOT NULL COMMENT 'Tipo de mídia (audio, image, video, document, sticker)',
            mime_type VARCHAR(100) NULL COMMENT 'MIME type do arquivo',
            stored_path VARCHAR(500) NULL COMMENT 'Caminho relativo do arquivo armazenado',
            file_name VARCHAR(255) NULL COMMENT 'Nome do arquivo',
            file_size INT UNSIGNED NULL COMMENT 'Tamanho do arquivo em bytes',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_event_id (event_id),
            INDEX idx_media_id (media_id),
            INDEX idx_media_type (media_type),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (event_id) REFERENCES communication_events(event_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($sql);
    
    echo "✓ Tabela 'communication_media' criada com sucesso!\n\n";
    
    // Verifica estrutura criada
    $stmt = $db->query("DESCRIBE communication_media");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Estrutura da tabela:\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']})";
        if ($column['Null'] === 'NO') {
            echo " NOT NULL";
        }
        if ($column['Key'] === 'PRI') {
            echo " PRIMARY KEY";
        }
        if ($column['Extra']) {
            echo " {$column['Extra']}";
        }
        echo "\n";
    }
    
    // Verifica índices
    $stmt = $db->query("SHOW INDEX FROM communication_media");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nÍndices criados:\n";
    $seenIndexes = [];
    foreach ($indexes as $index) {
        if (!in_array($index['Key_name'], $seenIndexes)) {
            echo "  - {$index['Key_name']} ({$index['Column_name']})\n";
            $seenIndexes[] = $index['Key_name'];
        }
    }
    
    echo "\n✓ Migration executada com sucesso!\n";
    echo "\nPróximos passos:\n";
    echo "1. Teste enviando uma nova mídia pelo WhatsApp\n";
    echo "2. Verifique os logs para ver se o processamento está funcionando\n";
    echo "3. Se houver mensagens antigas com mídia, execute o script de reprocessamento\n";
    
} catch (\PDOException $e) {
    echo "✗ ERRO ao executar migration:\n";
    echo "   " . $e->getMessage() . "\n";
    echo "   Código SQL: " . $e->getCode() . "\n\n";
    
    if ($e->getCode() == '42S21') {
        echo "   A tabela ou algum índice já existe. Ignore este erro se a tabela foi criada.\n";
    } elseif ($e->getCode() == '42000') {
        echo "   Erro de sintaxe SQL. Verifique o SQL da migration.\n";
    } elseif (strpos($e->getMessage(), 'communication_events') !== false) {
        echo "   ERRO: A tabela 'communication_events' não existe!\n";
        echo "   Execute primeiro a migration: 20250201_create_communication_events_table.php\n";
    }
    
    exit(1);
} catch (\Exception $e) {
    echo "✗ ERRO inesperado:\n";
    echo "   " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

echo "\n=== Migration Concluída ===\n";









