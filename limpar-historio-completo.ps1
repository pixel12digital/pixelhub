# Script para limpeza completa do historico usando git filter-branch
# Metodo robusto que funciona no Windows

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Limpeza Completa de Historico Git" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

if (-not (Test-Path .git)) {
    Write-Host "ERRO: Nao e um repositorio Git!" -ForegroundColor Red
    exit 1
}

$env:GIT_PAGER = ''
git config core.pager ''

Write-Host "ATENCAO: Isso reescrevera TODO o historico do Git!" -ForegroundColor Yellow
Write-Host "Certifique-se de ter feito backup!" -ForegroundColor Yellow
Write-Host ""
$confirm = Read-Host "Deseja continuar? (S/N)"
if ($confirm -ne "S" -and $confirm -ne "s") {
    Write-Host "Cancelado." -ForegroundColor Yellow
    exit 0
}

Write-Host ""
Write-Host "[1/5] Verificando credenciais no historico..." -ForegroundColor Blue
$count = (git log --all -p 2>&1 | Select-String -Pattern "Los@ngo#081081").Count
Write-Host "  Encontradas: $count ocorrencias" -ForegroundColor Yellow

Write-Host ""
Write-Host "[2/5] Criando script de substituicao..." -ForegroundColor Blue

# Criar script PowerShell que sera executado em cada commit
$scriptContent = @'
# Script para substituir credenciais em todos os arquivos
$files = Get-ChildItem -Recurse -File | Where-Object {
    $_.FullName -notmatch '\.git' -and
    $_.FullName -notmatch 'backup-git' -and
    $_.Extension -match '\.(md|sh|txt|php|sql)$'
}

foreach ($file in $files) {
    try {
        $content = [System.IO.File]::ReadAllText($file.FullName, [System.Text.Encoding]::UTF8)
        $original = $content
        
        # Substituir credenciais
        $content = $content -replace 'Los@ngo#081081', '[USUARIO_REMOVIDO]'
        $content = $content -replace 'Los@ngo#2024!Dev\$Secure', '[SENHA_REMOVIDA]'
        $content = $content -replace 'USER="Los@ngo#081081"', 'USER="[CONFIGURE_USUARIO_AQUI]"'
        $content = $content -replace "A senha sera `"Los@ngo#081081`"", "A senha sera a que voce configurou"
        
        if ($content -ne $original) {
            [System.IO.File]::WriteAllText($file.FullName, $content, [System.Text.Encoding]::UTF8)
            git add $file.FullName 2>&1 | Out-Null
        }
    } catch {
        # Ignorar erros de arquivos que nao podem ser lidos
    }
}
'@

$scriptFile = "temp-substituir-all.ps1"
$scriptContent | Out-File -FilePath $scriptFile -Encoding UTF8

Write-Host "  Script criado: $scriptFile" -ForegroundColor Green

Write-Host ""
Write-Host "[3/5] Executando git filter-branch..." -ForegroundColor Blue
Write-Host "  Isso pode levar VÁRIOS MINUTOS dependendo do tamanho do repositorio..." -ForegroundColor Yellow
Write-Host "  Por favor, aguarde..." -ForegroundColor Gray

# Executar filter-branch
$filterCmd = "powershell -ExecutionPolicy Bypass -NoProfile -File $scriptFile"
$result = git filter-branch --force --tree-filter $filterCmd --prune-empty --tag-name-filter cat -- --all 2>&1

if ($LASTEXITCODE -eq 0) {
    Write-Host "  OK: Filter-branch executado com sucesso" -ForegroundColor Green
} else {
    Write-Host "  AVISO: Filter-branch retornou codigo $LASTEXITCODE" -ForegroundColor Yellow
    Write-Host "  Tentando metodo alternativo..." -ForegroundColor Yellow
    
    # Metodo alternativo: substituir arquivo por arquivo
    $filesToClean = @(
        "docs/ALTERAR_USUARIO.md",
        "docs/ALTERAR_USUARIO_BANCO_CPANEL.md",
        "docs/testar_gateway_completo.sh",
        "docs/ANALISE_SEGURANCA_SENHA.md"
    )
    
    foreach ($file in $filesToClean) {
        $inlineScript = "if (Test-Path '$file') { `$c = [System.IO.File]::ReadAllText('$file'); `$c = `$c -replace 'Los@ngo#081081', '[USUARIO_REMOVIDO]'; [System.IO.File]::WriteAllText('$file', `$c); git add '$file' }"
        git filter-branch --force --tree-filter "powershell -NoProfile -Command `"$inlineScript`"" --prune-empty --tag-name-filter cat -- --all 2>&1 | Out-Null
    }
}

# Remover script temporario
Remove-Item -Path $scriptFile -ErrorAction SilentlyContinue

Write-Host ""
Write-Host "[4/5] Limpando referencias antigas..." -ForegroundColor Blue
git for-each-ref --format='delete %(refname)' refs/original 2>&1 | git update-ref --stdin 2>&1 | Out-Null
git reflog expire --expire=now --all 2>&1 | Out-Null
git gc --prune=now --aggressive 2>&1 | Out-Null
Write-Host "  OK: Referencias limpas" -ForegroundColor Green

Write-Host ""
Write-Host "[5/5] Verificando resultado..." -ForegroundColor Blue
$found = git log --all -p 2>&1 | Select-String -Pattern "Los@ngo#081081" -Quiet
if ($found) {
    $newCount = (git log --all -p 2>&1 | Select-String -Pattern "Los@ngo#081081").Count
    Write-Host "  AVISO: Ainda ha $newCount ocorrencias no historico" -ForegroundColor Yellow
    Write-Host "  Pode ser necessario usar git filter-repo ou BFG para limpeza completa" -ForegroundColor Yellow
} else {
    Write-Host "  OK: Credenciais removidas do historico!" -ForegroundColor Green
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Proximos passos:" -ForegroundColor Blue
Write-Host "  1. Verificar: git log --all -p | Select-String 'Los@ngo'" -ForegroundColor Gray
Write-Host "  2. Force push: git push --force --all" -ForegroundColor Gray
Write-Host "  3. Notificar colaboradores" -ForegroundColor Gray
Write-Host ""
Write-Host "Limpeza concluida!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan

