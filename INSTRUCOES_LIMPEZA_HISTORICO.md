# 🔒 Instruções: Limpeza de Histórico Git - Remover Credenciais

## ⚠️ IMPORTANTE

Este processo irá **reescrever o histórico do Git**, removendo credenciais que foram expostas em commits anteriores.

**AÇÕES NECESSÁRIAS ANTES DE CONTINUAR:**

1. ✅ **Backup criado**: `backup-git-pre-limpeza-20260122-115703/`
2. ✅ **Arquivos corrigidos**: Credenciais substituídas por placeholders
3. ⚠️ **Revogar credenciais expostas**:
   - Senha do banco: `Los@ngo#081081` → **ALTERAR NO SERVIDOR**
   - Usuário HTTP: `Los@ngo#081081` → **ALTERAR NO SERVIDOR**
   - Senha admin padrão: `123456` → **ALTERAR EM PRODUÇÃO**

---

## 📋 Passo a Passo

### 1. Verificar Estado Atual

```powershell
# Ver commits
git log --oneline

# Verificar se ainda há credenciais no histórico
git log --all -p | Select-String "Los@ngo#081081"
```

### 2. Executar Limpeza do Histórico

**Opção A: Script Automatizado (Recomendado)**

```powershell
.\limpar-historio-simples.ps1
```

**Opção B: Manual (Mais Controle)**

```powershell
# Desabilitar pager
$env:GIT_PAGER = ''
git config core.pager ''

# Executar filter-branch para substituir credenciais
git filter-branch --force --tree-filter "powershell -Command `"`$files = @('docs/ALTERAR_USUARIO.md', 'docs/ALTERAR_USUARIO_BANCO_CPANEL.md', 'docs/testar_gateway_completo.sh'); foreach (`$f in `$files) { if (Test-Path `$f) { `$c = Get-Content `$f -Raw; `$c = `$c -replace 'Los@ngo#081081', '[USUARIO_REMOVIDO]'; [System.IO.File]::WriteAllText(`$f, `$c, [System.Text.Encoding]::UTF8); git add `$f } }`"" --prune-empty --tag-name-filter cat -- --all

# Limpar referências antigas
git for-each-ref --format='delete %(refname)' refs/original | git update-ref --stdin
git reflog expire --expire=now --all
git gc --prune=now --aggressive
```

### 3. Verificar Resultado

```powershell
# Verificar se as credenciais foram removidas
git log --all -p | Select-String "Los@ngo#081081"

# Se não retornar nada, está limpo!
```

### 4. Fazer Force Push (CUIDADO!)

```powershell
# ⚠️ ATENÇÃO: Isso reescreverá o histórico no GitHub!
git push --force --all
git push --force --tags
```

### 5. Notificar Colaboradores

**IMPORTANTE**: Após o force push, todos os colaboradores precisarão:

```bash
# Fazer backup local (se necessário)
# Depois refazer clone
git clone https://github.com/pixel12digital/pixelhub.git
```

---

## 🔧 Alternativa: BFG Repo-Cleaner (Mais Rápido)

Se o `git filter-branch` for muito lento, use o BFG:

1. **Baixar BFG**: https://rtyley.github.io/bfg-repo-cleaner/

2. **Criar arquivo `credenciais.txt`**:
```
Los@ngo#081081
Los@ngo#2024!Dev$Secure
```

3. **Executar**:
```bash
java -jar bfg.jar --replace-text credenciais.txt pixelhub.git
cd pixelhub.git
git reflog expire --expire=now --all
git gc --prune=now --aggressive
```

---

## ✅ Checklist Final

- [ ] Backup do repositório criado
- [ ] Arquivos corrigidos (credenciais substituídas)
- [ ] Histórico limpo (verificado)
- [ ] Force push realizado
- [ ] Colaboradores notificados
- [ ] Credenciais revogadas no servidor
- [ ] Novas credenciais configuradas

---

## 🚨 Se Algo Der Errado

Se precisar restaurar o backup:

```powershell
# Restaurar do backup
Remove-Item -Recurse -Force .git
Copy-Item -Recurse backup-git-pre-limpeza-20260122-115703/* .git/
```

---

## 📝 Notas

- O processo pode demorar dependendo do tamanho do repositório
- O force push é **irreversível** no GitHub
- Todos os colaboradores precisarão refazer clone após o force push
- Considere tornar o repositório **privado** após a limpeza

---

**Última atualização**: 2026-01-22

