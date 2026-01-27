<?php
$path = __DIR__ . '/../storage/whatsapp-media/tenant-25/2026/01/27/ca24d557de027f5deb9ce972891b58d9.ogg';
echo "Arquivo: $path\n";
echo "Existe: " . (file_exists($path) ? 'SIM' : 'NAO') . "\n";
if (file_exists($path)) {
    echo "Tamanho: " . filesize($path) . " bytes\n";
}

// Lista arquivos no diretório
$dir = __DIR__ . '/../storage/whatsapp-media/tenant-25/2026/01/27/';
echo "\nArquivos no diretório:\n";
if (is_dir($dir)) {
    $files = scandir($dir);
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..') {
            echo "  - $f (" . filesize($dir . $f) . " bytes)\n";
        }
    }
} else {
    echo "Diretório não existe!\n";
}
