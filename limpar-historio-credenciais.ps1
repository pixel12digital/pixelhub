# Script para limpar histórico do Git removendo credenciais expostas
# Execute: .\limpar-historio-credenciais.ps1

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Limpeza de Histórico Git - Credenciais" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Verificar se estamos em um repositório Git
if (-not (Test-Path .git)) {
    Write-Host "ERRO: Não é um repositório Git!" -ForegroundColor Red
    exit 1
}

Write-Host "⚠️  ATENÇÃO: Este script irá reescrever o histórico do Git!" -ForegroundColor Yellow
Write-Host "⚠️  Certifique-se de ter feito backup antes de continuar!" -ForegroundColor Yellow
Write-Host ""
$confirm = Read-Host "Deseja continuar? (S/N)"
if ($confirm -ne "S" -and $confirm -ne "s") {
    Write-Host "Operação cancelada." -ForegroundColor Yellow
    exit 0
}

Write-Host ""
Write-Host "[1/5] Desabilitando pager do Git..." -ForegroundColor Blue
$env:GIT_PAGER = ''
git config core.pager ''

Write-Host "[2/5] Removendo credenciais do histórico..." -ForegroundColor Blue

# Lista de padrões a serem removidos
$patterns = @(
    "Los@ngo#081081",
    "Los@ngo#2024!Dev`$Secure",
    "admin@pixel12.test",
    "123456"
)

# Criar arquivo temporário com padrões
$patternsFile = "patterns-to-remove.txt"
$patterns | ForEach-Object { Add-Content -Path $patternsFile -Value $_ }

Write-Host "   Padrões a remover:" -ForegroundColor Gray
$patterns | ForEach-Object { Write-Host "   - $_" -ForegroundColor Gray }

# Usar git filter-branch para remover credenciais
# Nota: Isso pode demorar dependendo do tamanho do repositório
Write-Host ""
Write-Host "   Executando git filter-branch (isso pode demorar)..." -ForegroundColor Gray

# Função para substituir em todos os arquivos
$filterScript = @"
`$patterns = @('Los@ngo#081081', 'Los@ngo#2024!Dev`$Secure')
`$files = Get-ChildItem -Recurse -File | Where-Object { `$_.FullName -notmatch '\.git' -and `$_.FullName -notmatch 'backup-git' }
foreach (`$file in `$files) {
    if (Test-Path `$file.FullName) {
        `$content = Get-Content `$file.FullName -Raw -ErrorAction SilentlyContinue
        if (`$content) {
            `$modified = `$false
            foreach (`$pattern in `$patterns) {
                if (`$content -match [regex]::Escape(`$pattern)) {
                    `$content = `$content -replace [regex]::Escape(`$pattern), '[CREDENCIAL_REMOVIDA]'
                    `$modified = `$true
                }
            }
            if (`$modified) {
                [System.IO.File]::WriteAllText(`$file.FullName, `$content, [System.Text.Encoding]::UTF8)
                git add `$file.FullName
            }
        }
    }
}
"@

# Executar filter-branch
try {
    git filter-branch --force --tree-filter "powershell -Command `"$filterScript`"" --prune-empty --tag-name-filter cat -- --all
    
    Write-Host "   ✓ Filter-branch executado com sucesso" -ForegroundColor Green
} catch {
    Write-Host "   ⚠️  Erro ao executar filter-branch: $_" -ForegroundColor Yellow
    Write-Host "   Continuando com limpeza manual..." -ForegroundColor Yellow
}

# Limpar arquivo temporário
Remove-Item -Path $patternsFile -ErrorAction SilentlyContinue

Write-Host ""
Write-Host "[3/5] Limpando referências antigas..." -ForegroundColor Blue
git for-each-ref --format='delete %(refname)' refs/original | git update-ref --stdin
git reflog expire --expire=now --all
git gc --prune=now --aggressive

Write-Host ""
Write-Host "[4/5] Verificando se as credenciais foram removidas..." -ForegroundColor Blue
$found = git log --all -p | Select-String -Pattern "Los@ngo#081081|Los@ngo#2024!Dev`$Secure"
if ($found) {
    Write-Host "   ⚠️  Ainda foram encontradas credenciais no histórico!" -ForegroundColor Yellow
    Write-Host "   Considere usar BFG Repo-Cleaner para limpeza mais eficiente" -ForegroundColor Yellow
} else {
    Write-Host "   ✓ Nenhuma credencial encontrada no histórico" -ForegroundColor Green
}

Write-Host ""
Write-Host "[5/5] Próximos passos:" -ForegroundColor Blue
Write-Host "   1. Revise as alterações: git log --all" -ForegroundColor Gray
Write-Host "   2. Faça force push: git push --force --all" -ForegroundColor Gray
Write-Host "   3. Notifique colaboradores para refazer clone" -ForegroundColor Gray
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Limpeza concluída!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

