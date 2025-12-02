<?php
/**
 * Página pública para visualização de gravações de tela
 * Não exige login - usa token público
 */

// Log apenas em caso de erro

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
    
    // Busca gravação por token público (inclui task_id para verificar se é anexo de tarefa)
    $stmt = $db->prepare("
        SELECT 
            id, task_id, file_path, file_name, original_name, mime_type, 
            size_bytes, duration_seconds, has_audio, public_token, created_at
        FROM screen_recordings
        WHERE public_token = ?
        LIMIT 1
    ");
    // Log para debug
    $stmt->execute([$token]);
    $recording = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recording) {
        error_log('[ScreenRecordings Share] ERRO: Gravação não encontrada para token: ' . substr($token, 0, 8) . '...');
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
        $fileExists = false;
        
        // PRIORIDADE 1: Se task_id não é NULL, verifica primeiro em storage/tasks/
        if (!empty($recording['task_id'])) {
            $taskId = (int)$recording['task_id'];
            $fileName = $recording['file_name'] ?? $recording['original_name'];
            
            if (!empty($fileName)) {
                $taskFilePath = __DIR__ . '/../../storage/tasks/' . $taskId . '/' . $fileName;
                $taskFilePathNormalized = realpath($taskFilePath);
                
                if ($taskFilePathNormalized && file_exists($taskFilePathNormalized) && is_file($taskFilePathNormalized)) {
                    $filePath = $taskFilePathNormalized;
                    $fileExists = true;
                }
            }
        }
        
        // PRIORIDADE 2: Se não encontrou e file_path indica storage/tasks/
        if (!$fileExists && strpos($relativePath, 'storage/tasks/') === 0) {
            // Arquivo de tarefa: busca em storage/tasks/ (raiz do projeto)
            $filePath = __DIR__ . '/../../' . $relativePath;
            $normalizedPath = realpath($filePath);
            if ($normalizedPath) {
                $filePath = $normalizedPath;
            }
        } elseif (!$fileExists && strpos($relativePath, 'screen-recordings/') === 0) {
            // Arquivo da biblioteca: busca em public/screen-recordings/
            $fileRelativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
            $filePath = __DIR__ . '/' . $fileRelativePath;
            
            // Se não encontrou com file_path, tenta com file_name
            if (!file_exists($filePath) && !empty($recording['file_name'])) {
                $pathDir = dirname($fileRelativePath);
                $filePathAlt = __DIR__ . '/' . $pathDir . '/' . $recording['file_name'];
                if (file_exists($filePathAlt) && is_file($filePathAlt)) {
                    $filePath = $filePathAlt;
                    $fileExists = true;
                }
            }
            
            if ($filePath && file_exists($filePath) && is_file($filePath)) {
                $fileExists = true;
            }
        } elseif (!$fileExists) {
            // Tenta como caminho relativo a partir de public/screen-recordings/
            $filePath = __DIR__ . '/' . $relativePath;
            if (file_exists($filePath) && is_file($filePath)) {
                $fileExists = true;
            }
        }
        
        // PRIORIDADE 3: Se ainda não encontrou e task_id não é NULL, tenta storage/tasks/ com original_name
        if (!$fileExists && !empty($recording['task_id']) && !empty($recording['original_name'])) {
            $taskId = (int)$recording['task_id'];
            $originalName = $recording['original_name'];
            $taskFilePath = __DIR__ . '/../../storage/tasks/' . $taskId . '/' . $originalName;
            $taskFilePathNormalized = realpath($taskFilePath);
            
            if ($taskFilePathNormalized && file_exists($taskFilePathNormalized) && is_file($taskFilePathNormalized)) {
                $filePath = $taskFilePathNormalized;
                $fileExists = true;
            }
        }
        
        // Verifica se o arquivo existe antes de servir
        if (!$filePath || !$fileExists) {
            error_log('[ScreenRecordings Share Stream] ERRO: Arquivo não encontrado para token: ' . substr($token, 0, 8) . '...');
            if ($filePath) {
                error_log('[ScreenRecordings Share Stream] Caminho tentado: ' . $filePath);
            }
            http_response_code(404);
            echo 'Arquivo não encontrado';
            exit;
        }
        
        // Valida novamente antes de servir
        if (!file_exists($filePath) || !is_file($filePath)) {
            error_log('[ScreenRecordings Share Stream] ERRO: Arquivo não existe ou não é um arquivo válido: ' . $filePath);
            http_response_code(404);
            echo 'Arquivo não encontrado';
            exit;
        }
        
        // Tenta normalizar o caminho
        $normalizedPath = realpath($filePath);
        if ($normalizedPath) {
            $filePath = $normalizedPath;
        }
        
        if (file_exists($filePath) && is_file($filePath)) {
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
                
                $fp = @fopen($filePath, 'rb');
                if ($fp) {
                    fseek($fp, $start);
                    echo fread($fp, $length);
                    fclose($fp);
                } else {
                    error_log('[ScreenRecordings Share Stream] Erro ao abrir arquivo: ' . $filePath);
                    http_response_code(500);
                    echo 'Erro ao ler arquivo';
                }
            } else {
                @readfile($filePath);
            }
            exit;
        } else {
            // Log detalhado para debug
            error_log('[ScreenRecordings Share Stream] Arquivo não encontrado!');
            error_log('[ScreenRecordings Share Stream] relativePath: ' . $relativePath);
            error_log('[ScreenRecordings Share Stream] filePath: ' . $filePath);
            error_log('[ScreenRecordings Share Stream] __DIR__: ' . __DIR__);
            error_log('[ScreenRecordings Share Stream] file_exists: ' . (file_exists($filePath) ? 'SIM' : 'NÃO'));
            if ($filePath) {
                error_log('[ScreenRecordings Share Stream] is_file: ' . (is_file($filePath) ? 'SIM' : 'NÃO'));
                error_log('[ScreenRecordings Share Stream] is_dir: ' . (is_dir($filePath) ? 'SIM' : 'NÃO'));
                // Tenta verificar diretório pai
                $parentDir = dirname($filePath);
                error_log('[ScreenRecordings Share Stream] parentDir existe: ' . (is_dir($parentDir) ? 'SIM' : 'NÃO'));
                if (is_dir($parentDir)) {
                    $files = scandir($parentDir);
                    error_log('[ScreenRecordings Share Stream] Arquivos no diretório: ' . implode(', ', $files));
                }
            }
            
            http_response_code(404);
            echo 'Arquivo não encontrado';
            exit;
        }
    }
    
    // Verifica se o arquivo existe (para exibição da página)
    // O file_path no banco pode ser:
    // 1. screen-recordings/2025/11/28/xxx.webm (biblioteca) -> public/screen-recordings/2025/11/28/xxx.webm
    // 2. storage/tasks/1/xxx.webm (tarefa) -> storage/tasks/1/xxx.webm
    // 3. Se task_id não é NULL mas file_path está errado, tenta storage/tasks/{task_id}/{file_name}
    $relativePath = ltrim($recording['file_path'], '/');
    $filePath = null;
    $fileExists = false;
    $fileRelativePath = ''; // Inicializa para evitar undefined variable
    
    // PRIORIDADE 1: Se task_id não é NULL, verifica primeiro em storage/tasks/
    if (!empty($recording['task_id'])) {
        $taskId = (int)$recording['task_id'];
        $fileName = $recording['file_name'] ?? $recording['original_name'];
        
        if (!empty($fileName)) {
            $taskFilePath = __DIR__ . '/../../storage/tasks/' . $taskId . '/' . $fileName;
            $taskFilePathNormalized = realpath($taskFilePath);
            
            if ($taskFilePathNormalized && file_exists($taskFilePathNormalized) && is_file($taskFilePathNormalized)) {
                $filePath = $taskFilePathNormalized;
                $fileExists = true;
                $fileRelativePath = 'storage/tasks/' . $taskId . '/' . $fileName;
            }
        }
    }
    
    // PRIORIDADE 2: Se não encontrou e file_path indica storage/tasks/
    if (!$fileExists && strpos($relativePath, 'storage/tasks/') === 0) {
        $filePath = __DIR__ . '/../../' . $relativePath;
        $normalizedPath = realpath($filePath);
        if ($normalizedPath) {
            $filePath = $normalizedPath;
        }
        $fileExists = file_exists($filePath) && is_file($filePath);
    } elseif (!$fileExists && strpos($relativePath, 'screen-recordings/') === 0) {
        // Arquivo da biblioteca: busca em public/screen-recordings/
        $fileRelativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
        $filePath = __DIR__ . '/' . $fileRelativePath;
        $fileExists = file_exists($filePath) && is_file($filePath);
        
        // Se não encontrou com file_path, tenta com file_name
        if (!$fileExists && !empty($recording['file_name'])) {
            $pathDir = dirname($fileRelativePath);
            $filePathAlt = __DIR__ . '/' . $pathDir . '/' . $recording['file_name'];
            if (file_exists($filePathAlt) && is_file($filePathAlt)) {
                $filePath = $filePathAlt;
                $fileExists = true;
            }
        }
    } elseif (!$fileExists) {
        // Tenta como caminho relativo a partir de public/screen-recordings/
        $filePath = __DIR__ . '/' . $relativePath;
        $fileExists = file_exists($filePath) && is_file($filePath);
    }
    
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
    
    // Constrói BASE_URL corretamente (sem duplicar /screen-recordings)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $protocol . $domainName; // URL base sem caminho
    
    // Verifica se o arquivo está em storage/tasks/ (via task_id ou filePath físico)
    $isTaskFile = false;
    if (!empty($recording['task_id'])) {
        $isTaskFile = true;
    } elseif ($filePath && strpos($filePath, '/storage/tasks/') !== false) {
        $isTaskFile = true;
    } elseif (strpos($relativePath, 'storage/tasks/') === 0) {
        $isTaskFile = true;
    }
    
    if ($isTaskFile) {
        // Arquivo de tarefa: serve diretamente via PHP para streaming
        // Usa o próprio share.php para servir o arquivo (já temos o token validado)
        $videoUrl = $baseUrl . '/screen-recordings/share?token=' . urlencode($token) . '&stream=1';
    } else {
        // Arquivo da biblioteca: URL pública direta
        $videoUrl = $baseUrl . '/' . $relativePath;
    }
    
    // Variável para debug na página (se necessário)
    $debugInfo = [
        'token' => $token,
        'file_path_banco' => $recording['file_path'],
        'relativePath' => $relativePath,
        'filePath_fisico' => $filePath ?? 'NULL',
        'fileExists' => ($filePath && file_exists($filePath)) ? 'SIM' : 'NÃO',
        'BASE_URL' => BASE_URL,
        'videoUrl' => $videoUrl,
        '__DIR__' => __DIR__
    ];
    
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
        <?php if (!empty($videoUrl)): ?>
            <video controls preload="metadata" style="max-width: 100%; border-radius: 6px; outline: none; background: #000;">
                <source src="<?= htmlspecialchars($videoUrl) ?>" type="<?= htmlspecialchars($recording['mime_type'] ?? 'video/webm') ?>">
                Seu navegador não suporta a reprodução de vídeo.
            </video>
            <p style="margin-top: 10px; font-size: 12px; color: #666;">
                <a href="<?= htmlspecialchars($videoUrl) ?>" target="_blank" style="color: #023A8D; text-decoration: underline;">
                    Abrir vídeo diretamente
                </a>
            </p>
            <?php if (!$fileExists): ?>
                <p style="margin-top: 10px; padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; color: #856404; font-size: 12px;">
                    ⚠️ O arquivo pode não estar disponível no servidor, mas você pode tentar reproduzir o vídeo acima.
                </p>
            <?php endif; ?>
            
            <!-- DEBUG INFO (adicionar ?debug=1 na URL para ver) -->
            <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                <details style="margin-top: 20px; padding: 15px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; text-align: left;">
                    <summary style="cursor: pointer; font-weight: 600; color: #023A8D;">🔍 Informações de Debug</summary>
                    <div style="margin-top: 10px; font-family: monospace; font-size: 11px; line-height: 1.6;">
                        <strong>Token:</strong> <?= htmlspecialchars($debugInfo['token']) ?><br>
                        <strong>file_path (banco):</strong> <?= htmlspecialchars($debugInfo['file_path_banco']) ?><br>
                        <strong>relativePath:</strong> <?= htmlspecialchars($debugInfo['relativePath']) ?><br>
                        <strong>filePath (físico):</strong> <?= htmlspecialchars($debugInfo['filePath_fisico']) ?><br>
                        <strong>fileExists:</strong> <?= htmlspecialchars($debugInfo['fileExists']) ?><br>
                        <strong>BASE_URL:</strong> <?= htmlspecialchars($debugInfo['BASE_URL']) ?><br>
                        <strong>videoUrl:</strong> <a href="<?= htmlspecialchars($debugInfo['videoUrl']) ?>" target="_blank"><?= htmlspecialchars($debugInfo['videoUrl']) ?></a><br>
                        <strong>__DIR__:</strong> <?= htmlspecialchars($debugInfo['__DIR__']) ?><br>
                        <?php if ($filePath): ?>
                            <strong>is_file:</strong> <?= is_file($filePath) ? 'SIM' : 'NÃO' ?><br>
                            <strong>is_readable:</strong> <?= is_readable($filePath) ? 'SIM' : 'NÃO' ?><br>
                            <strong>is_dir (parent):</strong> <?= is_dir(dirname($filePath)) ? 'SIM' : 'NÃO' ?><br>
                            <strong>parentDir:</strong> <?= htmlspecialchars(dirname($filePath)) ?><br>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>
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

