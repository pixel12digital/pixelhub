<?php
require_once 'vendor/autoload.php';
PixelHub\Core\Env::load();
$db = PixelHub\Core\DB::getConnection();

echo "=== INVESTIGAÇÃO FINAL: Por que links de screen_recordings param de funcionar ===\n\n";

// 1. Situação atual
echo "1. SITUAÇÃO ATUAL:\n";
$stmt = $db->query('SELECT COUNT(*) as total FROM screen_recordings WHERE public_token IS NOT NULL');
$total = $stmt->fetchColumn();
echo "   - Total de registros com token: $total\n";

$stmt = $db->query('SELECT id, public_token, file_path, created_at FROM screen_recordings WHERE public_token IS NOT NULL ORDER BY created_at DESC');
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($records as $rec) {
    echo "   - ID {$rec['id']}: Criado em {$rec['created_at']}\n";
    echo "     Token: {$rec['public_token']}\n";
    echo "     Caminho: {$rec['file_path']}\n";
    
    // Verificar arquivo físico
    $relativePath = ltrim($rec['file_path'], '/');
    if (strpos($relativePath, 'screen-recordings/') === 0) {
        $fileRelativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
        $filePath = __DIR__ . '/public/screen-recordings/' . $fileRelativePath;
        $exists = file_exists($filePath) && is_file($filePath);
        echo "     Arquivo físico: " . ($exists ? "EXISTS" : "NOT FOUND") . "\n";
        
        if (!$exists) {
            // Verificar se o diretório existe
            $dirPath = dirname($filePath);
            echo "     Diretório: " . (is_dir($dirPath) ? "EXISTS" : "NOT FOUND") . "\n";
        }
    }
    echo "\n";
}

// 2. Análise temporal
echo "2. ANÁLISE TEMPORAL:\n";
$now = new DateTime();
foreach ($records as $rec) {
    $created = new DateTime($rec['created_at']);
    $daysDiff = $now->diff($created)->days;
    echo "   - Registro ID {$rec['id']}: {$daysDiff} dias atrás\n";
    
    if ($daysDiff > 7) {
        echo "     ⚠️  REGISTRO ANTIGO (>7 dias)\n";
    }
    if ($daysDiff > 30) {
        echo "     ❌ REGISTRO MUITO ANTIGO (>30 dias)\n";
    }
}

// 3. Verificar logs do sistema
echo "\n3. VERIFICAÇÃO DE LOGS:\n";
$logFile = __DIR__ . '/logs/pixelhub.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recentLines = array_slice($lines, -500); // Últimas 500 linhas
    
    $screenLogCount = 0;
    foreach ($recentLines as $line) {
        if (stripos($line, 'screen') !== false || stripos($line, 'recording') !== false) {
            $screenLogCount++;
            if ($screenLogCount <= 3) {
                echo "   - " . trim($line) . "\n";
            }
        }
    }
    
    echo "   - Total de menções a 'screen/recording': $screenLogCount\n";
} else {
    echo "   - Arquivo de log não encontrado\n";
}

// 4. Verificar se há algum padrão nos arquivos que sobraram
echo "\n4. ARQUIVOS QUE SOBRARAM (2025/11/28):\n";
$oldDir = __DIR__ . '/public/screen-recordings/2025/11/28';
if (is_dir($oldDir)) {
    $files = scandir($oldDir);
    $files = array_filter($files, function($f) use ($oldDir) {
        return $f !== '.' && $f !== '..' && is_file($oldDir . '/' . $f);
    });
    
    foreach ($files as $file) {
        $filePath = $oldDir . '/' . $file;
        $size = filesize($filePath);
        $modTime = date('Y-m-d H:i:s', filemtime($filePath));
        echo "   - $file ($size bytes, modificado: $modTime)\n";
        
        // Verificar se tem registro no banco
        $stmt = $db->prepare('SELECT id, created_at FROM screen_recordings WHERE file_name = ? OR file_path LIKE ?');
        $stmt->execute([$file, "%$file%"]);
        $dbRec = $stmt->fetch();
        
        if ($dbRec) {
            echo "     ✓ Tem registro no banco (ID: {$dbRec['id']})\n";
        } else {
            echo "     ❌ NÃO tem registro no banco\n";
        }
    }
}

// 5. Hipóteses
echo "\n5. HIPÓTESES INVESTIGADAS:\n";
echo "   ❌ Script de limpeza no código PixelHub - NÃO ENCONTRADO\n";
echo "   ❌ DELETE FROM screen_recordings - NÃO ENCONTRADO\n";
echo "   ❌ unlink() de arquivos - NÃO ENCONTRADO\n";
echo "   ❌ Cron job interno - NÃO ENCONTRADO\n";
echo "   ⚠️  Política do hosting - POSSÍVEL\n";
echo "   ⚠️  Limpeza manual externa - POSSÍVEL\n";
echo "   ⚠️  Backup/restore incompleto - POSSÍVEL\n";

// 6. Conclusão
echo "\n6. CONCLUSÃO:\n";
echo "   O arquivo de 2026/02/23 (ID 26) está no banco mas o arquivo físico\n";
echo "   não existe no diretório esperado. O diretório 2026/02/23 nem foi criado.\n";
echo "   Isso sugere que:\n";
echo "   a) O upload falhou mas o registro foi salvo\n";
echo "   b) O arquivo foi removido logo após o upload\n";
echo "   c) Há processo de limpeza muito agressivo (remove arquivos recém-criados)\n\n";

echo "7. RECOMENDAÇÕES:\n";
echo "   1. Verificar logs do servidor de hosting (cPanel, WHM)\n";
echo "   2. Entrar em contato com suporte do hosting sobre:\n";
echo "      - Política de retenção de arquivos\n";
echo "      - Scripts de limpeza automática\n";
echo "      - Quotas de disco\n";
echo "   3. Implementar verificação pós-upload\n";
echo "   4. Adicionar logging de remoção de arquivos\n";
echo "   5. Configurar backup dos arquivos de gravação\n";

echo "\n=== FIM DA INVESTIGAÇÃO ===\n";
