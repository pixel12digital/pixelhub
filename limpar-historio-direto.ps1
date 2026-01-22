# Script direto para limpar historico - Metodo mais simples
# Usa substituicao direta nos commits

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Limpeza de Historico Git - Metodo Direto" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

if (-not (Test-Path .git)) {
    Write-Host "ERRO: Nao e um repositorio Git!" -ForegroundColor Red
    exit 1
}

$env:GIT_PAGER = ''
git config core.pager ''

Write-Host "ATENCAO: Isso reescrevera o historico do Git!" -ForegroundColor Yellow
Write-Host "Certifique-se de ter feito backup!" -ForegroundColor Yellow
Write-Host ""
$confirm = Read-Host "Deseja continuar? (S/N)"
if ($confirm -ne "S" -and $confirm -ne "s") {
    Write-Host "Cancelado." -ForegroundColor Yellow
    exit 0
}

Write-Host ""
Write-Host "[1/3] Substituindo credenciais nos arquivos atuais..." -ForegroundColor Blue

# Lista de arquivos e substituicoes
$substituicoes = @{
    "docs/ALTERAR_USUARIO.md" = @{
        "Los@ngo#081081" = "[USUARIO_ANTIGO]"
    }
    "docs/ALTERAR_USUARIO_BANCO_CPANEL.md" = @{
        "Los@ngo#081081" = "[SENHA_REMOVIDA]"
        "A senha sera `"Los@ngo#081081`"" = "A senha sera a que voce configurou"
    }
    "docs/testar_gateway_completo.sh" = @{
        'USER="Los@ngo#081081"' = 'USER="[CONFIGURE_USUARIO_AQUI]"'
    }
}

foreach ($arquivo in $substituicoes.Keys) {
    if (Test-Path $arquivo) {
        $content = Get-Content $arquivo -Raw -Encoding UTF8
        $original = $content
        
        foreach ($pattern in $substituicoes[$arquivo].Keys) {
            $replacement = $substituicoes[$arquivo][$pattern]
            $content = $content -replace [regex]::Escape($pattern), $replacement
        }
        
        if ($content -ne $original) {
            Set-Content -Path $arquivo -Value $content -Encoding UTF8 -NoNewline
            git add $arquivo
            Write-Host "  OK: $arquivo" -ForegroundColor Green
        }
    }
}

Write-Host ""
Write-Host "[2/3] Fazendo commit das correcoes..." -ForegroundColor Blue
git commit -m "Seguranca: Remover credenciais expostas" 2>&1 | Out-Null
Write-Host "  OK: Commit realizado" -ForegroundColor Green

Write-Host ""
Write-Host "[3/3] Limpando historico usando git filter-branch..." -ForegroundColor Blue
Write-Host "  Isso pode demorar varios minutos..." -ForegroundColor Gray

# Usar metodo mais simples: substituir diretamente usando PowerShell inline
$scriptInline = 'foreach($f in @("docs/ALTERAR_USUARIO.md","docs/ALTERAR_USUARIO_BANCO_CPANEL.md","docs/testar_gateway_completo.sh")){if(Test-Path $f){$c=[System.IO.File]::ReadAllText($f);$c=$c.Replace("Los@ngo#081081","[USUARIO_REMOVIDO]");$c=$c.Replace(''USER="Los@ngo#081081"'',''USER="[CONFIGURE_USUARIO_AQUI]"'');[System.IO.File]::WriteAllText($f,$c);git add $f}}'

# Executar filter-branch
git filter-branch --force --tree-filter "powershell -NoProfile -Command `"$scriptInline`"" --prune-empty --tag-name-filter cat -- --all 2>&1 | Out-Null

if ($LASTEXITCODE -eq 0) {
    Write-Host "  OK: Filter-branch executado" -ForegroundColor Green
} else {
    Write-Host "  AVISO: Filter-branch pode ter tido problemas" -ForegroundColor Yellow
}

# Limpar referencias
Write-Host "  Limpando referencias antigas..." -ForegroundColor Gray
git for-each-ref --format='delete %(refname)' refs/original | git update-ref --stdin 2>&1 | Out-Null
git reflog expire --expire=now --all 2>&1 | Out-Null
git gc --prune=now --aggressive 2>&1 | Out-Null

Write-Host ""
Write-Host "Verificando resultado..." -ForegroundColor Blue
$found = git log --all -p 2>&1 | Select-String -Pattern "Los@ngo#081081" -Quiet
if ($found) {
    Write-Host "  AVISO: Ainda ha credenciais no historico" -ForegroundColor Yellow
    Write-Host "  Recomendacao: Use BFG Repo-Cleaner (limpar-historio-bfg.ps1)" -ForegroundColor Cyan
} else {
    Write-Host "  OK: Credenciais removidas do historico" -ForegroundColor Green
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Proximos passos:" -ForegroundColor Blue
Write-Host "  1. Revise: git log --all" -ForegroundColor Gray
Write-Host "  2. Force push: git push --force --all" -ForegroundColor Gray
Write-Host "  3. Notifique colaboradores" -ForegroundColor Gray
Write-Host ""
Write-Host "Concluido!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan

