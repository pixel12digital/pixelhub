<?php
/**
 * Testa URL gerada para um stored_path de mídia.
 * Uso: php database/check-media-url.php [--path=whatsapp-media/tenant-2/2026/02/05/arquivo.ogg]
 */
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($c) {
        if (strncmp('PixelHub\\', $c, 9) !== 0) return;
        $f = __DIR__ . '/../src/' . str_replace('\\', '/', substr($c, 9)) . '.php';
        if (file_exists($f)) require $f;
    });
}
\PixelHub\Core\Env::load();

$opt = getopt('', ['path:']);
$path = $opt['path'] ?? 'whatsapp-media/tenant-2/2026/02/05/490717ae1abdf356db0660ed78de8d83.ogg';

$url = \PixelHub\Services\WhatsAppMediaService::getMediaUrl($path);
echo "stored_path: {$path}\n";
echo "URL gerada:  {$url}\n";
