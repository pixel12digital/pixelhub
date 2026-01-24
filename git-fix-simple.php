<?php
/**
 * Script SIMPLES para corrigir branches divergentes no Git
 * Execute via navegador com: ?action=fix
 * 
 * ATENÇÃO: Remova este arquivo após usar!
 */

$action = $_GET['action'] ?? '';

if ($action !== 'fix') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Correção Git - Branches Divergentes</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .danger { background: #f8d7da; border: 1px solid #dc3545; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .success { background: #d4edda; border: 1px solid #28a745; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .info { background: #d1ecf1; border: 1px solid #17a2b8; padding: 15px; border-radius: 5px; margin: 20px 0; }
            pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
            button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
            button:hover { background: #0056b3; }
        </style>
    </head>
    <body>
        <h1>🔧 Correção de Branches Divergentes no Git</h1>
        
        <div class="warning">
            <strong>⚠️ ATENÇÃO:</strong> Este script vai tentar sincronizar seu repositório local com o remoto.
            Certifique-se de que não há alterações importantes apenas no servidor que não foram commitadas.
        </div>
        
        <div class="info">
            <h3>O que este script faz:</h3>
            <ol>
                <li>Busca atualizações do repositório remoto</li>
                <li>Tenta fazer merge das branches divergentes</li>
                <li>Se merge falhar, tenta rebase</li>
                <li>Mostra o resultado da operação</li>
            </ol>
        </div>
        
        <div class="danger">
            <strong>🔒 SEGURANÇA:</strong> Remova este arquivo (<code>git-fix-simple.php</code>) após usar!
        </div>
        
        <p>
            <a href="?action=fix">
                <button>▶️ Executar Correção</button>
            </a>
        </p>
        
        <hr>
        <p><small>Script criado em: <?= date('Y-m-d H:i:s') ?></small></p>
    </body>
    </html>
    <?php
    exit;
}

// Executa a correção
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Executando Correção Git...</title>
    <style>
        body { font-family: 'Courier New', monospace; max-width: 1000px; margin: 20px auto; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        .info { color: #569cd6; }
        pre { background: #252526; padding: 15px; border-radius: 5px; overflow-x: auto; border-left: 3px solid #007acc; }
        h2 { color: #4ec9b0; border-bottom: 2px solid #007acc; padding-bottom: 5px; }
    </style>
</head>
<body>
    <h1>🔧 Executando Correção Git...</h1>
    
    <pre><?php

$repoDir = __DIR__;
$gitDir = $repoDir . '/.git';

if (!is_dir($gitDir)) {
    die("❌ ERRO: Diretório .git não encontrado!\n");
}

function execGit($cmd, $dir) {
    $fullCmd = "cd " . escapeshellarg($dir) . " && git " . $cmd . " 2>&1";
    $output = [];
    $code = 0;
    exec($fullCmd, $output, $code);
    return ['output' => $output, 'code' => $code];
}

echo "📁 Diretório: {$repoDir}\n\n";

// 1. Status inicial
echo "1️⃣ Status inicial:\n";
$status = execGit('status', $repoDir);
echo implode("\n", $status['output']) . "\n\n";

// 2. Fetch
echo "2️⃣ Buscando atualizações...\n";
$fetch = execGit('fetch origin', $repoDir);
if ($fetch['code'] === 0) {
    echo "✅ Atualizações buscadas\n";
} else {
    echo "⚠️ " . implode("\n", $fetch['output']) . "\n";
}
echo "\n";

// 3. Merge
echo "3️⃣ Tentando merge...\n";
$merge = execGit('merge --no-ff origin/main', $repoDir);
echo implode("\n", $merge['output']) . "\n";

if ($merge['code'] === 0) {
    echo "\n✅ MERGE CONCLUÍDO COM SUCESSO!\n\n";
} else {
    echo "\n⚠️ Merge falhou. Tentando rebase...\n\n";
    
    // 4. Rebase
    echo "4️⃣ Tentando rebase...\n";
    $rebase = execGit('rebase origin/main', $repoDir);
    echo implode("\n", $rebase['output']) . "\n";
    
    if ($rebase['code'] === 0) {
        echo "\n✅ REBASE CONCLUÍDO COM SUCESSO!\n\n";
    } else {
        echo "\n❌ Rebase também falhou. Pode haver conflitos.\n";
        echo "Considere executar manualmente no servidor:\n";
        echo "  git reset --hard origin/main\n";
        echo "(Isso apagará commits locais do servidor)\n\n";
    }
}

// 5. Status final
echo "5️⃣ Status final:\n";
$finalStatus = execGit('status', $repoDir);
echo implode("\n", $finalStatus['output']) . "\n";

?></pre>

    <hr>
    <p><strong>⚠️ IMPORTANTE:</strong> Remova este arquivo após usar por segurança!</p>
    <p><a href="?" style="color: #4ec9b0;">← Voltar</a></p>
</body>
</html>

