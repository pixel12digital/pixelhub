<?php
// Script para executar a migração de tracking no servidor
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Executando Migração de Tracking - Opportunities</h1>";

// Carrega ambiente
require_once 'src/Core/Env.php';
\PixelHub\Core\Env::load();
require_once 'src/Core/DB.php';

$db = \PixelHub\Core\DB::getConnection();

try {
    echo "<h2>Verificando se as colunas já existem</h2>";
    
    // Verifica se tracking_code já existe
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM information_schema.columns 
                          WHERE table_schema = DATABASE() 
                          AND table_name = 'opportunities' 
                          AND column_name = 'tracking_code'");
    $stmt->execute();
    $count = $stmt->fetch()['count'];
    
    if ($count > 0) {
        echo "<p style='color: orange;'>⚠ Coluna tracking_code já existe</p>";
        
        // Verifica as outras colunas
        $columns = ['tracking_source', 'tracking_auto_detected', 'tracking_metadata'];
        foreach ($columns as $col) {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM information_schema.columns 
                                  WHERE table_schema = DATABASE() 
                                  AND table_name = 'opportunities' 
                                  AND column_name = ?");
            $stmt->execute([$col]);
            $count = $stmt->fetch()['count'];
            
            if ($count > 0) {
                echo "<p style='color: orange;'>⚠ Coluna $col já existe</p>";
            } else {
                echo "<p style='color: red;'>✗ Coluna $col não existe</p>";
            }
        }
    } else {
        echo "<p style='color: green;'>✓ Colunas não existem, executando migração...</p>";
        
        // Executa a migração
        echo "<h2>Executando ALTER TABLE</h2>";
        
        $sql = "
            ALTER TABLE opportunities 
            ADD COLUMN tracking_code VARCHAR(100) NULL COMMENT 'Código de rastreamento extraído da mensagem (ex: SITE123)',
            ADD COLUMN tracking_source VARCHAR(50) NULL COMMENT 'Fonte do código: site, instagram, whatsapp, indicacao, outro',
            ADD COLUMN tracking_auto_detected BOOLEAN NULL DEFAULT FALSE COMMENT 'Se o código foi detectado automaticamente',
            ADD COLUMN tracking_metadata JSON NULL COMMENT 'Metadados do tracking (data/hora detecção, mensagem original, etc)',
            ADD INDEX idx_tracking_code (tracking_code),
            ADD INDEX idx_tracking_source (tracking_source)
        ";
        
        $db->exec($sql);
        
        echo "<p style='color: green;'>✓ Migração executada com sucesso!</p>";
    }
    
    // Verificação final
    echo "<h2>Verificação final</h2>";
    $stmt = $db->query('DESCRIBE opportunities');
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $trackingColumns = [];
    foreach ($columns as $col) {
        if (strpos($col, 'tracking') !== false) {
            $trackingColumns[] = $col;
            echo "<p style='color: green;'>✓ $col</p>";
        }
    }
    
    if (count($trackingColumns) >= 4) {
        echo "<h2 style='color: green;'>✅ Sucesso! Todas as colunas de tracking foram criadas</h2>";
        echo "<p>A página /opportunities deve funcionar agora.</p>";
    } else {
        echo "<h2 style='color: red;'>❌ Problema: Nem todas as colunas foram criadas</h2>";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Erro na migração</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>Instruções para o servidor</h2>";
echo "<p>Execute este script no servidor:</p>";
echo "<pre><code>cd ~/hub.pixel12digital.com.br<br>php fix_tracking_migration.php</code></pre>";
echo "<p>Depois teste a página: https://hub.pixel12digital.com.br/opportunities</p>";

?>
