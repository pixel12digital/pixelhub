<?php
/**
 * Script de Diagnóstico Robusto - Problema de Vídeo Não Exibindo
 * 
 * Este script realiza testes completos para identificar a causa raiz
 * do problema de vídeo não estar sendo exibido corretamente.
 */

// Headers para permitir acesso
header('Content-Type: text/html; charset=utf-8');

// Carrega autoload
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

use PixelHub\Core\DB;

// Token para testar (pode ser passado via GET)
$testToken = $_GET['token'] ?? 'eedb130c3f9c2e76c9994002c3b4c086';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico Completo - Problema de Vídeo</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            line-height: 1.6;
        }
        .section {
            background: #252526;
            border: 1px solid #3e3e42;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        .section h2 {
            color: #4ec9b0;
            margin-top: 0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 5px;
        }
        .test {
            margin: 10px 0;
            padding: 10px;
            background: #1e1e1e;
            border-left: 3px solid #007acc;
        }
        .pass { border-left-color: #4ec9b0; }
        .fail { border-left-color: #f48771; }
        .warn { border-left-color: #dcdcaa; }
        .info { border-left-color: #569cd6; }
        .label {
            font-weight: bold;
            color: #569cd6;
        }
        .value {
            color: #ce9178;
        }
        .error {
            color: #f48771;
            background: #3a1d1d;
            padding: 5px;
            border-radius: 3px;
        }
        .success {
            color: #4ec9b0;
            background: #1d3a1d;
            padding: 5px;
            border-radius: 3px;
        }
        .warning {
            color: #dcdcaa;
            background: #3a3a1d;
            padding: 5px;
            border-radius: 3px;
        }
        pre {
            background: #1e1e1e;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
            border: 1px solid #3e3e42;
        }
        .code {
            color: #ce9178;
        }
        .url-test {
            margin: 5px 0;
            padding: 8px;
            background: #2d2d30;
            border-radius: 3px;
        }
        .url-test a {
            color: #4ec9b0;
            text-decoration: none;
        }
        .url-test a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>🔍 Diagnóstico Completo - Problema de Vídeo Não Exibindo</h1>
    <p><strong>Token sendo testado:</strong> <span class="code"><?= htmlspecialchars($testToken) ?></span></p>
    <p><strong>Data/Hora:</strong> <?= date('Y-m-d H:i:s') ?></p>

<?php

$results = [];
$criticalIssues = [];
$warnings = [];

// ============================================
// SEÇÃO 1: VERIFICAÇÃO DO REGISTRO NO BANCO
// ============================================
echo '<div class="section">';
echo '<h2>1. Verificação do Registro no Banco de Dados</h2>';

try {
    $db = DB::getConnection();
    $stmt = $db->prepare("
        SELECT 
            id, file_path, file_name, original_name, mime_type,
            size_bytes, duration_seconds, has_audio, public_token, 
            created_at, task_id
        FROM screen_recordings
        WHERE public_token = ?
        LIMIT 1
    ");
    $stmt->execute([$testToken]);
    $recording = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($recording) {
        echo '<div class="test pass">';
        echo '<span class="success">✓ Registro encontrado no banco</span><br>';
        echo '<span class="label">ID:</span> <span class="value">' . $recording['id'] . '</span><br>';
        echo '<span class="label">Task ID:</span> <span class="value">' . ($recording['task_id'] ?? 'NULL') . '</span><br>';
        echo '<span class="label">file_path:</span> <span class="code">' . htmlspecialchars($recording['file_path']) . '</span><br>';
        echo '<span class="label">file_name:</span> <span class="code">' . htmlspecialchars($recording['file_name']) . '</span><br>';
        echo '<span class="label">original_name:</span> <span class="code">' . htmlspecialchars($recording['original_name']) . '</span><br>';
        echo '<span class="label">mime_type:</span> <span class="value">' . htmlspecialchars($recording['mime_type']) . '</span><br>';
        echo '<span class="label">Tamanho:</span> <span class="value">' . number_format($recording['size_bytes'] / 1024 / 1024, 2) . ' MB</span><br>';
        echo '</div>';
        $results['db_record'] = true;
    } else {
        echo '<div class="test fail">';
        echo '<span class="error">✗ Registro NÃO encontrado no banco com este token</span>';
        echo '</div>';
        $results['db_record'] = false;
        $criticalIssues[] = 'Registro não encontrado no banco de dados';
    }
} catch (Exception $e) {
    echo '<div class="test fail">';
    echo '<span class="error">✗ Erro ao consultar banco: ' . htmlspecialchars($e->getMessage()) . '</span>';
    echo '</div>';
    $results['db_record'] = false;
    $criticalIssues[] = 'Erro ao consultar banco de dados: ' . $e->getMessage();
    $recording = null;
}

echo '</div>';

if (!$recording) {
    echo '<div class="section">';
    echo '<h2>⚠️ Não é possível continuar sem registro no banco</h2>';
    echo '</div>';
    exit;
}

// ============================================
// SEÇÃO 2: VERIFICAÇÃO DE ARQUIVOS FÍSICOS
// ============================================
echo '<div class="section">';
echo '<h2>2. Verificação de Arquivos Físicos</h2>';

$filePaths = [];
$fileFound = false;
$foundPath = null;

// Tentativa 1: storage/tasks/ (se task_id existe)
if (!empty($recording['task_id'])) {
    $taskId = (int)$recording['task_id'];
    $fileName = $recording['file_name'] ?? $recording['original_name'];
    $path1 = __DIR__ . '/../../storage/tasks/' . $taskId . '/' . $fileName;
    $normalized1 = realpath($path1);
    $filePaths[] = [
        'label' => 'storage/tasks/' . $taskId . '/' . $fileName,
        'path' => $path1,
        'normalized' => $normalized1,
        'exists' => $normalized1 && file_exists($normalized1) && is_file($normalized1),
        'priority' => 1
    ];
    
    if ($normalized1 && file_exists($normalized1) && is_file($normalized1)) {
        $fileFound = true;
        $foundPath = $normalized1;
    }
}

// Tentativa 2: public/screen-recordings/ (biblioteca)
if (!$fileFound) {
    $relativePath = ltrim($recording['file_path'], '/');
    if (strpos($relativePath, 'screen-recordings/') === 0) {
        $fileRelativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
        $path2 = __DIR__ . '/' . $fileRelativePath;
        $normalized2 = realpath($path2);
        $filePaths[] = [
            'label' => 'public/screen-recordings/' . $fileRelativePath,
            'path' => $path2,
            'normalized' => $normalized2,
            'exists' => $normalized2 && file_exists($normalized2) && is_file($normalized2),
            'priority' => 2
        ];
        
        if ($normalized2 && file_exists($normalized2) && is_file($normalized2)) {
            $fileFound = true;
            $foundPath = $normalized2;
        }
    }
}

// Tentativa 3: storage/tasks/ com original_name
if (!$fileFound && !empty($recording['task_id']) && !empty($recording['original_name'])) {
    $taskId = (int)$recording['task_id'];
    $path3 = __DIR__ . '/../../storage/tasks/' . $taskId . '/' . $recording['original_name'];
    $normalized3 = realpath($path3);
    $filePaths[] = [
        'label' => 'storage/tasks/' . $taskId . '/' . $recording['original_name'] . ' (original_name)',
        'path' => $path3,
        'normalized' => $normalized3,
        'exists' => $normalized3 && file_exists($normalized3) && is_file($normalized3),
        'priority' => 3
    ];
    
    if ($normalized3 && file_exists($normalized3) && is_file($normalized3)) {
        $fileFound = true;
        $foundPath = $normalized3;
    }
}

// Exibe resultados
foreach ($filePaths as $fp) {
    $class = $fp['exists'] ? 'pass' : 'fail';
    echo '<div class="test ' . $class . '">';
    echo '<span class="label">Tentativa ' . $fp['priority'] . ':</span> <span class="code">' . htmlspecialchars($fp['label']) . '</span><br>';
    echo '<span class="label">Caminho:</span> <span class="code">' . htmlspecialchars($fp['path']) . '</span><br>';
    if ($fp['normalized']) {
        echo '<span class="label">Caminho normalizado:</span> <span class="code">' . htmlspecialchars($fp['normalized']) . '</span><br>';
    }
    if ($fp['exists']) {
        echo '<span class="success">✓ Arquivo encontrado</span><br>';
        echo '<span class="label">Tamanho:</span> <span class="value">' . number_format(filesize($fp['normalized']) / 1024 / 1024, 2) . ' MB</span><br>';
        echo '<span class="label">Permissões:</span> <span class="value">' . substr(sprintf('%o', fileperms($fp['normalized'])), -4) . '</span><br>';
        echo '<span class="label">Legível:</span> <span class="value">' . (is_readable($fp['normalized']) ? 'SIM' : 'NÃO') . '</span><br>';
    } else {
        echo '<span class="error">✗ Arquivo NÃO encontrado</span><br>';
        $parentDir = dirname($fp['path']);
        if (is_dir($parentDir)) {
            echo '<span class="label">Diretório pai existe:</span> <span class="success">SIM</span><br>';
            $files = @scandir($parentDir);
            if ($files) {
                $files = array_filter($files, function($f) { return $f !== '.' && $f !== '..'; });
                echo '<span class="label">Arquivos no diretório (' . count($files) . '):</span> <span class="code">' . implode(', ', array_slice($files, 0, 10)) . '</span><br>';
            }
        } else {
            echo '<span class="label">Diretório pai existe:</span> <span class="error">NÃO</span><br>';
        }
    }
    echo '</div>';
}

if ($fileFound) {
    $results['file_exists'] = true;
    echo '<div class="test pass">';
    echo '<span class="success">✓ Arquivo físico encontrado!</span><br>';
    echo '<span class="label">Caminho final:</span> <span class="code">' . htmlspecialchars($foundPath) . '</span>';
    echo '</div>';
} else {
    $results['file_exists'] = false;
    echo '<div class="test fail">';
    echo '<span class="error">✗ Arquivo físico NÃO encontrado em nenhum dos caminhos testados</span>';
    echo '</div>';
    $criticalIssues[] = 'Arquivo físico não encontrado';
}

echo '</div>';

// ============================================
// SEÇÃO 3: VERIFICAÇÃO DE ROTAS E URLS
// ============================================
echo '<div class="section">';
echo '<h2>3. Verificação de Rotas e URLs</h2>';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . $domainName;

echo '<div class="test info">';
echo '<span class="label">Protocolo:</span> <span class="value">' . $protocol . '</span><br>';
echo '<span class="label">Domínio:</span> <span class="value">' . $domainName . '</span><br>';
echo '<span class="label">Base URL calculada:</span> <span class="code">' . $baseUrl . '</span><br>';
if (defined('BASE_URL')) {
    echo '<span class="label">BASE_URL constante:</span> <span class="code">' . BASE_URL . '</span><br>';
    if (BASE_URL !== $baseUrl) {
        echo '<span class="warning">⚠ BASE_URL constante difere da calculada</span><br>';
        $warnings[] = 'BASE_URL constante difere da URL calculada';
    }
}
if (defined('BASE_PATH')) {
    echo '<span class="label">BASE_PATH:</span> <span class="code">' . BASE_PATH . '</span><br>';
}
echo '</div>';

// Testa diferentes URLs
$urls = [];

// URL 1: share.php com token
$url1 = $baseUrl . '/screen-recordings/share?token=' . urlencode($testToken);
$urls[] = ['label' => 'Página de compartilhamento', 'url' => $url1, 'type' => 'html'];

// URL 2: share.php com stream=1
$url2 = $baseUrl . '/screen-recordings/share?token=' . urlencode($testToken) . '&stream=1';
$urls[] = ['label' => 'Streaming direto', 'url' => $url2, 'type' => 'video'];

// URL 3: Se for arquivo da biblioteca
if (strpos($recording['file_path'], 'screen-recordings/') === 0 && !$fileFound) {
    $relativePath = ltrim($recording['file_path'], '/');
    $url3 = $baseUrl . '/' . $relativePath;
    $urls[] = ['label' => 'URL pública direta (biblioteca)', 'url' => $url3, 'type' => 'video'];
}

echo '<div class="test info">';
echo '<span class="label">URLs para testar:</span><br>';
foreach ($urls as $u) {
    echo '<div class="url-test">';
    echo '<strong>' . $u['label'] . ':</strong><br>';
    echo '<a href="' . htmlspecialchars($u['url']) . '" target="_blank">' . htmlspecialchars($u['url']) . '</a><br>';
    echo '<span class="label">Tipo esperado:</span> <span class="value">' . $u['type'] . '</span>';
    echo '</div>';
}
echo '</div>';

// Testa se a rota está acessível
$testUrl = $baseUrl . '/screen-recordings/share?token=' . urlencode($testToken);
$ch = curl_init($testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

echo '<div class="test ' . ($httpCode === 200 ? 'pass' : 'fail') . '">';
echo '<span class="label">Teste de acesso HTTP:</span><br>';
echo '<span class="label">URL testada:</span> <span class="code">' . htmlspecialchars($testUrl) . '</span><br>';
echo '<span class="label">Código HTTP:</span> <span class="value">' . $httpCode . '</span><br>';
echo '<span class="label">Content-Type:</span> <span class="value">' . ($contentType ?: 'N/A') . '</span><br>';
if ($httpCode === 200) {
    echo '<span class="success">✓ URL acessível</span>';
    $results['url_accessible'] = true;
} else {
    echo '<span class="error">✗ URL retornou código ' . $httpCode . '</span>';
    $results['url_accessible'] = false;
    $criticalIssues[] = 'URL de compartilhamento retornou código HTTP ' . $httpCode;
}
echo '</div>';

echo '</div>';

// ============================================
// SEÇÃO 4: SIMULAÇÃO DE CONSTRUÇÃO DE URL
// ============================================
echo '<div class="section">';
echo '<h2>4. Simulação de Construção de URL do Vídeo</h2>';

// Simula a lógica do share.php
$relativePath = ltrim($recording['file_path'], '/');
$isTaskFile = false;
if (!empty($recording['task_id'])) {
    $isTaskFile = true;
} elseif ($foundPath && strpos($foundPath, '/storage/tasks/') !== false) {
    $isTaskFile = true;
} elseif (strpos($relativePath, 'storage/tasks/') === 0) {
    $isTaskFile = true;
}

echo '<div class="test info">';
echo '<span class="label">Lógica de detecção:</span><br>';
echo '<span class="label">task_id:</span> <span class="value">' . ($recording['task_id'] ?? 'NULL') . '</span><br>';
echo '<span class="label">file_path:</span> <span class="code">' . htmlspecialchars($recording['file_path']) . '</span><br>';
echo '<span class="label">Arquivo encontrado em:</span> <span class="code">' . ($foundPath ?: 'NÃO ENCONTRADO') . '</span><br>';
echo '<span class="label">É arquivo de tarefa?</span> <span class="value">' . ($isTaskFile ? 'SIM' : 'NÃO') . '</span><br>';
echo '</div>';

if ($isTaskFile) {
    $simulatedVideoUrl = $baseUrl . '/screen-recordings/share?token=' . urlencode($testToken) . '&stream=1';
} else {
    $simulatedVideoUrl = $baseUrl . '/' . $relativePath;
}

echo '<div class="test ' . ($isTaskFile ? 'pass' : 'warn') . '">';
echo '<span class="label">URL do vídeo que seria gerada:</span><br>';
echo '<span class="code">' . htmlspecialchars($simulatedVideoUrl) . '</span><br>';
if ($isTaskFile) {
    echo '<span class="success">✓ Usando endpoint de streaming (correto para arquivos de tarefa)</span>';
} else {
    echo '<span class="warning">⚠ Usando URL pública direta (pode não funcionar se arquivo não estiver em public/)</span>';
}
echo '</div>';

// Testa se a URL do vídeo é acessível
if ($isTaskFile) {
    $ch = curl_init($simulatedVideoUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RANGE, '0-1023'); // Range request para streaming
    $response = curl_exec($ch);
    $videoHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $videoContentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $acceptRanges = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);
    
    echo '<div class="test ' . ($videoHttpCode === 200 || $videoHttpCode === 206 ? 'pass' : 'fail') . '">';
    echo '<span class="label">Teste de acesso à URL do vídeo:</span><br>';
    echo '<span class="label">Código HTTP:</span> <span class="value">' . $videoHttpCode . '</span> ';
    if ($videoHttpCode === 206) {
        echo '<span class="success">✓ (206 Partial Content - suporta streaming)</span>';
    } elseif ($videoHttpCode === 200) {
        echo '<span class="success">✓ (200 OK)</span>';
    } else {
        echo '<span class="error">✗ (Erro)</span>';
    }
    echo '<br>';
    echo '<span class="label">Content-Type:</span> <span class="value">' . ($videoContentType ?: 'N/A') . '</span><br>';
    if ($videoHttpCode === 200 || $videoHttpCode === 206) {
        $results['video_url_accessible'] = true;
    } else {
        $results['video_url_accessible'] = false;
        $criticalIssues[] = 'URL do vídeo retornou código HTTP ' . $videoHttpCode;
    }
    echo '</div>';
}

echo '</div>';

// ============================================
// SEÇÃO 5: VERIFICAÇÃO DE LOGS RECENTES
// ============================================
echo '<div class="section">';
echo '<h2>5. Análise de Logs Recentes</h2>';

$logFile = __DIR__ . '/../../logs/pixelhub.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $logLines = explode("\n", $logContent);
    $recentLines = array_slice($logLines, -100); // Últimas 100 linhas
    
    $relevantLines = array_filter($recentLines, function($line) use ($testToken) {
        return stripos($line, 'share') !== false || 
               stripos($line, $testToken) !== false ||
               stripos($line, 'screen-recordings') !== false;
    });
    
    echo '<div class="test info">';
    echo '<span class="label">Arquivo de log:</span> <span class="code">' . htmlspecialchars($logFile) . '</span><br>';
    echo '<span class="label">Tamanho:</span> <span class="value">' . number_format(filesize($logFile) / 1024, 2) . ' KB</span><br>';
    echo '<span class="label">Linhas relevantes encontradas:</span> <span class="value">' . count($relevantLines) . '</span><br>';
    if (count($relevantLines) > 0) {
        echo '<span class="label">Últimas linhas relevantes:</span><br>';
        echo '<pre>';
        echo htmlspecialchars(implode("\n", array_slice($relevantLines, -20)));
        echo '</pre>';
    } else {
        echo '<span class="warning">⚠ Nenhuma linha relevante encontrada nos logs recentes</span>';
    }
    echo '</div>';
} else {
    echo '<div class="test warn">';
    echo '<span class="warning">⚠ Arquivo de log não encontrado: ' . htmlspecialchars($logFile) . '</span>';
    echo '</div>';
}

echo '</div>';

// ============================================
// SEÇÃO 6: VERIFICAÇÃO DE CONFIGURAÇÃO DO SERVIDOR
// ============================================
echo '<div class="section">';
echo '<h2>6. Verificação de Configuração do Servidor</h2>';

echo '<div class="test info">';
echo '<span class="label">PHP Version:</span> <span class="value">' . PHP_VERSION . '</span><br>';
echo '<span class="label">Server Software:</span> <span class="value">' . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . '</span><br>';
echo '<span class="label">Document Root:</span> <span class="code">' . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . '</span><br>';
echo '<span class="label">Script Name:</span> <span class="code">' . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . '</span><br>';
echo '<span class="label">Request URI:</span> <span class="code">' . ($_SERVER['REQUEST_URI'] ?? 'N/A') . '</span><br>';
echo '<span class="label">__DIR__:</span> <span class="code">' . __DIR__ . '</span><br>';
echo '<span class="label">memory_limit:</span> <span class="value">' . ini_get('memory_limit') . '</span><br>';
echo '<span class="label">max_execution_time:</span> <span class="value">' . ini_get('max_execution_time') . '</span><br>';
echo '<span class="label">upload_max_filesize:</span> <span class="value">' . ini_get('upload_max_filesize') . '</span><br>';
echo '<span class="label">post_max_size:</span> <span class="value">' . ini_get('post_max_size') . '</span><br>';
echo '</div>';

// Verifica .htaccess
$htaccessFile = __DIR__ . '/../.htaccess';
if (file_exists($htaccessFile)) {
    echo '<div class="test info">';
    echo '<span class="label">.htaccess encontrado:</span> <span class="code">' . htmlspecialchars($htaccessFile) . '</span><br>';
    $htaccessContent = file_get_contents($htaccessFile);
    if (stripos($htaccessContent, 'RewriteEngine') !== false) {
        echo '<span class="success">✓ RewriteEngine está ativo</span><br>';
    }
    if (stripos($htaccessContent, 'screen-recordings') !== false) {
        echo '<span class="warning">⚠ .htaccess contém referências a screen-recordings</span><br>';
    }
    echo '</div>';
} else {
    echo '<div class="test warn">';
    echo '<span class="warning">⚠ .htaccess não encontrado em: ' . htmlspecialchars($htaccessFile) . '</span>';
    echo '</div>';
}

echo '</div>';

// ============================================
// SEÇÃO 7: RESUMO E RECOMENDAÇÕES
// ============================================
echo '<div class="section">';
echo '<h2>7. Resumo e Recomendações</h2>';

$allPassed = true;
foreach ($results as $key => $value) {
    if (!$value) {
        $allPassed = false;
        break;
    }
}

if ($allPassed && empty($criticalIssues)) {
    echo '<div class="test pass">';
    echo '<span class="success">✓ Todos os testes básicos passaram</span><br>';
    echo '<span class="label">Status:</span> <span class="success">Sistema parece estar funcionando corretamente</span><br>';
    echo '<span class="label">Próximos passos:</span><br>';
    echo '<ul>';
    echo '<li>Verificar se o problema está no frontend (JavaScript, HTML5 video player)</li>';
    echo '<li>Verificar console do navegador para erros JavaScript</li>';
    echo '<li>Verificar Network tab do DevTools para ver requisições HTTP</li>';
    echo '<li>Testar em diferentes navegadores</li>';
    echo '</ul>';
    echo '</div>';
} else {
    echo '<div class="test fail">';
    echo '<span class="error">✗ Problemas encontrados</span><br>';
    echo '<span class="label">Issues críticos:</span><br>';
    if (empty($criticalIssues)) {
        echo '<span class="success">Nenhum</span><br>';
    } else {
        echo '<ul>';
        foreach ($criticalIssues as $issue) {
            echo '<li><span class="error">' . htmlspecialchars($issue) . '</span></li>';
        }
        echo '</ul>';
    }
    echo '<span class="label">Avisos:</span><br>';
    if (empty($warnings)) {
        echo '<span class="success">Nenhum</span><br>';
    } else {
        echo '<ul>';
        foreach ($warnings as $warning) {
            echo '<li><span class="warning">' . htmlspecialchars($warning) . '</span></li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}

echo '<div class="test info">';
echo '<span class="label">Ações recomendadas:</span><br>';
echo '<ol>';
if (!$results['file_exists']) {
    echo '<li><strong>CRÍTICO:</strong> Arquivo físico não encontrado. Verificar onde o arquivo foi realmente salvo.</li>';
}
if (!$results['url_accessible']) {
    echo '<li><strong>CRÍTICO:</strong> URL de compartilhamento não está acessível. Verificar rotas e .htaccess.</li>';
}
if (!$results['video_url_accessible']) {
    echo '<li><strong>CRÍTICO:</strong> URL do vídeo não está acessível. Verificar lógica de streaming em share.php.</li>';
}
if (!empty($recording['task_id']) && !$fileFound) {
    echo '<li><strong>IMPORTANTE:</strong> Arquivo de tarefa não encontrado. Verificar se o arquivo foi salvo corretamente em storage/tasks/' . $recording['task_id'] . '/</li>';
}
echo '<li>Verificar logs do servidor (Apache/Nginx) para erros adicionais</li>';
echo '<li>Verificar permissões de arquivos e diretórios</li>';
echo '<li>Testar acesso direto ao arquivo via URL pública (se aplicável)</li>';
echo '</ol>';
echo '</div>';

echo '</div>';

?>

    <div class="section">
        <h2>8. Teste Manual</h2>
        <div class="test info">
            <p><strong>Para testar manualmente:</strong></p>
            <ol>
                <li>Abra o console do navegador (F12)</li>
                <li>Acesse a URL de compartilhamento acima</li>
                <li>Verifique a aba Network para ver as requisições</li>
                <li>Verifique se há erros no console</li>
                <li>Teste a URL do vídeo diretamente no navegador</li>
            </ol>
        </div>
    </div>

    <div style="text-align: center; margin-top: 30px; padding: 20px; background: #252526; border-radius: 4px;">
        <p><strong>Diagnóstico gerado em:</strong> <?= date('Y-m-d H:i:s') ?></p>
        <p><a href="?token=<?= urlencode($testToken) ?>" style="color: #4ec9b0;">Atualizar diagnóstico</a></p>
    </div>

</body>
</html>

