# Script para limpar historico do Git removendo credenciais
# Execute: .\limpar-historio-simples.ps1

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Limpeza de Historico Git" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Verificar se estamos em um repositorio Git
if (-not (Test-Path .git)) {
    Write-Host "ERRO: Nao e um repositorio Git!" -ForegroundColor Red
    exit 1
}

# Desabilitar pager
$env:GIT_PAGER = ''
git config core.pager ''

Write-Host "ATENCAO: Isso reescrevera o historico do Git!" -ForegroundColor Yellow
Write-Host "Certifique-se de ter feito backup!" -ForegroundColor Yellow
Write-Host ""
$confirm = Read-Host "Deseja continuar? (S/N)"
if ($confirm -ne "S" -and $confirm -ne "s") {
    Write-Host "Operacao cancelada." -ForegroundColor Yellow
    exit 0
}

Write-Host ""
Write-Host "[1/4] Executando git filter-branch (pode demorar)..." -ForegroundColor Blue
Write-Host "  Isso pode levar varios minutos dependendo do tamanho do repositorio..." -ForegroundColor Gray

# Comando inline para substituir credenciais
# Usando escape adequado para PowerShell e Git
$filterCommand = @'
$files = @("docs/ALTERAR_USUARIO.md", "docs/ALTERAR_USUARIO_BANCO_CPANEL.md", "docs/testar_gateway_completo.sh", "README.md", "docs/ANALISE_SEGURANCA_SENHA.md", "docs/pixel-hub-plano-geral.md", "docs/RECOMENDACAO_REPOSITORIO_PRIVADO.md"); foreach ($file in $files) { if (Test-Path $file) { $content = Get-Content $file -Raw -ErrorAction SilentlyContinue; if ($content) { $original = $content; $content = $content -replace "Los@ngo#081081", "[USUARIO_REMOVIDO]"; $content = $content -replace 'USER="Los@ngo#081081"', 'USER="[CONFIGURE_USUARIO_AQUI]"'; $content = $content -replace "A senha sera `"Los@ngo#081081`"", "A senha sera a que voce configurou"; if ($content -ne $original) { [System.IO.File]::WriteAllText($file, $content, [System.Text.Encoding]::UTF8); git add $file } } } }
'@

# Escapar aspas para o comando git filter-branch
$escapedCommand = $filterCommand -replace '"', '`"' -replace '\$', '`$'

# Executar filter-branch
try {
    $fullCommand = "powershell -Command `"$escapedCommand`""
    git filter-branch --force --tree-filter $fullCommand --prune-empty --tag-name-filter cat -- --all 2>&1 | Out-Null
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  OK: Filter-branch executado com sucesso" -ForegroundColor Green
    } else {
        Write-Host "  AVISO: Filter-branch retornou codigo $LASTEXITCODE" -ForegroundColor Yellow
        Write-Host "  Tentando metodo alternativo..." -ForegroundColor Yellow
        
        # Metodo alternativo: usar sed ou substituicao direta
        Write-Host "  Usando substituicao direta nos arquivos..." -ForegroundColor Gray
        git filter-branch --force --tree-filter 'if [ -f docs/ALTERAR_USUARIO.md ]; then sed -i "s/Los@ngo#081081/[USUARIO_REMOVIDO]/g" docs/ALTERAR_USUARIO.md; git add docs/ALTERAR_USUARIO.md; fi' --prune-empty --tag-name-filter cat -- --all 2>&1 | Out-Null
    }
} catch {
    Write-Host "  ERRO: $_" -ForegroundColor Red
    Write-Host "  Considere usar BFG Repo-Cleaner como alternativa" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "[2/4] Limpando referencias antigas..." -ForegroundColor Blue
git for-each-ref --format='delete %(refname)' refs/original | git update-ref --stdin 2>&1 | Out-Null
git reflog expire --expire=now --all 2>&1 | Out-Null
git gc --prune=now --aggressive 2>&1 | Out-Null
Write-Host "  OK: Referencias limpas" -ForegroundColor Green

Write-Host ""
Write-Host "[3/4] Verificando se as credenciais foram removidas..." -ForegroundColor Blue
$found = git log --all -p 2>&1 | Select-String -Pattern "Los@ngo#081081" -Quiet
if ($found) {
    Write-Host "  AVISO: Ainda foram encontradas credenciais no historico!" -ForegroundColor Yellow
    Write-Host "  Considere usar BFG Repo-Cleaner para limpeza mais eficiente" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  Alternativa: Use git filter-repo (mais moderno e rapido)" -ForegroundColor Cyan
    Write-Host "    pip install git-filter-repo" -ForegroundColor Gray
    Write-Host "    git filter-repo --replace-text credenciais.txt" -ForegroundColor Gray
} else {
    Write-Host "  OK: Nenhuma credencial encontrada no historico" -ForegroundColor Green
}

Write-Host ""
Write-Host "[4/4] Proximos passos:" -ForegroundColor Blue
Write-Host "  1. Revise as alteracoes: git log --all" -ForegroundColor Gray
Write-Host "  2. Faca force push: git push --force --all" -ForegroundColor Gray
Write-Host "  3. Notifique colaboradores para refazer clone" -ForegroundColor Gray
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Limpeza concluida!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
