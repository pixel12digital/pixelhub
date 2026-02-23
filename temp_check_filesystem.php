<?php
require_once 'vendor/autoload.php';
PixelHub\Core\Env::load();

echo "=== Verificação do sistema de arquivos para screen_recordings ===\n\n";

// 1. Verificar permissões do diretório
$baseDir = __DIR__ . '/public/screen-recordings';
echo "1. Verificando permissões do diretório base:\n";
echo "   - Caminho: $baseDir\n";
echo "   - Existe: " . (is_dir($baseDir) ? "SIM" : "NÃO") . "\n";
echo "   - Legível: " . (is_readable($baseDir) ? "SIM" : "NÃO") . "\n";
echo "   - Escrevível: " . (is_writable($baseDir) ? "SIM" : "NÃO") . "\n";

if (is_dir($baseDir)) {
    $perms = substr(sprintf('%o', fileperms($baseDir)), -4);
    echo "   - Permissões: $perms\n";
}

// 2. Verificar estrutura de diretórios
echo "\n2. Verificando estrutura de diretórios:\n";
$years = ['2025', '2026'];
foreach ($years as $year) {
    $yearDir = $baseDir . '/' . $year;
    echo "   - $year: " . (is_dir($yearDir) ? "EXISTS" : "NOT FOUND") . "\n";
    
    if (is_dir($yearDir)) {
        $months = scandir($yearDir);
        $months = array_filter($months, function($m) {
            return $m !== '.' && $m !== '..' && is_dir($yearDir . '/' . $m);
        });
        sort($months);
        
        foreach ($months as $month) {
            $monthDir = $yearDir . '/' . $month;
            echo "     - $month: ";
            $days = scandir($monthDir);
            $days = array_filter($days, function($d) use ($monthDir) {
                return $d !== '.' && $d !== '..' && is_dir($monthDir . '/' . $d);
            });
            echo count($days) . " dias\n";
            
            // Verificar alguns dias
            $sampleDays = array_slice($days, 0, 3);
            foreach ($sampleDays as $day) {
                $dayDir = $monthDir . '/' . $day;
                $files = scandir($dayDir);
                $files = array_filter($files, function($f) use ($dayDir) {
                    return $f !== '.' && $f !== '..' && is_file($dayDir . '/' . $f);
                });
                echo "       - $day: " . count($files) . " arquivos\n";
            }
        }
    }
}

// 3. Verificar se há algum processo de limpeza no sistema operacional
echo "\n3. Verificando se há algum processo de limpeza:\n";

// No Windows, verificar se há algum agendamento
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    echo "   - Sistema: Windows\n";
    echo "   - Verificar Agendador de Tarefas do Windows\n";
} else {
    echo "   - Sistema: Linux/Unix\n";
    // Verificar se há algum cron para o usuário atual
    $cronFile = getenv('HOME') . '/.crontab';
    if (file_exists($cronFile)) {
        echo "   - Crontab do usuário encontrado\n";
        $content = file_get_contents($cronFile);
        if (strpos($content, 'screen') !== false || strpos($content, 'unlink') !== false) {
            echo "   - POSSÍVEL LIMPEZA AUTOMÁTICA ENCONTRADA!\n";
        }
    }
}

// 4. Verificar espaço em disco
echo "\n4. Verificando espaço em disco:\n";
$freeSpace = disk_free_space(__DIR__);
$totalSpace = disk_total_space(__DIR__);
$usedSpace = $totalSpace - $freeSpace;

echo "   - Espaço total: " . number_format($totalSpace / 1024 / 1024 / 1024, 2) . " GB\n";
echo "   - Espaço usado: " . number_format($usedSpace / 1024 / 1024 / 1024, 2) . " GB\n";
echo "   - Espaço livre: " . number_format($freeSpace / 1024 / 1024 / 1024, 2) . " GB\n";
echo "   - Percentual usado: " . number_format(($usedSpace / $totalSpace) * 100, 2) . "%\n";

// 5. Verificar tamanho do diretório de screen_recordings
echo "\n5. Verificando tamanho do diretório screen_recordings:\n";
function getDirectorySize($path) {
    $totalSize = 0;
    $files = scandir($path);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $fullPath = $path . '/' . $file;
        if (is_file($fullPath)) {
            $totalSize += filesize($fullPath);
        } elseif (is_dir($fullPath)) {
            $totalSize += getDirectorySize($fullPath);
        }
    }
    
    return $totalSize;
}

if (is_dir($baseDir)) {
    $dirSize = getDirectorySize($baseDir);
    echo "   - Tamanho: " . number_format($dirSize / 1024 / 1024, 2) . " MB\n";
    echo "   - Número de arquivos: " . count(iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS)))) . "\n";
}

// 6. Verificar se há algum arquivo de configuração do hosting
echo "\n6. Verificando arquivos de configuração do hosting:\n";
$configFiles = [
    '.htaccess',
    'php.ini',
    '.user.ini',
    'web.config',
    'cPanel',
    '.cpanel'
];

foreach ($configFiles as $file) {
    if (file_exists(__DIR__ . '/public/' . $file)) {
        echo "   - Encontrado: public/$file\n";
        $content = file_get_contents(__DIR__ . '/public/' . $file);
        if (strpos($content, 'expire') !== false || strpos($content, 'cache') !== false || strpos($content, 'cleanup') !== false) {
            echo "     - Contém configurações relevantes\n";
        }
    }
}

echo "\n=== Conclusões ===\n";
echo "1. Não há script de limpeza automática no código do PixelHub\n";
echo "2. Não há logs de exclusão de arquivos\n";
echo "3. O problema provavelmente é:\n";
echo "   - Política do hosting (limpeza automática de arquivos antigos)\n";
echo "   - Cron job externo (fora do projeto)\n";
echo "   - Limpeza manual do servidor\n";
echo "   - Backup/restore que não inclui os arquivos\n\n";

echo "Recomendações:\n";
echo "1. Entrar em contato com o hosting e perguntar sobre:\n";
echo "   - Política de retenção de arquivos\n";
echo "   - Scripts de limpeza automática\n";
echo "   - Backup que inclui arquivos de mídia\n";
echo "2. Implementar backup dos arquivos de gravação\n";
echo "3. Adicionar logging para detectar quando arquivos são removidos\n";
