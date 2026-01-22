# ðŸš€ Guia de ExecuÃ§Ã£o Manual - Limpeza do HistÃ³rico Git

## ðŸ“‹ Resumo do que foi feito

âœ… **CÃ³digo corrigido:**
- Chave do Asaas removida de `test-asaas-key.php`
- Senhas hardcoded removidas dos scripts
- DocumentaÃ§Ã£o atualizada com placeholders
- `.gitignore` atualizado

âš ï¸ **Pendente:**
- Limpeza do histÃ³rico do Git (chave ainda estÃ¡ em commits antigos)
- RevogaÃ§Ã£o da chave do Asaas no painel

## ðŸ”§ Executar Limpeza do HistÃ³rico - Passo a Passo

### MÃ©todo 1: PowerShell (Recomendado para Windows)

1. **Abra o PowerShell como Administrador**

2. **Navegue atÃ© o diretÃ³rio do projeto:**
   ```powershell
   cd C:\xampp\htdocs\painel.pixel12digital
   ```

3. **Desabilite o pager do Git:**
   ```powershell
   $env:GIT_PAGER = ''
   git config core.pager ''
   ```

4. **Crie um backup:**
   ```powershell
   $backup = "backup-git-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
   git clone --mirror . $backup
   Write-Host "Backup criado em: $backup"
   ```

5. **Execute a limpeza:**
   ```powershell
   git filter-branch --force --tree-filter "powershell -Command \"if (Test-Path test-asaas-key.php) { `$c = Get-Content test-asaas-key.php -Raw; `$c = `$c -replace '\\`[CHAVE_REMOVIDA_POR_SEGURANCA]', 'Env::get(''ASAAS_API_KEY'')'; [System.IO.File]::WriteAllText((Resolve-Path test-asaas-key.php), `$c, [System.Text.Encoding]::UTF8); git add test-asaas-key.php }\" " --prune-empty --tag-name-filter cat -- --all
   ```

   **Nota:** Isso pode demorar 5-30 minutos dependendo do tamanho do repositÃ³rio.

6. **Limpe referÃªncias antigas:**
   ```powershell
   git for-each-ref --format='delete %(refname)' refs/original | git update-ref --stdin
   git reflog expire --expire=now --all
   git gc --prune=now --aggressive
   ```

7. **Verifique se funcionou:**
   ```powershell
   git log --all -p | Select-String "[CHAVE_REMOVIDA_POR_SEGURANCA]"
   ```
   
   Se nÃ£o retornar nada, a chave foi removida! âœ…

8. **Force push (CUIDADO!):**
   ```powershell
   git push --force --all
   git push --force --tags
   ```

### MÃ©todo 2: Usar o Script Batch

Execute o arquivo `limpar-git.bat` que foi criado:

```cmd
limpar-git.bat
```

### MÃ©todo 3: BFG Repo-Cleaner (Mais RÃ¡pido)

1. **Baixe BFG:** https://rtyley.github.io/bfg-repo-cleaner/
   - Ou use Chocolatey: `choco install bfg`

2. **Crie arquivo `chaves.txt`:**
   ```
   [CHAVE_REMOVIDA_POR_SEGURANCA]==>ASAAS_API_KEY_FROM_ENV
   ```

3. **Execute:**
   ```bash
   git clone --mirror https://github.com/pixel12digital/pixelhub.git pixelhub-mirror.git
   bfg --replace-text chaves.txt pixelhub-mirror.git
   cd pixelhub-mirror.git
   git reflog expire --expire=now --all
   git gc --prune=now --aggressive
   git push --force
   ```

## âš ï¸ AÃ§Ãµes Urgentes

### 1. Revogar Chave do Asaas (FAÃ‡A AGORA!)

1. Acesse: https://www.asaas.com/
2. VÃ¡ em **ConfiguraÃ§Ãµes** â†’ **API**
3. **Revogue** a chave: `[CHAVE_REMOVIDA_POR_SEGURANCA]`
4. **Gere uma nova chave**
5. **Atualize no `.env`** como `ASAAS_API_KEY`

### 2. Fazer Commit das CorreÃ§Ãµes

```bash
git add .
git commit -m "SeguranÃ§a: Remover dados sensÃ­veis do cÃ³digo e documentaÃ§Ã£o"
git push
```

### 3. Notificar Colaboradores

ApÃ³s o force push, envie este aviso:

```
âš ï¸ ATENÃ‡ÃƒO: O histÃ³rico do Git foi reescrito por questÃµes de seguranÃ§a.

Por favor, refaÃ§a o clone do repositÃ³rio:

git clone https://github.com/pixel12digital/pixelhub.git

Ou, se jÃ¡ tiver um clone local:

cd seu-repositorio
git fetch origin
git reset --hard origin/main
```

## âœ… Checklist Final

- [ ] Revogar chave do Asaas no painel
- [ ] Gerar nova chave do Asaas
- [ ] Atualizar `.env` com nova chave
- [ ] Fazer commit das correÃ§Ãµes no cÃ³digo
- [ ] Executar limpeza do histÃ³rico (escolher mÃ©todo acima)
- [ ] Verificar que a chave foi removida
- [ ] Fazer force push
- [ ] Notificar colaboradores

## ðŸ†˜ Se Algo Der Errado

**Restaure do backup:**
```powershell
# O backup estaria em: backup-git-YYYYMMDD-HHMMSS
cd ..
rm -rf painel.pixel12digital
cp -r backup-git-YYYYMMDD-HHMMSS painel.pixel12digital
cd painel.pixel12digital
```

**Ou restaure do remoto:**
```bash
git fetch origin
git reset --hard origin/main
```

---

**Status:** CÃ³digo corrigido âœ… | HistÃ³rico pendente âš ï¸

