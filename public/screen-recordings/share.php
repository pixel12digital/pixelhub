<?php
/**
 * Página pública para visualização de gravações de tela
 * Não exige login - usa token público
 */

// Log imediato para confirmar execução
error_log('[ScreenRecordings Share] ==========================================');
error_log('[ScreenRecordings Share] share.php INICIADO');
error_log('[ScreenRecordings Share] __DIR__: ' . __DIR__);
error_log('[ScreenRecordings Share] REQUEST_URI: ' . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
error_log('[ScreenRecordings Share] QUERY_STRING: ' . ($_SERVER['QUERY_STRING'] ?? 'N/A'));
error_log('[ScreenRecordings Share] $_GET: ' . json_encode($_GET));

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
    error_log('[ScreenRecordings Share] Buscando registro com token: ' . $token);
    $stmt->execute([$token]);
    $recording = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log para debug
    error_log('[ScreenRecordings Share] ==========================================');
    error_log('[ScreenRecordings Share] DEBUG COMPLETO - Token: ' . $token);
    error_log('[ScreenRecordings Share] Registro encontrado: ' . ($recording ? 'SIM' : 'NÃO'));
    
    if ($recording) {
        error_log('[ScreenRecordings Share] Dados do registro:');
        error_log('[ScreenRecordings Share]   - ID: ' . ($recording['id'] ?? 'N/A'));
        error_log('[ScreenRecordings Share]   - task_id: ' . ($recording['task_id'] ?? 'NULL'));
        error_log('[ScreenRecordings Share]   - file_path: ' . ($recording['file_path'] ?? 'N/A'));
        error_log('[ScreenRecordings Share]   - file_name: ' . ($recording['file_name'] ?? 'N/A'));
        error_log('[ScreenRecordings Share]   - original_name: ' . ($recording['original_name'] ?? 'N/A'));
        error_log('[ScreenRecordings Share]   - public_token: ' . ($recording['public_token'] ?? 'N/A'));
        error_log('[ScreenRecordings Share]   - mime_type: ' . ($recording['mime_type'] ?? 'N/A'));
    }
    
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
        error_log('[ScreenRecordings Share Stream] ==========================================');
        error_log('[ScreenRecordings Share Stream] MODO STREAMING ATIVADO');
        error_log('[ScreenRecordings Share Stream] file_path do banco: ' . ($recording['file_path'] ?? 'N/A'));
        
        $relativePath = ltrim($recording['file_path'], '/');
        $filePath = null;
        $fileExists = false;
        
        error_log('[ScreenRecordings Share Stream] relativePath (após ltrim): ' . $relativePath);
        error_log('[ScreenRecordings Share Stream] __DIR__: ' . __DIR__);
        error_log('[ScreenRecordings Share Stream] task_id: ' . ($recording['task_id'] ?? 'NULL'));
        
        // PRIORIDADE 1: Se task_id não é NULL, verifica primeiro em storage/tasks/
        if (!empty($recording['task_id'])) {
            $taskId = (int)$recording['task_id'];
            $fileName = $recording['file_name'] ?? $recording['original_name'];
            
            if (!empty($fileName)) {
                error_log('[ScreenRecordings Share Stream] task_id não é NULL, tentando storage/tasks/' . $taskId . '/' . $fileName);
                $taskFilePath = __DIR__ . '/../../storage/tasks/' . $taskId . '/' . $fileName;
                $taskFilePathNormalized = realpath($taskFilePath);
                
                if ($taskFilePathNormalized && file_exists($taskFilePathNormalized) && is_file($taskFilePathNormalized)) {
                    error_log('[ScreenRecordings Share Stream] ✓ Arquivo encontrado em storage/tasks/ (via task_id)');
                    $filePath = $taskFilePathNormalized;
                    $fileExists = true;
                } else {
                    error_log('[ScreenRecordings Share Stream] ✗ Arquivo NÃO encontrado em storage/tasks/' . $taskId . '/' . $fileName);
                }
            }
        }
        
        // PRIORIDADE 2: Se não encontrou e file_path indica storage/tasks/
        if (!$fileExists && strpos($relativePath, 'storage/tasks/') === 0) {
            error_log('[ScreenRecordings Share Stream] Tipo: Arquivo de tarefa (storage/tasks/)');
            // Arquivo de tarefa: busca em storage/tasks/ (raiz do projeto)
            // __DIR__ é public/screen-recordings/, então ../../ volta para a raiz
            $filePath = __DIR__ . '/../../' . $relativePath;
            
            error_log('[ScreenRecordings Share Stream] filePath calculado (antes de realpath): ' . $filePath);
            
            // Normaliza o caminho (resolve .. e .)
            $normalizedPath = realpath($filePath);
            if ($normalizedPath) {
                $filePath = $normalizedPath;
                error_log('[ScreenRecordings Share Stream] filePath normalizado (realpath): ' . $filePath);
            } else {
                error_log('[ScreenRecordings Share Stream] realpath FALHOU - tentando sem normalização');
            }
            
            // Log para debug
            error_log('[ScreenRecordings Share Stream] filePath final: ' . $filePath);
            error_log('[ScreenRecordings Share Stream] file_exists: ' . (file_exists($filePath) ? 'SIM' : 'NÃO'));
            error_log('[ScreenRecordings Share Stream] is_file: ' . (is_file($filePath) ? 'SIM' : 'NÃO'));
            error_log('[ScreenRecordings Share Stream] is_readable: ' . (is_readable($filePath) ? 'SIM' : 'NÃO'));
            
            // Verifica diretório pai
            if ($filePath) {
                $parentDir = dirname($filePath);
                error_log('[ScreenRecordings Share Stream] parentDir: ' . $parentDir);
                error_log('[ScreenRecordings Share Stream] parentDir existe: ' . (is_dir($parentDir) ? 'SIM' : 'NÃO'));
                if (is_dir($parentDir)) {
                    $files = @scandir($parentDir);
                    if ($files) {
                        error_log('[ScreenRecordings Share Stream] Arquivos no diretório: ' . implode(', ', array_slice($files, 0, 10)));
                    }
                }
            }
        } elseif (strpos($relativePath, 'screen-recordings/') === 0) {
            error_log('[ScreenRecordings Share Stream] Tipo: Arquivo da biblioteca (screen-recordings/)');
            // Arquivo da biblioteca: busca em public/screen-recordings/
            $fileRelativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
            $filePath = __DIR__ . '/' . $fileRelativePath;
            
            // CORREÇÃO: Se não encontrou com file_path, tenta com file_name (que deve ter o token)
            if (!file_exists($filePath) && !empty($recording['file_name'])) {
                // Extrai o diretório do file_path
                $pathDir = dirname($fileRelativePath);
                // Usa file_name (que deve ter o token) no mesmo diretório
                $filePathAlt = __DIR__ . '/' . $pathDir . '/' . $recording['file_name'];
                error_log('[ScreenRecordings Share Stream] Tentando caminho alternativo com file_name: ' . $filePathAlt);
                if (file_exists($filePathAlt) && is_file($filePathAlt)) {
                    $filePath = $filePathAlt;
                    error_log('[ScreenRecordings Share Stream] Arquivo encontrado com file_name!');
                }
            }
            
            error_log('[ScreenRecordings Share Stream] fileRelativePath: ' . $fileRelativePath);
            error_log('[ScreenRecordings Share Stream] filePath: ' . $filePath);
            error_log('[ScreenRecordings Share Stream] file_name do banco: ' . ($recording['file_name'] ?? 'N/A'));
        } else {
            error_log('[ScreenRecordings Share Stream] Tipo: Caminho relativo genérico');
            // Tenta como caminho relativo a partir de public/screen-recordings/
            $filePath = __DIR__ . '/' . $relativePath;
            error_log('[ScreenRecordings Share Stream] filePath: ' . $filePath);
        }
        
        // PRIORIDADE 3: Se ainda não encontrou e task_id não é NULL, tenta storage/tasks/ com original_name
        if (!$fileExists && !empty($recording['task_id']) && !empty($recording['original_name'])) {
            $taskId = (int)$recording['task_id'];
            $originalName = $recording['original_name'];
            error_log('[ScreenRecordings Share Stream] Tentando storage/tasks/' . $taskId . '/' . $originalName . ' (com original_name)');
            $taskFilePath = __DIR__ . '/../../storage/tasks/' . $taskId . '/' . $originalName;
            $taskFilePathNormalized = realpath($taskFilePath);
            
            if ($taskFilePathNormalized && file_exists($taskFilePathNormalized) && is_file($taskFilePathNormalized)) {
                error_log('[ScreenRecordings Share Stream] ✓ Arquivo encontrado em storage/tasks/ (via original_name)');
                $filePath = $taskFilePathNormalized;
                $fileExists = true;
            }
        }
        
        // Verifica se o arquivo existe antes de servir
        if (!$filePath || !$fileExists) {
            error_log('[ScreenRecordings Share Stream] filePath é NULL ou arquivo não existe');
            if ($filePath) {
                error_log('[ScreenRecordings Share Stream] filePath tentado: ' . $filePath);
            }
            http_response_code(404);
            echo 'Arquivo não encontrado - caminho não determinado';
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
    error_log('[ScreenRecordings Share] ==========================================');
    error_log('[ScreenRecordings Share] VERIFICAÇÃO DE ARQUIVO PARA EXIBIÇÃO');
    error_log('[ScreenRecordings Share] file_path do banco: ' . ($recording['file_path'] ?? 'N/A'));
    error_log('[ScreenRecordings Share] task_id: ' . ($recording['task_id'] ?? 'NULL'));
    
    // O file_path no banco pode ser:
    // 1. screen-recordings/2025/11/28/xxx.webm (biblioteca) -> public/screen-recordings/2025/11/28/xxx.webm
    // 2. storage/tasks/1/xxx.webm (tarefa) -> storage/tasks/1/xxx.webm
    // 3. Se task_id não é NULL mas file_path está errado, tenta storage/tasks/{task_id}/{file_name}
    $relativePath = ltrim($recording['file_path'], '/');
    $filePath = null;
    $fileExists = false;
    $fileRelativePath = ''; // Inicializa para evitar undefined variable
    
    error_log('[ScreenRecordings Share] relativePath (após ltrim): ' . $relativePath);
    error_log('[ScreenRecordings Share] __DIR__: ' . __DIR__);
    
    // PRIORIDADE 1: Se task_id não é NULL, verifica primeiro em storage/tasks/
    if (!empty($recording['task_id'])) {
        $taskId = (int)$recording['task_id'];
        $fileName = $recording['file_name'] ?? $recording['original_name'];
        
        if (!empty($fileName)) {
            error_log('[ScreenRecordings Share] task_id não é NULL, tentando storage/tasks/' . $taskId . '/' . $fileName);
            $taskFilePath = __DIR__ . '/../../storage/tasks/' . $taskId . '/' . $fileName;
            $taskFilePathNormalized = realpath($taskFilePath);
            
            if ($taskFilePathNormalized && file_exists($taskFilePathNormalized) && is_file($taskFilePathNormalized)) {
                error_log('[ScreenRecordings Share] ✓ Arquivo encontrado em storage/tasks/ (via task_id)');
                $filePath = $taskFilePathNormalized;
                $fileExists = true;
                // Define fileRelativePath para logs
                $fileRelativePath = 'storage/tasks/' . $taskId . '/' . $fileName;
            } else {
                error_log('[ScreenRecordings Share] ✗ Arquivo NÃO encontrado em storage/tasks/' . $taskId . '/' . $fileName);
                error_log('[ScreenRecordings Share] Caminho tentado: ' . $taskFilePath);
                if ($taskFilePathNormalized) {
                    error_log('[ScreenRecordings Share] Caminho normalizado: ' . $taskFilePathNormalized);
                }
            }
        }
    }
    
    // PRIORIDADE 2: Se não encontrou e file_path indica storage/tasks/
    if (!$fileExists && strpos($relativePath, 'storage/tasks/') === 0) {
        error_log('[ScreenRecordings Share] Tipo: Arquivo de tarefa (storage/tasks/)');
        // Arquivo de tarefa: busca em storage/tasks/ (raiz do projeto)
        // __DIR__ é public/screen-recordings/, então ../../ volta para a raiz
        $filePath = __DIR__ . '/../../' . $relativePath;
        
        error_log('[ScreenRecordings Share] filePath calculado (antes de realpath): ' . $filePath);
        
        // Normaliza o caminho (resolve .. e .)
        $normalizedPath = realpath($filePath);
        if ($normalizedPath) {
            $filePath = $normalizedPath;
            error_log('[ScreenRecordings Share] filePath normalizado (realpath): ' . $filePath);
        } else {
            error_log('[ScreenRecordings Share] realpath FALHOU - tentando sem normalização');
        }
        
        $fileExists = file_exists($filePath) && is_file($filePath);
        
        // Log para debug
        error_log('[ScreenRecordings Share] filePath final: ' . ($filePath ?? 'NULL'));
        error_log('[ScreenRecordings Share] fileExists: ' . ($fileExists ? 'SIM' : 'NÃO'));
        error_log('[ScreenRecordings Share] is_file: ' . (is_file($filePath) ? 'SIM' : 'NÃO'));
        error_log('[ScreenRecordings Share] is_readable: ' . (is_readable($filePath) ? 'SIM' : 'NÃO'));
        
        // Se não encontrou, tenta sem normalizar (pode ser problema de permissões)
        if (!$fileExists && $filePath) {
            $fileExists = @file_exists($filePath) && @is_file($filePath);
            error_log('[ScreenRecordings Share] fileExists (após @): ' . ($fileExists ? 'SIM' : 'NÃO'));
        }
        
        // Verifica diretório pai
        if ($filePath && !$fileExists) {
            $parentDir = dirname($filePath);
            error_log('[ScreenRecordings Share] parentDir: ' . $parentDir);
            error_log('[ScreenRecordings Share] parentDir existe: ' . (is_dir($parentDir) ? 'SIM' : 'NÃO'));
            if (is_dir($parentDir)) {
                $files = @scandir($parentDir);
                if ($files) {
                    error_log('[ScreenRecordings Share] Arquivos no diretório: ' . implode(', ', array_slice($files, 0, 10)));
                }
            }
        }
    } elseif (!$fileExists && strpos($relativePath, 'screen-recordings/') === 0) {
        error_log('[ScreenRecordings Share] Tipo: Arquivo da biblioteca (screen-recordings/)');
        // Arquivo da biblioteca: busca em public/screen-recordings/
        $fileRelativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
        
        // CORREÇÃO: Se o file_path tem o nome original mas o file_name tem o token,
        // tenta usar o file_name primeiro
        $filePath = __DIR__ . '/' . $fileRelativePath;
        $fileExists = file_exists($filePath) && is_file($filePath);
        
        // Se não encontrou com file_path, tenta com file_name (que deve ter o token)
        if (!$fileExists && !empty($recording['file_name'])) {
            // Extrai o diretório do file_path
            $pathDir = dirname($fileRelativePath);
            // Usa file_name (que deve ter o token) no mesmo diretório
            $filePathAlt = __DIR__ . '/' . $pathDir . '/' . $recording['file_name'];
            error_log('[ScreenRecordings Share] Tentando caminho alternativo com file_name: ' . $filePathAlt);
            if (file_exists($filePathAlt) && is_file($filePathAlt)) {
                $filePath = $filePathAlt;
                $fileExists = true;
                error_log('[ScreenRecordings Share] Arquivo encontrado com file_name!');
            }
        }
        
        error_log('[ScreenRecordings Share] fileRelativePath: ' . $fileRelativePath);
        error_log('[ScreenRecordings Share] filePath: ' . $filePath);
        error_log('[ScreenRecordings Share] fileExists: ' . ($fileExists ? 'SIM' : 'NÃO'));
        error_log('[ScreenRecordings Share] file_name do banco: ' . ($recording['file_name'] ?? 'N/A'));
    } elseif (!$fileExists) {
        error_log('[ScreenRecordings Share] Tipo: Caminho relativo genérico');
        // Tenta como caminho relativo a partir de public/screen-recordings/
        $filePath = __DIR__ . '/' . $relativePath;
        $fileExists = file_exists($filePath) && is_file($filePath);
        error_log('[ScreenRecordings Share] filePath: ' . $filePath);
        error_log('[ScreenRecordings Share] fileExists: ' . ($fileExists ? 'SIM' : 'NÃO'));
    }
    
    error_log('[ScreenRecordings Share] ==========================================');
    
    // Log detalhado
    error_log('[ScreenRecordings Share] Verificando arquivo:');
    error_log('[ScreenRecordings Share]   file_path (banco): ' . $recording['file_path']);
    error_log('[ScreenRecordings Share]   fileRelativePath: ' . ($fileRelativePath ?? 'N/A'));
    error_log('[ScreenRecordings Share]   filePath absoluto: ' . ($filePath ?? 'NULL'));
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
        // Garante que não há duplicação de 'screen-recordings/'
        if (substr($baseUrl, -strlen('/screen-recordings')) === '/screen-recordings') {
            $relativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
        }
        $videoUrl = $baseUrl . '/' . $relativePath;
    }
    
    // Debug: sempre loga para diagnóstico em produção
    error_log('[ScreenRecordings Share] ==========================================');
    error_log('[ScreenRecordings Share] CONSTRUÇÃO DE URL DO VÍDEO');
    error_log('[ScreenRecordings Share] Token: ' . $token);
    error_log('[ScreenRecordings Share] file_path do banco: ' . $recording['file_path']);
    error_log('[ScreenRecordings Share] relativePath: ' . $relativePath);
    error_log('[ScreenRecordings Share] BASE_URL: ' . BASE_URL);
    error_log('[ScreenRecordings Share] baseUrl (trimmed): ' . $baseUrl);
    error_log('[ScreenRecordings Share] videoUrl final: ' . $videoUrl);
    error_log('[ScreenRecordings Share] filePath físico: ' . ($filePath ?? 'NULL'));
    error_log('[ScreenRecordings Share] arquivo existe: ' . (($filePath && file_exists($filePath)) ? 'SIM' : 'NÃO'));
    error_log('[ScreenRecordings Share] ==========================================');
    
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

