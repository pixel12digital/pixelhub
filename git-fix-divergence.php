<?php
/**
 * Script para corrigir branches divergentes no Git
 * Execute este arquivo via navegador: http://seudominio.com/git-fix-divergence.php
 * 
 * ATENÇÃO: Remova este arquivo após usar por questões de segurança!
 */

// Verifica se está sendo executado via linha de comando ou navegador
$isWeb = php_sapi_name() !== 'cli';

if ($isWeb) {
    // Verifica se há uma chave de segurança (opcional, mas recomendado)
    $secretKey = $_GET['key'] ?? '';
    $expectedKey = 'fix-git-' . date('Y-m-d'); // Mude isso para algo mais seguro
    
    if ($secretKey !== $expectedKey) {
        die('Acesso negado. Use: ?key=' . $expectedKey);
    }
    
    header('Content-Type: text/plain; charset=utf-8');
    echo "=== Script de Correção Git ===\n\n";
}

// Define o diretório do repositório
$repoDir = __DIR__;
$gitDir = $repoDir . '/.git';

// Verifica se é um repositório Git
if (!is_dir($gitDir)) {
    die("ERRO: Diretório .git não encontrado em: {$repoDir}\n");
}

// Função para executar comandos Git
function execGit($command, $repoDir) {
    $fullCommand = "cd " . escapeshellarg($repoDir) . " && git " . $command . " 2>&1";
    $output = [];
    $returnCode = 0;
    
    exec($fullCommand, $output, $returnCode);
    
    return [
        'output' => $output,
        'code' => $returnCode,
        'command' => $fullCommand
    ];
}

echo "📁 Diretório do repositório: {$repoDir}\n\n";

// 1. Verifica status atual
echo "1️⃣ Verificando status atual...\n";
$status = execGit('status', $repoDir);
echo implode("\n", $status['output']) . "\n\n";

// 2. Busca atualizações do remoto
echo "2️⃣ Buscando atualizações do remoto...\n";
$fetch = execGit('fetch origin', $repoDir);
if ($fetch['code'] !== 0) {
    echo "⚠️ AVISO ao buscar: " . implode("\n", $fetch['output']) . "\n";
} else {
    echo "✅ Atualizações buscadas com sucesso\n";
}
echo "\n";

// 3. Verifica diferenças entre local e remoto
echo "3️⃣ Verificando diferenças...\n";
$logLocal = execGit('log --oneline -5', $repoDir);
$logRemote = execGit('log --oneline origin/main -5', $repoDir);

echo "Commits locais:\n";
echo implode("\n", $logLocal['output']) . "\n\n";
echo "Commits remotos:\n";
echo implode("\n", $logRemote['output']) . "\n\n";

// 4. Tenta fazer merge --no-ff
echo "4️⃣ Tentando fazer merge (--no-ff)...\n";
$merge = execGit('merge --no-ff origin/main', $repoDir);

if ($merge['code'] === 0) {
    echo "✅ MERGE realizado com sucesso!\n";
    echo implode("\n", $merge['output']) . "\n\n";
    
    // 5. Se merge funcionou, mostra status final
    echo "5️⃣ Status final:\n";
    $finalStatus = execGit('status', $repoDir);
    echo implode("\n", $finalStatus['output']) . "\n";
    
} else {
    echo "❌ Merge falhou. Tentando rebase...\n\n";
    
    // 6. Tenta rebase como alternativa
    echo "6️⃣ Tentando rebase...\n";
    $rebase = execGit('rebase origin/main', $repoDir);
    
    if ($rebase['code'] === 0) {
        echo "✅ REBASE realizado com sucesso!\n";
        echo implode("\n", $rebase['output']) . "\n\n";
        
        // Status final após rebase
        echo "7️⃣ Status final:\n";
        $finalStatus = execGit('status', $repoDir);
        echo implode("\n", $finalStatus['output']) . "\n";
        
    } else {
        echo "❌ Rebase também falhou. Pode haver conflitos.\n\n";
        echo "Saída do rebase:\n";
        echo implode("\n", $rebase['output']) . "\n\n";
        
        echo "⚠️ AÇÃO NECESSÁRIA:\n";
        echo "Parece que há conflitos que precisam ser resolvidos manualmente.\n";
        echo "Ou você pode tentar resetar para o remoto (PERDERÁ commits locais):\n";
        echo "git reset --hard origin/main\n\n";
        
        // Opção de reset automático (descomente se quiser usar)
        /*
        echo "🔄 Tentando reset --hard (isso apagará commits locais)...\n";
        $reset = execGit('reset --hard origin/main', $repoDir);
        if ($reset['code'] === 0) {
            echo "✅ Reset realizado com sucesso!\n";
            echo implode("\n", $reset['output']) . "\n";
        } else {
            echo "❌ Reset falhou: " . implode("\n", $reset['output']) . "\n";
        }
        */
    }
}

echo "\n=== Fim do script ===\n";
echo "\n⚠️ IMPORTANTE: Remova este arquivo após usar por segurança!\n";

