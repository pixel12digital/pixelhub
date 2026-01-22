@echo off
echo ========================================
echo LIMPANDO HISTORICO DO GIT
echo ========================================
echo.

REM Desabilita pager
set GIT_PAGER=
git config core.pager ""

echo Criando backup...
set BACKUP_DIR=backup-git-%date:~-4,4%%date:~-7,2%%date:~-10,2%-%time:~0,2%%time:~3,2%%time:~6,2%
set BACKUP_DIR=%BACKUP_DIR: =0%
git clone --mirror . %BACKUP_DIR% >nul 2>&1
if %errorlevel% equ 0 (
    echo Backup criado: %BACKUP_DIR%
) else (
    echo Aviso: Erro ao criar backup
)

echo.
echo Executando git filter-branch...
echo Isso pode demorar varios minutos...
echo.

REM Cria script PowerShell temporario para substituicao
set TEMP_SCRIPT=%TEMP%\git-replace-asaas.ps1
(
echo $file = "test-asaas-key.php"
echo if ^(Test-Path $file^) {
echo     $content = Get-Content $file -Raw
echo     $old = '[CHAVE_REMOVIDA_POR_SEGURANCA]'
echo     $new = 'Env::get^(''ASAAS_API_KEY''^)'
echo     if ^($content.Contains^($old^)^) {
echo         $content = $content.Replace^($old, $new^)
echo         [System.IO.File]::WriteAllText^((Resolve-Path $file^), $content, [System.Text.Encoding]::UTF8^)
echo         git add $file ^>nul 2^>^&1
echo     }
echo }
) > "%TEMP_SCRIPT%"

REM Executa filter-branch
git filter-branch --force --tree-filter "powershell -ExecutionPolicy Bypass -File %TEMP_SCRIPT%" --prune-empty --tag-name-filter cat -- --all >nul 2>&1

if %errorlevel% equ 0 (
    echo.
    echo Limpeza concluida!
    echo.
    echo Limpando referencias antigas...
    git for-each-ref --format="delete %%^(refname^)" refs/original 2>nul | git update-ref --stdin >nul 2>&1
    git reflog expire --expire=now --all >nul 2>&1
    git gc --prune=now --aggressive >nul 2>&1
    echo.
    echo ========================================
    echo LIMPEZA CONCLUIDA COM SUCESSO!
    echo ========================================
    echo.
    echo Proximos passos:
    echo 1. git push --force --all
    echo 2. git push --force --tags
    echo 3. Notifique colaboradores para refazer o clone
) else (
    echo.
    echo Erro durante a limpeza. Verifique os logs.
    echo Backup disponivel em: %BACKUP_DIR%
)

REM Remove script temporario
del "%TEMP_SCRIPT%" >nul 2>&1

echo.
echo Processo finalizado.
pause

