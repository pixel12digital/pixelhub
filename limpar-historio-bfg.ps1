# Script alternativo usando BFG Repo-Cleaner (mais rapido e eficiente)
# Requer: Java instalado e BFG baixado
# Download: https://rtyley.github.io/bfg-repo-cleaner/

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Limpeza de Historico Git - BFG Method" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Verificar se estamos em um repositorio Git
if (-not (Test-Path .git)) {
    Write-Host "ERRO: Nao e um repositorio Git!" -ForegroundColor Red
    exit 1
}

# Verificar se Java esta instalado
$javaCheck = Get-Command java -ErrorAction SilentlyContinue
if (-not $javaCheck) {
    Write-Host "ERRO: Java nao encontrado!" -ForegroundColor Red
    Write-Host "BFG Repo-Cleaner requer Java instalado" -ForegroundColor Yellow
    Write-Host "Download: https://www.java.com/download/" -ForegroundColor Cyan
    exit 1
}

Write-Host "ATENCAO: Isso reescrevera o historico do Git!" -ForegroundColor Yellow
Write-Host "Certifique-se de ter feito backup!" -ForegroundColor Yellow
Write-Host ""
$confirm = Read-Host "Deseja continuar? (S/N)"
if ($confirm -ne "S" -and $confirm -ne "s") {
    Write-Host "Operacao cancelada." -ForegroundColor Yellow
    exit 0
}

Write-Host ""
Write-Host "[1/3] Criando arquivo de credenciais para remover..." -ForegroundColor Blue

# Criar arquivo com credenciais a serem removidas
$credenciaisFile = "credenciais-remover.txt"
@"
Los@ngo#081081==>[USUARIO_REMOVIDO]
Los@ngo#2024!Dev`$Secure==>[SENHA_REMOVIDA]
"@ | Out-File -FilePath $credenciaisFile -Encoding UTF8

Write-Host "  Arquivo criado: $credenciaisFile" -ForegroundColor Gray

Write-Host ""
Write-Host "[2/3] Verificando se BFG esta disponivel..." -ForegroundColor Blue

# Verificar se BFG esta no PATH ou na pasta atual
$bfgPath = Get-Command bfg -ErrorAction SilentlyContinue
if (-not $bfgPath) {
    $bfgJar = Get-ChildItem -Path . -Filter "bfg*.jar" -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($bfgJar) {
        $bfgPath = $bfgJar.FullName
        Write-Host "  BFG encontrado: $bfgPath" -ForegroundColor Green
    } else {
        Write-Host "  AVISO: BFG nao encontrado!" -ForegroundColor Yellow
        Write-Host "  Download: https://rtyley.github.io/bfg-repo-cleaner/" -ForegroundColor Cyan
        Write-Host "  Coloque o arquivo bfg.jar na pasta atual" -ForegroundColor Yellow
        exit 1
    }
} else {
    $bfgPath = "bfg"
    Write-Host "  BFG encontrado no PATH" -ForegroundColor Green
}

Write-Host ""
Write-Host "[3/3] Executando BFG Repo-Cleaner..." -ForegroundColor Blue
Write-Host "  Isso pode levar alguns minutos..." -ForegroundColor Gray

# Criar clone mirror para BFG trabalhar
$mirrorPath = "pixelhub-mirror.git"
if (Test-Path $mirrorPath) {
    Write-Host "  Removendo mirror antigo..." -ForegroundColor Gray
    Remove-Item -Recurse -Force $mirrorPath
}

Write-Host "  Criando clone mirror..." -ForegroundColor Gray
git clone --mirror . $mirrorPath 2>&1 | Out-Null

# Executar BFG
if ($bfgPath -like "*.jar") {
    java -jar $bfgPath --replace-text $credenciaisFile $mirrorPath
} else {
    & $bfgPath --replace-text $credenciaisFile $mirrorPath
}

if ($LASTEXITCODE -eq 0) {
    Write-Host "  OK: BFG executado com sucesso" -ForegroundColor Green
    
    # Limpar e compactar
    Write-Host "  Limpando referencias..." -ForegroundColor Gray
    Push-Location $mirrorPath
    git reflog expire --expire=now --all 2>&1 | Out-Null
    git gc --prune=now --aggressive 2>&1 | Out-Null
    Pop-Location
    
    Write-Host ""
    Write-Host "  Para aplicar as mudancas:" -ForegroundColor Cyan
    Write-Host "  1. cd $mirrorPath" -ForegroundColor Gray
    Write-Host "  2. git push --force" -ForegroundColor Gray
    Write-Host "  3. Ou copie o .git de volta: Copy-Item -Recurse $mirrorPath\.git .git" -ForegroundColor Gray
} else {
    Write-Host "  ERRO: BFG retornou codigo $LASTEXITCODE" -ForegroundColor Red
}

# Limpar arquivo temporario
Remove-Item -Path $credenciaisFile -ErrorAction SilentlyContinue

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Processo concluido!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan

