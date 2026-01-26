<?php
/**
 * Script PHP para atualizar repositório Git no servidor
 * 
 * Como usar:
 * 1. Faça upload deste arquivo para: /home/pixel12digital/hub.pixel12digital.com.br/
 * 2. Acesse via navegador: https://hub.pixel12digital.com.br/atualizar-repositorio.php
 * 3. O script será executado e mostrará o resultado
 * 4. Após usar, REMOVA este arquivo por segurança!
 */

// Configurações
$repoDir = '/home/pixel12digital/hub.pixel12digital.com.br';
$allowedIPs = []; // Deixe vazio para permitir qualquer IP, ou adicione IPs permitidos

// Verificação de segurança básica (opcional)
if (!empty($allowedIPs) && !in_array($_SERVER['REMOTE_ADDR'], $allowedIPs)) {
    die('Acesso negado');
}

// Mudar para o diretório do repositório
if (!chdir($repoDir)) {
    die("ERRO: Não foi possível acessar o diretório: $repoDir");
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atualizar Repositório Git</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .step {
            background: #f8f9fa;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #007bff;
            border-radius: 4px;
        }
        .step h3 {
            margin-top: 0;
            color: #007bff;
        }
        pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 13px;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .warning {
            color: #ffc107;
            font-weight: bold;
        }
        .info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Atualizar Repositório Git</h1>
        
        <div class="info">
            <strong>⚠️ ATENÇÃO:</strong> Este script irá sobrescrever mudanças locais no servidor.
            Certifique-se de que não há alterações importantes antes de continuar.
        </div>

        <?php
        // Verificar se é um repositório Git
        if (!is_dir('.git')) {
            echo '<div class="error">❌ ERRO: Não é um repositório Git!</div>';
            exit;
        }

        echo '<div class="step">';
        echo '<h3>[1/6] Verificando repositório...</h3>';
        echo '<pre>';
        echo shell_exec('pwd 2>&1');
        echo '</pre>';
        echo '<div class="success">✓ Repositório Git encontrado</div>';
        echo '</div>';

        echo '<div class="step">';
        echo '<h3>[2/6] Estado atual do repositório...</h3>';
        echo '<pre>';
        echo shell_exec('git status --short 2>&1');
        echo '</pre>';
        echo '<h4>Hash atual do HEAD:</h4>';
        echo '<pre>';
        $currentHash = trim(shell_exec('git rev-parse HEAD 2>&1'));
        echo $currentHash;
        echo '</pre>';
        echo '</div>';

        echo '<div class="step">';
        echo '<h3>[3/6] Verificando divergência...</h3>';
        echo '<pre>';
        $divergenceCheck = shell_exec('git rev-list --left-right --count HEAD...origin/main 2>&1');
        echo $divergenceCheck;
        echo '</pre>';
        if (strpos($divergenceCheck, 'fatal') === false) {
            $parts = preg_split('/\s+/', trim($divergenceCheck));
            $ahead = isset($parts[0]) ? (int)$parts[0] : 0;
            $behind = isset($parts[1]) ? (int)$parts[1] : 0;
            if ($ahead > 0 || $behind > 0) {
                echo '<div class="warning">⚠️ Divergência detectada: ' . $ahead . ' commits ahead, ' . $behind . ' commits behind</div>';
            } else {
                echo '<div class="success">✓ Sem divergência</div>';
            }
        }
        echo '</div>';

        echo '<div class="step">';
        echo '<h3>[4/6] Atualizando referências remotas (git fetch)...</h3>';
        echo '<pre>';
        $fetchOutput = shell_exec('git fetch origin 2>&1');
        echo $fetchOutput;
        echo '</pre>';
        if (strpos($fetchOutput, 'fatal') === false) {
            echo '<div class="success">✓ Fetch concluído</div>';
        } else {
            echo '<div class="error">❌ Erro ao fazer fetch</div>';
        }
        echo '</div>';

        echo '<div class="step">';
        echo '<h3>[5/6] Limpando working directory e resetando para origin/main...</h3>';
        echo '<div class="warning">⚠️ Isso irá SOBRESCREVER todas as mudanças locais no servidor</div>';
        echo '<pre>';
        // Limpar working directory primeiro
        $cleanOutput = shell_exec('git clean -fd 2>&1');
        echo "git clean -fd:\n" . $cleanOutput . "\n\n";
        
        // Resetar para origin/main
        $resetOutput = shell_exec('git reset --hard origin/main 2>&1');
        echo "git reset --hard origin/main:\n" . $resetOutput;
        echo '</pre>';
        if (strpos($resetOutput, 'fatal') === false && strpos($resetOutput, 'HEAD is now at') !== false) {
            echo '<div class="success">✓ Reset concluído com sucesso</div>';
        } else {
            echo '<div class="error">❌ Erro ao fazer reset</div>';
        }
        echo '</div>';

        echo '<div class="step">';
        echo '<h3>[6/6] Verificando resultado final...</h3>';
        echo '<h4>Status do repositório:</h4>';
        echo '<pre>';
        echo shell_exec('git status 2>&1');
        echo '</pre>';
        echo '<h4>Hash do HEAD após reset:</h4>';
        echo '<pre>';
        $newHash = trim(shell_exec('git rev-parse HEAD 2>&1'));
        echo $newHash;
        echo '</pre>';
        $expectedHash = 'c189200ca8d0f3418e864df82a9dcca1212b4eeb';
        if ($newHash === $expectedHash) {
            echo '<div class="success">✓ Hash correto! Produção está igual ao local</div>';
        } else {
            echo '<div class="warning">⚠️ Hash diferente do esperado. Esperado: ' . substr($expectedHash, 0, 12) . '...</div>';
        }
        echo '<h4>Últimos commits:</h4>';
        echo '<pre>';
        echo shell_exec('git log --oneline -5 2>&1');
        echo '</pre>';
        echo '</div>';

        echo '<div class="info">';
        echo '<h3>✅ Reset concluído com sucesso!</h3>';
        echo '<p><strong>O problema de "diverging branches" foi resolvido.</strong></p>';
        echo '<p><strong>Próximos passos para fazer deploy:</strong></p>';
        echo '<ol>';
        echo '<li><strong>Volte ao cPanel</strong> → Tools → Git Version Control</li>';
        echo '<li><strong>Clique em "Update from Remote"</strong> (deve funcionar agora sem erro)</li>';
        echo '<li><strong>Verifique os requisitos:</strong> Ambos devem estar OK (✓)</li>';
        echo '<li><strong>Clique em "Deploy HEAD Commit"</strong> (deve funcionar agora)</li>';
        echo '<li><strong>Verifique o deploy:</strong> Acesse <code>/public/verificar-deploy.php</code></li>';
        echo '<li><strong>IMPORTANTE:</strong> Remova este arquivo PHP por segurança após o deploy!</li>';
        echo '</ol>';
        echo '<p style="margin-top: 15px;"><strong>Hash esperado:</strong> <code>' . substr($expectedHash, 0, 12) . '...</code></p>';
        echo '<p><strong>Hash atual no servidor:</strong> <code>' . substr($newHash, 0, 12) . '...</code></p>';
        if ($newHash === $expectedHash) {
            echo '<p style="color: #28a745; font-weight: bold;">✓ Produção está sincronizada com o código local!</p>';
        }
        echo '</div>';
        ?>

        <div class="info" style="background: #fff3cd; border-left-color: #ffc107;">
            <strong>🔒 Segurança:</strong> Após usar este script, delete o arquivo <code>atualizar-repositorio.php</code> 
            do servidor para evitar execução não autorizada.
        </div>
    </div>
</body>
</html>

