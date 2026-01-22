# Script manual passo a passo para limpar historico
# Execute cada passo conforme instrucoes

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Limpeza Manual de Historico Git" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "Este script ira guiar voce passo a passo" -ForegroundColor Yellow
Write-Host ""

# Passo 1: Verificar credenciais no historico
Write-Host "[PASSO 1] Verificando credenciais no historico..." -ForegroundColor Blue
$found = git log --all -p 2>&1 | Select-String -Pattern "Los@ngo#081081" -Quiet
if ($found) {
    Write-Host "  AVISO: Credenciais encontradas no historico" -ForegroundColor Yellow
    $count = (git log --all -p 2>&1 | Select-String -Pattern "Los@ngo#081081").Count
    Write-Host "  Total de ocorrencias: $count" -ForegroundColor Yellow
} else {
    Write-Host "  OK: Nenhuma credencial encontrada" -ForegroundColor Green
    exit 0
}

Write-Host ""
Write-Host "[PASSO 2] Opcoes disponiveis:" -ForegroundColor Blue
Write-Host ""
Write-Host "  Opcao A: Usar git filter-repo (RECOMENDADO - mais moderno)" -ForegroundColor Cyan
Write-Host "    1. pip install git-filter-repo" -ForegroundColor Gray
Write-Host "    2. git filter-repo --replace-text credenciais.txt" -ForegroundColor Gray
Write-Host ""
Write-Host "  Opcao B: Usar BFG Repo-Cleaner (mais rapido)" -ForegroundColor Cyan
Write-Host "    1. Baixar: https://rtyley.github.io/bfg-repo-cleaner/" -ForegroundColor Gray
Write-Host "    2. java -jar bfg.jar --replace-text credenciais.txt" -ForegroundColor Gray
Write-Host ""
Write-Host "  Opcao C: Reescrever historico manualmente" -ForegroundColor Cyan
Write-Host "    (Ver INSTRUCOES_LIMPEZA_HISTORICO.md)" -ForegroundColor Gray
Write-Host ""

# Criar arquivo de credenciais para BFG/filter-repo
Write-Host "[PASSO 3] Criando arquivo credenciais.txt para BFG/filter-repo..." -ForegroundColor Blue
$credenciaisFile = "credenciais.txt"
@"
Los@ngo#081081==>[USUARIO_REMOVIDO]
Los@ngo#2024!Dev`$Secure==>[SENHA_REMOVIDA]
"@ | Out-File -FilePath $credenciaisFile -Encoding UTF8 -NoNewline

Write-Host "  Arquivo criado: $credenciaisFile" -ForegroundColor Green
Write-Host "  Conteudo:" -ForegroundColor Gray
Get-Content $credenciaisFile | ForEach-Object { Write-Host "    $_" -ForegroundColor Gray }

Write-Host ""
Write-Host "[PASSO 4] Instrucoes para usar git filter-repo:" -ForegroundColor Blue
Write-Host ""
Write-Host "  # Instalar (se nao tiver)" -ForegroundColor Gray
Write-Host "  pip install git-filter-repo" -ForegroundColor White
Write-Host ""
Write-Host "  # Executar limpeza" -ForegroundColor Gray
Write-Host "  git filter-repo --replace-text credenciais.txt" -ForegroundColor White
Write-Host ""
Write-Host "  # Verificar resultado" -ForegroundColor Gray
Write-Host "  git log --all -p | Select-String 'Los@ngo'" -ForegroundColor White
Write-Host ""
Write-Host "  # Force push" -ForegroundColor Gray
Write-Host "  git push --force --all" -ForegroundColor White
Write-Host ""

Write-Host "[PASSO 5] Instrucoes para usar BFG Repo-Cleaner:" -ForegroundColor Blue
Write-Host ""
Write-Host "  # Baixar BFG" -ForegroundColor Gray
Write-Host "  # https://rtyley.github.io/bfg-repo-cleaner/" -ForegroundColor White
Write-Host ""
Write-Host "  # Criar clone mirror" -ForegroundColor Gray
Write-Host "  git clone --mirror . pixelhub-mirror.git" -ForegroundColor White
Write-Host ""
Write-Host "  # Executar BFG" -ForegroundColor Gray
Write-Host "  java -jar bfg.jar --replace-text credenciais.txt pixelhub-mirror.git" -ForegroundColor White
Write-Host ""
Write-Host "  # Limpar e aplicar" -ForegroundColor Gray
Write-Host "  cd pixelhub-mirror.git" -ForegroundColor White
Write-Host "  git reflog expire --expire=now --all" -ForegroundColor White
Write-Host "  git gc --prune=now --aggressive" -ForegroundColor White
Write-Host "  git push --force" -ForegroundColor White
Write-Host ""

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Arquivo credenciais.txt criado!" -ForegroundColor Green
Write-Host "Escolha uma das opcoes acima para continuar" -ForegroundColor Yellow
Write-Host "========================================" -ForegroundColor Cyan

