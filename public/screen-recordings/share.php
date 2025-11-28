<?php
/**
 * Página pública para visualização de gravações de tela
 * Não exige login - usa token público
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../../src/';
        
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Core\Storage;

// Carrega variáveis de ambiente
try {
    Env::load();
} catch (\Exception $e) {
    error_log('[ScreenRecordings Share] Erro ao carregar Env: ' . $e->getMessage());
}

// Define BASE_PATH se não estiver definido
if (!defined('BASE_PATH')) {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '') {
        define('BASE_PATH', '');
    } else {
        define('BASE_PATH', $scriptDir);
    }
}

// Define BASE_URL se não estiver definido
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', $protocol . $domainName . BASE_PATH);
}

// Lê token da query string
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Gravação não encontrada - Pixel Hub</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                background: #f5f5f5;
            }
            .container {
                text-align: center;
                padding: 40px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            h1 { color: #d32f2f; margin: 0 0 16px; }
            p { color: #666; margin: 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Gravação não encontrada</h1>
            <p>O link fornecido é inválido ou a gravação não existe mais.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

try {
    $db = DB::getConnection();
    
    // Busca gravação por token público
    $stmt = $db->prepare("
        SELECT 
            id, file_path, file_name, original_name, mime_type, 
            size_bytes, duration_seconds, has_audio, public_token, created_at
        FROM screen_recordings
        WHERE public_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $recording = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log para debug
    error_log('[ScreenRecordings Share] Buscando token: ' . $token);
    error_log('[ScreenRecordings Share] Registro encontrado: ' . ($recording ? 'SIM' : 'NÃO'));
    
    if (!$recording) {
        // Log adicional: verifica se a tabela existe e quantos registros tem
        try {
            $countStmt = $db->query("SELECT COUNT(*) as total FROM screen_recordings");
            $count = $countStmt->fetch(PDO::FETCH_ASSOC);
            error_log('[ScreenRecordings Share] Total de gravações no banco: ' . ($count['total'] ?? 0));
            
            // Lista últimos 3 tokens para debug
            $lastStmt = $db->query("SELECT public_token, file_path FROM screen_recordings ORDER BY id DESC LIMIT 3");
            $last = $lastStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($last as $r) {
                error_log('[ScreenRecordings Share] Token exemplo: ' . $r['public_token'] . ' -> ' . $r['file_path']);
            }
        } catch (\Exception $e) {
            error_log('[ScreenRecordings Share] Erro ao verificar tabela: ' . $e->getMessage());
        }
        http_response_code(404);
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Gravação não encontrada - Pixel Hub</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    margin: 0;
                    background: #f5f5f5;
                }
                .container {
                    text-align: center;
                    padding: 40px;
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                h1 { color: #d32f2f; margin: 0 0 16px; }
                p { color: #666; margin: 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Gravação não encontrada</h1>
                <p>O link fornecido é inválido ou a gravação não existe mais.</p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    // Verifica se o arquivo existe
    // O file_path no banco é: screen-recordings/2025/11/28/xxx.webm
    // O arquivo físico está em: public/screen-recordings/2025/11/28/xxx.webm
    // __DIR__ é public/screen-recordings/, então precisamos remover 'screen-recordings/' do início
    $relativePath = ltrim($recording['file_path'], '/');
    // Remove 'screen-recordings/' do início se existir
    $fileRelativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
    $filePath = __DIR__ . '/' . $fileRelativePath;
    if (!file_exists($filePath) || !is_file($filePath)) {
        http_response_code(404);
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Arquivo não encontrado - Pixel Hub</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    margin: 0;
                    background: #f5f5f5;
                }
                .container {
                    text-align: center;
                    padding: 40px;
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                h1 { color: #d32f2f; margin: 0 0 16px; }
                p { color: #666; margin: 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Arquivo não encontrado</h1>
                <p>O arquivo de vídeo não está mais disponível no servidor.</p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    // Monta URL do vídeo
    // O file_path no banco é: screen-recordings/2025/11/28/xxx.webm
    // O arquivo físico está em: public/screen-recordings/2025/11/28/xxx.webm
    // A URL pública deve ser: BASE_URL/screen-recordings/2025/11/28/xxx.webm
    $relativePath = ltrim($recording['file_path'], '/');
    // Remove 'screen-recordings/' do início se existir (já está no caminho)
    $videoRelativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
    
    // Constrói URL completa
    // Se BASE_URL já termina com /, não adiciona outro
    $baseUrl = rtrim(BASE_URL, '/');
    $videoUrl = $baseUrl . '/screen-recordings/' . $videoRelativePath;
    
    // Debug: sempre loga para diagnóstico em produção
    error_log('[ScreenRecordings Share] Token: ' . $token);
    error_log('[ScreenRecordings Share] file_path do banco: ' . $recording['file_path']);
    error_log('[ScreenRecordings Share] videoRelativePath: ' . $videoRelativePath);
    error_log('[ScreenRecordings Share] BASE_URL: ' . BASE_URL);
    error_log('[ScreenRecordings Share] videoUrl final: ' . $videoUrl);
    error_log('[ScreenRecordings Share] filePath físico: ' . $filePath);
    error_log('[ScreenRecordings Share] arquivo existe: ' . (file_exists($filePath) ? 'SIM' : 'NÃO'));
    
    // Formata duração
    $duration = $recording['duration_seconds'] ?? 0;
    $durationFormatted = $duration > 0 
        ? sprintf('%02d:%02d', floor($duration / 60), $duration % 60)
        : null;
    
    // Formata data
    $createdAt = $recording['created_at'] ?? null;
    $dateFormatted = $createdAt 
        ? date('d/m/Y H:i', strtotime($createdAt))
        : null;
    
} catch (Exception $e) {
    error_log('[ScreenRecordings Share] Erro: ' . $e->getMessage());
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Erro - Pixel Hub</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                background: #f5f5f5;
            }
            .container {
                text-align: center;
                padding: 40px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            h1 { color: #d32f2f; margin: 0 0 16px; }
            p { color: #666; margin: 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Erro ao carregar gravação</h1>
            <p>Ocorreu um erro ao tentar carregar a gravação. Tente novamente mais tarde.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Gravação de Tela - Pixel Hub</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .container {
            max-width: 1200px;
            width: 100%;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            color: #023A8D;
            margin: 0 0 20px;
            font-size: 24px;
        }
        .info {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .info span {
            margin-right: 20px;
        }
        video {
            max-width: 100%;
            width: 100%;
            max-height: 600px;
            background: #000;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gravação de Tela</h1>
        <div class="info">
            <?php if ($durationFormatted): ?>
                <span>Duração: <?= htmlspecialchars($durationFormatted) ?></span>
            <?php endif; ?>
            <?php if ($dateFormatted): ?>
                <span>Data: <?= htmlspecialchars($dateFormatted) ?></span>
            <?php endif; ?>
            <?php if ($recording['has_audio']): ?>
                <span>Com áudio</span>
            <?php endif; ?>
        </div>
        <video controls>
            <source src="<?= htmlspecialchars($videoUrl) ?>" type="<?= htmlspecialchars($recording['mime_type'] ?? 'video/webm') ?>">
            Seu navegador não suporta a reprodução de vídeo.
        </video>
    </div>
</body>
</html>

