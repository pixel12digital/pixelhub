<?php
/**
 * Script direto para executar SQL da migration communication_media
 */

require_once __DIR__ . '/../public/index.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

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

try {
    echo "Executando migration...\n";
    $db->exec($sql);
    echo "✅ Migration executada com sucesso!\n";
    
    // Verifica se foi criada
    $stmt = $db->query("SHOW TABLES LIKE 'communication_media'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabela 'communication_media' criada e verificada!\n";
    } else {
        echo "⚠️  Tabela pode não ter sido criada. Verifique os logs.\n";
    }
} catch (\PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false || $e->getCode() == '42S01') {
        echo "ℹ️  Tabela já existe. Tudo certo!\n";
    } else {
        echo "❌ ERRO: " . $e->getMessage() . "\n";
        echo "Código: " . $e->getCode() . "\n";
        exit(1);
    }
}







