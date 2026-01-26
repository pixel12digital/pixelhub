# 🔧 Solução Rápida: Erro "Diverging Branches" no Deploy

**Erro:** `fatal: Not possible to fast-forward, aborting`  
**Causa:** O histórico do servidor divergiu do GitHub (provavelmente por resets ou commits locais)

---

## ✅ Solução em 3 Passos

### Passo 1: Fazer Upload do Script

1. O arquivo `atualizar-repositorio.php` já está no projeto
2. Faça upload dele para a **raiz** do projeto no servidor:
   - Via FTP: `/home/pixel12digital/hub.pixel12digital.com.br/atualizar-repositorio.php`
   - Ou via File Manager do cPanel

### Passo 2: Executar o Script

1. Acesse no navegador:
   ```
   https://hub.pixel12digital.com.br/atualizar-repositorio.php
   ```

2. O script vai:
   - ✅ Verificar o estado atual
   - ✅ Detectar a divergência
   - ✅ Fazer `git fetch origin`
   - ✅ Limpar working directory (`git clean -fd`)
   - ✅ Fazer reset hard para `origin/main` (`git reset --hard origin/main`)
   - ✅ Verificar se o hash está correto: `c189200ca8d0f3418e864df82a9dcca1212b4eeb`

3. **Aguarde a conclusão** - você verá uma mensagem de sucesso

### Passo 3: Fazer Deploy no cPanel

1. **Volte ao cPanel** → Tools → Git Version Control
2. **Clique em "Update from Remote"** ⬇️
   - Agora deve funcionar sem erro!
3. **Verifique os requisitos:**
   - ✅ A valid `.cpanel.yml` file exists
   - ✅ No uncommitted changes exist
4. **Clique em "Deploy HEAD Commit"** 🚀
   - Agora deve funcionar!

### Passo 4: Remover o Script (IMPORTANTE!)

⚠️ **SEGURANÇA:** Após o deploy funcionar, **DELETE** o arquivo `atualizar-repositorio.php` do servidor!

---

## 🔍 O que o Script Faz?

O script resolve o problema executando:

```bash
# 1. Buscar atualizações do GitHub
git fetch origin

# 2. Limpar arquivos não rastreados
git clean -fd

# 3. Resetar completamente para origin/main
git reset --hard origin/main
```

Isso garante que o servidor fique **EXATAMENTE** igual ao código no GitHub (que está igual ao seu local).

---

## ✅ Verificação Pós-Deploy

Após o deploy, verifique:

1. **Script de verificação:**
   ```
   https://hub.pixel12digital.com.br/public/verificar-deploy.php
   ```

2. **Hash do commit:**
   - No cPanel Git Version Control, veja o hash do HEAD
   - Deve ser: `c189200ca8d0f3418e864df82a9dcca1212b4eeb`

3. **Teste a aplicação:**
   - Acesse o painel normalmente
   - Teste as funcionalidades

---

## ⚠️ Por que isso acontece?

O erro "diverging branches" acontece quando:

- O servidor tem commits locais que não estão no GitHub
- O histórico foi reescrito (reset, rebase) no servidor
- Alguém fez commits diretamente no servidor

O script resolve isso fazendo um **reset hard**, descartando qualquer mudança local e alinhando o servidor com o GitHub.

---

## 🎯 Resumo Ultra-Rápido

```
1. Upload: atualizar-repositorio.php → servidor
2. Acessar: https://hub.pixel12digital.com.br/atualizar-repositorio.php
3. Aguardar: script executar e mostrar sucesso
4. cPanel: "Update from Remote" → "Deploy HEAD Commit"
5. Deletar: atualizar-repositorio.php do servidor
```

---

**Hash esperado:** `c189200ca8d0f3418e864df82a9dcca1212b4eeb`  
**Última atualização:** 2025-01-22

