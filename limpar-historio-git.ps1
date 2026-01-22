# Script para limpar histÃ³rico do Git removendo chave do Asaas exposta
# ATENÃ‡ÃƒO: Este script reescreve o histÃ³rico do Git!
# Execute apenas se tiver certeza e tenha feito backup!

Write-Host "========================================" -ForegroundColor Yellow
Write-Host "LIMPEZA DE HISTÃ“RICO DO GIT" -ForegroundColor Yellow
Write-Host "========================================" -ForegroundColor Yellow
Write-Host ""

# Chave do Asaas que precisa ser removida
$chaveAsaas = '[CHAVE_REMOVIDA_POR_SEGURANCA]'
$placeholder = 'ASAAS_API_KEY_FROM_ENV'

Write-Host "Este script irÃ¡:" -ForegroundColor Cyan
Write-Host "1. Substituir a chave do Asaas por placeholder em todo o histÃ³rico" -ForegroundColor White
Write-Host "2. Reescrever todos os commits que contÃªm a chave" -ForegroundColor White
Write-Host "3. Exigir force push para atualizar o repositÃ³rio remoto" -ForegroundColor White
Write-Host ""

$confirmacao = Read-Host "Deseja continuar? (digite 'SIM' para confirmar)"
if ($confirmacao -ne "SIM") {
    Write-Host "OperaÃ§Ã£o cancelada." -ForegroundColor Red
    exit
}

Write-Host ""
Write-Host "Criando backup do repositÃ³rio..." -ForegroundColor Yellow
$backupDir = "backup-git-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
git clone . $backupDir 2>&1 | Out-Null
if ($LASTEXITCODE -eq 0) {
    Write-Host "âœ“ Backup criado em: $backupDir" -ForegroundColor Green
} else {
    Write-Host "âš  Aviso: NÃ£o foi possÃ­vel criar backup completo" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Iniciando limpeza do histÃ³rico..." -ForegroundColor Yellow
Write-Host "Isso pode demorar alguns minutos..." -ForegroundColor Yellow
Write-Host ""

# Usa git filter-branch para substituir a chave em todo o histÃ³rico
# Escapa caracteres especiais para o sed
$chaveEscapada = $chaveAsaas -replace '\$', '\$' -replace '\[', '\[' -replace '\]', '\]' -replace '\(', '\(' -replace '\)', '\)' -replace '\{', '\{' -replace '\}', '\}' -replace '\+', '\+' -replace '\.', '\.' -replace '\*', '\*' -replace '\?', '\?' -replace '\^', '\^'

# No Windows, vamos usar PowerShell para fazer a substituiÃ§Ã£o
git filter-branch --force --index-filter "git ls-files -s | sed 's|`t| |' | cut -d' ' -f2 | git update-index --index-info" --prune-empty --tag-name-filter cat -- --all 2>&1 | Out-Null

# Agora vamos usar uma abordagem diferente: substituir diretamente no arquivo
Write-Host "Substituindo chave nos arquivos do histÃ³rico..." -ForegroundColor Yellow

# Cria um script temporÃ¡rio para fazer a substituiÃ§Ã£o
$scriptTemp = [System.IO.Path]::GetTempFileName()
$scriptContent = @"
#!/bin/sh
git ls-files | while read file; do
    if [ -f `"`$file`" ]; then
        sed -i 's|$($chaveAsaas)|$placeholder|g' `"`$file`"
    fi
done
"@

# Como estamos no Windows, vamos usar uma abordagem PowerShell
Write-Host "Usando git filter-branch com tree-filter..." -ForegroundColor Yellow

# Cria um script PowerShell temporÃ¡rio para fazer a substituiÃ§Ã£o
$psScript = [System.IO.Path]::GetTempFileName() + ".ps1"
@"
`$files = git ls-files
foreach (`$file in `$files) {
    if (Test-Path `$file) {
        `$content = Get-Content `$file -Raw
        if (`$content -match [regex]::Escape('$chaveAsaas')) {
            `$content = `$content -replace [regex]::Escape('$chaveAsaas'), '$placeholder'
            Set-Content `$file -Value `$content -NoNewline
            git add `$file
        }
    }
}
"@ | Out-File -FilePath $psScript -Encoding UTF8

# Usa git filter-branch com tree-filter
Write-Host "Executando git filter-branch (isso pode demorar)..." -ForegroundColor Yellow
git filter-branch --force --tree-filter "powershell -ExecutionPolicy Bypass -File $psScript" --prune-empty --tag-name-filter cat -- --all

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "âœ“ HistÃ³rico limpo com sucesso!" -ForegroundColor Green
    Write-Host ""
    Write-Host "PrÃ³ximos passos:" -ForegroundColor Cyan
    Write-Host "1. Verifique as alteraÃ§Ãµes: git log --all" -ForegroundColor White
    Write-Host "2. Se estiver satisfeito, force push: git push --force --all" -ForegroundColor White
    Write-Host "3. Force push tags: git push --force --tags" -ForegroundColor White
    Write-Host ""
    Write-Host "âš  ATENÃ‡ÃƒO: Force push reescreve o histÃ³rico remoto!" -ForegroundColor Yellow
    Write-Host "   Certifique-se de que ninguÃ©m mais estÃ¡ trabalhando no repositÃ³rio." -ForegroundColor Yellow
} else {
    Write-Host ""
    Write-Host "âœ— Erro ao limpar histÃ³rico. Verifique os logs acima." -ForegroundColor Red
    Write-Host "   O backup estÃ¡ em: $backupDir" -ForegroundColor Yellow
}

# Limpa arquivos temporÃ¡rios
Remove-Item $psScript -ErrorAction SilentlyContinue

Write-Host ""
Write-Host "Script concluÃ­do." -ForegroundColor Green

