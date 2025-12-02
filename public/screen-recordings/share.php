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
    // Log inicial
    error_log('[ScreenRecordings Share] Iniciando busca por token: ' . $token);
    error_log('[ScreenRecordings Share] BASE_PATH: ' . (defined('BASE_PATH') ? BASE_PATH : 'NÃO DEFINIDO'));
    error_log('[ScreenRecordings Share] BASE_URL: ' . (defined('BASE_URL') ? BASE_URL : 'NÃO DEFINIDO'));
    
    $db = DB::getConnection();
    error_log('[ScreenRecordings Share] Conexão com banco estabelecida');
    
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
    
    // Se for requisição de streaming (parâmetro stream=1), serve o arquivo diretamente
    if (isset($_GET['stream']) && $_GET['stream'] == '1') {
        $relativePath = ltrim($recording['file_path'], '/');
        $filePath = null;
        
        if (strpos($relativePath, 'storage/tasks/') === 0) {
            // Arquivo de tarefa: busca em storage/tasks/
            $filePath = __DIR__ . '/../../' . $relativePath;
        } elseif (strpos($relativePath, 'screen-recordings/') === 0) {
            // Arquivo da biblioteca: busca em public/screen-recordings/
            $fileRelativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
            $filePath = __DIR__ . '/' . $fileRelativePath;
        } else {
            // Tenta como caminho relativo a partir de public/screen-recordings/
            $filePath = __DIR__ . '/' . $relativePath;
        }
        
        if ($filePath && file_exists($filePath) && is_file($filePath)) {
            // Serve o arquivo diretamente para streaming
            $mimeType = $recording['mime_type'] ?? 'video/webm';
            $fileSize = filesize($filePath);
            
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . $fileSize);
            header('Accept-Ranges: bytes');
            header('Cache-Control: public, max-age=3600');
            
            // Suporte a Range requests para streaming
            if (isset($_SERVER['HTTP_RANGE'])) {
                $range = $_SERVER['HTTP_RANGE'];
                $range = str_replace('bytes=', '', $range);
                $range = explode('-', $range);
                $start = intval($range[0]);
                $end = $range[1] ? intval($range[1]) : $fileSize - 1;
                $length = $end - $start + 1;
                
                header('HTTP/1.1 206 Partial Content');
                header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
                header('Content-Length: ' . $length);
                
                $fp = fopen($filePath, 'rb');
                fseek($fp, $start);
                echo fread($fp, $length);
                fclose($fp);
            } else {
                readfile($filePath);
            }
            exit;
        } else {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            exit;
        }
    }
    
    // Verifica se o arquivo existe (para exibição da página)
    // O file_path no banco pode ser:
    // 1. screen-recordings/2025/11/28/xxx.webm (biblioteca) -> public/screen-recordings/2025/11/28/xxx.webm
    // 2. storage/tasks/1/xxx.webm (tarefa) -> storage/tasks/1/xxx.webm
    $relativePath = ltrim($recording['file_path'], '/');
    $filePath = null;
    
    if (strpos($relativePath, 'storage/tasks/') === 0) {
        // Arquivo de tarefa: busca em storage/tasks/
        $filePath = __DIR__ . '/../../' . $relativePath;
    } elseif (strpos($relativePath, 'screen-recordings/') === 0) {
        // Arquivo da biblioteca: busca em public/screen-recordings/
        $fileRelativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
        $filePath = __DIR__ . '/' . $fileRelativePath;
    } else {
        // Tenta como caminho relativo a partir de public/screen-recordings/
        $filePath = __DIR__ . '/' . $relativePath;
    }
    
    $fileExists = $filePath && file_exists($filePath) && is_file($filePath);
    
    // Log detalhado
    error_log('[ScreenRecordings Share] Verificando arquivo:');
    error_log('[ScreenRecordings Share]   file_path (banco): ' . $recording['file_path']);
    error_log('[ScreenRecordings Share]   fileRelativePath: ' . $fileRelativePath);
    error_log('[ScreenRecordings Share]   filePath absoluto: ' . $filePath);
    error_log('[ScreenRecordings Share]   arquivo existe: ' . ($fileExists ? 'SIM' : 'NÃO'));
    
    if (!$fileExists) {
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
    
    // Monta URL do vídeo para streaming
    // Para arquivos de tarefas (storage/tasks/), usa endpoint de download protegido
    // Para arquivos da biblioteca (screen-recordings/), usa URL pública direta
    $relativePath = ltrim($recording['file_path'], '/');
    $baseUrl = rtrim(BASE_URL, '/');
    
    if (strpos($relativePath, 'storage/tasks/') === 0) {
        // Arquivo de tarefa: serve diretamente via PHP para streaming
        // Usa o próprio share.php para servir o arquivo (já temos o token validado)
        $videoUrl = $baseUrl . '/screen-recordings/share?token=' . urlencode($token) . '&stream=1';
    } else {
        // Arquivo da biblioteca: URL pública direta
        // Garante que não há duplicação de 'screen-recordings/'
        if (substr($baseUrl, -strlen('/screen-recordings')) === '/screen-recordings') {
            $relativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
        }
        $videoUrl = $baseUrl . '/' . $relativePath;
    }
    
    // Debug: sempre loga para diagnóstico em produção
    error_log('[ScreenRecordings Share] Token: ' . $token);
    error_log('[ScreenRecordings Share] file_path do banco: ' . $recording['file_path']);
    error_log('[ScreenRecordings Share] relativePath: ' . $relativePath);
    error_log('[ScreenRecordings Share] BASE_URL: ' . BASE_URL);
    error_log('[ScreenRecordings Share] baseUrl (trimmed): ' . $baseUrl);
    error_log('[ScreenRecordings Share] videoUrl final: ' . $videoUrl);
    error_log('[ScreenRecordings Share] filePath físico: ' . $filePath);
    error_log('[ScreenRecordings Share] arquivo existe: ' . (file_exists($filePath) ? 'SIM' : 'NÃO'));
    
    // Valida se as variáveis necessárias estão definidas
    if (empty($videoUrl)) {
        throw new \RuntimeException('URL do vídeo não pôde ser construída. BASE_URL: ' . BASE_URL);
    }
    
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
    
} catch (\Exception $e) {
    $errorMsg = $e->getMessage();
    $errorTrace = $e->getTraceAsString();
    error_log('[ScreenRecordings Share] ERRO: ' . $errorMsg);
    error_log('[ScreenRecordings Share] Trace: ' . $errorTrace);
    error_log('[ScreenRecordings Share] File: ' . $e->getFile() . ' Line: ' . $e->getLine());
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
        <?php if ($fileExists): ?>
            <video controls preload="metadata">
                <source src="<?= htmlspecialchars($videoUrl) ?>" type="<?= htmlspecialchars($recording['mime_type'] ?? 'video/webm') ?>">
                Seu navegador não suporta a reprodução de vídeo.
            </video>
            <p style="margin-top: 10px; font-size: 12px; color: #666;">
                <a href="<?= htmlspecialchars($videoUrl) ?>" target="_blank" style="color: #023A8D; text-decoration: underline;">
                    Abrir vídeo diretamente
                </a>
            </p>
        <?php else: ?>
            <div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 4px; padding: 20px; margin-top: 20px; text-align: left;">
                <h3 style="color: #856404; margin: 0 0 10px;">⚠️ Arquivo não encontrado no servidor</h3>
                <p style="color: #856404; margin: 0 0 10px;">O arquivo de vídeo não está disponível no servidor. Isso pode acontecer se:</p>
                <ul style="color: #856404; margin: 10px 0; padding-left: 20px;">
                    <li>O arquivo não foi enviado corretamente durante o upload</li>
                    <li>O arquivo foi movido ou deletado</li>
                    <li>O servidor está em um ambiente diferente do que fez o upload</li>
                </ul>
                <details style="margin-top: 15px;">
                    <summary style="color: #856404; cursor: pointer; font-weight: 600;">Detalhes técnicos</summary>
                    <div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-family: monospace; font-size: 11px;">
                        <strong>file_path (banco):</strong> <?= htmlspecialchars($recording['file_path']) ?><br>
                        <strong>Caminho físico esperado:</strong> <?= htmlspecialchars($filePath) ?><br>
                        <strong>URL do vídeo:</strong> <a href="<?= htmlspecialchars($videoUrl) ?>" target="_blank" style="color: #023A8D;"><?= htmlspecialchars($videoUrl) ?></a><br>
                        <strong>BASE_URL:</strong> <?= htmlspecialchars(BASE_URL) ?>
                    </div>
                </details>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

