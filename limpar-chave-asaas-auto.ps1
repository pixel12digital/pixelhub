# Script automÃ¡tico para remover chave do Asaas do histÃ³rico do Git
# VersÃ£o nÃ£o-interativa - executa automaticamente

$ErrorActionPreference = "Stop"

Write-Host "========================================" -ForegroundColor Yellow
Write-Host "REMOVENDO CHAVE DO ASAAS DO HISTÃ“RICO" -ForegroundColor Yellow
Write-Host "========================================" -ForegroundColor Yellow
Write-Host ""

# Chave que serÃ¡ removida
$chaveOriginal = '[CHAVE_REMOVIDA_POR_SEGURANCA]'
$substituicao = 'Env::get(''ASAAS_API_KEY'')'

Write-Host "Criando backup..." -ForegroundColor Cyan
$backupDir = "backup-git-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
if (Test-Path $backupDir) {
    Remove-Item -Recurse -Force $backupDir
}
git clone --mirror . $backupDir 2>&1 | Out-Null
if ($LASTEXITCODE -eq 0) {
    Write-Host "âœ“ Backup criado: $backupDir" -ForegroundColor Green
} else {
    Write-Host "âš  Erro ao criar backup, continuando mesmo assim..." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Verificando se a chave existe no histÃ³rico..." -ForegroundColor Cyan
$encontrado = git log --all --source -S $chaveOriginal --oneline 2>&1
if ($encontrado -and $encontrado -notmatch "fatal") {
    Write-Host "âœ“ Chave encontrada no histÃ³rico" -ForegroundColor Yellow
} else {
    Write-Host "âš  Chave nÃ£o encontrada no histÃ³rico (pode jÃ¡ ter sido removida)" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Iniciando limpeza do histÃ³rico..." -ForegroundColor Cyan
Write-Host "Isso pode demorar..." -ForegroundColor Yellow
Write-Host ""

# Cria script temporÃ¡rio para substituiÃ§Ã£o
$scriptTemp = Join-Path $env:TEMP "git-filter-asaas-$(Get-Random).ps1"
@"
`$ErrorActionPreference = 'SilentlyContinue'
`$arquivo = 'test-asaas-key.php'
if (Test-Path `$arquivo) {
    `$conteudo = Get-Content `$arquivo -Raw -ErrorAction SilentlyContinue
    if (`$conteudo -and `$conteudo.Contains('$chaveOriginal')) {
        `$conteudo = `$conteudo.Replace('$chaveOriginal', '$substituicao')
        [System.IO.File]::WriteAllText((Resolve-Path `$arquivo), `$conteudo, [System.Text.Encoding]::UTF8)
        git add `$arquivo 2>&1 | Out-Null
    }
}
"@ | Out-File -FilePath $scriptTemp -Encoding UTF8

# Executa git filter-branch
Write-Host "Executando git filter-branch (isso pode demorar vÃ¡rios minutos)..." -ForegroundColor Cyan
$output = git filter-branch --force --tree-filter "powershell -ExecutionPolicy Bypass -File $scriptTemp" --prune-empty --tag-name-filter cat -- --all 2>&1

if ($LASTEXITCODE -eq 0 -or $output -match "Ref 'refs/heads/main' was rewritten") {
    Write-Host ""
    Write-Host "âœ“ Limpeza concluÃ­da!" -ForegroundColor Green
    
    # Limpa referÃªncias antigas
    Write-Host ""
    Write-Host "Limpando referÃªncias antigas..." -ForegroundColor Cyan
    git for-each-ref --format='delete %(refname)' refs/original 2>&1 | git update-ref --stdin 2>&1 | Out-Null
    git reflog expire --expire=now --all 2>&1 | Out-Null
    git gc --prune=now --aggressive 2>&1 | Out-Null
    
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "LIMPEZA CONCLUÃDA!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "Verificando se a chave foi removida..." -ForegroundColor Cyan
    $verificacao = git log --all -p 2>&1 | Select-String "[CHAVE_REMOVIDA_POR_SEGURANCA]" -Quiet
    if (-not $verificacao) {
        Write-Host "âœ“ Chave removida com sucesso do histÃ³rico!" -ForegroundColor Green
    } else {
        Write-Host "âš  Ainda pode haver referÃªncias Ã  chave" -ForegroundColor Yellow
    }
    
    Write-Host ""
    Write-Host "PrÃ³ximos passos:" -ForegroundColor Cyan
    Write-Host "1. Force push: git push --force --all" -ForegroundColor White
    Write-Host "2. Force push tags: git push --force --tags" -ForegroundColor White
    Write-Host "3. Notifique colaboradores para refazer o clone" -ForegroundColor Yellow
} else {
    Write-Host ""
    Write-Host "âš  Verifique o resultado acima" -ForegroundColor Yellow
    Write-Host "   Backup disponÃ­vel em: $backupDir" -ForegroundColor Yellow
}

# Remove script temporÃ¡rio
Remove-Item $scriptTemp -ErrorAction SilentlyContinue

Write-Host ""
Write-Host "Processo finalizado." -ForegroundColor Green

