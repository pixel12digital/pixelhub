<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== CRIANDO TABELA wa_pnlid_cache ===\n\n";

$sql = "CREATE TABLE IF NOT EXISTS wa_pnlid_cache (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider VARCHAR(64) NOT NULL,
  session_id VARCHAR(128) NOT NULL,
  pnlid VARCHAR(64) NOT NULL,
  phone_e164 VARCHAR(32) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_provider_session_pnlid (provider, session_id, pnlid),
  KEY idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $pdo->exec($sql);
    echo "✅ Tabela wa_pnlid_cache criada com sucesso!\n\n";
    
    // Verificar se foi criada
    $stmt = $pdo->query("SHOW TABLES LIKE 'wa_pnlid_cache'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabela confirmada no banco.\n";
    } else {
        echo "⚠️  Tabela pode não ter sido criada. Verifique manualmente.\n";
    }
} catch (\Exception $e) {
    echo "❌ Erro ao criar tabela: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

