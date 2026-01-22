# ðŸ”’ InstruÃ§Ãµes para Limpar HistÃ³rico do Git

## âš ï¸ AVISO IMPORTANTE

**Esta operaÃ§Ã£o reescreve o histÃ³rico do Git!** Isso significa:
- Todos os commits serÃ£o alterados
- SerÃ¡ necessÃ¡rio fazer **force push** para atualizar o repositÃ³rio remoto
- **TODOS** os colaboradores precisarÃ£o refazer o clone do repositÃ³rio
- Se alguÃ©m jÃ¡ fez push de commits baseados no histÃ³rico antigo, haverÃ¡ conflitos

## ðŸ“‹ PrÃ©-requisitos

1. **Backup completo do repositÃ³rio** (jÃ¡ incluÃ­do no script)
2. **NinguÃ©m mais trabalhando no repositÃ³rio** no momento
3. **Acesso de administrador** ao repositÃ³rio remoto
4. **Notificar todos os colaboradores** antes de executar

## ðŸ› ï¸ MÃ©todo 1: Usando BFG Repo-Cleaner (Recomendado - Mais RÃ¡pido)

### Passo 1: Baixar BFG Repo-Cleaner

```bash
# Windows (usando Chocolatey)
choco install bfg

# Ou baixe manualmente de: https://rtyley.github.io/bfg-repo-cleaner/
```

### Passo 2: Criar arquivo com chave a ser removida

Crie um arquivo `chaves-remover.txt`:
```
[CHAVE_REMOVIDA_POR_SEGURANCA]
```

### Passo 3: Executar BFG

```bash
# 1. Clone um espelho do repositÃ³rio
git clone --mirror https://github.com/pixel12digital/pixelhub.git pixelhub-mirror.git

# 2. Execute o BFG para substituir a chave
bfg --replace-text chaves-remover.txt pixelhub-mirror.git

# 3. Limpe o repositÃ³rio
cd pixelhub-mirror.git
git reflog expire --expire=now --all
git gc --prune=now --aggressive

# 4. Force push
git push --force
```

## ðŸ› ï¸ MÃ©todo 2: Usando git filter-branch (Mais Lento)

### OpÃ§Ã£o A: Script PowerShell (Windows)

```powershell
# Execute o script
.\limpar-historio-git.ps1
```

### OpÃ§Ã£o B: Script Bash (Linux/Mac/Git Bash)

```bash
# DÃª permissÃ£o de execuÃ§Ã£o
chmod +x limpar-historio-git-simples.sh

# Execute o script
./limpar-historio-git-simples.sh
```

### OpÃ§Ã£o C: Manual

```bash
# 1. Criar backup
git clone . backup-repo

# 2. Substituir chave em todo o histÃ³rico
git filter-branch --force --tree-filter "
    find . -type f -name 'test-asaas-key.php' -exec sed -i 's|CHAVE_ORIGINAL|ASAAS_API_KEY_FROM_ENV|g' {} \;
" --prune-empty --tag-name-filter cat -- --all

# 3. Limpar referÃªncias antigas
git for-each-ref --format='delete %(refname)' refs/original | git update-ref --stdin
git reflog expire --expire=now --all
git gc --prune=now --aggressive
```

## ðŸ“¤ ApÃ³s a Limpeza

### 1. Verificar as AlteraÃ§Ãµes

```bash
# Verifique se a chave foi removida
git log --all -p | grep -i "aact_prod"

# Se nÃ£o retornar nada, a chave foi removida
```

### 2. Force Push (CUIDADO!)

```bash
# Force push para todas as branches
git push --force --all

# Force push para todas as tags
git push --force --tags
```

### 3. Notificar Colaboradores

Envie um aviso para todos os colaboradores:

```
âš ï¸ ATENÃ‡ÃƒO: O histÃ³rico do Git foi reescrito por questÃµes de seguranÃ§a.

Por favor, refaÃ§a o clone do repositÃ³rio:

git clone https://github.com/pixel12digital/pixelhub.git

Ou, se jÃ¡ tiver um clone local:

cd seu-repositorio
git fetch origin
git reset --hard origin/main
```

## ðŸ”„ Alternativa: Tornar RepositÃ³rio Privado

Se vocÃª nÃ£o quiser reescrever o histÃ³rico agora, pode:

1. **Tornar o repositÃ³rio privado temporariamente**
2. Limpar o histÃ³rico depois
3. Tornar pÃºblico novamente

## âœ… Checklist

- [ ] Backup do repositÃ³rio criado
- [ ] Todos os colaboradores notificados
- [ ] NinguÃ©m mais trabalhando no repositÃ³rio
- [ ] HistÃ³rico limpo executado
- [ ] VerificaÃ§Ã£o de que a chave foi removida
- [ ] Force push realizado
- [ ] Colaboradores notificados para refazer clone

## ðŸ†˜ Em Caso de Problemas

Se algo der errado:

1. **Restaure do backup:**
   ```bash
   cd ..
   rm -rf pixelhub
   cp -r backup-repo pixelhub
   cd pixelhub
   ```

2. **Ou restaure do repositÃ³rio remoto:**
   ```bash
   git fetch origin
   git reset --hard origin/main
   ```

## ðŸ“š ReferÃªncias

- [Git Filter-Branch Documentation](https://git-scm.com/docs/git-filter-branch)
- [BFG Repo-Cleaner](https://rtyley.github.io/bfg-repo-cleaner/)
- [Removing Sensitive Data from Git](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository)

