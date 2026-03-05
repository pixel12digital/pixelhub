<?php
/**
 * Script de deploy automático via web
 * Acesse: https://hub.pixel12digital.com.br/deploy.php?secret=DEPLOY_SECRET_2026
 */

// Segurança: apenas com secret correto
$secret = $_GET['secret'] ?? '';
if ($secret !== 'DEPLOY_SECRET_2026') {
    http_response_code(403);
    die('Acesso negado');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== DEPLOY AUTOMÁTICO - PIXELHUB ===\n\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n\n";

// Diretório do projeto
$projectDir = dirname(__DIR__);
chdir($projectDir);

echo "Diretório do projeto: {$projectDir}\n\n";

// 1. Git pull
echo "--- EXECUTANDO GIT PULL ---\n";
$output = [];
$returnVar = 0;
exec('git pull origin main 2>&1', $output, $returnVar);

foreach ($output as $line) {
    echo $line . "\n";
}

if ($returnVar !== 0) {
    echo "\n❌ ERRO ao executar git pull (código: {$returnVar})\n";
    exit(1);
}

echo "\n✅ Git pull executado com sucesso!\n\n";

// 2. Verifica status
echo "--- STATUS DO GIT ---\n";
$output = [];
exec('git status 2>&1', $output);
foreach ($output as $line) {
    echo $line . "\n";
}

echo "\n--- ÚLTIMO COMMIT ---\n";
$output = [];
exec('git log -1 --oneline 2>&1', $output);
foreach ($output as $line) {
    echo $line . "\n";
}

echo "\n✅ DEPLOY CONCLUÍDO COM SUCESSO!\n";
echo "\nPróximo passo: Teste o envio via Meta API novamente.\n";
