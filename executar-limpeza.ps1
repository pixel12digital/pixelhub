# Script simplificado para limpar histÃ³rico
$env:GIT_PAGER = ''
git config core.pager ''

Write-Host "Criando backup..." -ForegroundColor Yellow
$backup = "backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
git clone --mirror . $backup 2>&1 | Out-Null
Write-Host "Backup: $backup" -ForegroundColor Green

Write-Host "Executando git filter-branch..." -ForegroundColor Yellow

# Cria arquivo de substituiÃ§Ã£o temporÃ¡rio
$tempScript = "$env:TEMP\replace-asaas.ps1"
@'
$file = "test-asaas-key.php"
if (Test-Path $file) {
    $content = Get-Content $file -Raw
    $old = '[CHAVE_REMOVIDA_POR_SEGURANCA]'
    $new = 'Env::get(''ASAAS_API_KEY'')'
    if ($content.Contains($old)) {
        $content = $content.Replace($old, $new)
        [System.IO.File]::WriteAllText((Resolve-Path $file), $content, [System.Text.Encoding]::UTF8)
        git add $file 2>&1 | Out-Null
    }
}
'@ | Out-File $tempScript -Encoding UTF8

# Executa filter-branch
git filter-branch --force --tree-filter "powershell -ExecutionPolicy Bypass -File $tempScript" --prune-empty --tag-name-filter cat -- --all 2>&1 | Write-Host

# Limpa
git for-each-ref --format='delete %(refname)' refs/original 2>&1 | git update-ref --stdin 2>&1 | Out-Null
git reflog expire --expire=now --all 2>&1 | Out-Null
git gc --prune=now --aggressive 2>&1 | Out-Null

Remove-Item $tempScript -ErrorAction SilentlyContinue

Write-Host "Concluido! Execute: git push --force --all" -ForegroundColor Green

