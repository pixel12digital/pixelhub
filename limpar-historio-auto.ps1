# Script automatico para limpar historico - Sem confirmacao
# Execute: .\limpar-historio-auto.ps1

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Limpeza de Historico Git - Automatico" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

if (-not (Test-Path .git)) {
    Write-Host "ERRO: Nao e um repositorio Git!" -ForegroundColor Red
    exit 1
}

$env:GIT_PAGER = ''
git config core.pager ''

Write-Host "[1/4] Substituindo credenciais nos arquivos atuais..." -ForegroundColor Blue

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

$modified = $false
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
            git add $arquivo 2>&1 | Out-Null
            Write-Host "  OK: $arquivo" -ForegroundColor Green
            $modified = $true
        }
    }
}

if ($modified) {
    Write-Host ""
    Write-Host "[2/4] Fazendo commit das correcoes..." -ForegroundColor Blue
    git commit -m "Seguranca: Remover credenciais expostas" 2>&1 | Out-Null
    Write-Host "  OK: Commit realizado" -ForegroundColor Green
} else {
    Write-Host "  Nenhuma alteracao necessaria nos arquivos atuais" -ForegroundColor Gray
}

Write-Host ""
Write-Host "[3/4] Limpando historico usando git filter-branch..." -ForegroundColor Blue
Write-Host "  Isso pode demorar varios minutos..." -ForegroundColor Gray

# Script inline para substituir credenciais
$scriptInline = 'foreach($f in @("docs/ALTERAR_USUARIO.md","docs/ALTERAR_USUARIO_BANCO_CPANEL.md","docs/testar_gateway_completo.sh")){if(Test-Path $f){try{$c=[System.IO.File]::ReadAllText($f);$o=$c;$c=$c.Replace("Los@ngo#081081","[USUARIO_REMOVIDO]");$c=$c.Replace(''USER="Los@ngo#081081"'',''USER="[CONFIGURE_USUARIO_AQUI]"'');if($c -ne $o){[System.IO.File]::WriteAllText($f,$c);git add $f}}catch{}}}'

# Executar filter-branch
$filterCmd = "powershell -NoProfile -Command `"$scriptInline`""
git filter-branch --force --tree-filter $filterCmd --prune-empty --tag-name-filter cat -- --all 2>&1 | Out-Null

if ($LASTEXITCODE -eq 0) {
    Write-Host "  OK: Filter-branch executado" -ForegroundColor Green
} else {
    Write-Host "  AVISO: Filter-branch retornou codigo $LASTEXITCODE" -ForegroundColor Yellow
    Write-Host "  Tentando metodo alternativo com sed..." -ForegroundColor Yellow
    
    # Metodo alternativo usando sed (se disponivel) ou substituicao manual
    git filter-branch --force --tree-filter 'if [ -f docs/ALTERAR_USUARIO.md ]; then sed -i "s/Los@ngo#081081/[USUARIO_REMOVIDO]/g" docs/ALTERAR_USUARIO.md 2>/dev/null || true; git add docs/ALTERAR_USUARIO.md 2>/dev/null || true; fi' --prune-empty --tag-name-filter cat -- --all 2>&1 | Out-Null
}

# Limpar referencias
Write-Host "  Limpando referencias antigas..." -ForegroundColor Gray
git for-each-ref --format='delete %(refname)' refs/original 2>&1 | git update-ref --stdin 2>&1 | Out-Null
git reflog expire --expire=now --all 2>&1 | Out-Null
git gc --prune=now --aggressive 2>&1 | Out-Null

Write-Host ""
Write-Host "[4/4] Verificando resultado..." -ForegroundColor Blue
$found = git log --all -p 2>&1 | Select-String -Pattern "Los@ngo#081081" -Quiet
if ($found) {
    Write-Host "  AVISO: Ainda ha credenciais no historico" -ForegroundColor Yellow
    Write-Host "  Recomendacao: Use BFG Repo-Cleaner (limpar-historio-bfg.ps1)" -ForegroundColor Cyan
    Write-Host "  Ou use git filter-repo (mais moderno)" -ForegroundColor Cyan
} else {
    Write-Host "  OK: Credenciais removidas do historico" -ForegroundColor Green
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Proximos passos:" -ForegroundColor Blue
Write-Host "  1. Revise: git log --all | Select-String 'Los@ngo'" -ForegroundColor Gray
Write-Host "  2. Force push: git push --force --all" -ForegroundColor Gray
Write-Host "  3. Notifique colaboradores para refazer clone" -ForegroundColor Gray
Write-Host ""
Write-Host "Limpeza concluida!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan

