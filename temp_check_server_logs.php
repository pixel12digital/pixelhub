<?php
require_once 'vendor/autoload.php';
PixelHub\Core\Env::load();
$db = PixelHub\Core\DB::getConnection();

echo "=== Investigando por que links de screen_recordings param de funcionar ===\n\n";

// 1. Verificar se há registros antigos sem arquivos
echo "1. Verificando registros vs arquivos físicos:\n";
$stmt = $db->query('SELECT id, public_token, file_path, created_at FROM screen_recordings ORDER BY created_at DESC');
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRecords = count($records);
$missingFiles = 0;
$oldRecords = 0;

foreach ($records as $rec) {
    $relativePath = ltrim($rec['file_path'], '/');
    $filePath = null;
    
    if (strpos($relativePath, 'screen-recordings/') === 0) {
        $fileRelativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
        $filePath = __DIR__ . '/public/screen-recordings/' . $fileRelativePath;
    } elseif (strpos($relativePath, 'storage/tasks/') === 0) {
        $filePath = __DIR__ . '/' . $relativePath;
    }
    
    $exists = $filePath && file_exists($filePath) && is_file($filePath);
    
    if (!$exists) {
        $missingFiles++;
        echo "  - ID {$rec['id']}: Arquivo não encontrado ({$rec['file_path']})\n";
    }
    
    // Verificar registros antigos (mais de 30 dias)
    $created = new DateTime($rec['created_at']);
    $now = new DateTime();
    $daysDiff = $now->diff($created)->days;
    
    if ($daysDiff > 30) {
        $oldRecords++;
        echo "  - ID {$rec['id']}: Registro antigo ({$daysDiff} dias)\n";
    }
}

echo "\nResumo:\n";
echo "- Total de registros: $totalRecords\n";
echo "- Arquivos faltando: $missingFiles\n";
echo "- Registros antigos (>30 dias): $oldRecords\n\n";

// 2. Verificar se há algum processo de limpeza automático
echo "2. Verificando possíveis processos de limpeza:\n";

// Procura por tabelas que possam conter configurações de limpeza
try {
    $tables = $db->query("SHOW TABLES LIKE '%settings%'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "  - Tabela: $table\n";
        try {
            $stmt = $db->query("SELECT * FROM $table WHERE setting_key LIKE '%cleanup%' OR setting_key LIKE '%retention%' OR setting_key LIKE '%expire%' LIMIT 5");
            $settings = $stmt->fetchAll();
            if (!empty($settings)) {
                foreach ($settings as $setting) {
                    echo "    - {$setting['setting_key']}: {$setting['setting_value']}\n";
                }
            }
        } catch (Exception $e) {
            // Tabela pode ter estrutura diferente
        }
    }
} catch (Exception $e) {
    echo "  - Nenhuma tabela de settings encontrada\n";
}

// 3. Verificar se há logs de exclusão
echo "\n3. Verificando logs recentes do sistema:\n";

$logFile = __DIR__ . '/logs/pixelhub.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recentLines = array_slice($lines, -1000); // Últimas 1000 linhas
    
    $deleteCount = 0;
    $unlinkCount = 0;
    
    foreach ($recentLines as $line) {
        if (stripos($line, 'DELETE FROM screen_recordings') !== false) {
            $deleteCount++;
            if ($deleteCount <= 5) {
                echo "  - DELETE screen_recordings: " . trim($line) . "\n";
            }
        }
        if (stripos($line, 'unlink') !== false && stripos($line, 'screen-recordings') !== false) {
            $unlinkCount++;
            if ($unlinkCount <= 5) {
                echo "  - unlink screen-recordings: " . trim($line) . "\n";
            }
        }
    }
    
    echo "  - Total de DELETEs encontrados: $deleteCount\n";
    echo "  - Total de unlinks encontrados: $unlinkCount\n";
} else {
    echo "  - Arquivo de log não encontrado\n";
}

// 4. Verificar configurações do servidor que possam afetar
echo "\n4. Verificando configurações do servidor:\n";

// Verificar se há algum cron configurado para limpeza
$cronFiles = [
    '/etc/crontab',
    '/var/spool/cron/crontabs/*',
    '/home/pixel12digital/.crontab'
];

foreach ($cronFiles as $pattern) {
    if (strpos($pattern, '*') !== false) {
        // Skip wildcard patterns for now
        continue;
    }
    
    if (file_exists($pattern)) {
        echo "  - Arquivo cron: $pattern\n";
        $content = file_get_contents($pattern);
        if (strpos($content, 'screen') !== false) {
            echo "    - Contém referência a 'screen'\n";
        }
    }
}

// 5. Verificar se há algum processo manual ou script de manutenção
echo "\n5. Verificando scripts de manutenção:\n";
$maintenanceScripts = [
    'scripts/maintenance.php',
    'scripts/cleanup.php',
    'scripts/archive.php'
];

foreach ($maintenanceScripts as $script) {
    if (file_exists($script)) {
        echo "  - Script encontrado: $script\n";
        $content = file_get_contents($script);
        if (strpos($content, 'screen_recordings') !== false) {
            echo "    - Contém referência a screen_recordings\n";
        }
    }
}

echo "\n=== Conclusão ===\n";
echo "Se os arquivos estão desaparecendo após um período, as causas prováveis são:\n";
echo "1. Script de limpeza/maintenance automático (não encontrado no código)\n";
echo "2. Cron job no servidor (fora do projeto)\n";
echo "3. Limpeza manual do servidor/hosting\n";
echo "4. Política de retenção do hosting (arquivos antigos são removidos)\n";
echo "5. Backup/restore que não inclui os arquivos físicos\n\n";

echo "Recomendações:\n";
echo "- Verificar com o hosting se há política de limpeza automática\n";
echo "- Verificar crons no cPanel/WHM\n";
echo "- Implementar backup dos arquivos de gravação\n";
echo "- Adicionar logging quando arquivos são removidos\n";
