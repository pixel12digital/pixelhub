# Limpeza de historico arquivo por arquivo - Metodo mais confiavel
# Processa cada arquivo individualmente

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Limpeza de Historico - Arquivo por Arquivo" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

if (-not (Test-Path .git)) {
    Write-Host "ERRO: Nao e um repositorio Git!" -ForegroundColor Red
    exit 1
}

$env:GIT_PAGER = ''
git config core.pager ''

Write-Host "ATENCAO: Isso reescrevera o historico do Git!" -ForegroundColor Yellow
Write-Host ""
$confirm = Read-Host "Continuar? (S/N)"
if ($confirm -ne "S" -and $confirm -ne "s") {
    exit 0
}

# Lista de arquivos conhecidos com credenciais
$arquivos = @(
    "docs/ALTERAR_USUARIO.md",
    "docs/ALTERAR_USUARIO_BANCO_CPANEL.md",
    "docs/testar_gateway_completo.sh",
    "docs/ANALISE_SEGURANCA_SENHA.md",
    "docs/RECOMENDACAO_REPOSITORIO_PRIVADO.md"
)

Write-Host ""
Write-Host "Processando arquivos individualmente..." -ForegroundColor Blue
Write-Host ""

foreach ($arquivo in $arquivos) {
    Write-Host "  Processando: $arquivo" -ForegroundColor Gray
    
    # Script inline para este arquivo especifico
    $script = "if (Test-Path '$arquivo') { try { `$c = [System.IO.File]::ReadAllText('$arquivo', [System.Text.Encoding]::UTF8); `$o = `$c; `$c = `$c -replace 'Los@ngo#081081', '[USUARIO_REMOVIDO]'; `$c = `$c -replace 'USER=`"Los@ngo#081081`"', 'USER=`"[CONFIGURE_USUARIO_AQUI]`"'; `$c = `$c -replace 'A senha sera `"Los@ngo#081081`"', 'A senha sera a que voce configurou'; if (`$c -ne `$o) { [System.IO.File]::WriteAllText('$arquivo', `$c, [System.Text.Encoding]::UTF8); git add '$arquivo' 2>&1 | Out-Null } } catch {} }"
    
    git filter-branch --force --tree-filter "powershell -NoProfile -Command `"$script`"" --prune-empty --tag-name-filter cat -- --all 2>&1 | Out-Null
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "    OK" -ForegroundColor Green
    } else {
        Write-Host "    AVISO: Codigo $LASTEXITCODE" -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "Limpando referencias..." -ForegroundColor Blue
git for-each-ref --format='delete %(refname)' refs/original 2>&1 | git update-ref --stdin 2>&1 | Out-Null
git reflog expire --expire=now --all 2>&1 | Out-Null
git gc --prune=now --aggressive 2>&1 | Out-Null

Write-Host ""
Write-Host "Verificando resultado..." -ForegroundColor Blue
$found = git log --all -p 2>&1 | Select-String -Pattern "Los@ngo#081081" -Quiet
if ($found) {
    $count = (git log --all -p 2>&1 | Select-String -Pattern "Los@ngo#081081").Count
    Write-Host "  Ainda ha $count ocorrencias" -ForegroundColor Yellow
} else {
    Write-Host "  OK: Credenciais removidas!" -ForegroundColor Green
}

Write-Host ""
Write-Host "Concluido!" -ForegroundColor Green

