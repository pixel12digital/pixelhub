# Script para remover chave do Asaas do histÃ³rico do Git
# Execute com: powershell -ExecutionPolicy Bypass -File limpar-chave-asaas.ps1

$ErrorActionPreference = "Stop"

Write-Host "========================================" -ForegroundColor Yellow
Write-Host "REMOVER CHAVE DO ASAAS DO HISTÃ“RICO GIT" -ForegroundColor Yellow
Write-Host "========================================" -ForegroundColor Yellow
Write-Host ""

# Chave que serÃ¡ removida
$chaveOriginal = '[CHAVE_REMOVIDA_POR_SEGURANCA]'
$substituicao = 'Env::get(''ASAAS_API_KEY'')'

Write-Host "ATENÃ‡ÃƒO: Esta operaÃ§Ã£o irÃ¡:" -ForegroundColor Red
Write-Host "  - Reescrever TODO o histÃ³rico do Git" -ForegroundColor White
Write-Host "  - Exigir force push para atualizar o remoto" -ForegroundColor White
Write-Host "  - Afetar TODOS os colaboradores do repositÃ³rio" -ForegroundColor White
Write-Host ""

$confirmacao = Read-Host "Digite 'CONFIRMAR' para continuar"
if ($confirmacao -ne "CONFIRMAR") {
    Write-Host "OperaÃ§Ã£o cancelada." -ForegroundColor Yellow
    exit
}

Write-Host ""
Write-Host "Criando backup..." -ForegroundColor Cyan
$backupDir = "backup-git-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
if (Test-Path $backupDir) {
    Remove-Item -Recurse -Force $backupDir
}
git clone --mirror . $backupDir 2>&1 | Out-Null
if ($LASTEXITCODE -eq 0) {
    Write-Host "âœ“ Backup criado: $backupDir" -ForegroundColor Green
} else {
    Write-Host "âš  Erro ao criar backup" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "Verificando se a chave existe no histÃ³rico..." -ForegroundColor Cyan
$encontrado = git log --all --source -S $chaveOriginal --oneline
if ($encontrado) {
    Write-Host "âœ“ Chave encontrada em commits do histÃ³rico" -ForegroundColor Yellow
    Write-Host "  Commits afetados:" -ForegroundColor White
    $encontrado | ForEach-Object { Write-Host "    $_" -ForegroundColor Gray }
} else {
    Write-Host "âš  Chave nÃ£o encontrada no histÃ³rico (pode jÃ¡ ter sido removida)" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Iniciando limpeza do histÃ³rico..." -ForegroundColor Cyan
Write-Host "Isso pode demorar vÃ¡rios minutos dependendo do tamanho do repositÃ³rio..." -ForegroundColor Yellow
Write-Host ""

# Cria script temporÃ¡rio para substituiÃ§Ã£o
$scriptTemp = Join-Path $env:TEMP "git-filter-$(Get-Random).ps1"
@"
`$ErrorActionPreference = 'SilentlyContinue'
`$arquivo = `$args[0]
if (Test-Path `$arquivo) {
    `$conteudo = Get-Content `$arquivo -Raw -ErrorAction SilentlyContinue
    if (`$conteudo -and `$conteudo.Contains('$chaveOriginal')) {
        `$conteudo = `$conteudo.Replace('$chaveOriginal', '$substituicao')
        [System.IO.File]::WriteAllText(`$arquivo, `$conteudo, [System.Text.Encoding]::UTF8)
        git add `$arquivo 2>&1 | Out-Null
    }
}
"@ | Out-File -FilePath $scriptTemp -Encoding UTF8

# Executa git filter-branch
Write-Host "Executando git filter-branch..." -ForegroundColor Cyan
git filter-branch --force --tree-filter "powershell -ExecutionPolicy Bypass -File $scriptTemp test-asaas-key.php" --prune-empty --tag-name-filter cat -- --all 2>&1 | Tee-Object -Variable output

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "âœ“ Limpeza concluÃ­da!" -ForegroundColor Green
    
    # Limpa referÃªncias antigas
    Write-Host ""
    Write-Host "Limpando referÃªncias antigas..." -ForegroundColor Cyan
    git for-each-ref --format='delete %(refname)' refs/original | git update-ref --stdin 2>&1 | Out-Null
    git reflog expire --expire=now --all 2>&1 | Out-Null
    git gc --prune=now --aggressive 2>&1 | Out-Null
    
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "LIMPEZA CONCLUÃDA COM SUCESSO!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "PrÃ³ximos passos:" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "1. Verifique se a chave foi removida:" -ForegroundColor White
    Write-Host "   git log --all -p | Select-String 'aact_prod'" -ForegroundColor Gray
    Write-Host ""
    Write-Host "2. Se estiver satisfeito, faÃ§a force push:" -ForegroundColor White
    Write-Host "   git push --force --all" -ForegroundColor Gray
    Write-Host "   git push --force --tags" -ForegroundColor Gray
    Write-Host ""
    Write-Host "3. Notifique TODOS os colaboradores para refazer o clone!" -ForegroundColor Yellow
    Write-Host ""
} else {
    Write-Host ""
    Write-Host "âœ— Erro durante a limpeza" -ForegroundColor Red
    Write-Host "   Verifique os logs acima" -ForegroundColor Yellow
    Write-Host "   Backup disponÃ­vel em: $backupDir" -ForegroundColor Yellow
}

# Remove script temporÃ¡rio
Remove-Item $scriptTemp -ErrorAction SilentlyContinue

Write-Host ""
Write-Host "Script finalizado." -ForegroundColor Green

